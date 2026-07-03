<?php

declare(strict_types=1);

/**
 * Türkiye için Instagram içerik takviminde kullanılacak özel günler.
 *
 * Yapı:
 *  - 'fixed'    → Sabit tarihli (her yıl aynı). md = "MM-DD".
 *  - 'movable'  → Hareketli (yıla göre değişen) — Diyanet takvimine göre güncel kalmalı.
 *  - Kategoriler: ulusal, dini, ozel, sezonsal
 *
 * Yeni yıl eklerken: 'movable' altına "YYYY" anahtarıyla yeni dizi ekle.
 * Diyanet kaynağı: https://namazvakitleri.diyanet.gov.tr/tr-TR/dini-gunler
 */
return [
    '_meta' => [
        'note'         => 'Hareketli (dini) günler her yıl Diyanet takvimine göre güncellenmelidir.',
        'last_updated' => '2026-05-08',
        'source'       => 'Diyanet İşleri Başkanlığı + resmi tatil takvimi',
    ],

    // ─── SABİT TARİHLİ ÖZEL GÜNLER (her yıl aynı) ───
    'fixed' => [
        ['md' => '01-01', 'name' => 'Yılbaşı',                              'category' => 'ulusal',   'theme' => 'Yeni yıl kutlaması, umut, doğal lezzet ile yeni başlangıç'],
        ['md' => '02-14', 'name' => 'Sevgililer Günü',                      'category' => 'ozel',     'theme' => 'Sevdiğine bal/peynir/kahvaltı sürprizi, bir tutam aşk'],
        ['md' => '03-08', 'name' => 'Dünya Kadınlar Günü',                  'category' => 'ozel',     'theme' => 'Köy kadınının emeği, üreten kadınlara saygı'],
        ['md' => '03-18', 'name' => 'Çanakkale Şehitlerini Anma',           'category' => 'ulusal',   'theme' => 'Saygı, dua, sade ve onurlu bir paylaşım'],
        ['md' => '03-21', 'name' => 'Nevruz / Bahar Bayramı',               'category' => 'sezonsal', 'theme' => 'Bahar uyanışı, taze otlar, ilkbahar süt-yoğurt'],
        ['md' => '04-23', 'name' => '23 Nisan Ulusal Egemenlik ve Çocuk Bayramı', 'category' => 'ulusal', 'theme' => 'Çocuklara doğal süt-yoğurt, çocukluk anıları'],
        ['md' => '05-01', 'name' => 'Emek ve Dayanışma Günü',               'category' => 'ulusal',   'theme' => 'Çiftçinin alın teri, üreticiye saygı'],
        ['md' => '05-06', 'name' => 'Hıdırellez',                           'category' => 'sezonsal', 'theme' => 'Bahar geleneği, dilek tutma, taze ürünler'],
        ['md' => '05-19', 'name' => '19 Mayıs Atatürk\'ü Anma Gençlik ve Spor Bayramı', 'category' => 'ulusal', 'theme' => 'Gençlik, sporcu kahvaltısı, sağlıklı beslenme'],
        ['md' => '06-21', 'name' => 'Yaz Gündönümü',                        'category' => 'sezonsal', 'theme' => 'Yaz başlangıcı, ayran-cacık, soğuk içecekler'],
        ['md' => '07-15', 'name' => '15 Temmuz Demokrasi ve Milli Birlik Günü', 'category' => 'ulusal', 'theme' => 'Saygı ve birlik teması, sade paylaşım'],
        ['md' => '08-30', 'name' => '30 Ağustos Zafer Bayramı',             'category' => 'ulusal',   'theme' => 'Zafer, gurur, milli vurgu'],
        ['md' => '09-23', 'name' => 'Sonbahar Ekinoksu',                    'category' => 'sezonsal', 'theme' => 'Sonbahar, hasat, reçel-pekmez başlangıcı'],
        ['md' => '10-29', 'name' => '29 Ekim Cumhuriyet Bayramı',           'category' => 'ulusal',   'theme' => 'Cumhuriyet, gurur, kırmızı-beyaz tema'],
        ['md' => '11-10', 'name' => 'Atatürk\'ü Anma Günü',                 'category' => 'ulusal',   'theme' => 'Saygı ve anma, sade paylaşım, 09:05 saatine vurgu'],
        ['md' => '11-24', 'name' => 'Öğretmenler Günü',                     'category' => 'ozel',     'theme' => 'Öğretmenlere kahvaltı sürprizi, emeğe saygı'],
        ['md' => '12-21', 'name' => 'Kış Gündönümü',                        'category' => 'sezonsal', 'theme' => 'Kış başlangıcı, tarhana çorbası, sıcak içecek'],
        ['md' => '12-31', 'name' => 'Yılbaşı Gecesi',                       'category' => 'ozel',     'theme' => 'Yıl sonu, lezzetli sofra hazırlığı, sürprizli kutlama'],
    ],

    // ─── HAREKETLİ (DİNİ + DEĞİŞKEN) GÜNLER ─── Diyanet takvimine göre yıllık güncel kalmalı
    // ⚠ ÖNEMLİ: Hareketli dini günler hicri takvime bağlıdır ve Diyanet'in resmi
    // takvimine göre değişir. Mevcut tarihler en yaygın kabul gören değerlerdir;
    // KESİN tarih için Diyanet İşleri Başkanlığı'nın resmi sayfasını kontrol edin:
    // https://namazvakitleri.diyanet.gov.tr/tr-TR/dini-gunler
    //
    // Sabit kurallar (her yıl otomatik hesaplanır — config'de değişmez):
    //  - Anneler Günü = Mayıs ayının 2. Pazarı
    //  - Babalar Günü = Haziran ayının 3. Pazarı
    //  - Ramazan Bayramı = 3 gün (1. Şevval'den başlar)
    //  - Kurban Bayramı = 4 gün (10. Zilhicce'den başlar)
    'movable' => [
        2026 => [
            ['date' => '2026-01-26', 'name' => 'Regaib Kandili',          'category' => 'dini',     'theme' => 'Manevi atmosfer, sade paylaşım, dua'],
            ['date' => '2026-02-13', 'name' => 'Berat Kandili',           'category' => 'dini',     'theme' => 'Manevi yenilenme, helva geleneği'],
            ['date' => '2026-02-17', 'name' => 'Ramazan Başlangıcı',      'category' => 'dini',     'theme' => 'İlk iftar sofrası, hurma-zeytin-pekmez, sahur paketleri'],
            ['date' => '2026-03-15', 'name' => 'Kadir Gecesi',            'category' => 'dini',     'theme' => 'Manevi atmosfer, sade ve içten paylaşım (27. Ramazan gecesi)'],
            ['date' => '2026-03-19', 'name' => 'Ramazan Bayramı 1. Gün',  'category' => 'dini',     'theme' => 'Bayram kahvaltısı, baklava, çikolata-şeker, ziyaret'],
            ['date' => '2026-03-20', 'name' => 'Ramazan Bayramı 2. Gün',  'category' => 'dini',     'theme' => 'Bayram ikramı, akraba ziyareti, kalan tatlılar'],
            ['date' => '2026-03-21', 'name' => 'Ramazan Bayramı 3. Gün',  'category' => 'dini',     'theme' => 'Bayramın son günü, komşu ziyareti, hediye paketleri'],
            ['date' => '2026-05-10', 'name' => 'Anneler Günü',            'category' => 'ozel',     'theme' => 'Anne emeği, anneye özel kahvaltı paketi (Mayıs ayının 2. Pazarı)'],
            ['date' => '2026-05-26', 'name' => 'Kurban Bayramı 1. Gün',   'category' => 'dini',     'theme' => 'Bayram kahvaltısı, et-süt sofrası, paylaşım'],
            ['date' => '2026-05-27', 'name' => 'Kurban Bayramı 2. Gün',   'category' => 'dini',     'theme' => 'Et yemekleri, kavurma, bayram tatlıları'],
            ['date' => '2026-05-28', 'name' => 'Kurban Bayramı 3. Gün',   'category' => 'dini',     'theme' => 'Komşu ziyareti, et paylaşımı, soframıza buyrun'],
            ['date' => '2026-05-29', 'name' => 'Kurban Bayramı 4. Gün',   'category' => 'dini',     'theme' => 'Bayram sonu, sade kapanış, evdeki sofra'],
            ['date' => '2026-06-21', 'name' => 'Babalar Günü',            'category' => 'ozel',     'theme' => 'Baba emeği, babaya özel kahvaltı (Haziran ayının 3. Pazarı)'],
            ['date' => '2026-08-25', 'name' => 'Mevlid Kandili',          'category' => 'dini',     'theme' => 'Manevi atmosfer, helva ikramı'],
        ],

        2027 => [
            ['date' => '2027-01-15', 'name' => 'Regaib Kandili',          'category' => 'dini',     'theme' => 'Manevi atmosfer, sade paylaşım, dua'],
            ['date' => '2027-02-02', 'name' => 'Berat Kandili',           'category' => 'dini',     'theme' => 'Manevi yenilenme, helva geleneği'],
            ['date' => '2027-02-07', 'name' => 'Ramazan Başlangıcı',      'category' => 'dini',     'theme' => 'İlk iftar sofrası, hurma-zeytin-pekmez, sahur paketleri'],
            ['date' => '2027-03-05', 'name' => 'Kadir Gecesi',            'category' => 'dini',     'theme' => 'Manevi atmosfer, sade ve içten paylaşım (27. Ramazan gecesi)'],
            ['date' => '2027-03-09', 'name' => 'Ramazan Bayramı 1. Gün',  'category' => 'dini',     'theme' => 'Bayram kahvaltısı, baklava, çikolata-şeker, ziyaret'],
            ['date' => '2027-03-10', 'name' => 'Ramazan Bayramı 2. Gün',  'category' => 'dini',     'theme' => 'Bayram ikramı, akraba ziyareti, kalan tatlılar'],
            ['date' => '2027-03-11', 'name' => 'Ramazan Bayramı 3. Gün',  'category' => 'dini',     'theme' => 'Bayramın son günü, komşu ziyareti, hediye paketleri'],
            ['date' => '2027-05-09', 'name' => 'Anneler Günü',            'category' => 'ozel',     'theme' => 'Anne emeği, anneye özel kahvaltı paketi (Mayıs ayının 2. Pazarı)'],
            ['date' => '2027-05-16', 'name' => 'Kurban Bayramı 1. Gün',   'category' => 'dini',     'theme' => 'Bayram kahvaltısı, et-süt sofrası, paylaşım'],
            ['date' => '2027-05-17', 'name' => 'Kurban Bayramı 2. Gün',   'category' => 'dini',     'theme' => 'Et yemekleri, kavurma, bayram tatlıları'],
            ['date' => '2027-05-18', 'name' => 'Kurban Bayramı 3. Gün',   'category' => 'dini',     'theme' => 'Komşu ziyareti, et paylaşımı, soframıza buyrun'],
            ['date' => '2027-05-19', 'name' => 'Kurban Bayramı 4. Gün',   'category' => 'dini',     'theme' => 'Bayram sonu, sade kapanış, evdeki sofra'],
            ['date' => '2027-06-20', 'name' => 'Babalar Günü',            'category' => 'ozel',     'theme' => 'Baba emeği, babaya özel kahvaltı (Haziran ayının 3. Pazarı)'],
            ['date' => '2027-08-14', 'name' => 'Mevlid Kandili',          'category' => 'dini',     'theme' => 'Manevi atmosfer, helva ikramı'],
        ],
    ],
];
