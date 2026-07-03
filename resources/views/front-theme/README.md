# Orhan Baba'nın Çiftliği

Doğal ve organik çiftlik ürünleri satan, 1975'ten bu yana faaliyet gösteren **Orhan Baba'nın Çiftliği** işletmesinin resmi e-ticaret web sitesi. Site, Çorum Merkez, Türkiye'de konumlanmaktadır.

---

## Proje Hakkında

Bu proje; ürün kataloğu, alışveriş sepeti, kullanıcı hesabı ve içerik yönetimi gibi tam kapsamlı bir e-ticaret deneyimi sunan web sitesidir. Frontend tamamen HTML5, CSS3 ve Vanilla JavaScript ile geliştirilmiştir. Backend altyapısı olarak **Laravel 12** kullanılmaktadır.

### Satılan Ürünler

- Taze Süt
- Köy Peyniri
- Tereyağı
- Yumurta
- Bal
- Diğer çiftlik ürünleri

---

## Teknolojiler

| Teknoloji | Sürüm | Açıklama |
|---|---|---|
| HTML5 | - | Semantik işaretleme dili |
| CSS3 | - | Özel stil ve animasyonlar |
| JavaScript | ES6+ | Vanilla JS, framework kullanılmadı |
| Bootstrap | 5.3.3 | Duyarlı (responsive) grid sistemi ve bileşenler |
| Font Awesome | 6.5.1 | İkon kütüphanesi |
| Google Fonts | - | Playfair Display & DM Sans yazı tipleri |
| Laravel | 12 | PHP backend framework |

**Veri Depolama:** `localStorage` (sepet ve kullanıcı oturumu için, istemci tarafı)

---

## Kurulum ve Çalıştırma

### Gereksinimler

- PHP >= 8.2
- Composer
- Laravel 12
- Modern bir tarayıcı (ES6 desteği)
- JavaScript ve localStorage etkin olmalı

### Laravel ile Kurulum

```bash
# Bağımlılıkları yükle
composer install

# .env dosyasını oluştur
cp .env.example .env

# Uygulama anahtarı oluştur
php artisan key:generate

# Veritabanı tablolarını oluştur
php artisan migrate

# Geliştirme sunucusunu başlat
php artisan serve
```

Tarayıcıda `http://localhost:8000` adresini açın.

---

## Proje Dosya Yapısı

Aşağıda projedeki her dosyanın konumu, amacı ve içeriği detaylı olarak açıklanmıştır.

```
orhanbabaciftlik/
├── index.html
├── urunler.html
├── urun-detay.html
├── sepet.html
├── odeme.html
├── hesabim.html
├── kategori.html
├── blog.html
├── blog-detay.html
├── galeri.html
├── hakkimizda.html
├── iletisim.html
├── giris.html
├── kayit.html
├── sifremi-unuttum.html
├── app.js
├── styles.css
├── sitemap.xml
└── robots.txt
```

---

## Dosya Referansları

### `app.js` — Paylaşılan JavaScript Çekirdeği

**Konum:** `/app.js`
**Tüm sayfalara dahil edilir.** `<script defer>` etiketiyle yüklenir.

Tanımlı fonksiyonlar ve işlevleri:

| Fonksiyon | Açıklama |
|---|---|
| `safeJsonParse(key, fallback)` | localStorage'dan JSON okur, hata varsa fallback döner. XSS koruması sağlar. |
| `escapeHtml(text)` | HTML özel karakterlerini escape eder (`<`, `>`, `&`, `"`, `'`). XSS saldırılarını önler. |
| `initNavbarScroll()` | Scroll event'i dinler; 50px sonra navbar'a `scrolled` class'ı ekler. |
| `initScrollToTop()` | "Yukarı çık" butonunu yönetir; 300px sonra görünür kılar, tıklanınca sayfayı başa alır. |
| `updateCartCount()` | localStorage'daki `cart` verisini okuyarak navbar'daki sepet rozetini günceller. |
| `initAnimateOnScroll()` | Intersection Observer ile `.animate-on-scroll` class'lı elementlere `animated` class'ı ekler. |

**Bağımlılıklar:** localStorage API, DOM API, Intersection Observer API, Bootstrap class'ları

---

### `styles.css` — Global Stil Sistemi

**Konum:** `/styles.css`
**Tüm sayfalara dahil edilir.** Tasarım sistemi ve paylaşılan bileşen stillerini içerir.

**CSS Değişkenleri (`:root`):**

| Değişken | Renk |
|---|---|
| `--green-dark` | `#2d5a27` |
| `--green-primary` | `#4a7c43` |
| `--green-light` | `#7cb342` |
| `--green-pale` | `#c5e1a5` |
| `--green-mist` | `#e8f5e9` |
| `--cream` | `#fdfdf5` |
| `--gold` | `#d4a84b` |
| `--brown` | `#5d4037` |
| `--brown-light` | `#8d6e63` |
| `--red` | `#e74c3c` |

**Animasyonlar (`@keyframes`):**

| Animasyon | Açıklama |
|---|---|
| `fadeInUp` | Aşağıdan yukarıya soluklaşarak giriş |
| `float` | Hafif yukarı-aşağı yüzme efekti |
| `fadeOut` | Saydamlığı azaltarak kaybolma |
| `shake` | Yatay sallama efekti (hata bildirimi) |
| `bounce` | Zıplama efekti |

**Bileşen Stilleri:**

| Class | Açıklama |
|---|---|
| `.navbar-custom` | Glassmorphism navbar (backdrop-blur, yarı şeffaf arka plan) |
| `.btn-custom` | Gradient yeşil birincil buton |
| `.cart-count` | Navbar'daki altın rengi sepet rozeti |
| `.page-header` | Sayfa üst başlığı (gradient arka plan) |
| `.breadcrumb-custom` | Özel breadcrumb navigasyonu |
| `.skip-nav` | Erişilebilirlik için "içeriğe atla" linki |
| `.scroll-top` | Sabit konumlu "yukarı çık" butonu |
| `.animate-on-scroll` | app.js ile tetiklenen scroll animasyon class'ı |
| `.footer` | Koyu yeşil footer bölümü |

**Yazı Tipleri:** Playfair Display (başlıklar, serif) · DM Sans (gövde, sans-serif)

---

### `index.html` — Ana Sayfa

**Konum:** `/index.html`
**URL:** `/` veya `/index.html`

Ana sayfa; ziyaretçiyi çiftlik ve ürünleriyle tanıştırır.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Hero Banner | Çiftlik tanıtımı, CTA butonları ("Ürünleri Keşfet", "Hakkımızda") |
| Öne Çıkan Ürünler | Ürün kartı grid'i, sepete ekle butonu |
| Hakkımızda Özeti | 1975'ten bu yana tarihçe, değerler |
| Müşteri Yorumları | Testimonial slider/kartları |
| Bülten Kaydı | E-posta abonelik formu (localStorage'a kaydeder) |
| Footer | Tüm sayfalara linkler, iletişim bilgisi |

**JavaScript Davranışı:**
- Bülten formu verisini localStorage'a yazar
- `app.js` üzerinden sepet sayısını günceller
- Scroll animasyonları çalışır

**Linklenen Sayfalar:** `giris.html`, `kayit.html`, `urunler.html`, `hakkimizda.html`, `blog.html`, `iletisim.html`

---

### `urunler.html` — Ürün Listeleme Sayfası

**Konum:** `/urunler.html`
**URL:** `/urunler`

Tüm ürünleri listeler; filtreleme, sıralama ve sayfalama destekler.

**Ürün Kategorileri:**

| Kategori ID | Adı | Ürün Sayısı |
|---|---|---|
| `sut` | Süt Ürünleri | 6 |
| `koy` | Köy Ürünleri | 4 |
| `bal` | Bal & Reçel | 2 |

**JavaScript Özellikleri:**

| Özellik | Açıklama |
|---|---|
| Sayfalama | `ITEMS_PER_PAGE = 6`, toplam 2 sayfa |
| Kategori Filtresi | Filtre butonları, aktif state yönetimi |
| Görünüm Değiştirme | Grid (3 sütun) / Liste görünümü toggle |
| Sepete Ekle | localStorage'daki `cart` array'ine ürün ekler, toast gösterir |
| Favoriler | Kalp ikonu toggle (Font Awesome `far`/`fas` değişimi) |
| Sıralama | Dropdown: En Popüler, Fiyat (düşük→yüksek), Fiyat (yüksek→düşük), En Yeni, En Yüksek Puan |

**Ürün Kartı Yapısı:** Görsel · Rozet (Çok Satan / Yeni / İndirim / Organik) · Favori butonu · Hızlı bakış · Yıldız puanı · Fiyat · Sepete ekle butonu

---

### `urun-detay.html` — Ürün Detay Sayfası

**Konum:** `/urun-detay.html`
**URL:** `/urun-detay`

Tek bir ürünün tüm detaylarını gösterir.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Ürün Galerisi | Büyük görsel + thumbnail'lar, lightbox desteği |
| Ürün Bilgisi | İsim, fiyat, puan, açıklama, özellikler |
| Miktar Seçici | Adet artır/azalt, sepete ekle |
| Ürün Özellikleri | Menşei, içerik, saklama koşulları |
| Müşteri Yorumları | Yorum listesi ve yorum ekleme formu |
| Benzer Ürünler | İlgili ürün kartları |

**Animasyonlar:** `fadeInUp`, `fadeInLeft`, `fadeInRight`, `scaleIn`, `float`

---

### `sepet.html` — Alışveriş Sepeti

**Konum:** `/sepet.html`
**URL:** `/sepet`

localStorage'daki sepet verisini okuyarak gösterir ve düzenlemeye izin verir.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Ürün Listesi | Her ürün satırı: görsel, isim, fiyat, adet kontrolü, kaldır butonu |
| Sipariş Özeti | Ara toplam, kargo ücreti, vergi, genel toplam |
| Kupon Kodu | İndirim kodu girişi ve uygulama |
| CTA Butonları | "Alışverişe Devam Et" → `urunler.html`, "Ödemeye Geç" → `odeme.html` |
| Boş Sepet Durumu | Sepet boşsa gösterilen alternatif içerik |
| WhatsApp Butonu | Sepet içeriğini WhatsApp mesajı olarak gönderme |

**Animasyonlar:** `fadeInUp`, `fadeOut`, `shake`, `bounce`

---

### `odeme.html` — Ödeme / Checkout Sayfası

**Konum:** `/odeme.html`
**URL:** `/odeme`
**robots.txt:** Arama motorlarından gizlidir (`Disallow: /odeme.html`)

Siparişin tamamlandığı ödeme akışı sayfasıdır.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Sipariş Özeti | Sepetteki ürünler ve toplam tutar |
| Ödeme Yöntemi | Kredi kartı formu · Banka havalesi · Kapıda ödeme |
| Fatura Adresi | Ad, soyad, adres, il, ilçe, posta kodu |
| Kupon Kodu | İndirim kodu uygulama |
| Sipariş Ver | Formu doğrulayıp siparişi onaylama butonu |

---

### `hesabim.html` — Kullanıcı Hesap Paneli

**Konum:** `/hesabim.html`
**URL:** `/hesabim`
**robots.txt:** Arama motorlarından gizlidir (`Disallow: /hesabim.html`)

localStorage'daki kullanıcı verisini okuyarak hesap panelini oluşturur.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Profil Bilgileri | Ad, soyad, e-posta, telefon düzenleme formu |
| Sipariş Geçmişi | Geçmiş siparişlerin listesi ve durumları |
| Kayıtlı Adresler | Teslimat adresi ekleme/düzenleme/silme |
| Favorilerim | Favori ürün listesi |
| Hesap Ayarları | Şifre değiştirme, bildirim tercihleri |
| Çıkış Yap | localStorage'ı temizler, `giris.html`'e yönlendirir |

**Ekstra CSS Değişkeni:** `--blue: #3498db` (bu sayfaya özel)

---

### `giris.html` — Giriş Sayfası

**Konum:** `/giris.html`
**URL:** `/giris`

İki sütunlu giriş sayfası: sol dekoratif bölüm + sağ form bölümü.

**Form Alanları:** E-posta · Şifre · Beni Hatırla checkbox'ı

**JavaScript Özellikleri:**

| Özellik | Açıklama |
|---|---|
| `togglePassword()` | Şifre görünürlük toggle (Font Awesome `fa-eye` / `fa-eye-slash`) |
| Demo Hesap | `demo@orhan.com` / `demo123` ile giriş yapar |
| Beni Hatırla | E-posta localStorage'a kaydedilir, sayfa açılışında form doldurulur |
| Sosyal Giriş | Google, Facebook, Apple butonları (placeholder alert) |
| Yönlendirme | Başarılı girişte `hesabim.html`'e yönlendirir |

**Sol Kolon:** Gradient yeşil arka plan · Müşteri yorumu · Animasyonlu elementler (tablet/mobilde gizlenir)

---

### `kayit.html` — Kayıt Sayfası

**Konum:** `/kayit.html`
**URL:** `/kayit`

Yeni kullanıcı oluşturma formu.

**Form Alanları:** Ad · Soyad · E-posta · Telefon (Türkiye formatı) · Şifre · Şifre Onayı · Kullanım Koşulları checkbox'ı · Bülten opt-in

**JavaScript Özellikleri:**

| Özellik | Açıklama |
|---|---|
| `togglePassword(inputId)` | İki ayrı şifre alanı için görünürlük toggle |
| Şifre Güç Göstergesi | Uzunluk, büyük/küçük harf, rakam, özel karakter kontrolü; kırmızı/altın/yeşil renk göstergesi |
| Form Doğrulama | Şifre eşleşmesi, min 8 karakter, koşullar kabul zorunluluğu |
| Veri Kaydetme | Kullanıcı verisi JSON olarak localStorage'a kaydedilir |
| Yönlendirme | Başarılı kayıtta `hesabim.html`'e yönlendirir |

---

### `sifremi-unuttum.html` — Şifre Sıfırlama Sayfası

**Konum:** `/sifremi-unuttum.html`
**URL:** `/sifremi-unuttum`

E-posta tabanlı şifre sıfırlama akışı.

**İki Durum Yönetimi:**

| Durum | Açıklama |
|---|---|
| Form Durumu | E-posta giriş alanı + "Sıfırlama Linki Gönder" butonu |
| Başarı Durumu | Animasyonlu onay işareti + "E-posta gönderildi" mesajı + tekrar gönder butonu |

**Sol Kolon:** Güvenlik ipuçları listesi · Animasyonlu yaprak ikonu

---

### `blog.html` — Blog Listeleme Sayfası

**Konum:** `/blog.html`
**URL:** `/blog`

Tüm blog yazılarının listelendiği sayfa.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Öne Çıkan Yazı | En güncel veya seçilmiş büyük kart |
| Yazı Kartları | Görsel, başlık, kategori, tarih, özet, okuma süresi |
| Kategoriler | Kenar çubuğunda kategori filtreleme |
| Arama | Blog içi arama formu |
| Popüler Yazılar | Kenar çubuğunda en çok okunan yazılar |

**Linklenen Sayfa:** `blog-detay.html`, `kategori.html`

---

### `blog-detay.html` — Blog Yazı Detay Sayfası

**Konum:** `/blog-detay.html`
**URL:** `/blog-detay`

**Örnek Yazı Başlığı:** "Ev Yapımı Peynir Nasıl Yapılır?"

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Yazar Bilgisi | Avatar, isim, tarih, okuma süresi |
| Öne Çıkan Görsel | Tam genişlik başlık görseli |
| Yazı İçeriği | Formatlı metin, alt başlıklar, görseller |
| Sosyal Paylaşım | Facebook, Twitter, WhatsApp paylaşım butonları |
| Etiketler | Yazı etiket listesi |
| Yorumlar | Kullanıcı yorum listesi + yorum formu |
| İlgili Yazılar | Alt kısımda benzer yazı kartları |

---

### `galeri.html` — Fotoğraf ve Video Galerisi

**Konum:** `/galeri.html`
**URL:** `/galeri`

Çiftlik yaşamından fotoğraf ve video içeriklerini sergiler.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Fotoğraf Galerisi | Grid layout, tıklanınca lightbox açılır |
| Video Galerisi | Video küçük resimleri, oynat butonu |
| Galeri Filtresi | Kategoriye göre filtreleme (hayvanlar, ürünler, doğa vb.) |
| Lightbox | Tam ekran görüntüleme, önceki/sonraki navigasyon |

---

### `hakkimizda.html` — Hakkımızda Sayfası

**Konum:** `/hakkimizda.html`
**URL:** `/hakkimizda`

İşletmenin tarihi ve değerlerini anlatır.

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| Tarihçe | 1975'ten günümüze 3 nesil hikayesi |
| Misyon & Vizyon | İşletmenin temel değerleri |
| Ekibimiz | Çalışan profil kartları |
| Zaman Tüneli | Önemli milestones (1975, 1990, 2005, 2020 vb.) |
| Sertifikalar | Organik/doğallık belgeleri |

**Animasyonlar:** `fadeInUp`, `fadeInLeft`, `fadeInRight`, `float`, `pulse`

---

### `iletisim.html` — İletişim Sayfası

**Konum:** `/iletisim.html`
**URL:** `/iletisim`

**İçerik Bölümleri:**

| Bölüm | Açıklama |
|---|---|
| İletişim Formu | Ad, e-posta, konu, mesaj alanları |
| Konum Bilgisi | Orhan Köyü, Merkez / Çorum / Türkiye |
| Çalışma Saatleri | Pazartesi–Cumartesi, 08:00–18:00 |
| Harita | Gömülü harita veya konum görseli |
| İletişim Detayları | Telefon, e-posta, adres |
| Sosyal Medya | Instagram, Facebook linkleri |

---

### `kategori.html` — Blog Kategori Sayfası

**Konum:** `/kategori.html`
**URL:** `/kategori`

Belirli bir blog kategorisine ait yazıları listeler (örnek: "Tarifler").

**İçerik:** Kategori başlığı · Filtrelenmiş yazı kartları · Ana bloga dönüş linki

---

### `sitemap.xml` — SEO Site Haritası

**Konum:** `/sitemap.xml`

Arama motorlarının siteyi taramasına yardımcı olur. 12 public sayfanın URL'sini, güncelleme sıklığını ve öncelik değerini içerir. Özel sayfalar (`hesabim`, `sepet`, `odeme`) dahil edilmemiştir.

---

### `robots.txt` — Arama Motoru Yönergeleri

**Konum:** `/robots.txt`

| Kural | Açıklama |
|---|---|
| `Allow: /` | Tüm public sayfalara izin verir |
| `Disallow: /hesabim.html` | Kullanıcı paneli indekslenemez |
| `Disallow: /odeme.html` | Ödeme sayfası indekslenemez |
| `Disallow: /sepet.html` | Sepet sayfası indekslenemez |
| `Sitemap:` | sitemap.xml konumunu belirtir |

---

## Sayfa Bağlantı Haritası

```
index.html
├── urunler.html
│   └── urun-detay.html
│       └── sepet.html
│           └── odeme.html
├── blog.html
│   ├── blog-detay.html
│   └── kategori.html
├── galeri.html
├── hakkimizda.html
├── iletisim.html
├── giris.html
│   └── sifremi-unuttum.html
├── kayit.html
└── hesabim.html
```

---

## Paylaşılan Sistem Bağımlılıkları

Her HTML sayfası şu dosyaları içerir:

```html
<!-- Stil -->
<link rel="stylesheet" href="styles.css">

<!-- Script (tüm sayfalarda ortak) -->
<script defer src="app.js"></script>
```

**CDN Bağımlılıkları (tüm sayfalarda):**

| Kaynak | URL |
|---|---|
| Bootstrap 5.3.3 CSS | jsDelivr CDN |
| Bootstrap 5.3.3 JS | jsDelivr CDN |
| Font Awesome 6.5.1 | Cloudflare CDN |
| Google Fonts | fonts.googleapis.com |

---

## localStorage Veri Yapısı

| Anahtar | Tip | Açıklama |
|---|---|---|
| `cart` | `Array<Object>` | Sepet ürünleri (`id`, `name`, `price`, `qty`, `image`) |
| `user` | `Object` | Oturum açmış kullanıcı bilgisi (`name`, `email`, `phone`) |
| `rememberedEmail` | `String` | "Beni Hatırla" ile kaydedilen e-posta |
| `newsletter` | `String` | Bülten aboneliği e-postası |

---

## SEO Yapısı

Her sayfada bulunur:

- `<meta name="description">` — Sayfa açıklaması
- `<meta name="keywords">` — Anahtar kelimeler
- `<meta property="og:*">` — Open Graph (sosyal medya önizleme)
- `<meta name="twitter:*">` — Twitter Card
- `<link rel="canonical">` — Kanonik URL
- **JSON-LD** (`application/ld+json`) — `LocalBusiness` schema (index.html'de)

---

## Tasarım Sistemi

### Renk Paleti

| Değişken | Renk | Hex |
|---|---|---|
| `--green-dark` | Koyu Yeşil | `#2d5a27` |
| `--green-primary` | Ana Yeşil | `#4a7c43` |
| `--green-light` | Açık Yeşil | `#7cb342` |
| `--gold` | Altın | `#d4a84b` |
| `--cream` | Krem | `#fdfdf5` |

### Tipografi
- **Başlıklar:** Playfair Display (serif)
- **Gövde:** DM Sans (sans-serif)

---

## İletişim Bilgileri

- **Konum:** Çorum Merkez, Türkiye
- **Çalışma Saatleri:** Pazartesi - Cumartesi, 08:00 - 18:00

---

## Lisans

Bu proje tüm hakları saklı olmak üzere **Orhan Baba'nın Çiftliği** işletmesine aittir.
