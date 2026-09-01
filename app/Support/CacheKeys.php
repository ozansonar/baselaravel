<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Önbellek anahtarlarının kaydı.
 *
 * Anahtarlar otuzdan fazla yerde dizge sabiti olarak yazılıydı: bir servis
 * `'sitemap.urls'`, bir observer `'admin.pages.stats'`, bir başkası aynı
 * anahtarı bir harf farkla. Hangi içeriğin hangi türev önbelleği beslediği
 * kodun içine gömülüydü ve yeni bir içerik türü eklendiğinde `sitemap.urls`'i
 * unutmak, site haritasının bir saat bayat kalmasına yol açıyordu — hata
 * vermeden, testi kırmadan.
 *
 * Burada iki şey var: **anahtarların kendisi** ve **öneklerin listesi**.
 * Önekler önemli, çünkü bir anahtar tek başına değil bir kümenin parçası
 * olabiliyor (analitik anahtarları tarih aralığına göre türüyor); o kümeyi
 * silmenin tek yolu, tek tek adlarını bilmek değil, öneki bilmek.
 */
final class CacheKeys
{
    // ── Ön yüz içeriği ──

    public const SITEMAP_URLS = 'sitemap.urls';
    public const SITEMAP_PAGE_GROUPS = 'sitemap_page.groups';
    public const PAGES_PUBLISHED = 'pages.published';
    public const BLOG_CATEGORIES_ACTIVE = 'blog_categories.active';
    public const GALLERY_CATEGORIES_ACTIVE = 'gallery_categories.active';
    public const GALLERY_PHOTOS = 'gallery.photos';
    public const GALLERY_VIDEOS = 'gallery.videos';
    public const FAQS_ACTIVE = 'faqs.active';
    public const SLIDERS_ACTIVE = 'sliders.active';
    public const REDIRECTS_ACTIVE = 'redirects.active';

    // ── Panel sayaçları ──

    public const ADMIN_DASHBOARD_STATS = 'admin.dashboard.stats';
    public const ADMIN_USER_STATS = 'admin_user_stats';
    public const ADMIN_PAGES_STATS = 'admin.pages.stats';
    public const ADMIN_SLIDERS_STATS = 'admin.sliders.stats';
    public const ADMIN_POPUPS_STATS = 'admin.popups.stats';
    public const ADMIN_FAQS_STATS = 'admin.faqs.stats';
    public const ADMIN_GALLERY_STATS = 'admin.gallery.stats';
    public const ADMIN_GALLERY_CATEGORIES_STATS = 'admin.gallery_categories.stats';
    public const ADMIN_BLOG_CATEGORIES_STATS = 'admin.blog_categories.stats';
    public const ADMIN_BLOG_COMMENTS_STATS = 'admin.blog_comments.stats';
    public const ADMIN_CONTACT_MESSAGES_STATS = 'admin.contact_messages.stats';
    public const ADMIN_MAIL_LOGS_STATS = 'admin.mail_logs.stats';
    public const BLOG_ADMIN_STATS = 'blog.admin_stats';
    public const REDIRECTS_ADMIN_STATS = 'redirects.admin_stats';

    // ── Sistem ──

    public const SETTINGS_ALL = 'settings.all';
    public const SETTINGS_PUBLIC = 'api.settings.public';
    public const LANGUAGES_ACTIVE = 'languages.active';
    public const LANGUAGES_DEFAULT = 'languages.default';
    public const CUSTOM_ROUTES_MAP = 'custom_routes.map';
    public const MAIL_TEMPLATES_ALL = 'mail_templates.all';

    // ── Önekler ──

    /**
     * Analitik anahtarları tarih aralığına, süzgece ve sınıra göre türüyor;
     * kaç tane olduğu önceden bilinmiyor. Ortak önek, tek tek adlarını bilmeden
     * hepsini silebilmenin tek yolu.
     */
    public const PREFIX_ANALYTICS = 'analytics.';

    /** Sayfaya göre türeyen popup listeleri. */
    public const PREFIX_POPUPS = 'popups.';

    /** Ön yüz parça önbelleği (dil ve parça adına göre türüyor). */
    public const PREFIX_FRAGMENT = 'fragment.';

    /**
     * Toplu SEO denetimi sonuçları.
     *
     * Anahtar içeriğin güncelleme zamanını taşıyor; kayıt değişince eski
     * anahtar kendiliğinden terk ediliyor. Önek yine de gerekli: ayarlar
     * değiştiğinde (karakter sınırı gibi) bütün denetimler bayatlıyor ve
     * hepsinin birden düşmesi gerekiyor.
     */
    public const PREFIX_SEO_AUDIT = 'seo.audit.';

    /**
     * İçerik değiştiğinde bayatlayan ön yüz anahtarları.
     *
     * Tek tek hatırlanması gereken listeyi tek yerde tutmak, yeni bir içerik
     * türü eklendiğinde neyin düşürülmesi gerektiğini de tek yerde
     * güncellemeyi sağlıyor.
     *
     * @return list<string>
     */
    public static function contentKeys(): array
    {
        return [
            self::SITEMAP_URLS,
            self::SITEMAP_PAGE_GROUPS,
            self::PAGES_PUBLISHED,
            self::BLOG_CATEGORIES_ACTIVE,
            self::GALLERY_CATEGORIES_ACTIVE,
            self::GALLERY_PHOTOS,
            self::GALLERY_VIDEOS,
            self::FAQS_ACTIVE,
            self::SLIDERS_ACTIVE,
        ];
    }

    /**
     * Önek bazlı temizlik yapılabilen kümeler.
     *
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return [
            self::PREFIX_ANALYTICS,
            self::PREFIX_POPUPS,
            self::PREFIX_FRAGMENT,
            self::PREFIX_SEO_AUDIT,
        ];
    }
}
