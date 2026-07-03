# Performans, SEO & Optimizasyon Raporu

**Tarih:** 2026-03-13
**Durum:** Beklemede

---

## KIRMIZI — Kritik (Hemen Yapılmalı)

### 1. `.htaccess` — Cache Header ve Gzip Sıkıştırma Eksik

**Dosya:** `public/.htaccess`

**Sorun:** Statik dosyalar (CSS, JS, görseller, fontlar) için hiçbir cache header veya sıkıştırma direktifi yok. Her ziyarette tarayıcı tüm dosyaları sıfırdan indiriyor.

**Çözüm:** `.htaccess`'e Expires, Cache-Control ve Gzip modülleri ekle:
- CSS/JS/font: 1 yıl cache (`max-age=31536000`)
- Görseller (WebP, PNG, JPG, SVG): 1 yıl cache
- HTML: cache yok veya çok kısa (no-cache)
- Gzip/Deflate: text/html, text/css, application/javascript, image/svg+xml

**Etki:** Sayfa yükleme hızında büyük iyileşme — tekrar ziyaretlerde dosyalar cache'den yüklenir.

---

### 2. `<link rel="preconnect">` Eksik

**Dosya:** `resources/views/layouts/app.blade.php` (satır 52-56 arası)

**Sorun:** External kaynaklara (Google Fonts, CDN) preconnect/dns-prefetch hint'leri yok. Tarayıcı DNS lookup + TCP + TLS handshake'i CSS'i gördüğünde yapıyor — zaman kaybı.

**Çözüm:** `<head>` bölümünün en üstüne (CSS'lerden önce) ekle:
```html
<link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
<link rel="dns-prefetch" href="https://www.googletagmanager.com">
```
(Fontlar ve Bootstrap self-hosted olduğu için sadece GTM ve reCAPTCHA için gerekli.)

**Etki:** İlk yüklemede 100-300ms tasarruf.

---

### 3. HomeController — Cache Eksik Sorgular

**Dosya:** `app/Http/Controllers/HomeController.php` (satır 28-39)

**Sorun:** Ana sayfa her istek'te şu sorguları çalıştırıyor:
- `testimonialService->allActive(3)` — **CACHE YOK** (her istekte DB'ye gidiyor)
- `blogService->latestPublished(3)` — **CACHE YOK** (her istekte DB'ye gidiyor)

Diğer servisler cache kullanıyor:
- `sliderService->allActive()` — ✅ cache var (3600s)
- `categoryService->allActive()` — ✅ cache var
- `productService->featured(6)` — ✅ cache var (1800s)
- `googleReviewService->getVisibleReviews(5)` — ✅ cache var (3600s)
- `youtubeService->getVisibleVideos(6)` — ✅ cache var (3600s)

**Çözüm:**
- `TestimonialService::allActive()` → `Cache::remember('testimonials.active', 3600, ...)` ekle
- `BlogService::latestPublished()` → `Cache::remember('blog.latest_published.{limit}', 1800, ...)` ekle
- Her iki service'te ilgili veri değiştiğinde cache'i temizle (`clearCache()` method)

**Etki:** Ana sayfada 2 gereksiz DB sorgusu kaldırılır. En çok ziyaret edilen sayfa.

---

### 4. `BlogService::latestPublished()` — Cache Eksik

**Dosya:** `app/Services/BlogService.php` (satır 24-31)

**Sorun:** `latestPublished()` her çağrıda `BlogPost::with(['category', 'author'])` ile DB'ye gidiyor. Ana sayfa, sidebar ve başka yerlerde kullanılabilir.

**Çözüm:**
```php
public function latestPublished(int $limit = 3): Collection
{
    return Cache::remember("blog.latest_published.{$limit}", 1800, fn () =>
        BlogPost::with(['category', 'author'])
            ->published()
            ->recent()
            ->limit($limit)
            ->get()
    );
}
```
Blog post CRUD işlemlerinde `Cache::forget()` ile temizle.

---

### 5. `TestimonialService::allActive()` — Cache Eksik

**Dosya:** `app/Services/TestimonialService.php` (satır 15-22)

**Sorun:** Her çağrıda direkt DB sorgusu. Müşteri yorumları nadiren değişir, cache'lenmeli.

**Çözüm:**
```php
public function allActive(int $limit = 3): Collection
{
    return Cache::remember("testimonials.active.{$limit}", 3600, fn () =>
        Testimonial::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
    );
}
```

---

## TURUNCU — Orta Öncelik (Kısa Vadede Yapılmalı)

### 6. Asset Versioning (Cache Busting) Eksik

**Dosya:** `resources/views/layouts/app.blade.php` (satır 53-56, 128-129)

**Sorun:** CSS ve JS dosyaları versiyon parametresi olmadan yükleniyor:
```html
<link href="/css/app.css" rel="stylesheet">
<script src="/js/app.js"></script>
```
`.htaccess`'e cache header ekledikten sonra, CSS/JS güncellendiğinde kullanıcılar eski cache'li versiyonu görür.

**Çözüm:** Tüm `asset()` çağrılarına versiyon query string'i ekle. Bir helper veya config değişkeni kullan:
```php
// app/Helpers/helpers.php veya config
define('ASSET_VERSION', '1.0.0');

// View'larda:
{{ asset('css/app.css') }}?v={{ config('app.asset_version') }}
```
Veya dosya hash'i ile otomatik busting:
```php
function versioned_asset(string $path): string
{
    $file = public_path($path);
    $version = file_exists($file) ? filemtime($file) : '1';
    return asset($path) . '?v=' . $version;
}
```

---

### 7. BreadcrumbList JSON-LD Schema Eksik

**Dosya:** Tüm iç sayfalar

**Sorun:** Breadcrumb UI bileşeni var ama JSON-LD structured data yok. Google arama sonuçlarında breadcrumb zengin snippet'i gösterilemiyor.

**Etkilenen sayfalar:**
- `resources/views/products/show.blade.php`
- `resources/views/products/index.blade.php`
- `resources/views/blog/show.blade.php`
- `resources/views/blog/index.blade.php`
- `resources/views/gallery/index.blade.php`
- `resources/views/faq.blade.php`
- `resources/views/contact.blade.php`
- `resources/views/pages/hakkimizda.blade.php`

**Çözüm:** Bir `partials/breadcrumb-jsonld.blade.php` component oluştur ve tüm sayfaların `@push('json-ld')` bloğuna ekle:
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Ana Sayfa", "item": "https://..." },
    { "@type": "ListItem", "position": 2, "name": "Ürünler", "item": "https://..." }
  ]
}
```

---

### 8. LocalBusiness JSON-LD Eksik

**Dosya:** `resources/views/contact.blade.php`

**Sorun:** İletişim sayfasında LocalBusiness schema markup'ı yok. Google'ın işletme bilgilerini (adres, telefon, çalışma saatleri) zengin snippet olarak göstermesi için gerekli.

**Çözüm:** İletişim sayfasına LocalBusiness JSON-LD ekle:
```json
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "...",
  "address": { "@type": "PostalAddress", ... },
  "telephone": "...",
  "openingHoursSpecification": [...]
}
```

---

### 9. Pagination Sayfalarında `rel="prev"` / `rel="next"` Eksik

**Dosya:** `resources/views/layouts/app.blade.php`

**Sorun:** Sayfalama olan sayfalarda (ürünler, blog) `<link rel="prev">` ve `<link rel="next">` tag'leri yok. Google bu bilgiyi sayfalanmış içeriği anlamak için kullanır.

**Etkilenen sayfalar:**
- `resources/views/products/index.blade.php`
- `resources/views/blog/index.blade.php`

**Çözüm:** Sayfalama olan view'larda `@push('styles')` veya `@section` ile head'e ekle:
```html
@if($products->previousPageUrl())
<link rel="prev" href="{{ $products->previousPageUrl() }}">
@endif
@if($products->nextPageUrl())
<link rel="next" href="{{ $products->nextPageUrl() }}">
@endif
```

---

### 10. CollectionPage / ItemList JSON-LD Eksik

**Dosya:** `resources/views/products/index.blade.php`, `resources/views/blog/index.blade.php`

**Sorun:** Ürün ve blog listeleme sayfalarında ItemList/CollectionPage schema yok. Google'ın bu sayfaları düzgün indekslemesi için önemli.

**Çözüm:** Ürün listeleme sayfasına ItemList JSON-LD ekle:
```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "url": "https://.../urun/slug" }
  ]
}
```
Blog listeleme için de benzer Blog/CollectionPage schema.

---

### 11. Görsel `width` / `height` Attribute Kullanımı Eksik — CLS Sorunu

**Dosya:** `responsive-image` component kullanan tüm view'lar

**Sorun:** `<x-responsive-image>` component'inde `width` ve `height` prop'ları opsiyonel ve çoğu yerde gönderilmiyor. Tarayıcı görsel boyutunu bilemediği için sayfa yüklenirken layout kayması (CLS - Cumulative Layout Shift) oluşur. Core Web Vitals metriği etkilenir.

**Etkilenen view'lar:**
- `resources/views/partials/home-products.blade.php` — Ürün kartları
- `resources/views/partials/home-blog.blade.php` — Blog kartları
- `resources/views/products/index.blade.php` — Ürün listeleme
- `resources/views/blog/index.blade.php` — Blog listeleme
- `resources/views/gallery/index.blade.php` — Galeri

**Çözüm:** İki yaklaşım:
1. Her `<x-responsive-image>` çağrısına `:width` ve `:height` ekle
2. VEYA (daha pratik) CSS ile `aspect-ratio` uygula — component'e size bazlı varsayılan aspect-ratio ekle:
```css
.responsive-image { aspect-ratio: 4/3; }
.responsive-image--square { aspect-ratio: 1/1; }
.responsive-image--hero { aspect-ratio: 16/9; }
```

---

### 12. FontAwesome Gereksiz Yük

**Dosya:** `resources/views/layouts/app.blade.php` (satır 55)

**Sorun:** FontAwesome `all.min.css` tüm ikonları yüklüyor (~80KB CSS + font dosyaları). Projede sadece birkaç ikon kullanılıyor olabilir (whatsapp, arrow-up, vb.).

**Çözüm:** Kullanılan ikonları tespit et. Eğer az sayıda ikon varsa:
- FontAwesome'u kaldır, Bootstrap Icons'a geç (zaten admin'de kullanılıyor)
- VEYA sadece kullanılan ikonları içeren custom kit oluştur
- VEYA inline SVG olarak kullan

**Etki:** ~80KB CSS + ~300KB font dosyası tasarrufu.

---

## SARI — İyileştirme (Planlı Yapılabilir)

### 13. Kullanılmayan Vendor Kütüphaneleri — Gereksiz Dosya Yükü

**Dosya:** `public/assets/vendor/`

**Sorun:** Aşağıdaki vendor kütüphaneleri projede mevcut ama hiçbir yerde kullanılmıyor:
- `public/assets/vendor/jquery/` — jQuery (~250KB) — Proje Vanilla JS kullanıyor
- `public/assets/vendor/jquery-validation-engine/` — jQuery Validation Engine

Bu dosyalar sunucuda yer kaplıyor ve yanlışlıkla dahil edilme riski taşıyor.

**Çözüm:** Kullanılmadıkları doğrulandıktan sonra bu dizinleri sil.

---

### 14. `BlogService::paginatePublished()` — Cache Stratejisi

**Dosya:** `app/Services/BlogService.php` (satır 33-39)

**Sorun:** Blog listeleme sayfası pagination'la cache'lenmemiş. Her sayfa tıklamasında DB sorgusu.

**Çözüm:** Pagination query'sini cache'lemek zor (her sayfa farklı key). Alternatif:
- Sadece sık erişilen ilk 3 sayfa cache'le
- VEYA query'yi optimize et (sadece gerekli kolonları çek):
```php
BlogPost::select(['id', 'title', 'slug', 'excerpt', 'image', 'blog_category_id', 'author_id', 'published_at'])
    ->with(['category:id,name,slug', 'author:id,name'])
    ->published()
    ->recent()
    ->paginate($perPage);
```

---

### 15. Product Listeleme — Ağır Kolon Yükleme

**Dosya:** `app/Services/ProductService.php`

**Sorun:** Ürün listeleme sorgularında `description` (TEXT tipi, potansiyel olarak büyük) kolonu da çekiliyor. Listede sadece isim, fiyat, resim gösterilir.

**Çözüm:** Listeleme method'larında `->select()` ile sadece gerekli kolonları çek:
```php
Product::select(['id', 'name', 'slug', 'price', 'sale_price', 'image', 'category_id', 'status', 'is_featured', 'stock_quantity'])
    ->with('category:id,name,slug')
```

---

### 16. Blog Listeleme — Ağır Kolon Yükleme

**Dosya:** `app/Services/BlogService.php`

**Sorun:** Blog listeleme sorgularında `content` (LONGTEXT) kolonu da çekiliyor. Blog listesinde sadece başlık, özet, resim gösterilir.

**Çözüm:**
```php
BlogPost::select(['id', 'title', 'slug', 'excerpt', 'image', 'blog_category_id', 'author_id', 'published_at', 'reading_time'])
    ->with(['category:id,name,slug', 'author:id,name'])
```

---

### 17. Loop İçinde `create()` — Bulk Insert Kullanılmalı

**Dosya:** `app/Services/ProductService.php` (satır 189-266)

**Sorun:** `syncImages()`, `syncNutritions()`, `syncShippingItems()`, `syncFeatures()` method'larında loop içinde `.create()` yapılıyor — her iterasyonda 1 INSERT query'si. 10 görsel = 10 query.

**Çözüm:** Bulk insert'e çevir:
```php
$imageData = [];
foreach ($images as $index => $file) {
    $imageData[] = ['product_id' => ..., 'image' => ..., 'sort_order' => ...];
}
ProductImage::insert($imageData);
```

---

### 18. Stats Method'larında `.get()` Sonrası Aggregate — DB'de Yapılmalı

**Dosya:** `app/Services/YouTubeService.php` (satır 178), `app/Services/GoogleReviewService.php` (satır 133)

**Sorun:** Tüm kayıtlar `.get()` ile belleğe çekiliyor, sonra PHP'de `->count()`, `->avg()`, `->sum()` yapılıyor. Kayıt sayısı arttıkça bellek tüketimi artar.

**Çözüm:** DB aggregate fonksiyonlarını kullan:
```php
// Yerine:
$reviews = GoogleReview::visible()->get();
return ['average' => $reviews->avg('rating'), 'count' => $reviews->count()];

// Şunu yap:
$stats = GoogleReview::visible()
    ->selectRaw('COUNT(*) as count, ROUND(AVG(rating), 1) as average')
    ->first();
return ['average' => (float) $stats->average, 'count' => $stats->count];
```

---

### 19. `count()` Yerine `exists()` Kullanılmalı

**Dosya:** `app/Services/AccountService.php` (satır 110)

**Sorun:** `$user->addresses()->count() === 0` ile varlık kontrolü yapılıyor. `count()` tüm kayıtları sayar, `exists()` ilk kaydı bulunca durur.

**Çözüm:** `$user->addresses()->doesntExist()` kullan.

---

### 20. Admin Status Count'ları — Çoklu Query

**Dosya:** `app/Services/BlogService.php` (satır 108-125), `app/Services/CampaignService.php` (satır 131-149), `app/Services/ProductService.php` (satır 397-410)

**Sorun:** `getStatusCounts()` method'larında her durum için ayrı `count()` query'si çalışıyor (5 query).

**Çözüm:** Tek query'de tüm durumları al:
```php
$counts = BlogPost::selectRaw("
    COUNT(*) as total,
    SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) as draft
")->first();
```
Veya `Cache::remember()` ile sarın.

---

### 21. `og:url` Meta Tag Eksik

**Dosya:** `resources/views/layouts/app.blade.php` (satır 32-43)

**Sorun:** Open Graph tag'lerinde `og:url` tanımlanmamış. Facebook, LinkedIn vb. paylaşımda canonical URL bilinmezse yanlış URL görüntülenebilir.

**Çözüm:** OG bölümüne ekle:
```html
<meta property="og:url" content="@yield('canonical', url()->current())">
```

---

### 22. Blog Post — `<article>` Semantic Tag Eksik

**Dosya:** `resources/views/blog/show.blade.php`

**Sorun:** Blog post içeriği `<div>` içinde, `<article>` semantic tag'ı kullanılmamış. Arama motorları ve ekran okuyucular için blog post'lar `<article>` ile sarılmalı.

**Çözüm:** Blog post ana içeriğini `<article class="blog-detail">` ile sar.

---

### 23. Pagination — `<nav>` Semantic Tag Eksik

**Dosya:** `resources/views/vendor/pagination/custom.blade.php`

**Sorun:** Pagination `<div>` ile sarılmış, `<nav aria-label="Sayfalama">` olmalı. WCAG ve SEO açısından navigation landmark olarak işaretlenmeli.

**Çözüm:** En dıştaki `<div>` yerine `<nav aria-label="Sayfalama">` kullan.

---

### 24. Product JSON-LD — `aggregateRating` Eksik

**Dosya:** `resources/views/products/show.blade.php`

**Sorun:** Product schema'sında `aggregateRating` ve `review` alanları yok. Ürünün değerlendirmesi varsa Google zengin snippet'te yıldız gösteremez.

**Çözüm:** Ürünün onaylı yorumları varsa JSON-LD'ye ekle:
```php
if ($product->approvedReviews->count() > 0) {
    $schema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => $product->approvedReviews->avg('rating'),
        'reviewCount' => $product->approvedReviews->count(),
    ];
}
```

---

### 25. AdminMiddleware — Her Admin Request'te Roles Sorgusu

**Dosya:** `app/Http/Middleware/AdminMiddleware.php` (satır 22-24)

**Sorun:** Her admin isteğinde `$user->roles()->whereIn(...)->exists()` sorgusu çalışıyor. Admin panelinde her sayfa yüklemesinde ekstra DB sorgusu.

**Çözüm:** User model'de role'ları cache'le veya session'da sakla:
```php
// Middleware'de:
$hasAccess = cache()->remember(
    "user.{$user->id}.admin_access",
    300,
    fn () => $user->roles()->whereIn('slug', ['admin', 'editor', 'moderator'])->exists()
);
```

---

### 26. Route Closure — `route:cache` Engelliyor

**Dosya:** `routes/web.php` (satır 135)

**Sorun:** Closure route tanımı var:
```php
Route::get('/sayfa/{slug}', fn (string $slug) => redirect('/' . $slug, 301));
```
Bu, `php artisan route:cache` komutunun çalışmasını engeller. Production'da route cache performans için kritik.

**Çözüm:** Closure'u controller method'una taşı:
```php
// routes/web.php
Route::get('/sayfa/{slug}', [PageController::class, 'redirectOld']);

// PageController'a ekle:
public function redirectOld(string $slug): RedirectResponse
{
    return redirect('/' . $slug, 301);
}
```

---

### 27. Desktop-First CSS → Mobile-First Dönüşümü

**Dosya:** `public/css/app.css` (20+ yer)

**Sorun:** `@media (max-width: ...)` kullanılıyor, CLAUDE.md kuralı `@media (min-width: ...)` mobile-first yaklaşımını gerektiriyor.

**Çözüm:** Büyük refactoring — tüm media query'lerin mantığını ters çevir. Ayrı task olarak planlandı (FRONTEND-ISSUES.md #9.1).

---

### 28. reCAPTCHA Script — Sayfa Bazlı Yükleme

**Dosya:** `resources/views/layouts/app.blade.php` (satır 132-134)

**Sorun:** reCAPTCHA etkinse script her sayfada yükleniyor (`async defer` olsa bile DNS lookup + connection maliyeti var). Sadece iletişim ve yorum formlarında gerekli.

**Çözüm:** reCAPTCHA script'ini sadece gerekli sayfalarda `@push('scripts')` ile yükle:
```blade
{{-- contact.blade.php --}}
@push('scripts')
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endpush
```
Layout'taki global yüklemeyı kaldır.

---

### 29. External Link'lerde `rel="nofollow"` Eksik

**Dosya:** Çeşitli view'lar

**Sorun:** Footer sosyal medya linkleri, blog içeriğindeki dış linkler, Place ID Finder linki vb. `rel="nofollow"` kullanmıyor. Link juice dışarı akıyor.

**Çözüm:** Tüm external linklere `rel="nofollow noopener noreferrer"` ekle. Partner/sponsorluk linkleri ise `rel="sponsored noopener"` kullan.

---

### 30. Twitter Card Meta Tag'leri Eksik/Yetersiz

**Dosya:** `resources/views/layouts/app.blade.php`

**Sorun:** Layout'ta `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` meta tag'leri yok. Twitter/X paylaşımlarında zengin önizleme gösterilmez.

**Çözüm:** Layout head bölümüne ekle:
```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="@yield('title', $ogTitle)">
<meta name="twitter:description" content="@yield('meta_description', $ogDesc)">
@hasSection('og_image')
<meta name="twitter:image" content="@yield('og_image')">
@endif
```

---

## YEŞİL — Sorunsuz Alanlar (Zaten İyi Olan)

- [x] `Setting::getValue()` — tüm ayarlar tek sorguda cache'leniyor (86400s) ✅
- [x] ViewServiceProvider yok — gereksiz global query yok ✅
- [x] robots.txt — doğru yapılandırılmış (admin, sepet, giriş disallow) ✅
- [x] Sitemap — `/sitemap.xml` route mevcut, SitemapService cache kullanıyor ✅
- [x] Organization JSON-LD — layout'ta global olarak mevcut ✅
- [x] Product JSON-LD — ürün detay sayfasında mevcut ✅
- [x] Article JSON-LD — blog detay sayfasında mevcut ✅
- [x] FAQPage JSON-LD — SSS sayfasında mevcut ✅
- [x] BreadcrumbList JSON-LD — Ürün detay ve blog detay sayfalarında mevcut ✅
- [x] CSS head'de, JS body sonunda — render-blocking minimize ✅
- [x] Bootstrap, fontlar self-hosted — CDN bağımlılığı yok ✅
- [x] `loading="lazy"` — responsive-image component'inde varsayılan ✅
- [x] Products tablosu — index'ler tam (status, is_featured, sort_order, category_id, price) ✅
- [x] Blog posts tablosu — index'ler tam (is_published, published_at, blog_category_id) ✅
- [x] Orders tablosu — index'ler tam (user_id, status, created_at) ✅
- [x] Testimonials tablosu — index'ler tam (is_active, sort_order) ✅
- [x] Google Reviews tablosu — index'ler tam ✅
- [x] YouTube Videos tablosu — index'ler tam ✅
- [x] Route cache uyumlu — Closure route yok, tümü controller-based ✅
- [x] Eager loading genel olarak doğru uygulanmış ✅
- [x] SliderService, CategoryService, ProductService, GoogleReviewService, YouTubeService — cache kullanıyor ✅
- [x] Heading hiyerarşisi (H1 > H2 > H3) doğru ✅
- [x] Semantic HTML (nav, main, article, section, footer) doğru ✅
- [x] Alt text görsellerde mevcut ✅
- [x] Canonical URL tüm sayfalarda mevcut ✅
- [x] Open Graph tag'leri layout'ta mevcut ✅

---

## Öncelik Sırası (Uygulama Planı)

| # | Madde | Öncelik | Tahmini Zorluk |
|---|-------|---------|---------------|
| 1 | `.htaccess` cache header + gzip | KIRMIZI | Kolay |
| 2 | Preconnect hint'leri | KIRMIZI | Kolay |
| 3 | TestimonialService cache | KIRMIZI | Kolay |
| 4 | BlogService latestPublished cache | KIRMIZI | Kolay |
| 5 | Asset versioning (cache busting) | TURUNCU | Kolay |
| 6 | BreadcrumbList JSON-LD (eksik sayfalar) | TURUNCU | Orta |
| 7 | LocalBusiness JSON-LD | TURUNCU | Kolay |
| 8 | Pagination prev/next link | TURUNCU | Kolay |
| 9 | CollectionPage/ItemList JSON-LD | TURUNCU | Orta |
| 10 | Görsel width/height (CLS) | TURUNCU | Orta |
| 11 | FontAwesome analiz/optimizasyon | TURUNCU | Orta |
| 12 | Kullanılmayan vendor kütüphaneleri | SARI | Kolay |
| 13 | Blog pagination select optimize | SARI | Kolay |
| 14 | Product listeleme select optimize | SARI | Kolay |
| 15 | Blog listeleme select optimize | SARI | Kolay |
| 16 | Loop içinde create → bulk insert | SARI | Kolay |
| 17 | Stats method'larında DB aggregate | SARI | Kolay |
| 18 | count() → exists() dönüşümü | SARI | Kolay |
| 19 | Admin status count → tek query | SARI | Kolay |
| 20 | og:url meta tag eksik | SARI | Kolay |
| 21 | Blog post `<article>` semantic tag | SARI | Kolay |
| 22 | Pagination `<nav>` semantic tag | SARI | Kolay |
| 23 | Product JSON-LD aggregateRating | SARI | Kolay |
| 24 | AdminMiddleware roles cache | SARI | Kolay |
| 25 | Route closure → controller (route:cache) | SARI | Kolay |
| 26 | Mobile-first CSS refactoring | SARI | Zor |
| 27 | reCAPTCHA sayfa bazlı yükleme | SARI | Kolay |
| 28 | External link rel="nofollow" | SARI | Kolay |
| 29 | Twitter Card meta tag'leri | SARI | Kolay |
