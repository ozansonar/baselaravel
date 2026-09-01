<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bir SEO bulgusunun ne kadar ciddi olduğu.
 *
 * Üç seviye üç ayrı şey söylüyor ve ayrımı korumak önemli: her şeye "uyarı"
 * demek listeyi okunmaz hâle getirir, her şeye "hata" demek yazarı denetimi
 * kapatmaya iter. Hiçbiri kaydetmeyi engellemiyor — kaydetmeyi FormRequest
 * sınırlar, denetleyici yalnız gösterir.
 */
enum SeoLevel: string
{
    /** Arama motoru sayfayı yanlış anlıyor ya da ziyaretçi kırık bir şeyle karşılaşıyor. */
    case Error = 'error';

    /** Sayfa çalışıyor ama arama sonucunda görünürlüğü düşüyor. */
    case Warning = 'warning';

    /** İyileştirme önerisi; bugün bir şey bozmuyor. */
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Error   => __('seo.levels.error'),
            self::Warning => __('seo.levels.warning'),
            self::Info    => __('seo.levels.info'),
        };
    }

    /**
     * Sıralama ağırlığı — küçük olan önce gösterilir.
     *
     * Bulgular seviyeye göre sıralanıyor: yazarın önce göreceği şey, önce
     * düzeltmesi gereken şey olmalı.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Error   => 0,
            self::Warning => 1,
            self::Info    => 2,
        };
    }

    /**
     * Panel rozetinin renk sınıfı.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Error   => 'seo-badge--error',
            self::Warning => 'seo-badge--warning',
            self::Info    => 'seo-badge--info',
        };
    }

    /**
     * Skor cezası.
     *
     * Skor bir not değil, bir sıralama aracı: yüz içerikten hangisine önce
     * bakılacağını söylüyor. Bu yüzden ceza seviyeyle orantılı ve hata,
     * kaç uyarıya bedel olduğu düşünülerek ağırlıklandırıldı.
     */
    public function penalty(): int
    {
        return match ($this) {
            self::Error   => 15,
            self::Warning => 7,
            self::Info    => 2,
        };
    }
}
