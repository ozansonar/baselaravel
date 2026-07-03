# SEO Geliştirme Planı — Orhan Babanın Çiftliği

## Hedef
"köy ürünleri", "çorum köy ürünleri", "istanbul köy ürünleri", "tereyağ",
"günlük köy sütü" gibi aramalarda Google'da üst sıralarda çıkmak.

---

## MODÜL 1: Şehir Bazlı Dinamik Hizmet Sayfaları

### Amaç
Her büyük şehir için ayrı landing page → "istanbul köy ürünleri" aramasında
direkt sıralama.

### Veritabanı

**Tablo: `city_landing_pages`**
```
id, city_name, city_slug, region, title, meta_title, meta_description,
hero_heading, hero_description, content (HTML), shipping_note,
delivery_time, is_active, sort_order, created_at, updated_at, deleted_at
```

### Model: `CityLandingPage`
- SoftDeletes, fillable, slug scope
- `products()` → mevcut aktif ürünler (hepsi gösterilir)

### Admin Yönetimi
- `/admin/city-pages` → CRUD listesi
- `/admin/city-pages/create` → form (şehir adı, slug, içerik, SEO alanları)
- `/admin/city-pages/{id}/edit` → düzenleme
- "AI ile İçerik Üret" butonu → şehre özel içerik Gemini ile üretilir
- Toplu oluşturma: şehir listesinden seç → AI hepsine içerik üretsin

### Frontend
- URL: `/{city_slug}-koy-urunleri` (örn: `/istanbul-koy-urunleri`)
- H1: "İstanbul'a Doğal Köy Ürünleri — Çorum'dan Kapınıza"
- İçerik: şehre özel teslimat bilgisi, kargo süresi, ürün listesi
- Ürün kartları (mevcut ürünler listelenir + yıldız + fiyat)
- CTA: "Hemen Sipariş Ver" + WhatsApp
- Schema.org: LocalBusiness + AreaServed (ilgili şehir)
- Breadcrumb: Ana Sayfa > {Şehir} Köy Ürünleri

### Şehir Öncelik Sırası (büyükten küçüğe)
```
Tier 1 (ilk yapılacak):
  istanbul, ankara, izmir, bursa, antalya

Tier 2:
  konya, adana, gaziantep, kayseri, samsun

Tier 3:
  trabzon, eskisehir, denizli, sakarya, mersin

Tier 4 (Çorum çevresi — zaten güçlü):
  corum, amasya, cankiri, tokat, yozgat
```

### SEO Detayları (her şehir sayfası)
- Title: "{Şehir} Köy Ürünleri — Doğal Süt, Peynir, Tereyağı | Orhan Babanın Çiftliği"
- Meta desc: "Çorum köyünden {şehir}'a soğuk zincir kargo ile doğal köy ürünleri. Taze süt, köy peyniri, tereyağı. {şehir}'da kapınıza teslimat."
- H1: "{Şehir}'a Doğal Köy Ürünleri"
- H2'ler: "{şehir}'a Kargo Süresi", "Neden Çorum Köy Ürünleri?", "Ürünlerimiz"
- İç linkler: ürün sayfalarına, blog yazılarına
- Canonical: `/{city_slug}-koy-urunleri`

### Dosyalar
```
database/migrations/xxxx_create_city_landing_pages_table.php
app/Models/CityLandingPage.php
app/Services/CityLandingPageService.php
app/Services/AiCityContentService.php
app/Http/Controllers/CityLandingPageController.php (frontend)
app/Http/Controllers/Admin/CityLandingPageController.php (admin CRUD)
resources/views/city-landing.blade.php (frontend)
resources/views/admin/city-pages/index.blade.php
resources/views/admin/city-pages/create.blade.php
resources/views/admin/city-pages/edit.blade.php
routes/web.php → Route::get('/{slug}-koy-urunleri', ...)
routes/admin.php → CRUD routes
```

---

## MODÜL 2: Blog Prompt Şehir Rotasyonu

### Amaç
Günde 4 blog yazısında farklı şehir + ürün kombinasyonu → geniş anahtar
kelime kapsamı.

### Değişiklik: `AiPromptSetting` veya `BlogGenerationService`

**Yeni setting: `ai_blog_target_cities`**
```
İstanbul, Ankara, İzmir, Bursa, Antalya, Konya, Adana, Gaziantep,
Kayseri, Samsun, Çorum
```

### Rotasyon Mantığı
```
09:07 → Ürün: rastgele + Şehir: İstanbul (Tier 1 öncelikli)
12:23 → Ürün: rastgele + Şehir: Ankara
15:41 → Ürün: rastgele + Şehir: yok (genel SEO yazısı)
18:16 → Ürün: rastgele + Şehir: İzmir
```

Her cron çalışmasında bir sonraki şehir seçilir (round-robin).
Haftada 1 kez genel yazı (şehirsiz) → "köy tereyağı nasıl yapılır" gibi.

### Prompt Eklentisi
Mevcut system instruction'a dinamik ekleme:
```
HEDEF ŞEHİR: {city}
Bu yazıda "{city}" şehrini hedefle:
- "{city} köy ürünleri", "{city} doğal süt" gibi anahtar kelimeler kullan
- {city}'a teslimat/kargo bilgisi ekle
- "{city}'dan sipariş verin" CTA'sı olsun
- Title'da {city} geçsin
```

### Dosyalar
```
app/Services/BlogGenerationService.php (değişiklik)
app/Models/AiPromptSetting.php (yeni field: target_cities)
database/migrations/xxxx_add_target_cities_to_ai_prompt_settings.php
```

---

## MODÜL 3: Ürün Meta Title/Description Zenginleştirme

### Amaç
Mevcut ürün sayfalarının CTR'ını artırmak.

### Değişiklik: `products/show.blade.php`

**Mevcut title pattern:**
```
Köy Tereyağı | Orhan Babanın Çiftliği
```

**Yeni title pattern:**
```
Doğal Köy Tereyağı — Çorum'dan Kapınıza | Orhan Babanın Çiftliği
```

### Kurallar
- Ürünün meta_title'ı varsa → onu kullan (admin girişi öncelikli)
- Yoksa → otomatik oluştur: "Doğal {ürün_adı} — Çorum Köy Ürünleri | {site_adı}"
- meta_description yoksa → "Çorum Büyük Palabıyık Köyü'nden {ürün_adı}. {kısa_açıklama}. Doğal, katkısız. Hızlı kargo ile kapınıza."
- Maks 60 karakter title, 160 karakter description

### Dosyalar
```
resources/views/products/show.blade.php (@section title/meta_description)
```

---

## MODÜL 4: Topic Cluster Blog Planı

### Amaç
Her ürün etrafında blog kümesi oluştur → Google'a konu otoritesi sinyali.

### Ürün Başına Konu Listesi (AI üretecek)

**Köy Tereyağı kümesi:**
```
1. "Köy Tereyağı Nasıl Yapılır? Geleneksel Üretim Süreci"
2. "Köy Tereyağı ile Market Tereyağı Arasındaki 7 Fark"
3. "Köy Tereyağı Nasıl Saklanır? Buzluk ve Buzdolabı Rehberi"
4. "Çorum Köy Tereyağı Nereden Alınır? Online Sipariş"
5. "Kahvaltıda Köy Tereyağı: 5 Lezzetli Tarif"
6. "Tereyağının Sağlık Faydaları — Bilimsel Gerçekler"
7. "En İyi Tereyağ Nasıl Anlaşılır? Alırken Dikkat Edilecekler"
```

**Taze Süt kümesi:**
```
1. "Günlük Çiğ Süt ile Pastörize Süt Farkı"
2. "Çiğ Süt Nasıl Kaynatılır? Doğru Kaynatma Tekniği"
3. "Çorum'dan Taze Süt Siparişi — Soğuk Zincir Kargo"
4. "Çocuklar İçin Doğal Süt: Neden Önemli?"
5. "Ev Yapımı Yoğurt Tarifi — Köy Sütü ile"
```

### Uygulama
- Admin panelde "Topic Cluster Üret" butonu → ürün seç → AI 7 konu önersin
- Her konuyu tek tıkla blog yazısına çevir (mevcut AI blog sistemi)
- Her yazıda ana ürün sayfasına iç link (zaten otomatik ekleniyor)
- Cluster hub: ürün sayfası / kategori sayfası

### Dosyalar
```
Mevcut blog üretim sistemi kullanılır — ek dosya gerekmez
Admin'den konu önerisi için AiTopicService eklenebilir (opsiyonel)
```

### Faz 2: Cron Otomasyonuna Bağlama

**Problem:** Şu an cron 4×/gün çalışırken Topic Cluster'ı kullanmıyor —
sadece rastgele ürün + şehir rotation kullanıyor. Havuz: 11 şehir × 6
ürün = 66 kombinasyon, 2 haftada tükeniyor, 3. haftadan itibaren tekrar.

**Çözüm:** Cron her çalıştığında önce ProductTopic havuzundan henüz
blog'a çevrilmemiş bir topic seçsin. Havuz: 11 × 6 ürün × 7 topic = 462
kombinasyon → 4 ay tekrarsız.

#### Tasarım

```
Cron tetiklenir (09:07 / 12:23 / 15:41 / 18:16)
   ↓
BlogGenerationService::generate (cron path — 'topic' option yok)
   ↓
TopicRotationService::pickNextTopic()
   ├─ 1) Aktif ürünleri çek
   ├─ 2) Her birinin "henüz blog'a çevrilmemiş" (blog_post_id IS NULL)
   │     topic'lerinden rastgele birini seç
   ├─ 3) Rastgele bir ürünün rastgele topic'ini döndür
   └─ Hiç boş topic yoksa → null (havuz tükendi)
   ↓
Topic varsa:
   topic.title  → BlogGenerationService.options.topic
   topic.intent → options.topic_intent (Modül 4 prompt block aktifleşir)
Topic yoksa (havuz tükendi):
   Eski davranış: rastgele ürün adı (Modül 2 ile aynı)
   ↓
AI yazıyı üretir → BlogPost oluşur
   ↓
Topic varsa: topic.blog_post_id = post.id, converted_at = now()
   (bir daha cron tarafından seçilmez)
```

#### Yeni Dosyalar

- `app/Services/TopicRotationService.php` — pickNextTopic + atomic "claim" mantığı
- `BlogGenerationService` güncellemesi — generate'in cron path'ında topic seçimi
- AiLog'a `product_topic_id` opsiyonel — hangi topic için yazıldı (izlenebilirlik)

#### Yapılacak İş (kod)

| Adım | Süre |
|---|---|
| TopicRotationService + test | 15 dk |
| BlogGenerationService entegrasyonu | 10 dk |
| AiLog migration (product_topic_id) | 5 dk |
| Manuel doğrulama (cron simülasyonu) | 10 dk |
| **Toplam** | **~40 dk** |

#### Kullanıcının Yapması Gereken (kod deploy sonrası)

Faz 2'nin işe yaraması için **havuz dolu olmalı**. Admin elle:

1. **`/admin/product-topics`** sayfasına git
2. Her ürün için "**AI ile 7 Konu Üret**" butonuna tıkla
   - Her tıklama ~30 sn AI çalıştırır
   - 6 ürün × 30 sn = ~3 dakika toplam
3. Önerilen başlıkları gözden geçir:
   - Beğenmediğini × ile sil
   - Komple yeniden öner için "Yeni 7 Konu Daha Üret"
4. Onayladıkça cron havuza topic ekleniyor (DB'ye yazılı)

Sonuç: 6 ürün × 7 topic = **42 unique blog başlığı** — cron 11 şehirle
kombinleyerek **462 yazı** tekrarsız üretir (4 aydan fazla).

#### Havuz Tükenince Davranış

42 topic'in hepsi blog'a çevrilince yeni cron çağrılarında
TopicRotationService null döner → BlogGenerationService eski davranışa
düşer (rastgele ürün adı, topic_intent yok). Admin yeniden 7 konu
üreterek havuzu doldurabilir veya o ürün için artık blog üretilmez.

İlerideki opsiyonel iyileştirme: cron her N hafta'da bir AiTopicService'i
otomatik çağırıp havuzu yeniler.

---
---

## MODÜL 5: Google Business Profile (Kod Dışı)

### Yapılacaklar (admin panelden değil, Google'dan)
1. Google My Business'a giriş yap
2. İşletme bilgilerini kontrol et:
   - Ad: Orhan Babanın Çiftliği
   - Adres: Büyük Palabıyık Köyü, Çorum/Merkez
   - Telefon: +905059424124
   - Web: https://orhanbabaninciftligi.com
   - Kategori: Çiftlik + Gıda mağazası
3. Hizmet alanlarına ekle:
   - İstanbul, Ankara, İzmir, Bursa, Antalya, Konya, Adana
   - "Türkiye geneli kargo" notu
4. Haftada 1 Google Post paylaş:
   - Ürün fotoğrafı + "Taze köy tereyağı — Çorum'dan kapınıza"
   - Blog yazısı linki
5. Müşterilerden Google yorum iste:
   - Sipariş sonrası otomatik WhatsApp/e-posta (Modül 6'da)
6. SSS bölümünü doldur (Google Business'ta)
7. Fotoğraf galerisi ekle (ürün + çiftlik + üretim)

---

## MODÜL 6: Sipariş Sonrası Otomatik Yorum İsteği (İleride)

### Amaç
Gerçek müşteri yorumları → en güçlü SEO sinyali.

### Akış
```
Sipariş teslim edildi (3 gün sonra)
    → Otomatik e-posta/WhatsApp
    → "Ürünümüzü denediniz mi? Deneyiminizi paylaşın!"
    → Link: ürün sayfası yorum formu
    → Link: Google Maps yorum sayfası
```

### Dosyalar (ileride)
```
app/Console/Commands/SendReviewRequestCommand.php
app/Mail/ReviewRequestMail.php
resources/views/emails/review-request.blade.php
routes/console.php → Schedule::command('reviews:request')->dailyAt('10:00')
```

---

## MODÜL 7: Sitemap Güncellemesi

### Şehir sayfaları sitemap'e eklenmeli
```php
// SitemapService.php'ye eklenecek
$cityPages = CityLandingPage::where('is_active', true)->get();
foreach ($cityPages as $cityPage) {
    $urls[] = [
        'loc' => route('city.landing', $cityPage->city_slug . '-koy-urunleri'),
        'lastmod' => $cityPage->updated_at->toW3cString(),
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ];
}
```

---

## MODÜL 8: Internal Linking Güçlendirme

### Mevcut
- Blog → ürün sayfalarına otomatik iç link (zaten yapıldı)

### Eklenmesi gereken
- Blog → şehir sayfalarına iç link ("İstanbul'a kargo" geçince link)
- Ürün sayfası → ilgili blog yazılarına link
- Şehir sayfası → ürün sayfalarına link
- Footer'a şehir sayfaları linkleri (SEO juice)

### Footer Şehir Linkleri
```blade
<div class="col-lg-3 col-md-6">
    <h5 class="footer-title">Hizmet Bölgelerimiz</h5>
    <ul class="footer-links">
        @foreach($cityPages as $city)
        <li><a href="/{{ $city->slug }}-koy-urunleri">{{ $city->name }}</a></li>
        @endforeach
    </ul>
</div>
```

---

## UYGULAMA TAKVİMİ

### Hafta 1 — TAMAMLANDI ✓
- [x] Modül 1: city_landing_pages migration + model + admin CRUD
- [x] Modül 1: AI şehir içerik üretici (AiCityContentService — Gemini)
- [x] Modül 1: Frontend şehir sayfası (LocalBusiness + AreaServed + Breadcrumb + ItemList schema)
- [x] Modül 1: Tier 1 şehirler oluştur (İstanbul, Ankara, İzmir, Bursa, Antalya)
- [x] Modül 7: Sitemap'e şehir sayfaları eklendi (priority 0.8, weekly)

### Hafta 2
- [x] Modül 2: Blog prompt şehir rotasyonu (round-robin + haftada 1 genel)
- [x] Modül 3: Ürün meta title/description zenginleştirme (ProductSeoService)
- [x] Modül 7: Sitemap'e şehir sayfaları ekleme
- [x] Modül 8: Footer şehir linkleri + Blog→şehir + Şehir→ürün + Ürün→Topic blogs

### Hafta 3
- [ ] Modül 1: Tier 2 + Tier 3 şehirler
- [x] Modül 4: Topic cluster planı + ilk küme üretimi
- [x] Modül 8: Blog → şehir sayfası iç link (Hafta 2'de tamamlandı)

### Hafta 4
- [ ] Modül 5: Google Business Profile optimizasyonu
- [ ] Modül 1: Tier 4 şehirler (Çorum çevresi)
- [ ] Modül 4: Kalan kümeler

### Hafta 5+
- [ ] Modül 6: Sipariş sonrası yorum isteği
- [ ] Performans izleme (Search Console, Analytics)
- [ ] A/B test title/description

---

## HEDEF METRİKLER (3 ay sonra)

| Metrik | Şu an | Hedef |
|---|---|---|
| "çorum köy ürünleri" sıralaması | ? | Top 3 |
| "istanbul köy ürünleri" sıralaması | Yok | Top 10 |
| "köy tereyağı" sıralaması | ? | Top 10 |
| Organik trafik (aylık) | ? | 2x artış |
| İndekslenen sayfa sayısı | ~50 | ~200+ |
| Google rich result sayısı | 2-3 | 20+ |
| Ortalama CTR | ? | %3+ |

---

## TEKNİK NOTLAR

- Tüm şehir sayfaları `is_active` flag ile kontrol edilir
- AI içerik üretimi text key (gemini_api_key) ile çalışır — ücretsiz
- Her şehir sayfasında unique content olmalı (duplicate content cezası riski)
- Şehir sayfaları birbirine çok benzemezse Google ödüllendirir
- `hreflang` tek dil (tr) — değişiklik gerekmez
- Sitemap cache'i şehir CRUD sonrası otomatik temizlenmeli
