<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ErrorLog;
use App\Support\LikeSearch;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hata kayıtlarını yazan ve okuyan katman.
 *
 * **Yazma tarafı hiçbir koşulda fırlatmıyor.** Buraya hata işlemenin ortasından
 * geliniyor; buradan çıkan bir istisna asıl hatanın yerini alır ve yönetici
 * yanlış şeye bakar. Veritabanı düştüğünde —ki 500'lerin en sık sebebi bu—
 * yazma sessizce başarısız oluyor, Telegram bildirimi ve dosya logu yerinde
 * duruyor.
 */
final class ErrorLogService
{
    /**
     * Kayıtların saklanma süresi.
     *
     * Aktivite loglarından kısa: hata kaydının değeri tazeliğinde. İki ay önce
     * çözülmüş bir kusurun yığın izini kimse açmıyor, ama satır tablonun
     * içinde yer kaplıyor — üstelik yığın izleri büyük.
     */
    public const RETENTION_DAYS = 60;

    /**
     * Yığın izinin saklanan en uzun hâli.
     *
     * Derin bir çerçeve izi yüz kilobaytı geçebiliyor; ilk kareler zaten
     * sorunun geldiği yeri gösteriyor. Kırpma sessiz değil, sonuna not
     * düşülüyor.
     */
    private const TRACE_LIMIT = 20000;

    /**
     * Hatayı kaydeder; aynı hata daha önce görüldüyse sayacını artırır.
     *
     * Kısma yok — bildirim on dakikada bir gidiyor ama kayıt her seferinde
     * tutuluyor. Zaten kaybedilen bilgi tam olarak buydu: "bu hata bir kez mi
     * oldu, günde bin kez mi?"
     */
    public function record(Throwable $e): ?ErrorLog
    {
        try {
            return $this->write($e);
        } catch (Throwable) {
            // Hata işlemenin içindeyiz; buradan bir şey fırlatmak asıl hatayı
            // gizler. Veritabanı erişilemiyorsa geriye dosya logu kalıyor.
            return null;
        }
    }

    private function write(Throwable $e): ErrorLog
    {
        $now = now();
        $fingerprint = $this->fingerprint($e);
        $request = $this->requestContext();

        $log = ErrorLog::withTrashed()->firstOrNew(['fingerprint' => $fingerprint]);

        // Silinmiş bir kayıt yeniden görülüyorsa geri geliyor: yönetici satırı
        // temizlemiş olabilir ama hata devam ediyorsa listede olmalı.
        if ($log->trashed()) {
            $log->deleted_at = null;
        }

        $log->fill([
            'exception'  => $e::class,
            'message'    => $this->clip((string) $e->getMessage(), 2000),
            'file'       => $e->getFile(),
            'line'       => $e->getLine(),
            'trace'      => $this->trace($e),
            'url'        => $request['url'],
            'method'     => $request['method'],
            'ip_address' => $request['ip'],
            'user_agent' => $this->clip((string) $request['user_agent'], 255),
            'user_id'    => $request['user_id'],
        ]);

        $log->last_seen_at = $now;
        $log->first_seen_at ??= $now;
        $log->occurrences = $log->exists ? $log->occurrences + 1 : 1;

        // Çözüldü işareti kalkıyor: düzeldiği sanılan bir kusur geri döndüyse
        // bunun listede görünmesi gerekiyor, sessizce çözülmüş kalması değil.
        if ($log->resolved_at !== null) {
            $log->resolved_at = null;
            $log->resolved_by = null;
        }

        $log->save();

        return $log;
    }

    /**
     * Aynı hata mı?
     *
     * Tür + dosya + satır. Mesaj kasten dışarıda: aynı kusur her istekte farklı
     * mesaj üretebiliyor ("User 41 not found", "User 87 not found") ve mesaja
     * bakan bir parmak izi listeyi aynı hatanın binlerce kopyasıyla doldururdu.
     *
     * `ExceptionNotifier` da bildirim kısması için aynı üçlüyü kullanıyor;
     * ikisinin aynı hatayı aynı şey sayması bilinçli.
     */
    private function fingerprint(Throwable $e): string
    {
        return md5($e::class . '|' . $e->getFile() . ':' . $e->getLine());
    }

    /**
     * Yığın izi, varsa önceki hatalarla birlikte.
     *
     * Zincirin tamamı yazılıyor: "SQLSTATE bağlantı kurulamadı" hatasının
     * gerçek sebebi çoğu zaman iki katman aşağıda duruyor.
     */
    private function trace(Throwable $e): string
    {
        $parts = [$e::class . ': ' . $e->getMessage(), $e->getTraceAsString()];

        $previous = $e->getPrevious();
        $depth = 0;

        // Zincir kendine döngü yapabiliyor; derinlik sınırı sonsuz döngüyü
        // kesiyor.
        while ($previous !== null && $depth < 5) {
            $parts[] = '';
            $parts[] = '--- Önceki hata: ' . $previous::class . ': ' . $previous->getMessage();
            $parts[] = $previous->getTraceAsString();

            $previous = $previous->getPrevious();
            $depth++;
        }

        // Proje kökü kırpılıyor. İki sebep: satırlar ekrana sığıyor ve
        // paylaşımlı hosting'in mutlak yolu (`/home/musteri123/public_html/…`)
        // hosting kullanıcı adını ekrana taşımıyor.
        return $this->clip(
            str_replace(base_path() . DIRECTORY_SEPARATOR, '', implode(PHP_EOL, $parts)),
            self::TRACE_LIMIT,
        );
    }

    private function clip(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit) . PHP_EOL . '… (kırpıldı)'
            : $value;
    }

    /**
     * Hatanın geldiği istek. Konsoldan gelen hatalarda hepsi boş.
     *
     * @return array{url: ?string, method: ?string, ip: ?string, user_agent: ?string, user_id: ?int}
     */
    private function requestContext(): array
    {
        $empty = ['url' => null, 'method' => null, 'ip' => null, 'user_agent' => null, 'user_id' => null];

        if (app()->runningInConsole()) {
            return $empty;
        }

        return rescue(static function (): array {
            $request = request();

            return [
                'url'        => mb_substr($request->fullUrl(), 0, 2048),
                'method'     => $request->method(),
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id'    => auth()->id(),
            ];
        }, $empty, false);
    }

    // ── Okuma ──

    /**
     * Liste ekranının ve dışa aktarmanın tanıdığı süzgeç anahtarları.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['status', 'exception', 'source', 'from', 'to', 'q'];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return Builder<ErrorLog>
     */
    public function query(array $filters = []): Builder
    {
        $query = ErrorLog::query()->with(['user:id,first_name,last_name,email', 'resolver:id,first_name,last_name,email']);

        $status = (string) ($filters['status'] ?? '');

        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if (($filters['exception'] ?? '') !== '') {
            $query->where('exception', (string) $filters['exception']);
        }

        // Kendi kodumuz mu, paket mi? Dosya yolu mutlak saklandığı için
        // karşılaştırma proje kökü üzerinden yapılıyor.
        //
        // Kalıp elle kurulmuyor: paylaşımlı hosting yolları alt çizgi taşıyor
        // (`/home/my_user/public_html/...`) ve alt çizgi LIKE'ta tek karakterlik
        // joker — kaçırılmadan yazılsa süzgeç yanlış satırları da getirirdi.
        $source = (string) ($filters['source'] ?? '');
        $vendorPrefix = LikeSearch::prefix(base_path('vendor') . DIRECTORY_SEPARATOR);

        if ($source === 'app') {
            $query->whereRaw('not (' . LikeSearch::clause('file') . ')', [$vendorPrefix]);
        } elseif ($source === 'vendor') {
            $query->whereRaw(LikeSearch::clause('file'), [$vendorPrefix]);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = LikeSearch::term((string) $filters['q']);

            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw(LikeSearch::clause('message'), [$term])
                    ->orWhereRaw(LikeSearch::clause('exception'), [$term])
                    ->orWhereRaw(LikeSearch::clause('file'), [$term])
                    ->orWhereRaw(LikeSearch::clause('url'), [$term]);
            });
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('last_seen_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('last_seen_at', '<=', $filters['to']);
        }

        // Çözülmemişler önce, sonra en son görülen: ekranı açan kişinin ilk
        // görmesi gereken şey, şu anda devam eden hata.
        return $query->orderByRaw('resolved_at is null desc')->orderByDesc('last_seen_at');
    }

    /**
     * @param  array<string, mixed> $filters
     * @return LengthAwarePaginator<int, ErrorLog>
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Özet kutuları.
     *
     * @return array{open: int, resolved: int, today: int, occurrences: int, oldest: ?Carbon}
     */
    public function stats(): array
    {
        $oldest = ErrorLog::query()->min('first_seen_at');

        return [
            'open'        => ErrorLog::query()->whereNull('resolved_at')->count(),
            'resolved'    => ErrorLog::query()->whereNotNull('resolved_at')->count(),
            'today'       => ErrorLog::query()->whereDate('last_seen_at', now()->toDateString())->count(),
            // Satır sayısı değil tekrar sayısı: "üç hatam var" ile "üç hatam
            // var ve bugün dokuz bin kez patladılar" aynı şey değil.
            'occurrences' => (int) ErrorLog::query()->sum('occurrences'),
            'oldest'      => $oldest !== null ? Carbon::parse((string) $oldest) : null,
        ];
    }

    /**
     * Süzgeç açılır listesi için hata türü başına adet.
     *
     * @return array<string, int>
     */
    public function exceptionOptions(): array
    {
        /** @var array<string, int> $rows */
        $rows = ErrorLog::query()
            ->select('exception', DB::raw('count(*) as total'))
            ->groupBy('exception')
            ->orderByDesc('total')
            ->pluck('total', 'exception')
            ->all();

        return $rows;
    }

    /**
     * En çok tekrar eden hatalar — özet kartı için.
     *
     * @return list<array{label: string, location: string, count: int, percent: int}>
     */
    public function topRepeating(int $limit = 5): array
    {
        $logs = ErrorLog::query()->orderByDesc('occurrences')->limit($limit)->get();
        $max = (int) $logs->max('occurrences');

        return $logs->map(static fn (ErrorLog $log): array => [
            'label'    => $log->shortException(),
            'location' => $log->location(),
            'count'    => $log->occurrences,
            // Çubuk genişliği CSS sınıfıyla veriliyor (inline style yasak),
            // o yüzden beşin katına yuvarlanıyor.
            'percent'  => $max > 0 ? (int) (round($log->occurrences / $max * 100 / 5) * 5) : 0,
        ])->all();
    }

    // ── Yazma (panel) ──

    public function resolve(ErrorLog $log, int $userId): void
    {
        $log->forceFill(['resolved_at' => now(), 'resolved_by' => $userId])->save();
    }

    public function reopen(ErrorLog $log): void
    {
        $log->forceFill(['resolved_at' => null, 'resolved_by' => null])->save();
    }

    /**
     * Çözülmüş kayıtları topluca siler.
     *
     * Tek tek silmek yerine "temizlik" düğmesi: liste çoğu zaman tek bir
     * oturumda toparlanıyor.
     *
     * @return int silinen satır sayısı
     */
    public function purgeResolved(): int
    {
        return ErrorLog::query()->whereNotNull('resolved_at')->delete();
    }

    /**
     * Saklama süresini geçmiş kayıtları siler.
     *
     * Ölçüt son görülme: aylardır tekrar eden bir hata "eski" değil, açık.
     *
     * @return int silinen satır sayısı
     */
    public function prune(int $days = self::RETENTION_DAYS): int
    {
        return ErrorLog::query()
            ->where('last_seen_at', '<', now()->subDays($days))
            ->forceDelete();
    }
}
