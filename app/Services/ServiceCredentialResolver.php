<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Support\ServiceCredentials;
use Throwable;

/**
 * Paneldeki servis anahtarlarını çalışma zamanında `config()` üzerine yazar.
 *
 * Amaç, kodda hiçbir servisin "acaba bu değer panelden mi geliyor" diye
 * sormasına gerek kalmaması. Google doğrulayıcısı `config('services.google...')`
 * okumaya devam ediyor; oraya değerin panelden mi yoksa `.env`'den mi geldiği
 * hiç ulaşmıyor.
 *
 * Sıra: **panel → .env → config varsayılanı**. Panelde boş bırakılan bir alan
 * `.env`'i ezmiyor; bugüne kadar `.env`'e yazmış bir kurulum güncellemeden
 * sonra da çalışmaya devam ediyor.
 *
 * Maliyet bir istek için tek bir dizi okuması: ayarlar zaten önbellekte ve
 * istek içinde bir kez okunuyor. Panelden kaydedilince önbellek düşüyor, yani
 * yeni anahtar bir sonraki istekte geçerli — sunucuya dokunmaya gerek yok.
 *
 * @see \App\Support\ServiceCredentials
 */
final class ServiceCredentialResolver
{
    /**
     * Panel değeri yazılmadan ÖNCE config'te duran değer — yani .env'den ya da
     * config dosyasının varsayılanından gelen.
     *
     * Ekran "bu alan nereden besleniyor" diye soruyor ve cevabı `env()`
     * çağırarak veremez: `config:cache` sonrası —üretimin varsayılan durumu—
     * `env()` null döner ve rozet asla ".env" demezdi. Değer yalnız burada,
     * ezilmeden hemen önce görülebiliyor.
     *
     * @var array<string, mixed>
     */
    private array $fallbacks = [];

    public function apply(): void
    {
        // Uygulama veritabanı olmadan da ayağa kalkabilmeli: taze bir klon,
        // göç öncesi, `key:generate`. Ayarlar okunamıyorsa .env zaten geçerli.
        try {
            $settings = Setting::getCachedSettings();
        } catch (Throwable) {
            return;
        }

        foreach (ServiceCredentials::fields() as $key => $field) {
            // config yolu olmayan alanlar var: bazı servisler ayarı zaten
            // doğrudan okuyor (RecaptchaService, ön yüz düzenindeki analitik
            // kimlikleri). Kayıt defteri onları da tanıyor çünkü paneli o
            // çiziyor; köprü yalnız gerektiği yerde kuruluyor.
            if ($field['config'] === '') {
                continue;
            }

            $this->fallbacks[$key] = config($field['config']);

            $value = $settings[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            config([$field['config'] => $this->cast($field, $value)]);
        }
    }

    /**
     * Bu alan panel dışında bir kaynaktan besleniyor mu?
     *
     * `apply()` çalışmadan sorulursa cevap "hayır": henüz kimse bakmamış
     * demektir ve uydurmak yanlış olurdu.
     */
    public function hasFallback(string $key): bool
    {
        $value = $this->fallbacks[$key] ?? null;

        return $value !== null && $value !== '' && $value !== 'null';
    }

    /**
     * Ayarlar metin olarak duruyor; config'in beklediği değere çevriliyor.
     *
     * Açık/kapalı düğmeleri her servis için aynı şeyi yazmıyor: push sürücüsü
     * bir sürücü adı bekliyor ("fcm" / "null"), başka bir alan mantıksal
     * isteyebilir. Karşılıklar bu yüzden tahmin edilmiyor, kayıt defterinde
     * alanın kendi `on`/`off` değerleri olarak duruyor.
     *
     * @param array<string, mixed> $field
     */
    private function cast(array $field, string $value): mixed
    {
        if (($field['type'] ?? '') !== 'toggle') {
            return $value;
        }

        return $value === '1' ? ($field['on'] ?? true) : ($field['off'] ?? false);
    }
}
