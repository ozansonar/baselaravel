# Görsel Kütüphanesi — Kullanıcı Rehberi

Otomatik blog cron'u her çalıştığında bu kütüphaneden ürünle eşleşen
rastgele bir görsel seçer ve blog kapağına atar. **Sıfır AI maliyeti.**

## Hızlı başlangıç

### 1. Migration çalıştır (bir kerelik)

```bash
php artisan migrate
```

Beklenen çıktı:
- `media_assets` tablosu oluştu
- `blog_cover_source` setting'i `'media_library'` olarak eklendi

### 2. Görsel kütüphanesi sayfasına git

Admin → sol menü → **Görsel Kütüphanesi**

Boşken stat kartlarında 0 görsel görünür ve ürün kartlarında "Henüz görsel yok"
uyarısı çıkar.

### 3. İlk görselleri yükle

#### a) Nano Banana ile görsel ürettir (tarayıcıdan, ücretsiz)

`docs/media-library-modulu.md` dosyasında her ürün için hazır prompt'lar var.
Örneğin **"Günlük Taze Süt"** için:

```
Kare 1500×1500 görsel. Cam süt şişesi (eski tip), içinde taze köy sütü.
Rustik ahşap masa üstünde. Yanında bir kase yulaf.
Arka plan: yumuşak bulanık çiftlik manzarası.

KARE FORMAT, 1:1 oranı, 1500×1500 piksel.
KESİNLİKLE YAZMA: text, harfler, rakamlar, watermark, URL, telefon, hashtag.
Stil: rustik Anadolu çiftliği, doğal gün ışığı, food photography.
```

Her ürün için 30-50 görsel ürettirmeyi hedefle (varyasyon önerileri rehberdeki
prompt'larda var). Beğendiklerini bilgisayarına indir.

#### b) Admin panele yükle

1. Görsel Kütüphanesi sayfasında **"Günlük Taze Süt"** kartına tıkla
2. Üstteki "Görsel Yükle" alanına dosyaları sürükle (veya tıkla)
3. Dropzone otomatik kontrol eder:
   - **Kare olmayan** görseller kırmızı X ile reddedilir
   - **1080×1080 altı** görseller reddedilir
   - **4 MB üstü** görseller reddedilir
4. **"Tümünü Yükle"** butonuna bas
5. Paralel 4 dosya/saniye yüklenir, ~30 sn'de 50 görsel hazır

### 4. Diğer ürünler için tekrarla

7 ürün × 40 görsel = ~280 görsel hedef. Toplu emek ~5 saat (bir defalık).

### 5. "Genel" havuza yükle (önemli — fallback)

"Genel" kartına da görsel yükle. Bunlar **ürün eşleşmesi bulunamadığında**
kullanılır:
- Yeni ürün eklediğinde henüz görseli yokken
- Cron beklenmedik bir konu seçtiğinde
- Genel havuz tamamen güvenli fallback

Genel için prompt: çiftlik manzarası, köy yaşamı, hayvanlar, mevsimler vb.
~50-100 görsel öneririm.

## Akıllı seçim — neden monoton olmuyor

Cron her çağrıldığında:
1. Ürünün görselleri arasından **en uzun süredir kullanılmamış** + **en az
   kullanılmış** olanları sıralar
2. Top 5'ten **rastgele bir tane** seçer
3. O görselin `usage_count`'unu 1 artırır + `last_used_at`'i şu an yapar

Sonuç: 70 görselin **eşit dönüşümlü** kullanılır, aynı görsel sürekli dönmez.

## Ürün detay sayfasında ne var

Her ürün galerisinde:

- **Görsel grid** — her thumbnail üzerinde:
  - Kullanım sayısı (örn. "12× kullanıldı")
  - Son kullanım zamanı (örn. "3 gün önce")
  - Düzenle butonu
  - Sil butonu
- **Düzenleme modal'ı:**
  - Başlık (SEO alt text — boşsa blog başlığı kullanılır)
  - Ürün taşıma (başka ürüne aktar)
  - Aktif/Pasif (pasif görseller cron tarafından seçilmez)
- **Tam boyutta açma** — thumbnail'a tıkla

## Cron mod seçimi (ileri kullanım)

Default: `media_library` (sıfır AI maliyeti). Diğer modlar:

| Mod | Davranış | Maliyet (120 blog/ay) |
|---|---|---|
| `media_library` | Kütüphane → boşsa placeholder | **0 TL** |
| `media_library_then_ai` | Kütüphane → boşsa AI | ~10-30 TL |
| `ai` | Sadece AI (eski sistem) | ~145 TL |

Modu değiştirmek için (DB'den):
```sql
UPDATE settings SET value = 'media_library_then_ai' WHERE `key` = 'blog_cover_source';
```

## Yeni ürün eklediğinde ne olur?

1. AI Prompt Settings → "Ürün Listesi"ne yeni ürün ekle
2. Görsel Kütüphanesi otomatik yeni ürün için kart oluşturur
3. **Hemen görsel yükleme şart değil** — cron yeni ürün için yazı yazarken
   "Genel" havuzdan görsel kullanır
4. Sen müsait olduğunda yeni ürün için görsel yüklersin
5. Sonraki blog'lardan itibaren ürün-spesifik görseller kullanılır

## Sorun giderme

### "Kare değil — 1:1 oran zorunlu" hatası
Görsel kare olmalı (eşit genişlik ve yükseklik). Nano Banana'da prompt'a
**"KARE FORMAT, 1:1 oranı"** ekle.

### "Çok küçük — min 1080×1080" hatası
Görsel en az 1080×1080 piksel olmalı. Önerilen 1500×1500. Nano Banana
default 1024×1024 üretir — paid tier'da 2K seçeneği var.

### Cron blog kapağı kütüphaneden gelmedi (placeholder kullanıldı)
Sebep:
1. `blog_cover_source` ayarı `'ai'` olabilir → DB kontrol et
2. Seçilen ürünün ne kütüphanesinde ne "Genel" havuzda görseli yok
3. AI Prompt Settings.products'tan ürün adı silinmiş

Çözüm: `/admin/media-library` sayfasından stat kartlarını kontrol et.

### Tüm görselleri yedeklemek istiyorum
Görseller `public/uploads/media-library/` altında. Düz klasör backup yeterli.
DB tarafı: `media_assets` tablosu.

## Yıllık AI maliyeti karşılaştırma

| Sistem | Aylık | Yıllık |
|---|---|---|
| Eski (her blog için AI) | ~145 TL | ~1.730 TL |
| **MediaLibrary (mevcut)** | **0 TL** | **0 TL** |

Bir defalık ~5 saat manuel görsel yükleme emeği karşılığında yıllık 1.700+ TL
tasarruf + tutarlı görsel kalitesi + sıfır AI metin/Türkçe karakter riski.
