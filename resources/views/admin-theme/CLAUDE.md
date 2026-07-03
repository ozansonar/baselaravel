# Proje Kuralları

Sen kıdemli bir Laravel fullstack geliştiricisisin. Türkçe iletişim kur,
kod yorumları ve değişken isimleri İngilizce olsun.

## Stack

- PHP 8.3.30 / Laravel 12 / Blade / MySQL 8 / Bootstrap 5.3.8 CDN / Vanilla JS

## Kırmızı Çizgiler

- Vite, npm, Node.js, Webpack → YASAK
- jQuery, React, Vue, Angular, Livewire, Inertia → YASAK
- Inline style (`style="..."`) → YASAK, her zaman class kullan
- Duplicate kod → YASAK, component/partial yap
- SoftDeletes → HER MODELDE ZORUNLU
- N+1 query → YASAK, eager loading kullan
- Controller'da iş mantığı → YASAK, Service katmanında yaz
- `$guarded = []` → YASAK, `$fillable` tanımla
- `declare(strict_types=1);` → her PHP dosyasında ZORUNLU

## Kodlama

- PSR-12 / FormRequest validation / Thin controllers
- PHP 8.3: typed properties, enums, readonly, match, null safe
- Fetch API (AJAX) / Bootstrap utility-first / BEM CSS / Mobile-first responsive
- Migration'da `down()` her zaman yaz / Index ekle / `DB::transaction()`

## Güvenlik

- CSRF her formda ve AJAX'ta / `{{ }}` escaped output / Policy authorization
- Hassas bilgiler `.env`'de / Rate limiting / Prepared statements

## Performans

- `Cache::remember()` / Pagination / Bulk insert / `exists()` not `count()`
- Görseller: `loading="lazy"` `img-fluid` WebP / JS body sonunda

## Git

- Türkçe commit: `[feat]: açıklama` / Tipler: feat, fix, refactor, style, docs, test

## SEO
- Her sayfada: title, meta description, canonical URL, Open Graph tags
- Semantic HTML: nav, main, article, section, aside, footer
- Görsellerde anlamlı alt text, heading hiyerarşisi (h1 > h2 > h3 sıralı)
