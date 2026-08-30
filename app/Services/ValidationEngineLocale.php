<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Doğrulama motorunun hangi dil dosyasını yükleyeceği.
 *
 * Düzenler dosyayı sabit yazıyordu (jquery.validationEngine-tr.js), yani
 * İngilizce sayfadaki bir formda uyarılar Türkçe çıkıyordu — sunucu tarafı
 * çevrilmişken istemci tarafı geride kalmıştı.
 *
 * Paket her dil için ayrı dosya taşıyor ve hepsi projede bulunmuyor: olmayan
 * bir dosyayı yüklemek motoru hiç kurulmamış hâlde bırakır ve form sessizce
 * doğrulamasız kalır. Bu yüzden dosyanın varlığı kontrol ediliyor, yoksa
 * varsayılan dile düşülüyor.
 */
final class ValidationEngineLocale
{
    /** Dosyası kesin bulunan dil; ötekiler buna düşüyor. */
    private const FALLBACK = 'tr';

    private const DIRECTORY = 'assets/vendor/jquery-validation-engine/js';

    /**
     * @var array<string, string>
     */
    private array $memo = [];

    /**
     * Verilen dilin dosya yolu — yoksa varsayılanınki.
     *
     * @param string|null $locale null ise isteğin dili
     */
    public function scriptPath(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->memo[$locale] ??= $this->resolve($locale);
    }

    /** Bu dilin kendi dosyası var mı, yoksa varsayılana mı düşüyor? */
    public function hasOwnFile(string $locale): bool
    {
        return is_file(public_path($this->fileFor($locale)));
    }

    private function resolve(string $locale): string
    {
        // Dil kodu dosya adına giriyor: uydurulmuş bir kodun dosya yolunda
        // gezinmesine izin verilmiyor.
        $safe = preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/i', $locale) === 1 ? strtolower($locale) : self::FALLBACK;

        if ($this->hasOwnFile($safe)) {
            return $this->fileFor($safe);
        }

        // "en-gb" gibi bir kodun temel dili varsa o kullanılıyor.
        $base = explode('-', $safe)[0];

        return $this->hasOwnFile($base) ? $this->fileFor($base) : $this->fileFor(self::FALLBACK);
    }

    private function fileFor(string $locale): string
    {
        return self::DIRECTORY . '/jquery.validationEngine-' . $locale . '.js';
    }
}
