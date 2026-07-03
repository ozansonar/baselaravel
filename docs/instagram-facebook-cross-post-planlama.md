# Instagram + Facebook Cross-Post — Geliştirme Planı

**Tarih:** 27 Nisan 2026
**Durum:** Plan onayı bekliyor
**Hedef:** Tek formdan görsel + AI ile metin/hashtag → Instagram + Facebook'a aynı anda otomatik yayın

---

## 🎯 Senin İstediğin Akış

```
1. Drag-drop ile görsel(ler) yükle
2. Konu başlığı yaz veya ürün seç
3. AI butonu → metin + popüler/trend hashtag öner
4. Instagram-style önizleme (post nasıl görünecek)
5. Yayın tarihi seç (veya hemen paylaş)
6. ☑ Instagram'da paylaş
   ☑ Facebook sayfasında paylaş
7. Kaydet → cron zamanı geldiğinde otomatik yayınlar
```

---

## ✅ Mevcut Durum (önceki 8 özellikten sonra)

### Çalışıyor olan
- ✅ AI Caption Üretici (Gemini, 4 ton)
- ✅ AI Hashtag Önerisi (tematik, 10-15 adet)
- ✅ Carousel desteği (max 10 görsel)
- ✅ Scheduled at + Cron 5 dk yayın
- ✅ Token expiry uyarısı
- ✅ Görsel boyut/aspect ratio validation
- ✅ Calendar view
- ✅ Bulk operations
- ✅ Validation (geçmiş tarih, image format)
- ✅ AdminModal ile onay
- ✅ Settings: Instagram Business Account ID, Facebook Page ID, Token, App ID/Secret

### Kısmen Çalışıyor
- 🟡 Görsel yükleme: file input + image preview var, **gerçek drop zone değil**
- 🟡 Hashtag: AI tematik üretiyor ama **trend takibi yok**

### Eksik
- ❌ Instagram-style live preview (mock-up)
- ❌ Facebook page cross-posting
- ❌ Trend hashtag havuzu

---

## 🚀 4 Modüllü Plan

### MODÜL 1: Gerçek Drag-Drop UX (1 saat)

**Şu anki:** `<input type="file">` + sonra preview
**Hedef:** Sürükle-bırak alan + multi-file destek + thumb grid + remove

**Yapılacak:**
- `instagram-posts.js` içinde drop zone event listeners
- Drag enter/over/leave/drop event handling
- Visual feedback: `border: dashed → solid teal` hover
- Multi-file pre-upload thumbnail grid (re-order destekli olabilir bonus)
- Drop zone CSS — büyük tıklanabilir alan, "Sürükle bırak veya tıkla"

**Görsel sonuç:**
```
┌─────────────────────────────────────────┐
│        ⬆️                              │
│   Görsellerin Sürükle Bırak             │
│   veya tıkla, dosya seç                 │
│   JPG/PNG/WebP — max 8 MB her biri      │
└─────────────────────────────────────────┘

[Yüklü görseller — sürüklenebilir grid]
[img1] [img2] [img3] [img4] [+ekle]
```

**Etkilenen dosyalar:**
- `resources/views/admin/instagram-posts/_form.blade.php` (tasarım)
- `public/assets/admin/js/instagram-posts.js` (logic)
- `public/assets/admin/css/styles.css` (CSS)

**Mevcut özellikleri bozmaz:** File input fallback olarak kalır.

---

### MODÜL 2: Instagram-Style Live Preview (1.5 saat)

**Şu anki:** Yok
**Hedef:** Form sağında gerçek Instagram post mock-up — caption girince/görsel seçince anlık güncelle

**Yapılacak:**
- Sağ kolona "Önizleme" kartı eklenecek
- Profil header: site logosu + "@orhanbabaninciftligi" + tarih
- Görsel: kare crop simülasyonu (carousel ise dot indicator)
- Action bar: Like/Comment/Share/Bookmark ikonları (statik)
- Caption preview: ilk 125 karakter + "...devamını gör"
- Hashtag'ler altta mavi renkte
- Mobile-first design (Instagram'ın gerçek görünümü)
- Dinamik: caption yazınca anlık güncellenir (input event)

**Görsel sonuç:**
```
┌─ Önizleme ──────────────────┐
│ 🌿 orhanbabaninciftligi      │
│ ─────────────────────────── │
│ ┌─────────────────────────┐ │
│ │                         │ │
│ │    [ Yüklenen görsel ]  │ │
│ │                         │ │
│ └─────────────────────────┘ │
│ ❤️ 💬 ✈️           🔖       │
│ ─────────────────────────── │
│ orhanbabaninciftligi        │
│ Çorum köyünden taze köy     │
│ tereyağı ☀️ ...devamını gör │
│ #köyürünleri #organik #...  │
│ 27 Nisan 2026               │
└─────────────────────────────┘
```

**Etkilenen dosyalar:**
- `resources/views/admin/instagram-posts/_form.blade.php` (yeni preview kart)
- `public/assets/admin/js/instagram-posts.js` (live update binding)
- `public/assets/admin/css/styles.css` (Instagram skin)

**Mevcut özellikleri bozmaz:** Tamamen yeni kart, varolan UI sağlanır.

---

### MODÜL 3: Trend Hashtag Havuzu (1.5 saat)

**Şu anki:** AI tematik hashtag öneriyor, manuel ekleme yok
**Hedef:** Admin'in elle eklediği "evergreen" hashtag listesi + AI'a fed etme

**Yapılacak:**

#### Veritabanı
```
hashtag_pool tablo:
- id, tag (unique), category (organik/lokasyon/sektör/trend)
- usage_count, last_used_at
- is_active, sort_order, created_at
```

#### Yönetim sayfası
- `/admin/hashtag-pool` — CRUD sayfa
- AI tarafından önerilen hashtag'ler tek tıkla havuza eklenebilir
- "En çok kullanılan", "Hiç kullanılmayan" filtreler
- Inline editing

#### AI prompt entegrasyonu
- Caption üretirken havuzdaki **aktif hashtag'leri context'e ver**
- AI "bu listeden uygun olanları öner + 3-5 yeni öner"
- Üretilen hashtag'lerin `usage_count` artar

#### Trend kaynağı (basit)
- Manuel: Admin haftalık bir kaç güncel hashtag ekler
- Otomatik (gelecek): RiteTag/all-hashtag.com API entegrasyonu (paid)

**Mevcut özellikleri bozmaz:** AI hâlâ üretiyor, sadece havuzdan beslenecek.

---

### MODÜL 4: Facebook Page Cross-Posting (3-4 saat)

**Şu anki:** Yok
**Hedef:** Aynı görsel + caption Facebook'ta da otomatik paylaşılır

#### Facebook Graph API spec

**Tek görsel:**
```
POST /{page-id}/photos
  url=image_url
  caption=metin
  published=true
  access_token=PAGE_ACCESS_TOKEN
```

**Multi-photo (album):**
```
1. Her görsel için: POST /{page-id}/photos?published=false
   → media_id'leri al
2. POST /{page-id}/feed
   message=caption
   attached_media=[{"media_fbid":id1},{"media_fbid":id2}...]
```

#### Auth
- Mevcut `instagram_access_token` USER token (long-lived)
- Page Access Token ALMAK GEREK:
  - `GET /me/accounts?access_token={user_token}`
  - Response'da `data[].access_token` her sayfa için page token
  - Bunu `instagram_facebook_page_token` setting'ine kaydet
- Page tokens **süresiz** (bu güzel haber)

#### Yapılacak

**1. Service: `FacebookPageService`**
```
publish(InstagramPost $post): array
  - Tek görsel → /photos endpoint
  - Carousel → /photos x N (published=false) + /feed (attached_media)
  - Hata yönetimi + log (instagram_post_logs'a action='facebook_publish' yaz)
```

**2. Migration: `add_facebook_columns_to_instagram_posts`**
```
ALTER TABLE instagram_posts ADD:
  - publish_to_facebook (boolean, default false)
  - fb_post_id (string nullable) — Facebook tarafındaki post ID
  - fb_permalink (string nullable)
  - fb_published_at (timestamp nullable)
  - fb_error_message (text nullable)
```

**3. Form'a toggle**
- "Yayın Planı" kartında 2 checkbox:
  - ☑ Instagram'da paylaş (default checked)
  - ☑ Facebook sayfasında paylaş (Page ID set ise default checked)

**4. Cron entegrasyonu**
- `PublishScheduledInstagramPosts` command'ı:
  - Önce Instagram → sonra Facebook (Instagram başarısızsa Facebook iptal mi?)
  - Karar: Bağımsız çalışacaklar — biri başarısız olursa diğeri devam
  - `error_message` ve `fb_error_message` ayrı

**5. Token alma sayfası**
- Settings → Instagram bölümüne ek:
  - "Facebook Page Token Al" butonu
  - User token + Page ID ile `/me/accounts` çağrısı
  - Bulunan page token'ı otomatik kaydet
  - Token'ın süresiz olduğunu göster

**6. Page seçici**
- Settings'te `instagram_facebook_page_id` zaten var
- Birden fazla sayfa varsa dropdown ile seçilebilir

**Mevcut özellikleri bozmaz:**
- Instagram-only post hâlâ çalışır (`publish_to_facebook=false` default)
- Cron logic backward compatible

---

## 🛠️ Toplam Efor

| Modül | Süre | Karmaşıklık |
|---|---|---|
| 1. Drag-Drop UX | 1 saat | 🟢 Kolay |
| 2. Live Preview | 1.5 saat | 🟢 Kolay |
| 3. Hashtag Havuzu | 1.5 saat | 🟡 Orta |
| 4. Facebook Cross-Post | 3-4 saat | 🔴 Karmaşık |
| **TOPLAM** | **7-8 saat** | — |

---

## 🚦 Geliştirme Sırası

**Akıllı sıralama** (her adım test + commit):

### Aşama 1: Hızlı UX kazanımları
1. **Modül 1** — Drag-Drop UX (kullanıcı hemen fark eder)
2. **Modül 2** — Live Preview (görsel etki yüksek)

→ İlk demo bu adımdan sonra. Kullanıcı %80 deneyimi alır.

### Aşama 2: İçerik kalitesi
3. **Modül 3** — Hashtag Havuzu (AI'ı güçlendirir)

### Aşama 3: Asıl değer
4. **Modül 4** — Facebook Cross-Post (en büyük yatırım, en büyük getiri)

---

## 📐 Veritabanı Değişiklikleri

### Yeni tablo: `hashtag_pool`
```php
Schema::create('hashtag_pool', function (Blueprint $table) {
    $table->id();
    $table->string('tag', 100)->unique();
    $table->string('category', 32)->default('genel');
    $table->unsignedInteger('usage_count')->default(0);
    $table->timestamp('last_used_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['is_active', 'category']);
    $table->index('usage_count');
});
```

### Mevcut `instagram_posts` tablosuna eklenecek
```php
Schema::table('instagram_posts', function (Blueprint $table) {
    $table->boolean('publish_to_facebook')->default(false)->after('status');
    $table->string('fb_post_id', 64)->nullable()->after('permalink');
    $table->string('fb_permalink', 512)->nullable()->after('fb_post_id');
    $table->timestamp('fb_published_at')->nullable()->after('published_at');
    $table->text('fb_error_message')->nullable()->after('error_message');

    $table->index('fb_post_id');
});
```

### Yeni Settings anahtarları
- `instagram_facebook_page_token` (password type, 64+ char)
- `instagram_facebook_default_publish` (boolean — default Facebook'a da gönder mi)

---

## 🔒 Güvenlik & Sınırlar

### Facebook Graph API limitleri
- Page posts: 50 post/saat (asla aşamayız)
- Photo upload: 1000/saat (asla aşamayız)
- Token expiry: User token 60 gün, **Page token süresiz**

### Risk yönetimi
- Instagram başarılı + Facebook başarısız → log kaydedilir, admin bilgi alır, gönderi kaybedilmez
- Facebook 401 = page token expire / permission revoke → uyarı banner'ı dashboard'da
- Carousel boyut: Facebook'ta max 30 image/album → bizim 10 limit altında, sorun yok

### Yetki gereksinimleri (Meta'da)
Mevcut Instagram için olan + ek:
- `pages_manage_posts` ✅ (Page'e post atma)
- `pages_read_engagement` ✅ (zaten kullanıyoruz)
- `publish_to_groups` (gruplara paylaşım için, bizim gerekli değil)

---

## ✅ Geriye Uyumluluk Garantisi

**Mevcut özellikler hiçbiri kaybolmaz:**

| Özellik | Korunur |
|---|---|
| Tek görsel post | ✅ Hâlâ çalışır |
| Carousel post | ✅ Hâlâ çalışır |
| AI Caption | ✅ Genişletilir (havuz ile) |
| Bulk operations | ✅ Aynı |
| Calendar | ✅ Aynı + FB durumu da gösterilir |
| Token expiry | ✅ FB için de kontrol |
| Cron 5 dk publish | ✅ FB ile genişletilir |
| Manual "Şimdi Paylaş" | ✅ FB toggle ile |
| Validation | ✅ Aynı |

**Tek "değişen":** Form'da "Yayın Planı" kartına 2 checkbox eklenir (Instagram + Facebook).

---

## 🎬 Final Sonuç

Plan tamamen geriye uyumlu. Her modül **bağımsız** olarak yapılabilir, ama önerilen sıraya uyulması iyi olur (hızlı kazanımlardan büyük yatırıma).

**Onay verirsen** şu sırayla yapacağım:
1. Modül 1 (Drag-Drop) — 1 saat → commit
2. Modül 2 (Preview) — 1.5 saat → commit
3. Modül 3 (Hashtag Havuzu) — 1.5 saat → commit + migration
4. Modül 4 (Facebook) — 3-4 saat → commit + migration

Her commit'ten sonra test + sen kontrol edebilirsin.

**Toplam: 7-8 saat geliştirme. Bütçe sıfır (Meta API ücretsiz).**

---

## ❓ Senin Yapman Gerekenler

### Hemen
- Plan onayı

### Geliştirme bittiğinde
- Meta for Developers'da uygulamaya `pages_manage_posts` izni eklemek (5 dk)
- Settings → Facebook Page Token Al butonuna basmak (10 saniye)

Hiçbir şey daha yok.
