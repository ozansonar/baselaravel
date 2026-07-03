---
name: laravel
description: >
  Laravel 12 (PHP 8.3) ile backend geliştirme. Bu skill'i şu durumlarda kullan:
  model, migration, controller, service, route, middleware, policy, observer, event,
  job, API endpoint, veritabanı işlemi, authentication, cache, queue, validation
  veya herhangi bir Laravel backend işlemi istendiğinde. "Model oluştur", "migration",
  "controller", "API", "route", "eloquent", "artisan", "seeder", "soft delete",
  "service class", "query" gibi ifadelerde tetiklen.
---

# Laravel 12 — Proje Kuralları

## Stack

- PHP 8.3.30 (strict types, enums, readonly, match, typed properties)
- Laravel 12
- Blade
- MySQL 8
- Redis (cache & queue, varsa)

## Ortam Kısıtlamaları — YASAKLAR

- Vite, Laravel Mix, npm, Node.js, Webpack → YASAK
- React, Vue, Angular, jQuery, Livewire, Inertia → YASAK
- Asset'ler `/public` dizininde manuel yönetilir, `asset()` helper ile dahil edilir

## Temel Kurallar

- `declare(strict_types=1);` → her PHP dosyasında ZORUNLU
- PSR-12 coding standard
- PHP 8.3 features aktif kullan: typed properties, return types, enums, readonly, match, `?->`, `#[Override]`
- Controller İNCE olacak → sadece request al, service'e ilet, response dön
- İş mantığı ASLA controller'da → her zaman Service katmanında
- FormRequest ile validation → controller içinde `$request->validate()` YASAK
- English comments, Türkçe iletişim

## Soft Delete — HER MODELDE ZORUNLU

Bu kural istisnasız her model için geçerlidir:

1. Migration'da `$table->softDeletes();` ekle
2. Model'de `use SoftDeletes;` trait ekle
3. Cascade delete gerekiyorsa Observer ile yönet (`cascadeOnDelete()` foreign key'de KULLANMA)
4. Observer'da `deleting` → ilişkileri `->delete()`, `restoring` → ilişkileri `->restore()`
5. `forceDelete()` SADECE açıkça istenirse kullan

```php
// Her model bu yapıda olacak
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'slug', 'body', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
```

## N+1 Query Önleme — KRİTİK

- İlişkili veri çekerken HER ZAMAN eager loading: `with(['author', 'tags'])`
- Sayı lazımsa `withCount('comments')` kullan
- `SELECT *` yerine sadece gerekli kolonlar: `select(['id', 'title', 'slug'])`
- Döngüde tekli query YASAK → bulk işlem kullan
- Büyük veri setlerinde `chunk(200)` veya `cursor()` kullan

## Veritabanı Kuralları

- Migration'da `down()` her zaman yazılacak
- Sık sorgulanan kolonlara index ekle
- Composite index gerekli yerlerde kullan
- Enum kolonu yerine string + PHP backed enum tercih et
- Bulk insert: `Model::insert()` veya `upsert()`
- `exists()` kullan, `count() > 0` değil

## Enum Kullanımı — ZORUNLU

Select, radio, checkbox gibi sabit seçenek listeleri ASLA statik/hardcoded yazılmayacak.
Her liste için `app/Enums/` altında ayrı PHP backed enum oluştur.
```php
// app/Enums/ExpenseType.php
enum ExpenseType: string
{
    case Fuel = 'fuel';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Fuel => 'Yakıt',
            self::Maintenance => 'Bakım',
        };
    }
}
```

Blade'de tekrar etmemek için enum-select component'i kullan:
```blade
{{-- resources/views/components/enum-select.blade.php --}}
@props(['name', 'enum', 'label', 'selected' => null])
<div class="mb-3">
    <label class="form-label">{{ $label }}</label>
    <select name="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
        <option value="">Seçiniz</option>
        @foreach($enum::cases() as $case)
            <option value="{{ $case->value }}" @selected(old($name, $selected) === $case->value)>
                {{ $case->label() }}
            </option>
        @endforeach
    </select>
    @error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- Kullanım --}}
<x-enum-select name="expense_type" :enum="App\Enums\ExpenseType::class" label="Gider Tipi" />
```

Kurallar:
- Her sabit liste → ayrı enum dosyası (`ExpenseType`, `ApprovalStatus`, `PaymentMethod`)
- Blade, controller, export, validation → her yerde enum'dan oku
- Hardcoded option/liste YASAK
- Yeni seçenek = sadece enum'a case ekle, başka dosyaya dokunma

## Mimari Yapı

```
app/
├── Http/Controllers/     ← İnce (max 5-7 method)
├── Http/Requests/        ← FormRequest validation
├── Http/Resources/       ← API Resource/Collection
├── Models/               ← Eloquent + SoftDeletes
├── Services/             ← İş mantığı BURADA
├── Enums/                ← PHP 8.3 Backed Enums
├── Observers/            ← Model event + cascade soft delete
├── Policies/             ← Authorization
├── Events/ & Listeners/
├── Jobs/
└── Exceptions/
```

## Güvenlik

- `@csrf` her formda, AJAX'ta `X-CSRF-TOKEN` header
- `{{ }}` kullan (escaped), `{!! !!}` sadece sanitize edilmiş içerikte
- `$fillable` her modelde tanımlı, `$guarded = []` YASAK
- Raw SQL string birleştirme YASAK → Eloquent veya prepared statement
- Hassas bilgiler `.env`'de, loglarda hassas veri YASAK
- Rate limiting hassas endpoint'lerde (login, API)
- Policy ile authorization

## Performans

- Cache: `Cache::remember()` sık erişilen veriler için
- Pagination: `paginate()` veya `cursorPaginate()`, `get()` ile tüm veriyi çekme
- Route/config/view cache: production'da `artisan optimize`
- Transaction: birden fazla DB işlemi `DB::transaction()` içinde

## Service Örneği

```php
final class ArticleService
{
    public function create(StoreArticleRequest $request): Article
    {
        return DB::transaction(function () use ($request): Article {
            $article = Article::create($request->safe()->except('tags'));
            if ($request->validated('tags')) {
                $article->tags()->sync($request->validated('tags'));
            }
            return $article->load(['author', 'tags']);
        });
    }
}
```
