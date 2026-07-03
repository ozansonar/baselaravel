<?php

declare(strict_types=1);

/**
 * Instagram Hashtag Filter Domain — whitelist + blacklist + Türkiye şehirleri.
 *
 * KULLANIM:
 *  - InstagramHashtagBuilder her hashtag'i 2 testten geçirir:
 *    1) Blacklist test → eşleşirse REDDEDİLİR (substring match)
 *    2) Whitelist test → en az 1 kelimeyle örtüşmüyorsa REDDEDİLİR
 *  - Eşleşme diacritics-aware: 'köytereyağı' → 'koy' / 'tereyagi' bulur
 *  - Tüm değerler lowercase ASCII normalize edilmiş halde tutulur
 *
 * AMAÇ: AI veya başka kaynak konu-dışı/spam/siyasi/argo etiket üretirse
 *       bunları sistematik olarak reddet. Ürün/çiftlik/tarım odaklı kal.
 */

return [
    /*
     * WHITELIST — kabul edilen tematik kelime kökleri.
     * Substring match ile çalışır (iki yönlü):
     *   "köytereyağı" tag'i → 'koy' veya 'tereyagi' bulur → kabul
     *   "tarımsal" tag'i → 'tarim' bulur → kabul
     */
    'whitelist' => [
        // ── Çiftlik / tarım / hayvancılık ────────────────────────
        'ciftlik', 'tarim', 'hayvancilik', 'koyun', 'inek', 'keci',
        'tavuk', 'aricilik', 'sagmal', 'mera', 'kirsal', 'bahce',
        'koyhayati', 'koylu', 'cobandurus', 'cobankoy',

        // ── Süt / mandıra ürünleri ───────────────────────────────
        'sut', 'sutum', 'sutkremasi', 'kaymak', 'yogurt', 'ayran',
        'kefir', 'lor', 'cokelek', 'peynir', 'beyazpeynir', 'kasarpeynir',
        'tulumpeyniri', 'orgupeyniri', 'tereyagi', 'butter', 'naturalbutter',

        // ── Diğer çiftlik ürünleri ───────────────────────────────
        'bal', 'organikbal', 'aribal', 'cikolatabal', 'recel',
        'yumurta', 'kazyumurtasi', 'ordekyumurtasi', 'gezenyumurta',
        'et', 'kuzueti', 'dana', 'kuzu', 'koyuneti', 'sucuk',
        'pastirma', 'kavurma', 'salam', 'sosis', 'pekmez', 'tarhana',
        'tursu', 'salca', 'tahin', 'helva', 'erik', 'kayısı', 'incir',

        // ── Kalite / üretim sıfatları ────────────────────────────
        'dogal', 'organik', 'koy', 'koyurun', 'koyurunleri', 'koyurunu',
        'geleneksel', 'taze', 'evyapimi', 'katkisiz', 'hormonsuz',
        'glutensiz', 'kaliteli', 'sertifikali', 'temizgida', 'safgida',

        // ── Mutfak / yemek / lezzet ──────────────────────────────
        'mutfak', 'turkmutfagi', 'turkishfood', 'lezzet', 'tarif',
        'yemek', 'yemektarifi', 'kahvalti', 'kahvaltilik', 'kahvaltikeyfi',
        'sofra', 'sofralik', 'gida', 'gidaurunleri', 'beslenme',
        'saglikli', 'sagliklibeslenme', 'sagliklitarifler',

        // ── Marka / yer (firmaya özel) ────────────────────────────
        'orhanbabaninciftligi', 'orhanbabacift', 'orhanbaba', 'corum',

        // ── Coğrafi bölge ────────────────────────────────────────
        'anadolu', 'icanadolu', 'karadeniz', 'ege', 'akdeniz', 'guneydogu',
        'doguanadolu', 'marmara', 'turkiye', 'turkey',

        // ── Genel ürün kelimesi ──────────────────────────────────
        'urun', 'urunler', 'urunlerimiz', 'cifligimizden',
    ],

    /*
     * 81 İL — şehir tag'leri için. Whitelist ayrı tutulur ki
     * "blog'da geçmeyen şehir" tag'i AI'dan dönse bile kabul edilmesin
     * (HashtagBuilder::detectCities sadece blog title'ında geçen şehri ekler).
     * Yine de filtreden geçerken whitelist olarak kabul edilmesi için listede.
     */
    'cities' => [
        'adana', 'adiyaman', 'afyon', 'agri', 'amasya', 'ankara', 'antalya',
        'artvin', 'aydin', 'balikesir', 'bilecik', 'bingol', 'bitlis', 'bolu',
        'burdur', 'bursa', 'canakkale', 'cankiri', 'corum', 'denizli',
        'diyarbakir', 'edirne', 'elazig', 'erzincan', 'erzurum', 'eskisehir',
        'gaziantep', 'giresun', 'gumushane', 'hakkari', 'hatay', 'isparta',
        'mersin', 'istanbul', 'izmir', 'kars', 'kastamonu', 'kayseri',
        'kirklareli', 'kirsehir', 'kocaeli', 'konya', 'kutahya', 'malatya',
        'manisa', 'kahramanmaras', 'mardin', 'mugla', 'mus', 'nevsehir',
        'nigde', 'ordu', 'rize', 'sakarya', 'samsun', 'siirt', 'sinop',
        'sivas', 'tekirdag', 'tokat', 'trabzon', 'tunceli', 'sanliurfa',
        'usak', 'van', 'yozgat', 'zonguldak', 'aksaray', 'bayburt', 'karaman',
        'kirikkale', 'batman', 'sirnak', 'bartin', 'ardahan', 'igdir',
        'yalova', 'karabuk', 'kilis', 'osmaniye', 'duzce',
    ],

    /*
     * BLACKLIST — kabul edilmez. Substring match (tag içinde geçerse red).
     * AI hata yapsa bile bu filtre son barikat.
     */
    'blacklist' => [
        // Discovery / takipleşme spam'leri
        'kesfet', 'kesfetbeniöneöner', 'kesfetbeni', 'kesfetdarbe',
        'kesfetteyiz', 'viral', 'foryou', 'fyp', 'trend', 'trends',
        'trendler', 'takipet', 'takiplesme', 'takiples', 'like4like',
        'l4l', 'f4f', 'gunungondersi', 'gunungonderisi', 'gunungonderim',
        'instagood', 'instadaily', 'photooftheday', 'tflers',

        // Siyaset / parti / siyasetçi
        'siyaset', 'akp', 'akparti', 'chp', 'mhp', 'iyiparti', 'iyip',
        'hdp', 'dem', 'tip', 'recep', 'tayyip', 'erdogan', 'kemal',
        'kilicdaroglu', 'devletbahceli', 'meralaksener', 'alibabacan',
        'cumhurbaskan', 'basbakan', 'milletvekili', 'belediyebaskani',
        'secimler', 'oyver',

        // Asker / şehit / silahlı
        'asker', 'askeri', 'jandarma', 'sehit', 'gazi', 'tsk', 'sat',
        'mit', 'sehitler', 'silahli',

        // Genel duygusal / lifestyle
        'sevgi', 'ask', 'mutluluk', 'huzur', 'gunaydin', 'iyiaksamlar',
        'iyigeceler', 'iyihaftasonlari', 'tatil', 'yaztatili', 'kistatili',
        'moda', 'fashion', 'stil', 'guzellik', 'makyaj', 'cilt', 'sacbakimi',
        'fitness', 'spor', 'gym',

        // Argo / küfür / +18
        'sex', 'porno', 'erotik', 'gay', 'lezbiyen', 'cinsel', 'siyasi',
        'icki', 'alkol', 'sarap', 'bira', 'raki', 'votka', 'viski',
        'sigara', 'tutun', 'esrar', 'uyusturucu', 'kumar', 'bahis',

        // Şiddet / olum
        'siddet', 'savas', 'olum', 'silah', 'kavga', 'dovus', 'kan',

        // Ünlü / futbol / magazin
        'galatasaray', 'fenerbahce', 'besiktas', 'trabzonspor', 'futbol',
        'mac', 'derbi', 'magazin', 'unlu', 'jetset', 'paparazzi',

        // İlgisiz tüketici/teknoloji
        'iphone', 'samsung', 'apple', 'huawei', 'tesla', 'bmw', 'mercedes',
        'oyun', 'gaming', 'pubg', 'fortnite',
    ],

    /*
     * AI'a explicit talimat olarak verilecek "yasak etiket" örnekleri.
     * Sıkı prompt'ta kullanıcıya örnekler — model "bunlar yasak" görsün.
     */
    'forbidden_examples' => [
        'kesfet', 'viral', 'foryou', 'trend', 'takipet', 'siyaset',
        'galatasaray', 'sevgi', 'mutluluk', 'gunaydin', 'tatil',
    ],
];
