<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dile göre değişebilen ayarlar.
 *
 * Ayarların çoğu dilden bağımsız: renk, telefon, e-posta, dosya yolu, anahtar,
 * açık/kapalı. Bir avuç tanesi ise ziyaretçinin okuduğu **metin** ve tek dilde
 * tutulunca /en'de Türkçe çıkıyordu — alt bilgi telif satırı, mail başlığındaki
 * slogan, çalışma saatlerindeki "Kapalı".
 *
 * Liste bilerek dar ve açık: her ayara dil sekmesi koymak, panelde on iki dilde
 * doldurulacak bir renk kutusu demek olurdu. Buraya yeni bir anahtar eklemek
 * yeterli — şema, panel ve çözümleme listeyi kendisi okuyor.
 *
 * Marka adı (`site_name`) bilerek dışarıda: marka çevrilmez.
 */
final class TranslatableSettings
{
    /**
     * @var list<string>
     */
    private const KEYS = [
        // Kimlik ve SEO
        'site_title',
        'site_description',
        'site_keywords',
        'og_title',
        'og_description',

        // Alt bilgi
        'footer_text',
        'footer_credit',

        // İletişim — adres ve çalışma saatleri metin taşıyor ("Kapalı")
        'contact_address',
        'working_hours_weekday',
        'working_hours_saturday',
        'working_hours_sunday',

        // Mail şablonlarının altbilgisi
        'mail_theme_footer_text',

        // Bakım ekranı — ziyaretçinin okuduğu tek şey bu
        'maintenance_message',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return self::KEYS;
    }

    public static function has(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }
}
