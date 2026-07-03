# Frontend Eksiklik ve Hata Raporu

**Tarih:** 2026-03-12
**Durum:** Beklemede

---

## KIRMIZI - Acil Düzeltilmeli ✓

### 1. Inline Style Ihlalleri (CLAUDE.md Kuralı: YASAK)

- [x] **1.1** `resources/views/blog/show.blade.php:68` — `style="color: var(--green-dark);"` → `.text-green-dark` class'ı kullan
- [x] **1.2** `resources/views/blog/show.blade.php:80` — `style="color: var(--green-dark);"` → `.text-green-dark` class'ı kullan
- [x] **1.3** `resources/views/products/show.blade.php:282` — `style="opacity: 0.3"` → Bootstrap `.opacity-25` veya custom `.opacity-30` class'ı kullan
- [x] **1.4** `resources/views/admin/settings/index.blade.php:278` — `style="color:#e4405f"` → `.text-instagram` class'ı oluştur
- [x] **1.5** `resources/views/admin/blog-comments/show.blade.php:27` — `style="white-space: pre-wrap; line-height: 1.8;"` → `.comment-body` class'ı oluştur
- [x] **1.6** `resources/views/admin/sliders/index.blade.php:176` — `style="width:80px;height:40px"` → `.slider-thumb` class'ı oluştur
- [x] **1.7** `resources/views/welcome.blade.php:145,151,192,196,214,259,263` — SVG `style="mix-blend-mode: ..."` (7 adet) → CSS class'a taşı

### 2. JavaScript Inline Style Manipulasyonu (CLAUDE.md Kuralı: YASAK)

- [x] **2.1** `public/js/cart.js:300-301` — `input.style.animation = 'shake 0.5s ease'` → `input.classList.add('animate-shake')` yap
- [x] **2.2** `public/js/cart.js:314-315` — Ayni sorun, `classList.remove('animate-shake')` ile temizle
- [x] **2.3** `public/js/cart.js:344-346` — JS'den `@keyframes shake` inject ediliyor → `public/css/app.css`'e tasi

### 3. Erisilebirlik (Accessibility - WCAG AA)

- [x] **3.1** `resources/views/cart/index.blade.php:74-80` — Miktar +/- butonlarinda `aria-label` eksik → `aria-label="Miktari azalt"` / `aria-label="Miktari arttir"` ekle
- [x] **3.2** `resources/views/cart/index.blade.php:51` — Sepeti temizle butonunda `aria-label` eksik → `aria-label="Sepeti tamamen temizle"` ekle
- [x] **3.3** `resources/views/cart/index.blade.php:155` — Kupon input'unda `<label>` eksik → `<label for="couponInput">` ekle (visually-hidden olabilir)
- [x] **3.4** `resources/views/checkout/index.blade.php:63-122` — Zorunlu form alanlarinda `aria-required="true"` eksik
- [x] **3.5** `resources/views/partials/home-hero.blade.php:46-52` — Dekoratif emoji karakterlerde (`bird`, `butterfly`) `aria-hidden="true"` eksik
- [x] **3.6** `resources/views/partials/home-hero.blade.php:61-193` — Dekoratif SVG elemanlarinda `aria-hidden="true"` eksik
- [x] **3.7** `resources/views/blog/show.blade.php:80` — Heading hiyerarsisi bozuk: H1 → H3 atlama var → `<h3>` yerine `<h2>` kullan
- [x] **3.8** `resources/views/faq.blade.php:12` — Breadcrumb `<nav>` etiketinde `aria-label="Breadcrumb"` eksik

---

## TURUNCU - Kisa Vadede Duzeltilmeli ✓ (9.1 hariç)

### 4. Performans - Cache Eksiklikleri

- [x] **4.1** `resources/views/partials/navbar.blade.php:85-90` — Kategori sorgusu her sayfa yuklemede calisiyor → `Cache::remember('nav_categories', 3600, ...)` kullan
- [x] **4.2** `resources/views/partials/footer.blade.php:53-57` — Ayni kategori sorgusu footer'da da tekrarlaniyor → Ayni cache key'i paylasabilir
- [x] **4.3** `resources/views/blog/index.blade.php:95-100` — Populer yazilar sorgusu cache'lenmemis → `Cache::remember('popular_blog_posts', 3600, ...)` kullan
- [x] **4.4** `resources/views/blog/show.blade.php:139-144` — Ayni populer yazilar sorgusu burada da var → Cache paylassın

### 5. Performans - N+1 Query Riski

- [x] **5.1** `app/Http/Controllers/ProductController.php:45` — `$product->approvedReviews()->get()` eager-loaded degil → `$product->load('approvedReviews')` veya Service'te `.with()` kullan

### 6. SEO Eksiklikleri

- [x] **6.1** `resources/views/products/show.blade.php:7` — og:image icin `asset('uploads/...')` kullaniliyor → `upload_url($product->image, 'lg')` olmali
- [x] **6.2** `resources/views/blog/index.blade.php` — `@section('og_image')` tanimlanmamis → Varsayilan og:image ekle
- [x] **6.3** `resources/views/products/index.blade.php:5` — Pagination'li sayfalarda canonical URL `?page=` parametresini icermiyor
- [x] **6.4** Tum sayfalar — JSON-LD yapilandirilmis veri (structured data) eksik: Product, Article, Organization, BreadcrumbList semalari

### 7. Mobile/Responsive Sorunlari

- [x] **7.1** `resources/views/products/index.blade.php:602-606` — List view'da sabit `width: 250px` → Mobilde kirilir, `max-width` + responsive breakpoint ekle
- [x] **7.2** `resources/views/contact.blade.php:301-306` — 4 kolonlu grid mobile-first degil → `repeat(auto-fit, minmax(250px, 1fr))` veya breakpoint ekle
- [x] **7.3** `resources/views/products/index.blade.php:186-188` — Sticky filter `top: 70px` sabit navbar yuksekligi varsayiyor → Mobilde navbar daha uzun, overlap olur

### 8. Gorsel Optimizasyonu

- [x] **8.1** `resources/views/partials/navbar.blade.php:58` — Logo `loading="lazy"` kullanıyor → Above-fold oldugu icin `loading="eager"` olmali

### 9. CSS Yaklasimi

- [ ] **9.1** `public/css/app.css` (20+ yer) — Desktop-first `@media (max-width: ...)` kullaniliyor → CLAUDE.md kurali: Mobile-first `@media (min-width: ...)` olmali _(büyük refactoring, ayrı task olarak planlandı)_

---

## SARI - Iyilestirme ✓

### 10. Guvenlik

- [x] **10.1** `resources/views/contact.blade.php:169` — `{!! $mapEmbed !!}` ile harita embed ediliyor → iframe sandbox attribute'u eksik, XSS riski

### 11. Eksik Tema Donusumleri

- [x] **11.1** `resources/views/front-theme/galeri.html` — Galeri sayfasi Blade'e donusturulmemis _(zaten daha önce dönüştürülmüş: gallery/index.blade.php)_
- [x] **11.2** `resources/views/front-theme/hakkimizda.html` — Hakkimizda sayfasi Blade'e donusturulmemis _(pages/hakkimizda.blade.php olarak dönüştürüldü)_

---

## YESIL - Sorunsuz Alanlar (Referans)

- [x] 48 frontend route tamamı calisiyor
- [x] 16 controller ve tum method'lari mevcut
- [x] Tum view dosyalari mevcut
- [x] CSRF korumasi tum formlarda ve AJAX'ta
- [x] Vanilla JS kullanimi (jQuery sadece izin verilen blog/show'da)
- [x] `console.log` yok, production temiz
- [x] Service layer pattern dogru uygulanmis
- [x] FormRequest validation tutarli
- [x] Semantic HTML (nav, main, article, section, footer) genel olarak dogru
- [x] CDN bagimliligi kalmamis (reCAPTCHA harici)
- [x] Vendor dosyalari saglam, bozuk dosya yok
- [x] Frontend/Admin asset ayrimi dogru
- [x] Responsive image component duzgun calisiyor
- [x] Error page template'leri (404, 500) mevcut
- [x] Pagination tum listelerde uygulanmis
- [x] BEM CSS naming convention takip ediliyor
