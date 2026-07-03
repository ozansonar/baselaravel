---
name: admin-panel
description: >
  Admin panel sayfası oluşturma, düzenleme ve tema uyarlama. Bu skill'i şu
  durumlarda kullan: admin sayfası, admin panel, admin tema, admin-theme HTML
  referans dosyası, dashboard, kullanıcı yönetimi, ayarlar sayfası, sipariş,
  analitik, rapor, kampanya, mesaj, yardım merkezi veya herhangi bir admin
  CRUD sayfası istendiğinde. "admin", "panel", "dashboard", "settings",
  "users", "orders", "analytics", "reports", "campaigns", "messages",
  "tema referans", "HTML'den Blade'e", "stat card", "toolbar", "admin tablo",
  "admin form", "admin liste", "admin ekle", "admin düzenle" gibi ifadelerde tetiklen.
---

# Admin Panel — Tema Uyarlama Rehberi

## Tema Dosya Yapısı

- **47 HTML referans dosyası:** `resources/views/admin-theme/` altında
- **Tema CSS:** `resources/views/admin-theme/styles.css` (535 KB, ~4001 class, dark/light)
- **Hangi sayfa hangi HTML?** → `admin-theme/README.md` > "Sidebar Full Navigation Tree"
- **Admin CSS (aktif):** `public/assets/admin/css/styles.css`
- **Admin JS (aktif):** `public/assets/admin/js/app.js` + sayfa özel JS'ler

## CSS Class Prefix Sistemi

Her sayfanın kendine ait CSS prefix'i var, HTML'deki class'ları **aynen** kullan:

| Prefix | Sayfa | Kullanım Alanı |
|--------|-------|----------------|
| `usr-` | users.html | Kullanıcı yönetimi, istatistik kartları (tüm stat card'lar) |
| `stg-` | settings.html | Ayarlar, form layout, sol nav + panel yapısı |
| `uf-`  | user-form.html | Kullanıcı formu, avatar, rol kartları, izinler |
| `cl-`  | content-list.html | İçerik listesi, toolbar, tablo, pagination |
| `ca-`  | content-add.html | İçerik ekleme/düzenleme, SEO preview, editor |
| `prd-` | products.html, product-add.html | Ürünler, kartlar, modal, galeri |
| `ord-` | orders.html | Siparişler, timeline, fatura, takip |
| `anl-` | analytics.html | Analitik, KPI kartları, grafikler |
| `rpr-` | reports.html | Raporlar, grafik, indirme, zamanlama |
| `cmp-` | campaigns.html | Kampanyalar, takvim, badge'ler |
| `msg-` | messages.html | Mesajlar, folder, liste, detay panel |
| `hlp-` | help.html | Yardım merkezi, arama, guide kartları |
| `auth-`| login/register/forgot | Giriş/Kayıt/Şifre sıfırlama |

## Ortak UI Pattern'leri

### Breadcrumb + Page Header
```html
<nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
  <ol class="breadcrumb">
    <li><a href="..." class="breadcrumb-link"><i class="bi bi-house"></i>Ana Sayfa</a></li>
    <li class="breadcrumb-item active text-teal">Sayfa Adı</li>
  </ol>
</nav>
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
  <div>
    <h1 class="page-title">Başlık</h1>
    <p class="page-subtitle">Açıklama</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass">İkincil</button>
    <button class="btn-teal">Ana Aksiyon</button>
  </div>
</div>
```

### İstatistik Kartları (4'lü row)
```html
<div class="row g-4 mb-4">
  <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="0|100|200|300">
    <div class="usr-stat-card">
      <div class="usr-stat-icon usr-stat-icon-blue|green|orange|red|purple|teal|pink">
        <i class="bi bi-xxx"></i>
      </div>
      <div class="usr-stat-info">
        <span class="usr-stat-label">Etiket</span>
        <h3 class="usr-stat-value" data-count="123">0</h3>
      </div>
    </div>
  </div>
</div>
```

### Durum Sekmeleri
`cl-status-tabs` > `cl-status-tab` + `cl-tab-count`

### Filtre Toolbar
`cl-toolbar` > `cl-search` + `cl-filters` + `cl-filter-select`

### Tablo
`cl-table` içinde `usr-action-btn` aksiyon butonları

### Pagination
`cl-pagination-wrapper` (bilgi metni + sayfalama)

### Silme Modal
`modal-custom` + `delete-modal-icon` + CSRF form

### Kart Container
```html
<div class="card-dark mb-4" data-aos="fade-up">
  <div class="card-header-custom"><h6><i class="bi bi-xxx me-2 text-teal"></i>Başlık</h6></div>
  <div class="card-body-custom"><!-- İçerik --></div>
</div>
```

### Settings Tipi Sol Nav + Panel Layout
settings.html ve user-form.html bu yapıyı kullanır:
```html
<div class="stg-layout">
  <div class="stg-nav d-none d-lg-block">
    <a class="stg-nav-item active" onclick="switchTab('section-id', this)">...</a>
  </div>
  <div class="d-lg-none mb-3">
    <select class="stg-select" onchange="switchTab(this.value, null)">...</select>
  </div>
  <div class="stg-content">
    <div class="stg-panel active" id="section-id">...</div>
  </div>
</div>
```

### Form Elemanları (settings/form sayfaları)
- `stg-field` > `stg-label` + `stg-input` / `stg-textarea` / `stg-select`
- `stg-toggle-list` > `stg-toggle-item` > `stg-switch` + `stg-switch-slider`
- `stg-hint` → input altı açıklama metni
- `stg-input-group` > `stg-input-prefix` + `stg-input`

### Toggle/Switch Listesi
```html
<div class="stg-toggle-list">
  <div class="stg-toggle-item">
    <div class="stg-toggle-info">
      <span>Başlık</span>
      <small>Açıklama metni</small>
    </div>
    <label class="stg-switch">
      <input type="checkbox" checked>
      <span class="stg-switch-slider"></span>
    </label>
  </div>
</div>
```

## Admin Sayfa Oluşturma Adımları

### 1. Referans HTML'i bul ve oku
- `admin-theme/README.md` → hangi sayfa hangi `.html` dosyası
- İlgili HTML'i baştan sona oku, section'ları, class'ları, yapıyı anla

### 2. CSS class'larını kontrol et
- HTML'deki tüm özel class'lar `public/assets/admin/css/styles.css`'de olmalı
- Yoksa `admin-theme/styles.css`'den bulup `public/assets/admin/css/styles.css`'e ekle
- Bootstrap utility class'ları zaten CDN'den geliyor, onlara dokunma

### 3. Backend hazırla (Service + Controller)
- `XxxService::getAdminStats()` → istatistik kartları için cached veriler
- `XxxService::getStatusCounts()` → durum sekmeleri için sayılar
- `XxxService::paginate($perPage, $filters)` → filtrelenebilir liste
- Controller'da iş mantığı YASAK, sadece Service çağır ve view'e veri geçir
- `per_page` → izin verilen değerler array'i ile `in_array()` kontrolü

### 4. Blade'e dönüştür
- HTML yapısını **BİREBİR** koru (class, ikon, section sırası)
- Statik veriyi Eloquent verileriyle değiştir
- Tema'daki client-side filtreleme → Laravel server-side'a çevir (URL query params)
- Durum sekmeleri → `<a href>` ile server-side link (onclick JS değil)
- Filtreler → `<form method="GET">` ile server-side
- Delete → CSRF korumalı `<form method="POST">` modal içinde
- Sayfa özel JS → `@push('scripts')` ile yükle

### 5. JavaScript dosyası oluştur
- `public/assets/admin/js/{sayfa-adi}.js` olarak kaydet
- Counter animasyonu: `data-count` attribute + `requestAnimationFrame`
- Checkbox/bulk: `toggleSelectAll()`, `updateBulk()`, `confirmBulkDelete()`
- Modal: `openDeleteModal(id, name)` → form action URL'sini dinamik set et
- View toggle (grid/table varsa): `switchView()` + `localStorage`
- Function declaration kullan (hoisting), IIFE içinde erken çağırma YAPMA

## Blade'de Dikkat Edilecekler

- `@json()` içinde closure/array bracket → Blade parser BOZULUR
  - **Çözüm:** `@php` block + `json_encode()` + `{!! !!}` kullan
- JS'de IIFE içinde fonksiyon `window.fn = ...` atanmadan önce çağrılırsa → "not a function" hatası
  - **Çözüm:** Function declaration kullan, init() fonksiyonu DOMContentLoaded'da çağır
- Duplicate `class` attribute → HTML'de `class="a" class="b"` YASAK, tek `class="a b"` kullan
- Model'de olmayan alan → HTML'de görünse bile Blade'e EKLEME, kullanıcıya sor

## AOS Animasyon Kuralları

- Breadcrumb/header: `data-aos="fade-down" data-aos-duration="400"`
- Stat kartları: `data-aos="fade-up" data-aos-delay="0|100|200|300"` (staggered)
- Section/card'lar: `data-aos="fade-up" data-aos-delay="50"`
- Sol navigasyon: `data-aos="fade-right" data-aos-delay="100"`
- AOS init: duration 600, easing ease-out-cubic, once true, offset 50

## Admin Layout

- Layout: `resources/views/layouts/admin.blade.php`
- CDN'ler: Bootstrap 5.3.8, Bootstrap Icons 1.13.1, Dropzone 5.9.3, AOS 2.3.1, Sortable.js 1.15.6
- Sidebar: `@include('partials.admin.sidebar')`
- Topbar: `@include('partials.admin.topbar')`
- Dark tema: `<html data-bs-theme="dark">`
- Sayfa JS: `@push('scripts')` ile eklenir
- Sayfa CSS: `@push('styles')` ile eklenir

## Tamamlanan Sayfalar

| Referans HTML | Blade Dosyası | JS Dosyası |
|---------------|---------------|------------|
| `products.html` | `admin/products/index.blade.php` | `products.js` |
| `content-list.html` | `admin/blog-posts/index.blade.php` | `blog-list.js` |
| `content-add.html` | `admin/blog-posts/create.blade.php` + `edit.blade.php` | `content-add.js` |
