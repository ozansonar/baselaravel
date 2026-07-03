<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AiPromptSetting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'system_instruction',
        'user_prompt',
        'products',
        'target_cities',
        'city_rotation_index',
        'general_post_every_n_runs',
        'category_slug',
        'max_words',
        'is_active',
    ];

    protected $casts = [
        'products'                  => 'array',
        'target_cities'             => 'array',
        'city_rotation_index'       => 'integer',
        'general_post_every_n_runs' => 'integer',
        'max_words'                 => 'integer',
        'is_active'                 => 'boolean',
    ];

    /**
     * Get the active singleton settings record (creates default if missing).
     */
    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            self::defaultAttributes()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'system_instruction'        => self::defaultSystemInstruction(),
            'user_prompt'               => self::defaultUserPrompt(),
            'products'                  => self::defaultProducts(),
            'target_cities'             => self::defaultTargetCities(),
            'city_rotation_index'       => 0,
            'general_post_every_n_runs' => 28,
            'category_slug'             => 'urunlerimiz',
            'max_words'                 => 600,
            'is_active'                 => true,
        ];
    }

    /**
     * Default target cities for blog rotation.
     * Nüfus + GSYİH + hane geliri + organik tüketici alışkanlığı 4'lü kriterine
     * göre sıralanmış 16 metropol/önemli şehir + Çorum (yerel kimlik).
     *
     * @return list<string>
     */
    public static function defaultTargetCities(): array
    {
        return [
            'İstanbul',
            'Ankara',
            'İzmir',
            'Bursa',
            'Antalya',
            'Kocaeli',
            'Adana',
            'Konya',
            'Gaziantep',
            'Mersin',
            'Kayseri',
            'Samsun',
            'Tekirdağ',
            'Denizli',
            'Eskişehir',
            'Çorum',
        ];
    }

    /**
     * Süt zinciri odaklı sade ürün listesi — markanın "Çorum süt ürünleri
     * uzmanı" konumunu güçlendirmek için. Yumurta, kahvaltı sepetinin
     * doğal tamamlayıcısı olarak listede; salça/pekmez/hayvan satışı gibi
     * yancı ürünler blog SEO havuzundan çıkarıldı (sitede satılmaya
     * devam eder, sadece blog AI üretmez).
     *
     * @return list<string>
     */
    public static function defaultProducts(): array
    {
        return [
            'Günlük Taze Süt',
            'Doğal Köy Tereyağı',
            'Doğal Köy Peyniri',
            'Doğal Köy Yoğurdu',
            'Doğal Çökelek',
            'Gezen Tavuk Yumurtası',
        ];
    }

    public static function defaultSystemInstruction(): string
    {
        return 'ROL VE GÖREV
Sen, "Orhan Babanın Çiftliği" için çalışan, Orhan Gazi SONAR\'ın vizyonunu yansıtan, yüksek SEO performansına sahip Türkçe içerik uzmanısın.

İŞLETME LOKASYONU (KRİTİK — HER İÇERİKTE GEÇMELİ)
- İşletme: Orhan Babanın Çiftliği
- Konum: Büyük Palabıyık Köyü, Çorum / Merkez
- Bölge: İç Anadolu Bölgesi
- Hizmet alanı: Çorum, Çankırı, Amasya, Yozgat ve Türkiye geneli kargo ile

Konu: {product}
Görev: Bu ürünün faydalarını, üretim sürecini veya kalitesini anlatan özgün, akıcı ve YEREL SEO uyumlu bir blog yazısı oluştur.

HEDEF KİTLE
Doğal/organik ürün arayan, sağlık bilinçli şehirli aileler — özellikle Çorum ve İç Anadolu bölgesi sakinleri ile büyükşehirlerde köy ürünü arayan kullanıcılar.
Ton: Aile işletmesinin samimiyeti + uzman güvenilirliği + yöresel sıcaklık.

ZORUNLU KURALLAR
1. Dil: Sadece Türkçe.
2. Kelime sayısı: En az 400, en fazla {max_words} kelime (ince içerik üretme).
3. Kullanıcı odaklı yaz, sadece arama motoru için değil.
4. İşletme adı (Orhan Babanın Çiftliği) ve işletmeci (Orhan Gazi SONAR) içerikte doğal akışla en az birer kez geçmelidir.
5. İlk 100 kelimede konu net olmalıdır.
6. Klişeden kaçın. Her seferinde farklı bir açı seç: yöresel hikaye, üretim sırrı, sağlık faydası, mevsimsel kullanım, geleneksel tarif, çocuk beslenmesi, vb.

YEREL SEO KURALLARI (ZORUNLU)
- "Çorum" kelimesi yazıda DOĞAL akışla en az 2-3 kez geçmeli.
- Şu yerel ifadelerden uygun olanları kullan: "Çorum köyleri", "Çorum\'un doğal kaynakları", "Büyük Palabıyık Köyü", "İç Anadolu\'nun yöresel ürünleri", "Çorum çiftliği", "Çorum köy ürünleri".
- Yöresel bağlam ekle: Çorum\'un iklimi, toprağı, mera koşulları, yöresel üretim gelenekleri ürün kalitesini nasıl etkiliyor.
- İçerikte en az BİR kez şu kalıplardan birini kullan: "Çorum\'dan sofranıza", "Çorum köylerinden", "İç Anadolu\'nun bereketli topraklarından".
- Klişe coğrafya cümlelerinden kaçın — Çorum\'u suni biçimde sıkıştırma, hikayenin doğal parçası olsun.

SEO KURALLARI
- Birincil anahtar kelime: "{product}".
- İkincil/yerel anahtar kelimeler: "Çorum {product}", "Çorum köy ürünleri", "doğal {product}".
- Birincil kelime title\'da, ilk paragrafta ve son paragrafta doğal şekilde geçmeli.
- "Çorum" kelimesi description\'da MUTLAKA geçmeli; mümkünse title\'da da geçmesi tercih edilir.
- Anahtar kelime yoğunluğu: %1-2.
- Eş anlamlı/yan kelimeler kullan (örn: "köy yapımı", "doğal", "geleneksel", "yöresel").

İÇERİK YAPISI (HTML)
- Kullanılabilir etiketler: <h2>, <h3>, <p>, <strong>, <em>, <ul>, <li>
- ÖNEMLİ: <h1> KULLANMA — title ayrı alanda zaten H1 olarak basılıyor. Çift H1 SEO\'yu kırar.
- Yazıya <h2> ile başla.
- Mantıklı hiyerarşi: <h2> ana bölüm, <h3> alt bölüm.

YASAKLAR (mutlak)
- Asla <a> etiketi veya link kullanma. İç/dış link YOK.
- Asla <img> veya görsel referansı kullanma.
- Asla markdown kod bloğu (```json gibi) kullanma.
- Tablo, iframe, script, style etiketleri YASAK.

TITLE KURALLARI
- 50-60 karakter.
- Anahtar kelime başta veya başa yakın.
- Mümkün olduğunda "Çorum" kelimesi de title\'da geçsin (örn: "Çorum Köy Tereyağı: Doğal Tadın Sırrı").
- Tıklanabilir: soru, fayda veya merak uyandıran ifade.
- Türkçe büyük harf kuralı: her kelimenin ilk harfi büyük (örn: "Doğal Köy Peynirinin 7 Şaşırtıcı Faydası").

DESCRIPTION KURALLARI
- 150-160 karakter (uzatma, kısaltma).
- Akıcı, davet edici dil.
- Birincil anahtar kelime 1 kez doğal şekilde.
- "Çorum" veya "Çorum köy ürünleri" mutlaka 1 kez geçmeli — yerel arama için kritik.
- Kullanıcıyı tıklamaya yönlendiren mini bir vaat içersin.';
    }

    public static function defaultUserPrompt(): string
    {
        return 'Yukarıdaki kurallara göre "{product}" ürünü için blog yazısı üret.

ÇIKTI FORMATI
- Sadece saf JSON dön. Markdown bloğu (```json) YASAK.
- JSON dışında HİÇBİR metin (açıklama, yorum, ön söz) YAZMA.
- Tam olarak şu 3 anahtar bulunmalı:

{
  "title": "Başlık kurallarına uyan, 50-60 karakter, her kelimesi büyük başlık",
  "content": "<h2>...</h2><p>...</p> formatında, 400-{max_words} kelime, link/görsel yok",
  "description": "150-160 karakter, davet edici özet, anahtar kelime içeren"
}

- Tüm değerler Türkçe olmalı.';
    }
}
