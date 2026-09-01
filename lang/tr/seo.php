<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO Denetleyici Metinleri
|--------------------------------------------------------------------------
|
| Her bulgunun iki cümlesi var: mesaj sorunu söylüyor, ipucu çözümü. Ayrım
| bilinçli — yalnız sorunu söyleyen bir uyarı yazarı ekranda arattırıyor.
|
| Cümleler suçlayıcı değil, tarif edici: "hata yaptın" değil, "şu eksik".
|
*/

return [

    'levels' => [
        'error'   => 'Hata',
        'warning' => 'Uyarı',
        'info'    => 'Öneri',
    ],

    'panel' => [
        'title'   => 'SEO denetimi',
        'score'   => 'puan',
        'run'     => 'Denetle',
        'idle'    => 'Kaydetmeden önce içeriği denetleyin: eksik meta, fazladan başlık, alt metinsiz görsel ve kırık bağlantı burada görünür.',
        'running' => 'Denetleniyor…',
        'clean'   => 'Bu dilde bir sorun bulunmadı.',
        'failed'  => 'Denetim şu an çalışmadı; kaydetmeyi engellemez, birazdan tekrar deneyebilirsiniz.',
        'note'    => 'Bulgular uyarıdır, kaydetmeyi engellemez.',
    ],

    'checks' => [

        'meta_title' => [
            'missing'       => 'Meta başlık boş; arama sonucunda sayfa başlığı kullanılacak.',
            'missing_hint'  => 'Arama sonucunda görünmesini istediğiniz başlığı yazın.',
            'duplicate'     => 'Meta başlık, sayfa başlığının birebir kopyası.',
            'duplicate_hint' => 'Alanı boş bıraksanız da aynı sonuç doğardı; farklılaştırın ya da boşaltın.',
            'too_long'      => 'Meta başlık :length karakter; :max karakterden sonrası arama sonucunda kırpılıyor.',
            'too_long_hint' => 'En önemli kelimeleri ilk :max karaktere alın.',
            'too_short'     => 'Meta başlık :length karakter; :min karakterin altı sonuçta zayıf duruyor.',
            'too_short_hint' => 'Sayfanın konusunu anlatan birkaç kelime daha ekleyin.',
            'too_long_fallback'  => 'Arama sonucunda kullanılacak sayfa başlığı :length karakter; :max karakterden sonrası kırpılıyor.',
            'too_short_fallback' => 'Arama sonucunda kullanılacak sayfa başlığı :length karakter; :min karakterin altı zayıf duruyor.',
        ],

        'meta_description' => [
            'missing'      => 'Meta açıklama boş; arama motoru gövdeden rastgele bir parça seçecek.',
            'missing_hint' => 'Sayfanın ne anlattığını bir iki cümlede özetleyin.',
            'too_long'     => 'Meta açıklama :length karakter; :max karakterden sonrası görünmüyor.',
            'too_long_hint' => 'Özeti :max karaktere sığdırın.',
            'too_short'    => 'Meta açıklama :length karakter; :min karakterin altı sonuçta boş duruyor.',
            'too_short_hint' => 'Bir cümle daha ekleyerek açıklamayı tamamlayın.',
        ],

        'heading' => [
            'extra_h1'      => 'Gövdede :count adet H1 başlığı var; sayfanın başlığı zaten H1.',
            'extra_h1_hint' => 'Gövdedeki başlıkları H2 ve altına çevirin.',
            'skipped'       => 'Başlık sırası atlıyor: :from' . "'" . 'den :to' . "'" . 'ye geçilmiş.',
            'skipped_hint'  => 'Araya eksik seviyeyi ekleyin ya da başlığı bir üst seviyeye çekin.',
        ],

        'image_alt' => [
            'missing'      => ':count görselin alt metni yok.',
            'missing_hint' => 'Görselin ne gösterdiğini yazın; süs görselse alt metnini boş bırakın.',
        ],

        'cover_image' => [
            'missing'      => 'Kapak görseli seçilmemiş; paylaşımlarda önizleme çıkmayacak.',
            'missing_hint' => 'İçeriği temsil eden bir görsel seçin.',
        ],

        'link_text' => [
            'generic'      => ':count bağlantının metni nereye gittiğini söylemiyor.',
            'generic_hint' => 'Bağlantı metnini hedef sayfanın adıyla değiştirin.',
            'empty'        => ':count bağlantının metni yok.',
            'empty_hint'   => 'Bağlantıya metin ekleyin; görselse alt metnini doldurun.',
        ],

        'internal_link' => [
            'broken'      => ':count site içi bağlantı hiçbir yere çıkmıyor: :sample',
            'broken_hint' => 'Adresi düzeltin ya da bağlantıyı kaldırın.',
        ],

        'slug' => [
            'empty'          => 'Adres boş; kaydedilirken başlıktan üretilecek.',
            'empty_hint'     => 'Üretilecek adresi görmek için alanı doldurun.',
            'too_long'       => 'Adres :length karakter; :max karakterin altı daha kolay paylaşılıyor.',
            'too_long_hint'  => 'Adresi kısaltın; başlığın tamamını taşıması gerekmiyor.',
            'invalid'        => 'Adres yalnız küçük harf, rakam ve tire içermeli.',
            'invalid_hint'   => 'Boşluk, büyük harf ve Türkçe karakterler adreste bozuluyor.',
            'mismatch'       => 'Adres başlıkla hiçbir kelimeyi paylaşmıyor.',
            'mismatch_hint'  => 'Başlık sonradan değiştiyse adresi de gözden geçirin — ama yayındaysa değiştirmeden önce yönlendirme kurun.',
        ],

        'content' => [
            'empty'      => 'Gövde boş.',
            'empty_hint' => 'Sayfanın içeriğini yazın.',
            'thin'       => 'Gövde :words kelime; :threshold kelimenin altı ince içerik sayılıyor.',
            'thin_hint'  => 'Konuyu biraz daha açın ya da bu sayfanın kısa olması gerekiyorsa yok sayın.',
        ],

    ],

];
