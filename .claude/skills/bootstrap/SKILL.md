---
name: bootstrap
description: >
  Bootstrap 5.3.8 ile frontend UI, layout ve responsive tasarım. Bu skill'i şu
  durumlarda kullan: sayfa tasarımı, navbar, sidebar, footer, card, modal, form,
  tablo, accordion, alert, toast, pagination veya herhangi bir UI component'i
  istendiğinde. Responsive düzenleme, CSS, Blade view/partial/component oluşturma,
  görsel iyileştirme istendiğinde tetiklen. "Tasarla", "form", "tablo", "navbar",
  "modal", "responsive", "layout", "card", "sidebar", "footer", "buton", "ikon",
  "mobilde düzgün görünsün", "CSS", "stil" gibi ifadelerde tetiklen.
---

# Bootstrap 5.3.8 — Proje Kuralları

## Stack

- Bootstrap 5.3.8 (CDN)
- Bootstrap Icons 1.11.3 (CDN)
- Vanilla JavaScript (ES6+)
- Blade template engine
- Custom CSS (BEM convention)

## YASAKLAR

- Vite, npm, Node.js, Webpack → YASAK
- React, Vue, Angular, Livewire, Inertia → YASAK
- jQuery → YASAK (DOM ve AJAX için Vanilla JS kullan)
- **Inline style (`style="..."`) → KESİNLİKLE YASAK** — her zaman class kullan
- **Duplicate kod → YASAK** — 2+ yerde kullanılan UI bloğu component/partial olacak

## İkon Kullanımı

- Font Awesome 7.2.0 ve Bootstrap Icons ikisi de mevcut
- Font Awesome öncelikli tercih et: `<i class="fa-solid fa-user"></i>`
- Bootstrap Icons alternatif: `<i class="bi bi-person"></i>`

## CDN

```html
<!-- head içinde -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.2.0/css/all.min.css" rel="stylesheet">
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
@stack('styles')

<!-- body sonunda -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
```

## Inline Style Yasağı

```blade
{{-- ❌ YANLIŞ --}}
<div style="background-color: #f8f9fa; padding: 20px;">

{{-- ✅ DOĞRU — Bootstrap utility --}}
<div class="bg-light p-4">

{{-- ✅ DOĞRU — Custom class (Bootstrap yetersizse) --}}
<div class="hero-section">
```

Önce Bootstrap utility class → yetersizse `public/css/app.css`'e BEM class yaz.

## Duplicate Yasağı — Component Yapısı

```
resources/views/
├── layouts/
│   ├── app.blade.php
│   └── guest.blade.php
├── components/          ← Reusable UI blokları
│   ├── navbar.blade.php
│   ├── footer.blade.php
│   ├── card.blade.php
│   ├── modal.blade.php
│   └── alert.blade.php
├── partials/            ← Sayfa parçaları
│   ├── _flash-messages.blade.php
│   └── _breadcrumb.blade.php
└── pages/
```

Anonymous Blade component kullan, `@props` ile parametrik yap:

```blade
{{-- components/card.blade.php --}}
@props(['title' => null, 'shadow' => true])
<div {{ $attributes->merge(['class' => 'card' . ($shadow ? ' shadow-sm' : '')]) }}>
    @if($title)
        <div class="card-header"><h5 class="card-title mb-0">{{ $title }}</h5></div>
    @endif
    <div class="card-body">{{ $slot }}</div>
</div>

{{-- Kullanım --}}
<x-card title="Başlık" class="mb-4">İçerik</x-card>
```

## Responsive — KRİTİK

**Tüm sayfalar tüm cihazlarda kusursuz çalışmalı. Mobile-first yaklaşım.**

Breakpoint'ler: `sm(≥576)` `md(≥768)` `lg(≥992)` `xl(≥1200)` `xxl(≥1400)`

```blade
{{-- Mobilde full, tablette yarım, desktop'ta üçte bir --}}
<div class="col-12 col-md-6 col-lg-4">

{{-- Navbar: Mobilde hamburger --}}
<nav class="navbar navbar-expand-lg">

{{-- Tablo: Mobilde scroll --}}
<div class="table-responsive"><table class="table">...</table></div>

{{-- Görsel: Fluid + lazy --}}
<img src="{{ asset('images/photo.webp') }}" alt="Açıklama"
     class="img-fluid rounded" loading="lazy" decoding="async">

{{-- Mobilde gizle / göster --}}
<div class="d-none d-lg-block">Desktop only</div>
<div class="d-lg-none">Mobile only</div>

{{-- Sidebar: Mobilde offcanvas, desktop'ta sabit --}}
<div class="d-none d-lg-block col-lg-3">@include('components.sidebar')</div>
<div class="offcanvas offcanvas-start d-lg-none" id="mobileSidebar">
    @include('components.sidebar')
</div>
```

## CSS Değişkenleri (public/css/app.css)

```css
:root {
    --color-primary: #1a1a2e;
    --color-accent: #0f3460;
    --color-highlight: #e94560;
    --font-heading: 'Georgia', serif;
    --font-body: 'Inter', system-ui, sans-serif;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
    --transition: 300ms ease;
}
```

BEM naming: `.block__element--modifier`

## Vanilla JavaScript

```javascript
// AJAX — Fetch API + CSRF
async function postData(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

// Bootstrap component — Vanilla
const bsModal = new bootstrap.Modal(document.getElementById('myModal'));
bsModal.show();
```

## Form Yapısı

```blade
<form action="{{ route('resource.store') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="title" class="form-label">Başlık <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control @error('title') is-invalid @enderror"
               id="title" name="title" value="{{ old('title') }}" required>
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i> Kaydet
    </button>
</form>
```

## Performans

- Görseller: `loading="lazy"` `decoding="async"` `img-fluid`, WebP format
- CSS `<head>` içinde, JS `</body>` öncesinde
- Animasyonlar: `transform` ve `opacity` kullan (GPU-accelerated), `width/height/margin` animasyonu YASAK
- `@once` directive ile tekrar eden asset'leri önle

## Erişilebilirlik

- `aria-label` ikon butonlarda, `aria-expanded` toggle'larda
- Form input'larında `<label>` + `id` eşleşmesi
- `alt` text her görselde anlamlı
- `<a class="visually-hidden-focusable" href="#main">İçeriğe atla</a>`
