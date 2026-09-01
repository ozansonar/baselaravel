<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * İçerik sürümlemesinin tek karar yeri.
 *
 * Üç iş yapıyor: kaydedilen hâli saklamak, tavanı aşan eskileri budamak ve
 * istenen sürümü geri yüklemek. Üçü de burada çünkü üçü de aynı soruya
 * bağlı — "hangi alanlar sürümleniyor" — ve o soru `config/revisions.php`'de
 * tek yerde cevaplanıyor.
 */
final class ContentRevisionService
{
    /**
     * Bu model sürümleniyor mu?
     */
    public function tracks(Model $model): bool
    {
        return array_key_exists($model::class, $this->map());
    }

    /**
     * Modelin sürümlenen alanları.
     *
     * @return list<string>
     */
    public function fieldsFor(Model $model): array
    {
        return array_values($this->map()[$model::class] ?? []);
    }

    /**
     * Kaydedilen hâli sakla.
     *
     * Sürüm yalnız **içerik gerçekten değiştiğinde** yazılıyor ve bu, tavanın
     * kendisi kadar önemli: blog yazısının `views` sayacı her ziyarette
     * artıyor, `increment()` de model olayı doğuruyor. Sayaç tetikleyici
     * sayılsaydı popüler bir yazının geçmişi bir günde dolar, gerçek
     * düzenlemeler listeden düşerdi.
     *
     * Karar Eloquent'in değişiklik izine değil, **son sürümle karşılaştırmaya**
     * bakıyor. Sebebi ölçüldü: hiçbir alanı kirletmeyen bir `save()` çağrısında
     * Eloquent güncelleme sorgusunu atlıyor ve `getChanges()` bir önceki
     * kaydın değerlerini taşımaya devam ediyor — `wasChanged()` o anda "evet"
     * diyor. Alakasız bir alanı kaydetmek sahte bir sürüm doğuruyordu.
     *
     * Karşılaştırma ayrıca kendiliğinden tekrarı da önlüyor: aynı içeriği iki
     * kez kaydetmek tek sürüm bırakıyor.
     */
    public function capture(Model $model): ?ContentRevision
    {
        if (! $this->tracks($model)) {
            return null;
        }

        $fields = $this->fieldsFor($model);
        $payload = $this->snapshot($model, $fields);

        if ($this->matchesLatest($model, $payload)) {
            return null;
        }

        $revision = ContentRevision::create([
            'revisionable_type' => $model->getMorphClass(),
            'revisionable_id'   => $model->getKey(),
            'locale'            => (string) $model->getAttribute('locale'),
            // Konsoldan (tohumlama, komut) yapılan kayıtta oturum yok.
            'user_id'           => Auth::id(),
            'payload'           => $payload,
        ]);

        $this->prune($model);

        return $revision;
    }

    /**
     * Tavanı aşan en eski sürümleri kalıcı olarak siler.
     *
     * Yumuşak silme kullanılmıyor: tavanın var olma sebebi disk ve yumuşak
     * silinen satır diskte durmaya devam ederdi.
     */
    public function prune(Model $model): int
    {
        // "En yenileri tut, gerisini sil" biçiminde kuruluyor. Tersi
        // (`skip(20)` + `take(sonsuz)`) sürücüye göre değişen bir LIMIT
        // üretiyor; bu biçim her veritabanında aynı çalışıyor ve sorguya en
        // fazla yirmi kimlik giriyor.
        $keepIds = ContentRevision::query()
            ->forTarget($model)
            ->limit($this->keep())
            ->pluck('id');

        return ContentRevision::query()
            ->forTarget($model)
            ->whereNotIn('id', $keepIds)
            ->forceDelete();
    }

    /**
     * Bir sürümü geri yükler.
     *
     * Yeni satır açılmıyor, mevcut kayıt güncelleniyor — adres, kimlik ve
     * bağlantılar korunuyor.
     *
     * Geri yükleme de bir kayıt ve kendi sürümünü doğuruyor. Bu bilinçli:
     * listenin en üstündeki sürüm **her zaman** içeriğin şu anki hâli oluyor,
     * yani "geri aldım ama yanlış sürümü seçmişim" diyen kişi bir öncekine
     * dönebiliyor. Geri yükleme sürüm yazmasaydı geçmiş, ekranda duran
     * içerikle uyuşmayan bir liste hâline gelirdi.
     *
     * @throws RuntimeException Sürüm başka bir kayda aitse
     */
    public function restore(ContentRevision $revision, Model $target): Model
    {
        if ($revision->revisionable_type !== $target->getMorphClass()
            || (int) $revision->revisionable_id !== (int) $target->getKey()) {
            throw new RuntimeException('Bu sürüm başka bir içeriğe ait.');
        }

        $fields = $this->fieldsFor($target);

        return DB::transaction(function () use ($revision, $target, $fields): Model {
            foreach ($fields as $field) {
                // Alan sonradan eklenmişse eski sürümde yok; olmayan alana
                // null yazmak, o alanı sessizce silmek olurdu.
                if (array_key_exists($field, $revision->payload ?? [])) {
                    $target->setAttribute($field, $revision->payload[$field]);
                }
            }

            $target->save();

            return $target;
        });
    }

    /**
     * Bu hâl zaten en son sürümde duruyor mu?
     *
     * İki şey karşılaştırmayı bozabiliyor, ikisi de içeriğin kendisiyle
     * ilgisiz:
     *
     * 1. **Anahtar sırası.** payload bir `json` sütunu ve MySQL 8 nesne
     *    anahtarlarını depolarken yeniden sıralıyor (önce uzunluğa, sonra
     *    bayt sırasına). Sayfanın alanları `title, slug, content…` sırasıyla
     *    yazılıyor, geri `slug, image, title…` olarak okunuyor. Eski karşılaştırma
     *    kodlanmış dizgeye baktığı için MySQL'de **hiçbir zaman** eşleşmiyordu:
     *    hiçbir şey değişmese bile her kaydetme yeni bir sürüm yazıyor, yirmi
     *    sürümlük tavan gerçek geçmişi dışarı itiyordu. SQLite ve MariaDB
     *    anahtarları sıralamadığı için sorun yalnız üretimde görünüyordu.
     * 2. **Değer türü.** Aynı değer bir yandan veritabanından dizge, bir
     *    yandan PHP tarafından sayı olarak geliyor.
     *
     * Bu yüzden karşılaştırma kanonik bir biçim üzerinden: anahtarlar sıralı,
     * değerler ya null ya dizge.
     *
     * @param array<string, mixed> $payload
     */
    private function matchesLatest(Model $model, array $payload): bool
    {
        $latest = ContentRevision::query()->forTarget($model)->first();

        if ($latest === null) {
            return false;
        }

        return $this->canonical((array) $latest->payload) === $this->canonical($payload);
    }

    /**
     * Karşılaştırılabilir hâl: anahtarlar sıralı, değerler null ya da dizge.
     *
     * null korunuyor — "boş bırakıldı" ile "boş dizge yazıldı" aynı şey değil
     * ve ikisini birbirine katmak gerçek bir değişikliği görünmez yapardı.
     *
     * @param  array<string, mixed> $payload
     * @return array<string, string|null>
     */
    private function canonical(array $payload): array
    {
        $values = [];

        foreach ($payload as $field => $value) {
            $values[$field] = $value === null ? null : (is_scalar($value) ? (string) $value : json_encode($value));
        }

        ksort($values);

        return $values;
    }

    /**
     * Modelin sürümlenen alanlarının o anki değerleri.
     *
     * Değerler ham hâlleriyle alınıyor (`getAttributes`), okunmuş hâlleriyle
     * değil: enum ve tarih nesneleri JSON'a yazılıp geri okunduğunda aynı
     * nesne olmuyor, ham değer ise aynen geri konabiliyor.
     *
     * @param  list<string> $fields
     * @return array<string, mixed>
     */
    private function snapshot(Model $model, array $fields): array
    {
        $raw = $model->getAttributes();

        $payload = [];

        foreach ($fields as $field) {
            $payload[$field] = $raw[$field] ?? null;
        }

        return $payload;
    }

    public function keep(): int
    {
        return max(1, (int) config('revisions.keep', 20));
    }

    /**
     * @return array<class-string, list<string>>
     */
    private function map(): array
    {
        /** @var array<class-string, list<string>> $models */
        $models = config('revisions.models', []);

        return $models;
    }
}
