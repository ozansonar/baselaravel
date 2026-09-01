<?php

declare(strict_types=1);

namespace App\Session;

/**
 * Geçiş dönemi: eski biçimde yazılmış oturumları da okur.
 *
 * Oturum verisi varsayılan olarak PHP'nin `serialize()` biçiminde saklanıyor.
 * Bu biçimin bilinen bir tehlikesi var: `unserialize()` veri okumakla kalmıyor,
 * **nesne kuruyor** — saklanan dizgeyi değiştirebilen biri, uygulamada var olan
 * sınıflardan bir zincir kurup kod çalıştırabiliyor (POP zinciri). JSON'da
 * böyle bir yüzey yok; okunan şey yalnız veri.
 *
 * Ama biçimi değiştirmek tek başına bir bedel doğuruyor: o anda açık olan
 * bütün oturumlar eski biçimde yazılmış durumda ve yeni ayarla okunamıyorlar.
 * Çerçeve bu durumda sessizce boş bir oturum veriyor — yani **herkes aynı anda
 * çıkış yapmış oluyor.** Çalışan bir kurulumda bu, bakım penceresi gerektiren
 * bir karardı ve bu yüzden geçiş uzun süre ertelendi.
 *
 * Bu ara katman o bedeli kaldırıyor:
 *
 *  - **Okurken** önce yeni biçim (JSON) deneniyor, tutmazsa eski biçim.
 *  - **Yazarken** her zaman yeni biçim kullanılıyor.
 *
 * Sonuç: açık oturumlar bir sonraki isteklerinde sessizce yeni biçime geçiyor,
 * kimse düşmüyor. Geçiş tamamlandığında (oturum ömrü kadar bir süre sonra)
 * ayar `json`'a alınıp bu katman devreden çıkarılıyor — çünkü katman açıkken
 * `unserialize()` yolu hâlâ duruyor ve asıl kazanç onu kapatmak.
 *
 * @see \App\Session\SessionManager  hangi ayarda hangi deponun kurulduğu
 */
trait ReadsLegacySessions
{
    /**
     * Oturum verisini okur — önce yeni biçim, sonra eskisi.
     *
     * @return array<string, mixed>
     */
    protected function readFromHandler()
    {
        $raw = $this->handler->read($this->getId());

        if (! $raw) {
            return [];
        }

        $hazir = $this->prepareForUnserialize($raw);

        $data = json_decode($hazir, true);

        if (is_array($data)) {
            return $data;
        }

        // Eski biçim. `unserialize` burada bilinçli olarak sınırlı:
        // `allowed_classes: false` verildiğinde nesne kurulmuyor, gelen her
        // nesne `__PHP_Incomplete_Class` oluyor. Böylece geçiş dönemi
        // boyunca bile POP zinciri kurulamıyor — okunan şey yalnız veri.
        $data = @unserialize($hazir, ['allowed_classes' => false]);

        return is_array($data) ? $this->dropIncompleteObjects($data) : [];
    }

    /**
     * Kurulamayan nesneleri atar.
     *
     * `allowed_classes: false` ile okunan bir nesne `__PHP_Incomplete_Class`
     * olarak geliyor; onu oturumda tutmak sonraki isteklerde beklenmedik
     * hatalar doğurur. Tek gerçek örneği doğrulama hataları torbası ve o zaten
     * bir sonraki istekte yeniden kuruluyor.
     *
     * @param  array<mixed, mixed> $data
     * @return array<string, mixed>
     */
    private function dropIncompleteObjects(array $data): array
    {
        $temiz = [];

        foreach ($data as $anahtar => $deger) {
            if ($deger instanceof \__PHP_Incomplete_Class) {
                continue;
            }

            $temiz[(string) $anahtar] = is_array($deger)
                ? $this->dropIncompleteObjects($deger)
                : $deger;
        }

        return $temiz;
    }
}
