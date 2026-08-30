<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Bir görselin alt metni.
 *
 * Boş alt, ekran okuyucu için görselin hiç olmaması demek; arama motoru için
 * de görselin ne anlattığını söyleyen tek ipucunun kaybı. Alan boş
 * bırakılabildiği sürece er ya da geç boş kalıyor — bu yüzden karar
 * görünümde değil burada: sırayla içeriğin kendi metni, sonra sitenin genel
 * tanımı deneniyor ve elde her zaman bir şey kalıyor.
 *
 * Maliyet sıfıra yakın. Ayarlar zaten istek başına bir kez okunup statik bir
 * diziye alınıyor (Setting::getValue), sitenin yedek metni de burada bir kez
 * seçilip saklanıyor: yüz görsellik bir galeride yüz kez değil, bir kez.
 */
final class ImageAltResolver
{
    /**
     * Alt metin ne kadar uzun olabilir.
     *
     * Ekran okuyucular uzun metni tek nefeste okuyor; arama motorları da
     * fazlasını yok sayıyor. Sitenin tanımı bir cümleden uzun olabildiği için
     * kesme burada yapılıyor.
     */
    private const MAX_LENGTH = 125;

    private ?string $siteFallback = null;

    /**
     * İlk dolu aday, yoksa sitenin genel metni.
     *
     * Adaylar çağıran yerin sırasıyla geliyor: görselin kendi metni, ait
     * olduğu içeriğin anahtar kelimesi ya da başlığı, sonra bu sınıfın
     * bildiği site geneli.
     */
    public function resolve(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $text = $this->clean($candidate);

            if ($text !== '') {
                return $text;
            }
        }

        return $this->siteFallback ??= $this->fromSite();
    }

    /** Testlerde ayar değişince yeniden seçilsin. */
    public function flush(): void
    {
        $this->siteFallback = null;
    }

    /**
     * Sitenin kendini anlattığı metin.
     *
     * Sıra kullanıcının istediği gibi: önce açıklama, sonra anahtar
     * kelimeler. İkisi de boşsa geriye site adı kalıyor — adı olmayan site
     * yok, yani zincir hiçbir zaman boş bitmiyor.
     */
    private function fromSite(): string
    {
        foreach (['site_description', 'site_keywords', 'site_name'] as $key) {
            $text = $this->clean(Setting::getValue($key));

            if ($text !== '') {
                return $text;
            }
        }

        return (string) config('app.name');
    }

    /**
     * Metni alt niteliğine uygun hâle getirir.
     *
     * HTML etiketi, satır sonu ve fazladan boşluk temizleniyor: alt metni
     * biçimlendirme taşımıyor, düz bir cümle.
     */
    private function clean(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return mb_strlen($text) > self::MAX_LENGTH
            ? rtrim(mb_substr($text, 0, self::MAX_LENGTH - 1)) . '…'
            : $text;
    }
}
