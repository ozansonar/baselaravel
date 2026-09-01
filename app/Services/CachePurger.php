<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheKeys;
use App\Support\LikeSearch;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bir önbellek kümesini, ötekilere dokunmadan siler.
 *
 * Eskiden analitik ekranındaki tek bir yenileme `Cache::flush()` çağırıyordu:
 * ayarlar, çeviriler, site haritası, dil listesi ve bütün ön yüz içerik
 * önbelleği birlikte gidiyordu. Varsayılan sürücü veritabanı olduğu için
 * yeniden ısınma da bedava değil — ilk ziyaretçiler bütün sorguları sırtlıyor.
 *
 * Sorun şuydu: silinecek anahtarların adları önceden bilinmiyor (analitik
 * anahtarları tarih aralığına göre türüyor) ve her sürücü etiket (tag)
 * desteklemiyor. Çözüm ikisini birden karşılıyor:
 *
 *  - **Veritabanı, Redis ve dizi** anahtarları adıyla saklıyor; önek doğrudan
 *    sorgulanabiliyor (tek `DELETE ... LIKE`, `SCAN`, ya da bellekteki dizinin
 *    kendisi).
 *  - **Dosya sürücüsü** anahtarı hash'leyip dizinlere dağıtıyor; orada önek
 *    diye bir şey yok. Onun için yazılan anahtarların kaydı tutuluyor ve
 *    temizlik o kayıttan yürüyor.
 *
 * İki yol da aynı sözleşmeyi veriyor: `forgetPrefix()` çağrıldığında yalnız o
 * önekin anahtarları gidiyor, başka hiçbir şey.
 */
final class CachePurger
{
    /**
     * Kayıt listesinin kendisi de önbellekte duruyor; sınırsız büyümesin diye
     * tavan var. Tavan aşılırsa kayıt bırakılıp önekin tamamı silinemez hâle
     * gelmiyor — en eskiler düşüyor, çünkü onların süresi zaten dolmuş olma
     * ihtimali en yüksek.
     */
    private const REGISTRY_LIMIT = 500;

    /** Kayıt listesinin ömrü: en uzun ömürlü kayıtlı anahtardan uzun olmalı. */
    private const REGISTRY_TTL = 86400;

    /**
     * Bu önekle yazılmış bütün anahtarları siler.
     *
     * @return int Silinen anahtar sayısı; sürücü sayamıyorsa -1.
     */
    public function forgetPrefix(string $prefix): int
    {
        $store = Cache::getStore();

        try {
            if ($store instanceof DatabaseStore) {
                return $this->purgeDatabase($prefix);
            }

            if ($store instanceof RedisStore) {
                return $this->purgeRedis($store, $prefix);
            }

            if ($store instanceof ArrayStore) {
                return $this->purgeArray($store, $prefix);
            }

            return $this->purgeRegistry($prefix);
        } catch (\Throwable $e) {
            // Önbellek temizliği hiçbir isteği düşürmemeli: silinemeyen anahtar
            // bayat veri gösterir, patlayan istek hiçbir şey göstermez.
            Log::warning('Önbellek öneki temizlenemedi', [
                'prefix' => $prefix,
                'error'  => $e->getMessage(),
            ]);

            return -1;
        }
    }

    /**
     * Bir dizi anahtarı birlikte siler.
     *
     * @param list<string> $keys
     */
    public function forget(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * İçerik değişti: ön yüzün bayatlayan anahtarlarını düşür.
     *
     * Hangi anahtarların bayatladığı `CacheKeys::contentKeys()`'te yazılı;
     * yeni bir içerik türü eklendiğinde orası güncelleniyor, çağıran yerler
     * değil.
     */
    public function forgetContent(): void
    {
        $this->forget(CacheKeys::contentKeys());
        $this->forgetPrefix(CacheKeys::PREFIX_FRAGMENT);
    }

    /**
     * Bir küme içinde önbelleğe alır.
     *
     * `Cache::remember()`'ın yerini alıyor: değeri aynı şekilde yazıyor, ama
     * anahtarı kümesine de kaydediyor. Kayıt yalnız önek bazlı silmeyi
     * desteklemeyen sürücülerde tutuluyor; veritabanı ve Redis'te bu satır
     * hiçbir şey yapmıyor.
     *
     * @template TValue
     * @param \Closure(): TValue $callback
     * @return TValue
     */
    public function rememberWithin(string $prefix, string $key, int $ttl, \Closure $callback): mixed
    {
        $this->remember($prefix, $key);

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Bir anahtarın yazıldığını kaydeder.
     *
     * Yalnız önek bazlı silinemeyen sürücülerde iş yapıyor; veritabanı ve
     * Redis'te gereksiz yazma olmasın diye erken çıkıyor.
     */
    public function remember(string $prefix, string $key): void
    {
        $store = Cache::getStore();

        if ($store instanceof DatabaseStore
            || $store instanceof RedisStore
            || $store instanceof ArrayStore
        ) {
            return;
        }

        $registry = $this->registry($prefix);

        if (in_array($key, $registry, true)) {
            return;
        }

        $registry[] = $key;

        if (count($registry) > self::REGISTRY_LIMIT) {
            $registry = array_slice($registry, -self::REGISTRY_LIMIT);
        }

        Cache::put($this->registryKey($prefix), $registry, self::REGISTRY_TTL);
    }

    /**
     * Veritabanı sürücüsü: tek ifade.
     *
     * Çerçeve anahtarların başına kendi önekini koyuyor (`cache.prefix`);
     * sorgu onu da hesaba katmalı, yoksa hiçbir satır eşleşmez.
     */
    private function purgeDatabase(string $prefix): int
    {
        /** @var DatabaseStore $store */
        $store = Cache::getStore();

        $table = (string) config('cache.stores.database.table', 'cache');
        $connection = config('cache.stores.database.connection');

        // Kalıp `LikeSearch` üzerinden kuruluyor: önek `custom_routes.` gibi bir
        // alt çizgi taşıyabiliyor ve kaçırılmazsa herhangi bir karakteri
        // eşleştirip silinmemesi gereken anahtarları götürür. Kaçış karakteri
        // ve `ESCAPE` bildirimi ikisi de oradan geliyor — MySQL ile SQLite
        // arasındaki farkı çözen tek yer orası.
        $pattern = LikeSearch::prefix($store->getPrefix() . $prefix);

        return DB::connection($connection)
            ->table($table)
            ->whereRaw(LikeSearch::clause('key'), [$pattern])
            ->delete();
    }

    /**
     * Redis: anahtarlar `SCAN` ile geziliyor.
     *
     * `KEYS` kullanılmıyor — tek komutta bütün anahtar uzayını tarar ve büyük
     * bir kurulumda sunucuyu kilitler.
     */
    private function purgeRedis(RedisStore $store, string $prefix): int
    {
        $connection = $store->connection();
        $pattern = $store->getPrefix() . $prefix . '*';

        $deleted = 0;
        $cursor = null;

        do {
            /** @var array{0: mixed, 1: array<int, string>}|false $result */
            $result = $connection->scan($cursor, ['match' => $pattern, 'count' => 500]);

            if ($result === false) {
                break;
            }

            [$cursor, $keys] = $result;

            if ($keys !== []) {
                $connection->del(...$keys);
                $deleted += count($keys);
            }
        } while ((int) $cursor !== 0);

        return $deleted;
    }

    /**
     * Dizi sürücüsü: anahtarlar bellekte düz duruyor.
     *
     * Yalnız testlerde kullanılıyor ama davranışı gerçeğinden ayırmamak
     * önemli — kayıt yolundan gitseydi, testler yalnız bu servis üzerinden
     * yazılan anahtarları görür, doğrudan yazılanları kaçırırdı.
     */
    private function purgeArray(ArrayStore $store, string $prefix): int
    {
        $deleted = 0;

        foreach (array_keys($store->all(unserialize: false)) as $key) {
            if (str_starts_with((string) $key, $store->getPrefix() . $prefix)) {
                $store->forget((string) $key);
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * Öteki sürücüler: kayıt listesinden yürü.
     */
    private function purgeRegistry(string $prefix): int
    {
        $registry = $this->registry($prefix);

        foreach ($registry as $key) {
            Cache::forget($key);
        }

        Cache::forget($this->registryKey($prefix));

        return count($registry);
    }

    /**
     * @return list<string>
     */
    private function registry(string $prefix): array
    {
        $registry = Cache::get($this->registryKey($prefix), []);

        return is_array($registry) ? array_values(array_filter($registry, 'is_string')) : [];
    }

    private function registryKey(string $prefix): string
    {
        return '__keys.' . $prefix;
    }
}
