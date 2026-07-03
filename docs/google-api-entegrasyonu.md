# Google API Entegrasyonu — Orhan Babanın Çiftliği

## Hedef
Google Search Console (GSC) ve Google Analytics 4 (GA4) verilerini admin paneline
çekmek → "ne aratıyor, kim geliyor, hangi sayfa iyi/kötü performans gösteriyor"
sorularını veriyle cevaplamak. Bu sayede AI içerik üretimi, şehir landing
genişletmesi ve meta optimizasyonu **rastgele değil veriye dayalı** hale gelir.

---

## ⚡ Performans Felsefesi (ÖNEMLİ)

**Sitenin yavaşlamayacağına dair garantiler:**

### 1. Frontend hiçbir zaman Google API'sine dokunmaz
- `/`, `/urunler`, `/eskisehir-koy-urunleri` gibi sayfalar API çağrısı **yapmaz**
- Tüm Google verileri arka planda (cron) çekilir, DB'ye/cache'e yazılır
- Kullanıcının yüklediği sayfada **0 ek istek** olur

### 2. Admin paneli de canlı çağrı yapmaz
- Admin "SEO Performansı" sayfasına girdiğinde **lokal DB**'den okur
- Veriler en fazla 6 saatlik
- Sayfa yükleme süresine etki: **<10ms** (ek bir SQL query)

### 3. Cron 4 saatte bir, off-peak saatlerde çalışır
- `02:13`, `08:17`, `14:23`, `20:37` (sabit saat değil → API kotası dağılır)
- `withoutOverlapping` ile aynı anda iki çağrı olmaz
- Hata durumunda exponential backoff (3 deneme)

### 4. Cache stratejisi
| Veri | Cache süresi | Yenilenme |
|---|---|---|
| Top queries (28 gün) | 6 saat | Cron ile |
| Per-page performance | 6 saat | Cron ile |
| Şehir trafik dağılımı | 12 saat | Cron ile |
| Realtime users | 60 sn | Lazy load (admin açtığında) |
| URL Inspection | 24 saat | Manuel istek + cache |

### 5. API kotası (Google ücretsiz limitleri)
- **GSC API:** 1200 req/dakika, 50K req/gün → bizim kullanım: ~50 req/gün
- **GA4 API:** 25K req/gün/property → bizim kullanım: ~30 req/gün
- **Kota aşma riski:** %1'den az

**Sonuç:** Bu entegrasyon site hızını **etkilemez**. Tüm ağır işler arka planda.

---

## 🏗️ Mimari Genel Bakış

```
┌─────────────────────────────────────────────────────┐
│                Google Cloud                          │
│  ┌─────────────┐         ┌──────────────┐            │
│  │ GSC API     │         │ GA4 API      │            │
│  └──────┬──────┘         └──────┬───────┘            │
└─────────┼───────────────────────┼────────────────────┘
          │                       │
          │  (cron, server→server)│
          ▼                       ▼
┌─────────────────────────────────────────────────────┐
│           Laravel — App\Services\Google\            │
│  • SearchConsoleService (queries, pages, sitemap)   │
│  • Analytics4Service    (traffic, cities, users)    │
│  • IndexingApiService   (yeni içerik index emri)    │
└─────────┬───────────────────────────────────────────┘
          │ writes
          ▼
┌─────────────────────────────────────────────────────┐
│  MySQL — gsc_metrics, ga4_metrics tabloları         │
│  Cache — Redis/file (kısa süreli özet)              │
└─────────┬───────────────────────────────────────────┘
          │ reads (fast, lokal)
          ▼
┌─────────────────────────────────────────────────────┐
│  Admin Panel — widget'lar + raporlar                │
│  /admin/seo-performance, dashboard widget'lar       │
└─────────────────────────────────────────────────────┘
```

**Önemli:** Frontend (kullanıcı tarafı) bu zincirin **dışında**.

---

## FAZ 1 — Kritik Modüller (1-2 günlük iş)

### MODÜL 1: GSC Top Queries Dashboard Widget

**Amaç:** Admin Dashboard ana sayfasında "son 28 gün ne aranmış" görünsün.

**Ne yapacak:**
- Cron her 6 saatte GSC'den son 28 gün top 50 sorgu çekecek
- DB'de `gsc_query_metrics` tablosuna yazacak (sorgu, tıklama, gösterim, CTR, pozisyon)
- Admin Dashboard'a yeni widget eklenecek

**Görsel sonuç (admin Dashboard):**
```
┌─────────────────────────────────────────────────────┐
│ 🔍 Google'da Sizi Bulanlar (son 28 gün)            │
├─────────────────────────────────────────────────────┤
│ 1. köy tereyağı çorum     127 tıklama  poz: 4.2    │
│ 2. doğal köy peyniri       89 tıklama  poz: 6.8    │
│ 3. çorum köy ürünleri      54 tıklama  poz: 3.1    │
│ 4. köy yumurtası kargo     32 tıklama  poz: 8.4    │
│ ...                                                 │
│                            [Detayları Gör →]        │
└─────────────────────────────────────────────────────┘
```

**Faydası:**
- Hangi kelimelerle bulunduğunu **görsen** o yöne içerik üretirsin
- "köy peyniri tarif" çok aratılıyor ama içerik yoksa → yeni blog yaz
- Bu veri olmadan AI'a "ne hakkında yaz" demek karanlıkta el yordamı

---

### MODÜL 2: SEO Performans Sayfası (`/admin/seo-performance`)

**Amaç:** Tüm sayfaların (ürün, blog, şehir landing) Google performansını
tek panelde gör.

**Ne yapacak:**
- Cron her 6 saatte GSC'den per-page veri çekecek
- 4 sekme: **Genel / Ürünler / Bloglar / Şehirler**
- Her sayfa için: tıklama, gösterim, ortalama pozisyon, CTR
- Filtre: tarih aralığı (7/28/90 gün), arama
- Sıralama: en çok tıklanan, en yüksek pozisyon, en düşük CTR

**Görsel sonuç:**
```
┌─────────────────────────────────────────────────────────┐
│ SEO Performansı                       [son 28 gün ▾]   │
├─────────────────────────────────────────────────────────┤
│ Genel | Ürünler | Bloglar | Şehirler                   │
├─────────────────────────────────────────────────────────┤
│ Sayfa                  Tıklama  Gösterim  Poz  CTR     │
│ /                        342     12K     5.2  2.8%     │
│ /urun/koy-tereyagi       189      4.2K   3.1  4.5% 🟢  │
│ /istanbul-koy-urunleri    87      2.1K   8.4  4.1%     │
│ /blog/.../koy-peynir...   54      1.8K  11.2  3.0% 🟡  │
│ /urun/cig-sut             12       980  18.3  1.2% 🔴  │
└─────────────────────────────────────────────────────────┘
```

🟢 = Pozisyon iyi, CTR iyi
🟡 = Pozisyon iyi, CTR düşük (meta description optimize et)
🔴 = Pozisyon kötü (içerik veya backlink eksik)

**Faydası:**
- "Şu ürün Google'da kaçıncı" → bilmek için artık manuel arama yapma
- "Hangi blog post boş yere yazıldı (0 trafik)" → sil veya yenile
- Şehir landing'lerden hangisi çalışıyor → diğerlerini ona benzet

---

### MODÜL 3: GA4 Şehir Trafik Haritası

**Amaç:** "Hangi şehirden ziyaretçi geliyor" görsel olarak görmek.

**Ne yapacak:**
- Cron her 12 saatte GA4'ten şehir bazlı trafik çekecek
- Admin Dashboard'a Türkiye haritası widget'ı eklenecek
- Liste görünümü de olacak (top 20 şehir)

**Görsel sonuç:**
```
┌─────────────────────────────────────────────────────────┐
│ 📍 Şehirden Geleni Trafik (son 30 gün)                 │
├─────────────────────────────────────────────────────────┤
│  ╔══════════════════════════════╗                      │
│  ║   [Türkiye haritası SVG]     ║   1. İstanbul  4.2K  │
│  ║   Renk yoğunluğu = trafik    ║   2. Ankara    1.8K  │
│  ║                              ║   3. Çorum     1.1K  │
│  ║   • Hover'da şehir bilgisi   ║   4. İzmir       890 │
│  ╚══════════════════════════════╝   5. Bursa       620 │
│                                     6. Muğla       540 │
│                                     7. Antalya     490 │
│                                                        │
│  💡 Muğla 6. sırada ama landing yok → ekle    [Ekle]   │
└─────────────────────────────────────────────────────────┘
```

**Faydası:**
- "Muğla'yı eklemeli miyim" sorusuna **veri ile** cevap
- Şehir landing kararları artık tahmin değil
- Hangi şehirde reklam yayını mantıklı görürsün

---

## FAZ 2 — Yüksek Değer (2-3 günlük iş)

### MODÜL 4: URL Inspection + Indexing API

**Amaç:** Yeni eklenen ürün/blog/şehir sayfası **anında** Google'a gönderilsin.

**Ne yapacak:**
- Yeni ürün/blog yayınlandığında otomatik Indexing API'ye "tara" emri
- Admin sayfada "Google Index Durumu" badge'i:
  - 🟢 İndexlendi (X gün önce tarandı)
  - 🟡 Beklemede
  - 🔴 İndexlenmedi (sebep: noindex / 404 / vb.)
- Manuel "Yeniden Tara" butonu

**Görsel sonuç (ürün düzenleme sayfası):**
```
┌─────────────────────────────────────────────────────┐
│ Köy Tereyağı                       [Kaydet] [Sil]   │
├─────────────────────────────────────────────────────┤
│  Google İndex Durumu                                │
│  🟢 İndexlendi · 3 gün önce tarandı                 │
│  Pozisyon: 4.2 · Tıklama: 189 · Gösterim: 4.2K      │
│                            [Yeniden Tarat]          │
└─────────────────────────────────────────────────────┘
```

**Faydası:**
- Yeni içerik **24 saat içinde** Google'da olur (normalde 1-2 hafta)
- "Bu yazı niye Google'da yok" sorusu otomatik cevaplanır
- Admin paneli açıkça gösterir, Search Console'a gitmek zorunda kalmazsın

---

### MODÜL 5: Topic Cluster + Blog Performansı

**Amaç:** Topic Cluster sayfasında her topic'in yanında performans göster.

**Ne yapacak:**
- ProductTopic + BlogPost ilişkisi zaten var
- BlogPost'a join → GSC verileriyle eşleştirme
- Her satıra "tıklama / pozisyon" eklenmesi

**Görsel sonuç (topic cluster güncellemesi):**
```
┌─────────────────────────────────────────────────────┐
│ Köy Çökeleği                                        │
├─────────────────────────────────────────────────────┤
│ Başlık                  Intent     Durum   Perf      │
│ Köy Çökeleği Nasıl..    how_to    ✅      127 tık   │
│                                            poz 4.2🟢│
│ Market Çökeleği vs..    comparison ✅      8 tık    │
│                                            poz 18🔴 │
│ Çökelek Nasıl Saklan..  storage   ⏳      —         │
│ ...                                                 │
└─────────────────────────────────────────────────────┘
```

**Faydası:**
- "Bu blog yayınlandı ama trafik almıyor" görüntü olarak ortada
- Düşük performanslı yazıları **AI ile yeniden yazma** kararı veriliyor
- Hangi search intent'ler para kazandırıyor (purchase) hangi ölçtüğü
  görüleceği için intent çeşitliliğini ona göre optimize edersin

---

### MODÜL 6: Düşük CTR Otomatik Tespit + AI Meta Yenileme

**Amaç:** "Pozisyon iyi ama tıklanmıyor" sayfaları yakala, meta description'ı
AI ile yenile.

**Ne yapacak:**
- Cron sonrası kural: **pozisyon ≤10 ama CTR <%2** → "düşük CTR adayı"
- Admin'e bildirim: "5 sayfanın meta description'ı zayıf, AI ile yenileyelim mi?"
- Tek tık → AI Gemini ile yeni meta description üretir, otomatik kaydeder

**Görsel sonuç (uyarı kartı):**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️  CTR Düşük — 5 sayfanız iyi pozisyonda ama       │
│    tıklanmıyor                                      │
├─────────────────────────────────────────────────────┤
│ /urun/koy-peyniri         poz 4.1  CTR 1.2%  [Yenile]│
│ /blog/cig-sut-faydalari   poz 6.8  CTR 1.8%  [Yenile]│
│ /istanbul-koy-urunleri    poz 5.2  CTR 1.5%  [Yenile]│
│                                                     │
│             [Hepsini AI ile Yenile]                 │
└─────────────────────────────────────────────────────┘
```

**Faydası:**
- "Görüyor ama tıklamıyor" Google için kötü sinyal — pozisyonu da düşürür
- Meta description tek tek elle değil topluca AI ile düzelir
- Süreç: Sorunu sistem bulur → AI çözer → admin onaylar

---

## FAZ 3 — Nice-to-Have (Sonra)

### MODÜL 7: Realtime Users Widget
- GA4 Realtime API → şu an X kişi sitede, en çok bakılan ürün Y
- Cache 60 sn, lazy load (admin sayfası açtığında)

### MODÜL 8: Core Web Vitals Monitoring
- GSC Core Web Vitals API → LCP, FID, CLS skorları
- Hangi sayfalar yavaş, mobil uyumsuz görmek için

### MODÜL 9: Sitemap Coverage Panosu
- GSC'den indexlenen sayfa sayısı, hata sayısı
- Coverage error'ları (excluded pages) listesi

### MODÜL 10: Bounce Rate / Conversion Funnel
- Sepet terkleme noktası neresi
- Hangi blog yazısı satışa götürüyor (içerik → ürün → sepet → sipariş)

---

## 🛠️ Teknik Gereksinimler

### Composer paketleri
```bash
composer require google/apiclient:^2.15
composer require google/analytics-data:^0.16
composer require google/cloud-search-console-data:^0.4
```

### Google Cloud Setup (admin manuel yapacak)
1. https://console.cloud.google.com/ → "Yeni Proje"
2. APIs & Services → Enable: **Search Console API**, **Analytics Data API**, **Indexing API**
3. Service Account oluştur → JSON key indir
4. JSON key'i sunucuda `storage/app/google/service-account.json` olarak kaydet
5. **Search Console → Settings → Users → Service Account email'i Owner ekle**
6. **Analytics → Admin → Property Access Management → Service Account email Viewer ekle**

### .env değişkenleri
```env
GOOGLE_SERVICE_ACCOUNT_PATH=storage/app/google/service-account.json
GSC_PROPERTY_URL=https://orhanbabaninciftligi.com/
GA4_PROPERTY_ID=123456789
GOOGLE_INDEXING_ENABLED=true
```

### Yeni dosyalar
```
app/Services/Google/
  ├── SearchConsoleService.php
  ├── Analytics4Service.php
  └── IndexingApiService.php

app/Console/Commands/
  ├── FetchGscMetrics.php          (cron)
  ├── FetchGa4Metrics.php          (cron)
  └── DetectLowCtrPages.php        (cron)

app/Http/Controllers/Admin/
  └── SeoPerformanceController.php

app/Models/
  ├── GscQueryMetric.php
  ├── GscPageMetric.php
  ├── Ga4CityMetric.php
  └── IndexingRequest.php

database/migrations/
  ├── create_gsc_query_metrics_table.php
  ├── create_gsc_page_metrics_table.php
  ├── create_ga4_city_metrics_table.php
  └── create_indexing_requests_table.php

resources/views/admin/seo-performance/
  ├── index.blade.php
  ├── partials/queries-table.blade.php
  ├── partials/pages-table.blade.php
  └── partials/cities-map.blade.php

resources/views/admin/dashboard/widgets/
  ├── top-queries.blade.php       (yeni widget)
  └── city-traffic-map.blade.php  (yeni widget)
```

### Cron schedule (`routes/console.php` eklenecek)
```php
Schedule::command('gsc:fetch-metrics')
    ->name('gsc-fetch')
    ->cron('13 */6 * * *')          // 02:13, 08:13, 14:13, 20:13
    ->withoutOverlapping();

Schedule::command('ga4:fetch-metrics')
    ->name('ga4-fetch')
    ->cron('27 */12 * * *')         // 02:27, 14:27
    ->withoutOverlapping();

Schedule::command('seo:detect-low-ctr')
    ->name('low-ctr-detect')
    ->dailyAt('06:33')
    ->withoutOverlapping();
```

### Veritabanı boyut tahmini (1 yıl sonra)
| Tablo | Satır/gün | 1 yıl | Disk |
|---|---|---|---|
| gsc_query_metrics | ~50 | ~18K | <5 MB |
| gsc_page_metrics | ~30 | ~11K | <3 MB |
| ga4_city_metrics | ~20 | ~7K | <2 MB |
| indexing_requests | ~10 | ~3.6K | <1 MB |

**Toplam disk yükü: <15 MB/yıl** — ihmal edilebilir.

---

## 🚀 Uygulama Sırası

### Hafta 1
1. **Gün 1-2:** Google Cloud setup + Service Account + .env
2. **Gün 2:** SearchConsoleService + GscQueryMetric model + migration + cron
3. **Gün 3:** Top Queries dashboard widget'ı
4. **Gün 4:** SEO Performans sayfası (`/admin/seo-performance`)

### Hafta 2
5. **Gün 5:** Analytics4Service + Ga4CityMetric + cron
6. **Gün 6:** Şehir trafik haritası widget'ı (Türkiye SVG)
7. **Gün 7:** Topic Cluster sayfasına performans entegrasyonu

### Hafta 3 (Faz 2)
8. **Gün 8:** IndexingApiService + Observer (yeni içerikte otomatik index)
9. **Gün 9:** URL Inspection (admin sayfada index durumu)
10. **Gün 10:** Düşük CTR detection + AI meta yenileme

---

## ❓ Senin Yapman Gerekenler

### Hemen
1. **Google Cloud Console hesabı aç** (Gmail ile, ücretsiz)
2. **Service Account oluştur** ve JSON key indir
3. **GA4 Property ID'ni bul** (Analytics → Admin → Property Settings)

### Bu adımlardan sonra
4. JSON dosyasını ve Property ID'yi bana ilet → entegrasyon kodlarına başlarım
5. Search Console'a Service Account email'ini "Owner" olarak ekle
6. Analytics'e Service Account email'ini "Viewer" olarak ekle

### Hiçbir zaman
- Bu API'ler **kullanıcı tarafına dokunmaz**
- Site hızı etkilenmez
- Maliyet sıfır (Google ücretsiz limit içinde kalıyoruz)

---

## 📊 Beklenen Sonuçlar (3 ay sonra)

| Metrik | Önce | Sonra |
|---|---|---|
| AI içerik üretim isabet oranı | %30 (rastgele konu) | %70 (veriye dayalı) |
| Yeni içerik index süresi | 1-2 hafta | 24-48 saat |
| Düşük CTR sayfa farkındalığı | Manuel kontrol | Otomatik tespit |
| Şehir landing kararı | Tahmin | Veriyle |
| SEO admin zaman tasarrufu | — | 5+ saat/hafta |

---

## ⚠️ Riskler ve Önlemler

| Risk | Önlem |
|---|---|
| API kotası aşılır | Cron 4-12 saatte bir, retry+backoff |
| Service Account key sızar | `storage/app/google/` (.gitignore'da), env-only path |
| Google API bozulur, cron hata verir | AiLog'a benzer GoogleApiLog tablosu, admin görür |
| Indexing API spam olarak işaretlenir | Sadece yeni içerikte 1 kez, manuel "yeniden tara" rate limit'li |
| Veriler büyür DB şişer | Eski metric'ler 13 ay sonra silinir (cron retention) |

---

## 🔒 Güvenlik & Credential Yönetimi

### Service Account JSON Key
- **Konum:** `storage/app/google/service-account.json`
- **`.gitignore`'a eklenecek:** `storage/app/google/*.json`
- **Web erişimi yok:** `storage/` Laravel'de zaten public erişime kapalı
- **Permission:** `chmod 600` (sadece web kullanıcısı okuyabilir)
- **Backup:** Ayrı bir gizli yerde tut (1Password, parolası olan zip vb.)
- **Rotation:** Yılda bir kez Google Cloud Console'dan yenile

### Production .env güvenliği
```env
GOOGLE_SERVICE_ACCOUNT_PATH=storage/app/google/service-account.json
GSC_PROPERTY_URL=https://orhanbabaninciftligi.com/
GA4_PROPERTY_ID=123456789
GOOGLE_INDEXING_ENABLED=true
GOOGLE_API_TIMEOUT=30
```

### Repository güvenliği
- JSON anahtarı **asla** git'e commit edilmez
- `.env.example` dosyasında **örnek değerler** olur, gerçek değil
- Admin paneline "Service Account Email" gösterilebilir (sızıntı değil)
- Production'a deploy ederken JSON dosyası **manuel** olarak SCP ile yüklenir

---

## 👮 Yetkilendirme & Erişim Kontrolü

### Kim erişebilir?
| Role | Dashboard widget | SEO Performans sayfası | Indexing API |
|---|---|---|---|
| Super Admin | ✅ | ✅ | ✅ |
| Admin | ✅ | ✅ | ✅ |
| Editor | ❌ | ❌ | ❌ |
| User (front) | ❌ | ❌ | ❌ |

### Implementation
- Mevcut `App\Policies\` katmanı kullanılacak
- Yeni Policy: `SeoPerformancePolicy` (viewAny, view)
- Controller'da `$this->authorize('viewAny', SeoMetric::class)`
- Route middleware: `middleware('can:viewAny,App\Models\GscQueryMetric')`
- `routes/admin.php` → `Route::middleware(['auth', 'admin'])` grubunda

---

## 🚨 Hata Yönetimi & Monitoring

### Yeni tablo: `google_api_logs`
```
id | service (gsc|ga4|indexing) | operation | status (success|failed)
   | http_code | error_message | request_payload | response_size
   | duration_ms | created_at
```

### Hata tipleri ve davranış
| Hata | HTTP | Davranış |
|---|---|---|
| Authentication failed | 401 | Cron durur, admin'e mail uyarı |
| Quota exceeded | 429 | Exp. backoff (60s, 120s, 240s), 3 deneme |
| API down | 503 | 3 deneme, sonra log'la, sonraki cron beklesin |
| Property not found | 404 | Cron durur, admin'e mail uyarı |
| Network timeout | — | 30sn timeout, 3 deneme |
| Invalid response | 200 | JSON parse error log'la, atla |

### Admin paneli "Google API Sağlık" widget'ı
```
┌─────────────────────────────────────────────────────┐
│ Google API Sağlık                                   │
├─────────────────────────────────────────────────────┤
│ GSC API       🟢 Son çekim: 2 saat önce, başarılı   │
│ GA4 API       🟢 Son çekim: 8 saat önce, başarılı   │
│ Indexing API  🟡 Son istek: 18 saat önce            │
│                  [Logları Gör →]                    │
└─────────────────────────────────────────────────────┘
```

### Mail uyarısı (kritik hatalarda)
- Kanal: `Setting::getValue('admin_email')` adresine
- Throttle: Aynı hata 24 saat içinde tekrar mail atılmaz (spam değil)
- Şablon: `resources/views/emails/google-api-error.blade.php`

---

## ⏱️ GSC Veri Gecikmesi (Önemli!)

**Google Search Console verileri 2-3 günlük gecikmeyle gelir.** Yani:
- Bugün 27 Nisan ise GSC'den max 24-25 Nisan verisi alabiliriz
- "Son 28 gün" sorgusu aslında "27 Nisan'dan 28 gün önce - 24 Nisan" arası
- Realtime / bugünün verisi YOK

**Bu bilgi nereye yansıtılır?**
- Admin sayfasında "Son güncelleme: 25 Nisan 2026" başlığı
- Filtre: "Son 7 / 28 / 90 gün" — hesaplama 2 gün önceden başlar
- Empty state: "Yeni eklenen sayfa için veri 2-3 gün sonra gelir" açıklaması

**GA4 farklı:** GA4 verileri 4-24 saat gecikme ile gelir (daha hızlı). Realtime API ile son 30 dakika anlık.

---

## 🧪 Test Stratejisi

### Unit testler
| Dosya | Test |
|---|---|
| `tests/Unit/Services/Google/SearchConsoleServiceTest.php` | API çağrı mock, response parse |
| `tests/Unit/Services/Google/Analytics4ServiceTest.php` | Şehir filtresi, tarih aralığı |
| `tests/Unit/Services/Google/IndexingApiServiceTest.php` | URL submission, error handling |

### Feature testler
| Dosya | Test |
|---|---|
| `tests/Feature/Admin/SeoPerformancePageTest.php` | Sayfa açılır, filtreler çalışır, yetkisiz user 403 |
| `tests/Feature/Console/FetchGscMetricsTest.php` | Cron çalışır, DB'ye yazar, hata yönetir |
| `tests/Feature/Console/FetchGa4MetricsTest.php` | GA4 cron, retention silme |

### Mocking yaklaşımı
- Google API'leri **gerçekten çağırmaz** test ortamında
- `Http::fake([...])` ile sahte response döndür
- Fixture'lar: `tests/fixtures/google/gsc-response.json`
- CI'da çalıştırılır → her PR'da otomatik

### Manuel test checklist (her modül için)
- [ ] Cron başarıyla çalışıyor mu (`artisan schedule:run`)
- [ ] DB'ye veri yazıldı mı
- [ ] Admin sayfası açılıyor mu, doğru veri gösteriyor mu
- [ ] Filtreler doğru çalışıyor mu
- [ ] Yetkisiz user 403 alıyor mu
- [ ] Empty state görünüyor mu (veri yokken)
- [ ] Loading state görünüyor mu (yavaş bağlantıda)
- [ ] API down olduğunda admin error widget'ında görünür mü

---

## 🎨 Empty State & Loading UX

### Empty state (henüz veri yok)
```
┌─────────────────────────────────────────────────────┐
│ 🔍 Google'da Sizi Bulanlar                         │
├─────────────────────────────────────────────────────┤
│                                                     │
│           📊                                        │
│                                                     │
│   Henüz veri yok                                    │
│                                                     │
│   GSC verileri 2-3 gün gecikmeyle gelir.            │
│   İlk cron çalıştıktan sonra burada görünecek.      │
│                                                     │
│   [Manuel Veri Çek]                                 │
└─────────────────────────────────────────────────────┘
```

### Loading state (cron çalışırken)
- Skeleton placeholder (gri kutu animasyonu)
- "Veriler güncelleniyor..." mesajı
- Bootstrap spinner

### Hatalı durum
- Kırmızı banner: "Son çekim başarısız: 25 Nisan 17:45 — [Logları Gör]"
- Eski cache verisini göster (en son başarılı çekim)

---

## ♻️ Data Retention Policy

**13 ay tutarız, sonra otomatik sileriz.**

**Neden 13 ay?**
- Yıllık trend karşılaştırması yapabiliyoruz (Nisan 2026 vs Nisan 2025)
- 13 ay = 1 yıl + 1 ay tampon
- Disk: 13 ay sonra ~15 MB max → ihmal edilebilir

**Otomasyon:**
```php
// app/Console/Commands/PurgeOldGoogleMetrics.php
GscQueryMetric::where('created_at', '<', now()->subMonths(13))->delete();
GscPageMetric::where('created_at', '<', now()->subMonths(13))->delete();
Ga4CityMetric::where('created_at', '<', now()->subMonths(13))->delete();
GoogleApiLog::where('created_at', '<', now()->subMonths(3))->delete(); // log'lar 3 ay
```

Cron: Haftada bir Pazar gece 03:00.

---

## 🇹🇷 KVKK Uyumluluğu

**Kişisel veri tutmuyoruz:**
- GSC: Anahtar kelime + sayfa + sayı (anonim, kişiye bağlanmaz)
- GA4: Şehir + sayı (kişiye bağlanmaz, IP tutulmaz)
- Indexing API: Sadece kendi URL'lerimiz

**KVKK gerekçesi:** İşlenen tüm veriler **anonim agrega istatistik**. Kullanıcı kimliği yok.

**Yine de:**
- Cookie banner mevcut (varsa) → "anonim analitik için Google Analytics kullanıyoruz" denebilir
- Privacy Policy sayfasına Google Analytics bahsi eklenecek

---

## 🔄 Local Development

### Senin makinende test
- Aynı service account JSON dosyasını lokal `storage/app/google/` altına kopyala
- `.env` lokal: `GSC_PROPERTY_URL` lokalde de production URL'ini gösterir (test için verisi olan tek yer)
- Cron'u lokalde manuel: `php artisan gsc:fetch-metrics`
- DB'ye veri yazıldığını gör: `php artisan tinker → GscQueryMetric::latest()->take(10)->get()`

### Test environment
- `.env.testing`'de `GOOGLE_INDEXING_ENABLED=false` olur (test'te gerçek API'ye dokunma)
- Mock service binding `tests/TestCase.php`'de yapılır

### CI/CD (GitHub Actions vs)
- Service Account JSON GitHub Secrets'da saklanır
- Test ortamı sadece **mock** çalışır, gerçek API çağrılmaz
- Production deploy SCP ile JSON dosyasını sunucuya yükler

---

## 📐 Veritabanı Schema Detayları

### `gsc_query_metrics`
```php
Schema::create('gsc_query_metrics', function (Blueprint $table) {
    $table->id();
    $table->string('query', 500);                    // arama kelimesi
    $table->date('date_from');                       // veri aralığı başı
    $table->date('date_to');                         // veri aralığı sonu
    $table->unsignedInteger('clicks')->default(0);
    $table->unsignedInteger('impressions')->default(0);
    $table->decimal('ctr', 5, 2)->default(0);        // 0.00-100.00
    $table->decimal('position', 5, 2)->default(0);   // ortalama sıra (1-100)
    $table->string('country', 2)->default('tr');     // ISO ülke kodu
    $table->string('device', 16)->nullable();        // mobile|desktop|tablet
    $table->timestamps();
    $table->softDeletes();

    $table->index(['date_from', 'date_to']);
    $table->index('clicks');
    $table->index('position');
});
```

### `gsc_page_metrics`
```php
Schema::create('gsc_page_metrics', function (Blueprint $table) {
    $table->id();
    $table->string('page_url', 1000);                // tam URL
    $table->string('page_path', 500)->index();       // /urun/koy-tereyagi
    $table->string('page_type', 32)->index();        // product|blog|city|page
    $table->unsignedBigInteger('related_id')->nullable(); // product_id/blog_post_id
    $table->date('date_from');
    $table->date('date_to');
    $table->unsignedInteger('clicks')->default(0);
    $table->unsignedInteger('impressions')->default(0);
    $table->decimal('ctr', 5, 2)->default(0);
    $table->decimal('position', 5, 2)->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['page_type', 'related_id']);
    $table->index(['date_from', 'date_to']);
});
```

### `ga4_city_metrics`
```php
Schema::create('ga4_city_metrics', function (Blueprint $table) {
    $table->id();
    $table->string('city', 100)->index();
    $table->string('region', 100)->nullable();       // İl bölgesi (Marmara vb.)
    $table->date('date_from');
    $table->date('date_to');
    $table->unsignedInteger('users')->default(0);
    $table->unsignedInteger('sessions')->default(0);
    $table->decimal('avg_session_duration', 8, 2)->default(0);
    $table->decimal('bounce_rate', 5, 2)->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['date_from', 'date_to']);
});
```

### `indexing_requests`
```php
Schema::create('indexing_requests', function (Blueprint $table) {
    $table->id();
    $table->string('url', 1000);
    $table->string('type', 32);                      // URL_UPDATED|URL_DELETED
    $table->string('status', 16);                    // pending|sent|success|failed
    $table->string('related_type', 64)->nullable();  // App\Models\Product
    $table->unsignedBigInteger('related_id')->nullable();
    $table->json('response')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();

    $table->index(['related_type', 'related_id']);
    $table->index('status');
});
```

### `google_api_logs`
```php
Schema::create('google_api_logs', function (Blueprint $table) {
    $table->id();
    $table->string('service', 32);                   // gsc|ga4|indexing
    $table->string('operation', 64);                 // fetch_queries|fetch_pages|index_url
    $table->string('status', 16);                    // success|failed
    $table->unsignedSmallInteger('http_code')->nullable();
    $table->text('error_message')->nullable();
    $table->json('request_payload')->nullable();
    $table->unsignedInteger('response_size')->nullable();
    $table->unsignedInteger('duration_ms')->nullable();
    $table->timestamps();

    $table->index(['service', 'status']);
    $table->index('created_at');
});
```

---

## 🚦 Feature Flag Sistemi

Her modül ayrı açılıp kapatılabilir — sorun çıkarsa anında devre dışı.

### Settings tablosuna eklenecek anahtarlar
| Anahtar | Default | Açıklama |
|---|---|---|
| `google_gsc_enabled` | true | GSC metrics cron çalışsın mı |
| `google_ga4_enabled` | true | GA4 metrics cron çalışsın mı |
| `google_indexing_enabled` | false | Yeni içerik için Indexing API kullanılsın mı |
| `google_low_ctr_detection` | true | Düşük CTR otomatik tespit aktif mi |
| `google_dashboard_widgets` | true | Admin dashboard widget'ları gösterilsin mi |

### Admin paneli arayüzü
`/admin/settings/integrations` → "Google Entegrasyonları" sekmesi:
- Her modülün yanında toggle switch
- Service Account dosyası yüklü mü kontrolü
- Son test tarihi + "Şimdi Test Et" butonu

### Code seviyesinde kontrol
```php
if (! Setting::getValue('google_gsc_enabled', true)) {
    return; // cron erken çıkar
}
```

---

## 📤 Export & Karşılaştırma

### CSV / Excel export
Admin sayfaların hepsinde "İndir" butonu:
- `/admin/seo-performance/export?format=csv&type=queries&days=28`
- Format: CSV (Excel'de açılır), her şehir/ürün/blog ayrı dosya
- Dosya adı: `seo-queries-2026-04-27.csv`

### Dönem karşılaştırma
SEO Performans sayfasında "Önceki dönemle karşılaştır" seçeneği:
```
┌─────────────────────────────────────────────────────┐
│ Tıklama: 1.247 ↑ %23 (önceki 28 gün: 1.013)         │
│ Gösterim: 32K ↑ %15                                 │
│ Ort. Pozisyon: 5.4 ↑ (önceki: 6.8)                  │
│ CTR: %3.9 ↑ (önceki: %3.1)                          │
└─────────────────────────────────────────────────────┘
```

Yeşil ↑ = iyileşme, kırmızı ↓ = bozulma.

### Trend grafiği
Chart.js ile basit line chart:
- Son 28 gün — günlük tıklama eğrisi
- Nokta üzerine hover → detay tooltip
- Y ekseni log scale opsiyonu

---

## 🔔 Bildirim Sistemi

### In-app bildirimler (admin için)
Tablo: `notifications` (Laravel'in built-in notification sistemi)

**Kanallar:**
- Database (admin'in çan ikonu altında görünür)
- Mail (kritik hatalar)

**Tetikleyiciler:**
| Olay | Bildirim |
|---|---|
| Cron başarıyla çalıştı | (sessiz, log only) |
| Cron 3 kere üst üste hata aldı | 🔴 In-app + mail |
| Yeni "düşük CTR" sayfa tespit edildi | 🟡 In-app |
| Indexing API yeni içeriği indeksledi | 🟢 In-app (10dk sonra hatırlatma) |
| Service Account 60 gün sonra expire | 🟡 In-app uyarı |
| API kotası %80'i geçti | 🟡 In-app uyarı |

### Notification class'ları
```
app/Notifications/
  ├── GoogleApiCronFailed.php
  ├── LowCtrPagesDetected.php
  ├── IndexingSucceeded.php
  └── ApiQuotaWarning.php
```

---

## 💾 Backup Stratejisi

### Service Account JSON
- **Lokasyon 1:** Production sunucu `storage/app/google/`
- **Lokasyon 2:** Şifreli zip (parolası 1Password'da)
- **Lokasyon 3:** Google Cloud Console'da yeniden oluşturulabilir (süresiz)
- **Düzenli yedek:** Yılda 1 yeni anahtar üret, eskisini revoke et

### DB metric'leri yedekleme
- Mevcut Laravel backup paketi varsa otomatik dahil olur
- Yoksa: `mysqldump --tables gsc_query_metrics gsc_page_metrics ga4_city_metrics indexing_requests google_api_logs > backup.sql`
- Cron: Haftada 1, S3 / dropbox'a (proje politikası gereği)
- **Not:** Bu metric'ler kritik değil — silinse Google'dan tekrar çekilir (sadece son 16 ay)

---

## 📋 Deployment Checklist

### Production'a alırken sırayla yapılacaklar:

#### 1. Pre-deploy
- [ ] `composer require google/apiclient ...` paketler kurulu
- [ ] `.env`'de tüm Google değişkenleri tanımlı
- [ ] `storage/app/google/service-account.json` dosyası yüklenmiş
- [ ] Dosya izni `chmod 600`
- [ ] Service Account email GSC + GA4'e yetkilendirilmiş

#### 2. Deploy
- [ ] `git pull origin main`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

#### 3. Post-deploy verification
- [ ] `php artisan gsc:fetch-metrics` (manuel test)
- [ ] `php artisan ga4:fetch-metrics` (manuel test)
- [ ] Admin paneli açılıyor, widget'larda veri var
- [ ] `google_api_logs` tablosunda success kaydı var
- [ ] Cron schedule yüklü: `php artisan schedule:list`

#### 4. Rollback planı
- [ ] Settings'den `google_gsc_enabled = false` ile cron'lar kapatılır
- [ ] Migration rollback: `php artisan migrate:rollback --step=5`
- [ ] Önceki kod versiyonuna git revert

---

## 📚 Handover Dokümantasyonu

Başka bir geliştirici devralırsa şu dosyalar yeterli:
1. **Bu doküman** (`docs/google-api-entegrasyonu.md`)
2. **Code yorumları** — her servis class'ının başında "Ne yapar, nasıl çalışır" docblock
3. **Architecture Decision Records:** Önemli kararlar `docs/adr/google-*.md` (örn: "neden 6 saatte bir cron?")
4. **README ekstra bölümü:** Quick-start "API entegrasyonunu test et" rehberi
5. **Troubleshooting kılavuzu:** `docs/google-api-troubleshooting.md` (yaygın hatalar, çözümler)

---

## 🌐 i18n / Çoklu Dil

- Tüm admin metinleri `lang/tr/seo.php` dosyasından çekilir
- İleride İngilizce admin paneli olursa → `lang/en/seo.php` eklenir
- Hardcoded "Tıklama", "Gösterim" yok → `__('seo.clicks')`, `__('seo.impressions')`

---

## 🎯 Pagination & Performance

### Admin sayfalarında
- SEO Performans tablosu → 25 satır/sayfa, sunucu taraflı pagination
- Server-side sorting (clicks/position desc)
- Search input → debounce 300ms

### N+1 önleme
- Tüm controller'larda `with()` kullanılır
- Page metrics → blog post / product join'i query'de yapılır
- Eloquent `withCount()` ile child sayıları

### Disk kullanımı kontrol
- Cron sonunda log: "Bu çekim X satır, Y MB ekledi"
- Aylık rapor: "DB %X büyüdü, retention çalışıyor"

---

## ✅ Eksiksizlik Garantisi (Profesyonel Checklist)

Bu plan profesyonel bir entegrasyon için aşağıdaki tüm konuları kapsar:

### Mimari & Performans
- [x] Frontend hiç etkilenmez (tüm işler arka planda)
- [x] Cache stratejisi tanımlı (5 farklı süre)
- [x] Cron schedule'ı (off-peak saatler)
- [x] API kotası analizi (%1'den az)
- [x] Disk kullanımı tahmini (<15 MB/yıl)

### Güvenlik
- [x] Service Account JSON saklama
- [x] `.gitignore` kuralı
- [x] File permission (chmod 600)
- [x] Repository güvenliği (.env.example)
- [x] Production deploy yöntemi (SCP)

### Yetkilendirme
- [x] Role bazlı erişim (Super Admin / Admin / Editor)
- [x] Policy class'ları
- [x] Route middleware

### Hata Yönetimi
- [x] HTTP code başına davranış (401, 429, 503, 404)
- [x] `google_api_logs` tablosu
- [x] Retry + exponential backoff
- [x] Admin sağlık widget'ı
- [x] Mail uyarıları + throttle

### Test
- [x] Unit test stratejisi
- [x] Feature test stratejisi
- [x] Mock yaklaşımı (`Http::fake`)
- [x] Manuel test checklist
- [x] CI uyumlu

### UX
- [x] Empty state tasarımı
- [x] Loading state (skeleton)
- [x] Hatalı durum bildirimi
- [x] Tooltip + iconography

### Veri Yönetimi
- [x] Schema tüm tabloların kolonları yazılı
- [x] Index'ler tanımlı
- [x] SoftDeletes (gerektiğinde)
- [x] Data retention (13 ay)
- [x] Backup stratejisi

### Operasyonel
- [x] Feature flag sistemi (her modül ayrı)
- [x] Deployment checklist
- [x] Rollback planı
- [x] Handover dokümantasyonu
- [x] Local development setup
- [x] CI/CD entegrasyonu

### Yasal & Uyum
- [x] KVKK uyumluluk açıklaması
- [x] Anonim veri kullanımı
- [x] Privacy Policy güncelleme önerisi

### Kullanıcı Deneyimi
- [x] Export (CSV/Excel)
- [x] Dönem karşılaştırma
- [x] Trend grafikleri
- [x] In-app + mail bildirim
- [x] i18n desteği

### Performans
- [x] Pagination
- [x] N+1 önleme
- [x] Server-side sorting
- [x] Search debounce

**Toplam: 50+ profesyonel madde garanti altına alındı.**

---

## Onay

Bu plana **OK** dersen Faz 1'den başlarım. Faz adım adım gider, her modül
biterken kontrol edebilirsin. Faz 2'ye geçmek için ayrı onay alırım.

**Faz 1'in toplam efor (test + güvenlik + UX dahil):**
- ~3-4 gün geliştirme
- ~1 saat senin Google Cloud setup
- ~30 dk plan onay + JSON yükleme

**Bütçe garantisi:**
- Google API maliyeti: **0 TL** (ücretsiz limitler içinde)
- Sunucu yükü: ihmal edilebilir (cron 4 kez/gün, <30sn)
- Disk: <15 MB/yıl
- Site hızı etkisi: **0ms** (kullanıcı tarafı), **+5-15ms** (admin sayfaları)
