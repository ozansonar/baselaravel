# MediaLibrary Modülü — Görsel Kütüphanesi

## Amaç

Otomatik blog cron'unun (ve ileride sosyal medya akışlarının) AI görsel
üretmek yerine **önceden hazırlanmış kütüphaneden** rastgele görsel seçmesi.
**Aylık AI maliyeti: 0 TL.**

## Çekirdek prensip

> Her görsel **bir ürüne** bağlıdır (veya "Genel" — ürünsüz).
> Cron blog yazarken hangi ürünü seçtiyse, o ürünün görsellerinden birini
> kullanır. Ürünün hiç görseli yoksa "Genel" havuzdan çeker. O da boşsa
> hardcoded placeholder (mevcut blog placeholder'ı).

**Tag sistemi YOK.** Doğrudan ürün-eşleşme. Karmaşa olmaz, kullanıcı için net.

## Ürün listesinin tek kaynağı

`AiPromptSetting.products` (admin → AI Prompt Settings → Ürün Listesi).
Cron zaten bu listeden seçiyor (örn. "Günlük Taze Süt", "Doğal Köy
Tereyağı", "Doğal Köy Peyniri"...).

MediaLibrary aynı listeyi okur. Yeni ürün ekleyince:
- AI cron otomatik o üründen yazı yazar
- MediaLibrary otomatik o ürün için yeni boş kart açar
- Sen görsel ekleyene kadar "Genel" kütüphane fallback olur

İki yerde ayrı ayrı tutmaya gerek yok — single source of truth.

## Cron akışı (yeni)

```
Cron blog üretti → AiLog.selected_product = "Günlük Taze Süt"
    ↓
BlogCoverService::pickCover():
    1. SELECT * FROM media_assets
       WHERE product_name = 'Günlük Taze Süt'
         AND is_active = 1
       ORDER BY last_used_at ASC NULLS FIRST, usage_count ASC
       LIMIT 5
       → top 5'ten 1 rastgele
    2. Boşsa: WHERE product_name IS NULL ("Genel" havuz)
    3. Boşsa: hardcoded placeholder (content/post-image.jpg)
    ↓
Seçilen görsel → blog_post.image
    ↓
media_asset.usage_count++, last_used_at = now()
```

**AI çağrısı yok.** Maliyet sıfır.

## Veritabanı şeması

```php
Schema::create('media_assets', function (Blueprint $table) {
    $table->id();
    $table->string('product_name', 191)->nullable()->index();  // null = "Genel"
    $table->string('title', 191)->nullable();                  // admin için açıklama
    $table->string('image_path', 255);                         // public/uploads/media-library/... (kare 1500×1500)
    $table->unsignedInteger('width')->nullable();              // doğrulama amaçlı (1500 olmalı)
    $table->unsignedInteger('height')->nullable();             // doğrulama amaçlı (1500 olmalı)
    $table->unsignedInteger('usage_count')->default(0);
    $table->timestamp('last_used_at')->nullable()->index();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['product_name', 'is_active']);
});
```

**Tablolar tek:** `media_assets`. Kategori tablosu yok, tag tablosu yok.

## Model — `App\Models\MediaAsset`

- SoftDeletes ✅
- `$fillable` tanımlı
- Scope: `active()`, `forProduct(string $name)`, `general()` (product_name = null)
- Method: `markUsed(): void` — usage_count++ + last_used_at = now()
- Relation: `creator()` BelongsTo User

## Service — `App\Services\MediaLibraryService`

```php
public function pickFor(?string $productName): ?MediaAsset
// 1. ürün-eşleşme; 2. Genel; 3. null

public function smartSelect(Builder $query): ?MediaAsset
// LRU + LFU + rastgele top-5'ten

public function uploadAsset(UploadedFile $file, ?string $productName,
                            ?string $title): MediaAsset
// DB::transaction ile atomik: UploadService::uploadImage + MediaAsset::create
// Cache invalidation: 'media_library.stats'

public function deleteAsset(MediaAsset $asset): void
// DB::transaction: UploadService::deleteImage + $asset->delete (soft)
// Cache invalidation

public function statsByProduct(): array
// Cache::remember('media_library.stats', 300, ...)
// ['Günlük Taze Süt' => 12, 'Doğal Köy Tereyağı' => 8, 'Genel' => 25, ...]
```

### `uploadAsset()` implementasyonu (CLAUDE.md uyumu)

```php
public function uploadAsset(
    UploadedFile $file,
    ?string $productName,
    ?string $title,
    ?int $userId = null,
): MediaAsset {
    return DB::transaction(function () use ($file, $productName, $title, $userId): MediaAsset {
        // 1. UploadService → public/uploads/media-library/ (WebP convert otomatik)
        $imagePath = $this->uploadService->uploadImage(
            $file,
            'media-library',
            'asset-' . now()->format('Ymd-His'),
        );

        // 2. Boyut probe (validation zaten kare doğruladı, ama kayıt için)
        $absolute = public_path('uploads/' . $imagePath);
        $info = @getimagesize($absolute);

        // 3. DB insert
        $asset = MediaAsset::create([
            'product_name' => $productName,
            'title'        => $title,
            'image_path'   => $imagePath,
            'width'        => $info ? (int) $info[0] : null,
            'height'       => $info ? (int) $info[1] : null,
            'created_by'   => $userId,
        ]);

        // 4. Stats cache invalidate
        Cache::forget('media_library.stats');

        return $asset;
    });
}

public function deleteAsset(MediaAsset $asset): void
{
    DB::transaction(function () use ($asset): void {
        if ($asset->image_path) {
            $this->uploadService->deleteImage($asset->image_path);
        }
        $asset->delete(); // soft delete

        Cache::forget('media_library.stats');
    });
}
```

**Önemli:** AiPromptSetting'den ürün listesini okur:
```php
public function availableProducts(): array
{
    $setting = app(AiPromptSettingService::class)->get();
    return $setting->products ?? [];
}
```

## BlogCoverImageService entegrasyonu

Mevcut `BlogCoverImageService::generateForBlog()` AI üretiyor. Refactor:

```php
public function generateForBlog(BlogPost $post, ?string $selectedProduct = null): array
{
    // 1. Önce kütüphaneden dene
    $asset = $this->mediaLibrary->pickFor($selectedProduct);
    if ($asset !== null) {
        $asset->markUsed();
        return [
            'success'    => true,
            'image_path' => $asset->image_path,
            'alt_text'   => $asset->title ?? $post->title,  // SEO — alt text
            'source'     => 'media-library',
            'asset_id'   => $asset->id,
        ];
    }

    // 2. Kütüphane boş + Genel boş → placeholder (AI çağrısı YOK)
    return [
        'success'    => true,
        'image_path' => 'content/post-image.jpg',
        'alt_text'   => $post->title,                       // fallback alt text
        'source'     => 'placeholder',
    ];
}
```

**SEO — alt text:** `MediaAsset.title` doluysa o kullanılır (örn. "Köy
tereyağı sabah ışığında — kahvaltı sahnesi"); boşsa blog başlığı fallback.
Blog show view zaten `<x-responsive-image :alt="...">` component'ini
çağırıyor, alt değeri otomatik geçer. Görsel olarak `loading="lazy"` ve
`class="img-fluid"` zaten responsive-image component'inde var (CLAUDE.md
"Görseller" kuralı).

`BlogGenerationService` çağırırken `AiLog.selected_product` değerini geçirir.

**Eski AI üretim akışı tamamen kaldırılır mı?** Hayır, mevcut
`BlogCoverImageService` (Imagen/Gemini ile üretim) korunur ama varsayılan
çağrılmaz. Admin paneline yeni setting eklenir:
- `blog_cover_source = 'media_library' | 'ai' | 'media_library_then_ai'`
- Default: `media_library` (sıfır maliyet)
- Power-user için `ai` veya hibrit seçenekler

## Admin UI

### `/admin/media-library` (ana sayfa — ürün grid)

```
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ 🥛 Günlük Taze   │ │ 🧈 Doğal Köy     │ │ 🧀 Doğal Köy     │
│    Süt           │ │    Tereyağı      │ │    Peyniri       │
│ 12 görsel        │ │ 8 görsel         │ │ 0 görsel ⚠️      │
│ [thumb thumb...] │ │ [thumb thumb...] │ │ Görsel ekle →    │
└──────────────────┘ └──────────────────┘ └──────────────────┘

┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ 🍶 Doğal Köy     │ │ 🧀 Doğal Çökelek │ │ 🥚 Gezen Tavuk   │
│    Yoğurdu       │ │ 5 görsel         │ │    Yumurtası     │
│ 6 görsel         │ │                  │ │ 3 görsel         │
└──────────────────┘ └──────────────────┘ └──────────────────┘

┌──────────────────┐
│ 🌾 Genel         │ ← her zaman görünür, ürünsüz/genel görseller
│ 25 görsel        │
└──────────────────┘

[+ Görsel Ekle (toplu)]
```

Üstte stat: "Toplam: 59 görsel · 7 ürün · Bu ay kullanılan: 12 / 47 boşta"

### `/admin/media-library/{slug}` (ürün detay)

`{slug}` = ürün adının slug'ı (örn. `gunluk-taze-sut`) veya `genel`.

```
┌─────────────────────────────────────────┐
│ ← Geri | 🥛 Günlük Taze Süt — 12 görsel │
├─────────────────────────────────────────┤
│ [+ Yeni Görsel Ekle]  [Toplu Yükle]     │
├─────────────────────────────────────────┤
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐             │
│ │img │ │img │ │img │ │img │             │
│ │12× │ │ 8× │ │ 0× │ │ 5× │ ← kullanım  │
│ └────┘ └────┘ └────┘ └────┘             │
│ ...                                     │
└─────────────────────────────────────────┘
```

Görsel kart üzerinde: thumb + kullanım sayısı + son kullanım tarihi +
"sil" + "düzenle" + "başka ürüne taşı".

### Görsel ekleme formu (Dropzone.js — çoklu yükleme)

50+ görseli tek tek seçmek külfetli. **Dropzone.js** ile drag-and-drop +
asenkron paralel yükleme.

```
┌──────────────────────────────────────────────────────────┐
│ Hangi ürün için yükleyeceksin?                           │
│   ┌───────────────────────────────────────────────────┐  │
│   │  Doğal Köy Tereyağı                            ▼  │  │
│   └───────────────────────────────────────────────────┘  │
│   (Dropdown: AiPromptSetting.products + "Genel")         │
│                                                          │
│   ╔════════════════════════════════════════════════════╗ │
│   ║                                                    ║ │
│   ║   📁 Görselleri buraya sürükle veya tıkla          ║ │
│   ║                                                    ║ │
│   ║   Kabul: JPG / PNG / WebP                          ║ │
│   ║   Boyut: 1:1 kare (min 1024×1024, önerilen 1500)   ║ │
│   ║   Dosya başına max: 4 MB                           ║ │
│   ║   Max dosya: 50 (tek seferde)                      ║ │
│   ║                                                    ║ │
│   ╚════════════════════════════════════════════════════╝ │
│                                                          │
│   Yüklenecekler:                                         │
│   ┌─────────┬─────────┬─────────┬─────────┐              │
│   │ thumb   │ thumb   │ thumb   │ thumb   │              │
│   │ ▓▓▓▓ %  │ ▓▓▓▓▓▓▓ │ ✓ Tamam │ ❌ Hata │              │
│   │ Yükle.. │ %85     │         │ Kare değil│            │
│   └─────────┴─────────┴─────────┴─────────┘              │
│                                                          │
│   [Tümünü Kaldır]  [Tümünü Yükle]  [Galeri'ye Dön]       │
└──────────────────────────────────────────────────────────┘
```

### Frontend — Dropzone.js bağımlılığı

CDN'den, vanilla JS (jQuery yok — CLAUDE.md uyumlu):

```html
<!-- admin layout footer'a eklenir, sadece bu sayfada yüklenir -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/dropzone@6.0.0-beta.2/dist/dropzone.min.css">
<script src="https://cdn.jsdelivr.net/npm/dropzone@6.0.0-beta.2/dist/dropzone-min.js"
        defer></script>
```

veya self-host (`public/assets/admin/vendor/dropzone/` altına indirip kullan
— CDN bağımlılığı olmadan). PR'da self-host tercih edelim, daha güvenilir.

### Init JS — `public/assets/admin/js/media-library-upload.js`

```js
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const dz = document.querySelector('#mediaLibraryDropzone');
    const productSelect = document.querySelector('#mediaProductSelect');
    if (! dz || typeof Dropzone === 'undefined') return;

    Dropzone.autoDiscover = false;

    const dropzone = new Dropzone(dz, {
        url: dz.dataset.uploadUrl,           // /admin/media-library/upload
        method: 'POST',
        paramName: 'image',
        maxFilesize: 4,                       // MB
        acceptedFiles: 'image/jpeg,image/png,image/webp',
        maxFiles: 50,
        parallelUploads: 4,                   // 4 paralel istek (server'ı zorlamamak)
        autoProcessQueue: false,              // "Tümünü Yükle" tıklanınca başlar
        addRemoveLinks: true,
        timeout: 60000,                       // 60 sn / dosya
        dictDefaultMessage: 'Görselleri buraya sürükle veya tıkla',
        dictRemoveFile: 'Kaldır',
        dictCancelUpload: 'İptal',
        dictMaxFilesExceeded: 'En fazla 50 dosya yükleyebilirsin.',
        dictInvalidFileType: 'Sadece JPG / PNG / WebP kabul edilir.',
        dictFileTooBig: 'Dosya çok büyük (max 4 MB).',
        dictResponseError: 'Sunucu hatası: {{statusCode}}',

        // Her POST'a ek meta gönder
        sending: (file, xhr, formData) => {
            formData.append('product_name', productSelect.value);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        },

        // Client-side kare doğrulama (sunucu da doğrular ama UX hızlansın)
        accept: (file, done) => {
            const img = new Image();
            img.onload = () => {
                if (img.width !== img.height) {
                    done('Kare değil — 1:1 oran zorunlu');
                } else if (img.width < 1024) {
                    done('Çok küçük — min 1024×1024');
                } else {
                    done();
                }
            };
            img.onerror = () => done('Görsel okunamadı');
            img.src = URL.createObjectURL(file);
        },

        success: (file, response) => {
            // response: { success: true, asset_id, image_url, thumbnail_url }
            file.previewElement.classList.add('dz-success');
        },

        error: (file, message) => {
            // message string veya object olabilir
            const msg = typeof message === 'string' ? message : (message.message || 'Hata');
            const errorEl = file.previewElement.querySelector('[data-dz-errormessage]');
            if (errorEl) errorEl.textContent = msg;
        },

        queuecomplete: () => {
            // Tüm yüklemeler bittikten sonra galeri sayfasına yönlendir
            const successCount = dropzone.getFilesWithStatus(Dropzone.SUCCESS).length;
            const errorCount = dropzone.getFilesWithStatus(Dropzone.ERROR).length;

            if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({
                    title: 'Yükleme tamamlandı',
                    message: `${successCount} başarılı, ${errorCount} başarısız.`,
                    type: errorCount === 0 ? 'success' : 'warning',
                });
            }
        },
    });

    // "Tümünü Yükle" butonu
    document.querySelector('#startUploadBtn')?.addEventListener('click', () => {
        dropzone.processQueue();
    });

    // "Tümünü Kaldır"
    document.querySelector('#removeAllBtn')?.addEventListener('click', () => {
        dropzone.removeAllFiles(true);
    });

    // Ürün seçimi değişirse kuyruktaki dosyaları temizle
    // (yanlış ürüne yüklenmesin)
    productSelect?.addEventListener('change', () => {
        if (dropzone.files.length > 0) {
            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Ürün değiştirildi',
                    message: 'Kuyruktaki dosyalar temizlensin mi? Yeni ürüne yükleneceksen "Evet".',
                    type: 'warning',
                }).then(confirmed => {
                    if (confirmed) dropzone.removeAllFiles(true);
                });
            }
        }
    });
});
```

### Backend — controller tek dosyalı upload

`MediaLibraryController::upload()` her POST'ta TEK dosya işler (Dropzone
paralel olarak 4 istek atıyor). Bu sayede:
- PHP `post_max_size` / `upload_max_filesize` başına dosya limiti kafi (4 MB)
- Tek istek fail ederse diğerleri devam eder
- Frontend'de her dosya bağımsız progress

```php
public function upload(UploadMediaAssetRequest $request): JsonResponse
{
    $asset = $this->mediaLibrary->uploadAsset(
        file:         $request->file('image'),
        productName:  $request->filled('product_name') && $request->input('product_name') !== 'Genel'
                          ? $request->input('product_name')
                          : null,
        title:        $request->input('title'),
    );

    return response()->json([
        'success'        => true,
        'asset_id'       => $asset->id,
        'image_url'      => upload_url($asset->image_path),
        'thumbnail_url'  => upload_url($asset->image_path, 'thumb'),
    ]);
}
```

### Validation — `UploadMediaAssetRequest`

```php
public function rules(): array
{
    return [
        'image' => [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:4096',
            'dimensions:min_width=1024,min_height=1024,ratio=1/1',
        ],
        'product_name' => ['nullable', 'string', 'max:191'],
        'title'        => ['nullable', 'string', 'max:191'],
    ];
}

public function messages(): array
{
    return [
        'image.required'    => 'Görsel yüklemelisiniz.',
        'image.image'       => 'Yüklenen dosya bir görsel olmalı.',
        'image.mimes'       => 'Sadece JPG, PNG veya WebP yüklenebilir.',
        'image.max'         => 'Görsel en fazla 4 MB olabilir.',
        'image.dimensions'  => 'Görsel kare olmalı (1:1 oran), min 1024×1024.',
    ];
}
```

### Sunucu yan paralel yükleme — concurrency

Dropzone `parallelUploads: 4` → aynı anda max 4 istek. 50 dosya yüklersen:
- 4 paralel × 12.5 batch ≈ 12-13 turda biter
- Her dosya ortalama 2-3 sn (4 MB upload + WebP convert + DB insert)
- Toplam: ~30-40 sn / 50 dosya

Server tarafında:
- Aynı ürüne aynı anda yazma yarışı yok (her dosya unique slug + ID)
- Race condition riski sıfır

### Hatalı dosya işleme

Bir dosya fail ederse (kare değil, çok büyük, server hatası):
- Dropzone preview'da kırmızı X ikonu + hata mesajı görünür
- Dosya kuyrukta kalır (kullanıcı görür ama eklenmedi)
- "Hatalıları Temizle" butonu sadece error'ları siler
- Diğer dosyalar etkilenmez, devam eder

### Görsel ekleme formu HTML iskeleti (Blade)

```blade
<form id="mediaLibraryUploadForm">
    <div class="stg-field">
        <label for="mediaProductSelect" class="stg-label">
            <i class="bi bi-box-seam me-2"></i>Hangi ürün için?
        </label>
        <select id="mediaProductSelect" class="stg-input" name="product_name">
            <option value="Genel">🌾 Genel (üründen bağımsız — fallback)</option>
            @foreach($products as $product)
                <option value="{{ $product }}" {{ old('product_name') === $product ? 'selected' : '' }}>
                    {{ $product }}
                </option>
            @endforeach
        </select>
    </div>

    <div id="mediaLibraryDropzone"
         class="dropzone media-library-dropzone"
         data-upload-url="{{ route('admin.media-library.upload') }}">
        {{-- Dropzone JS bunu auto-init eder --}}
    </div>

    <div class="d-flex gap-2 mt-3 justify-content-end">
        <button type="button" id="removeAllBtn" class="btn-glass">
            <i class="bi bi-x-circle me-1"></i> Tümünü Kaldır
        </button>
        <button type="button" id="startUploadBtn" class="btn-teal">
            <i class="bi bi-cloud-upload me-1"></i> Tümünü Yükle
        </button>
    </div>
</form>
```

### Self-host vs CDN

**Tercih: self-host** — `public/assets/admin/vendor/dropzone/`
- `dropzone.min.css` (~5 KB)
- `dropzone-min.js` (~40 KB)
- Toplam: ~45 KB ek statik
- CDN olmaması = offline ortamda da çalışır, gizlilik ihlali yok

PR'ın bir commit'inde bu dosyalar `public/assets/admin/vendor/dropzone/`
altına yerleştirilir, layout'ta `@stack('scripts')` üzerinden çağrılır.

### CLAUDE.md uyumu

- ✅ jQuery yok (Dropzone.js vanilla)
- ✅ Vite/npm/Webpack yok (CDN'den self-host)
- ✅ Inline style yok (CSS class'ları)
- ✅ AdminModal.confirm/status kullanılıyor (alert/confirm yasak)
- ✅ CSRF token meta'dan alınıyor

## Routes

```php
Route::prefix('media-library')->name('media-library.')->group(function () {
    Route::get('/',                  [MediaLibraryController::class, 'index'])->name('index');
    Route::get('/{productSlug}',     [MediaLibraryController::class, 'show'])->name('show');
    Route::post('/upload',           [MediaLibraryController::class, 'upload'])->name('upload');
    Route::patch('/{asset}',         [MediaLibraryController::class, 'update'])->name('update');
    Route::delete('/{asset}',        [MediaLibraryController::class, 'destroy'])->name('destroy');
});
```

## Sidebar

"AI Görsel Logları"'nın altına: **"Görsel Kütüphanesi"** menü öğesi.

## Settings

Yeni setting'ler:
- `blog_cover_source` (default: `media_library`)
- `media_library_general_fallback_enabled` (default: `1`)

Mevcut `blog_auto_cover_image` ve `blog_cover_image_verify` korunur ama
yalnızca `blog_cover_source = 'ai'` modunda anlamlı.

## Maliyet karşılaştırma

| Yaklaşım | AI çağrısı | Aylık AI maliyeti (120 blog) |
|---|---|---|
| Mevcut (her blog için AI) | 1/blog | ~145 TL |
| **MediaLibrary (önerilen)** | **0** | **0 TL** |
| Hibrit (kütüphane → AI fallback) | 0 (kütüphane doluyken) | ~10-30 TL |

## İlk emek (bir defalık)

Sen Nano Banana'dan görsel ürettir + tarayıcıdan indir + admin panelden yükle:
- 7 ürün × ortalama 30 görsel = 210 görsel
- "Genel" havuz: ~50 görsel
- Toplam: ~260 görsel
- Tahmini emek: 5-10 saat (bir defa)
- Sonra: sıfır iş

## Akıllı seçim (LRU + LFU)

70 görselin var. Hep aynı 5'i dönmesin diye:

```sql
SELECT * FROM media_assets
WHERE (product_name = ? OR product_name IS NULL)
  AND is_active = 1
  AND deleted_at IS NULL
ORDER BY
    last_used_at ASC NULLS FIRST,  -- önce hiç kullanılmamış
    usage_count ASC                 -- sonra az kullanılmış
LIMIT 5

-- top 5'ten PHP tarafında rastgele 1 tane
```

Bu sayede 70 görselin **eşit dönüşümlü** kullanılır.

## CSS class prefix sistemi (admin-panel skill kuralı)

Mevcut admin tema 2-4 harfli prefix kullanıyor (`cl-`, `usr-`, `stg-`, `gint-`,
`ail-`). Tutarlılık için MediaLibrary tüm class'ları **`mlib-`** prefix
kullanır:

| Class | Kullanım |
|---|---|
| `mlib-product-grid` | Ana sayfa ürün grid container |
| `mlib-product-card` | Tek ürün kartı (ürün adı + sayı + thumb'lar) |
| `mlib-product-card__title` | BEM modifier — ürün adı |
| `mlib-product-card__count` | BEM modifier — görsel sayısı |
| `mlib-product-card--empty` | BEM state — 0 görsel |
| `mlib-asset-grid` | Görsel detay sayfasındaki grid |
| `mlib-asset-tile` | Tek görsel kartı |
| `mlib-asset-tile__usage` | Kullanım sayısı badge |
| `mlib-dropzone` | Dropzone.js ana alanı |
| `mlib-dropzone__hint` | Yükleme talimat metni |
| `mlib-stat-card` | Üst stat panel |
| `mlib-empty-state` | Boş galeri mesajı |
| `mlib-product-select` | Yükleme formundaki ürün dropdown'ı |

BEM yaklaşımı ([CLAUDE.md](http://CLAUDE.md) kuralı): `block__element--modifier`.

CSS dosyası: `public/assets/admin/css/styles.css`'in **sonuna** eklenir
(diğer modüllerin yanına). Inline style YOK.

## Dosya ayrımı (CLAUDE.md zorunluluğu)

Front ve admin dosyaları **tamamen ayrı** — aynı dosya iki taraf için kullanılmaz.

| Yer | Tam yol |
|---|---|
| Admin CSS — Dropzone vendor | `public/assets/admin/vendor/dropzone/dropzone.min.css` |
| Admin CSS — kendi class'larımız (`mlib-*`) | `public/assets/admin/css/styles.css` (sonuna eklenir) |
| Admin JS — Dropzone vendor | `public/assets/admin/vendor/dropzone/dropzone-min.js` |
| Admin JS — init kodumuz | `public/assets/admin/js/media-library-upload.js` |
| **Front CSS** — blog kareye uyarlama | `public/css/app.css` (mevcut, yalnızca `.blog-detail-image` + `.blog-card-image`) |
| Front JS | Değişmez |

**Admin layout** içinde `@stack('scripts')` üzerinden:
```blade
@push('scripts')
<link rel="stylesheet" href="{{ asset('assets/admin/vendor/dropzone/dropzone.min.css') }}">
<script src="{{ asset('assets/admin/vendor/dropzone/dropzone-min.js') }}" defer></script>
<script src="{{ asset('assets/admin/js/media-library-upload.js') }}" defer></script>
@endpush
```

JS body sonunda yüklenir (CLAUDE.md "JS body sonunda" kuralı) — `defer`
attribute'u sayesinde HTML parse'ı bloklamaz.

## SEO meta — admin sayfası

Mevcut admin layout standartı:
```blade
@extends('layouts.admin')
@section('title', 'Görsel Kütüphanesi')
@section('page_title', 'Görsel Kütüphanesi')
@section('page_description', 'Blog ve sosyal medya için ürün-bazlı görsel kütüphanesi')
```

Breadcrumb: Ana Sayfa › Görsel Kütüphanesi › {Ürün adı}

## Performans notları (CLAUDE.md "Performans" kuralı)

- **`exists()` not `count()`:** placeholder fallback kontrolünde
  `MediaAsset::where(...)->exists()` kullanılır
- **`chunk(100)`:** ileride bulk işlemlerde (toplu silme, toplu taşıma vb.)
  kullanılmalı; bu PR'da bulk operation yok ama not düşülüyor
- **Eager loading:** `with('creator')` controller index'te
- **Pagination:** ürün detay sayfasında 24'lü pagination
- **`Cache::remember`:** statsByProduct 5 dk cache, upload/delete'te invalidate

## Authorization

Mevcut admin sayfaları middleware (`admin` group) ile korunuyor — ek Policy
yok, mevcut tarzla tutarlı kalmak için MediaLibrary için de **explicit
Policy YAZILMIYOR** (mevcut PR'larla uyumlu). Gelecekte multi-role admin
gerektiğinde Policy ayrı bir PR'da eklenebilir.

## Tek format kararı — **Kare 1500×1500**

Kullanıcı kararı: **Tüm görseller tek bir formatta — 1:1 kare 1500×1500.**
Otomatik varyant üretimi YOK, multi-aspect YOK. Aynı dosya hem blog'da hem
Instagram Feed'de hem Facebook'ta kullanılır.

### Neden tek kare format?

| Kullanım | Kare ile durum |
|---|---|
| Instagram Feed | ✅ Native — kare zaten Instagram'ın orijinal formatı |
| Blog (web) | ✅ CSS güncellemesiyle (aspect-ratio: 1/1) modern görünüm |
| Facebook page post | ⚠️ Link preview küçük thumbnail, ama kabul edilebilir |

**Avantajlar:**
- Sıfır crop kaybı (görsel olduğu gibi gösterilir)
- Tek dosya, tek master
- AI üretim 1 kez yeter (Nano Banana'da ücretsiz tier'da bedava)
- Manuel emek yarı yarıya azalır
- Sistem karmaşıklığı sıfır

### Yükleme validasyonu

`UploadMediaAssetRequest` zorunluluğu:
```php
'image' => [
    'required', 'image', 'mimes:jpg,jpeg,png,webp',
    'max:4096',                                    // 4 MB
    'dimensions:min_width=1024,min_height=1024,'
        . 'ratio=1/1',                             // KARE ZORUNLU
],
```

Kullanıcı 1500×1500 yerine 1024×1024 yüklerse de geçer (min 1024 — Nano Banana
free tier varsayılan). Tavsiye 1500×1500. Form'da bilgi notu:
**"Önerilen: 1500×1500 kare. Min 1024×1024."**

### Web template güncellemesi (PR'a dahil)

`public/css/app.css` küçük revizyon:
```css
.blog-detail-image {
    aspect-ratio: 1/1;        /* eski: 16/9 */
    max-width: 600px;          /* yeni: kare ezici büyüklükte olmasın */
    margin-left: auto;
    margin-right: auto;
    border-radius: 25px;
    overflow: hidden;
}
.blog-card-image {
    aspect-ratio: 1/1;        /* eski: height: 220px */
    height: auto;
}
```

Bu 5-10 satırlık CSS değişikliği PR'ın son commit'inde yapılır.

---

## AI Prompt Template'leri (Nano Banana için)

Bu prompt'ları **tarayıcıdan Nano Banana**'ya kopyala-yapıştır kullan. Her ürün
için 30-50 farklı varyant ürettir, beğendiklerini admin panele yükle.

### Genel format kuralları (HER prompt'a ekle)

```
KARE FORMAT, 1:1 oranı, 1500×1500 piksel.
Konu MERKEZ %50'de yer alsın (kenar dolgusu için boşluk bırak).
Üst ve alt %25'lik alan dekoratif (yumuşak bokeh, sahne genişlemesi).
KESİNLİKLE YAZMA: text, harfler, rakamlar, başlıklar, watermark,
URL, telefon, hashtag, placeholder ([NUMARANIZ] vb.), buton, rozet.
Saf görsel — yazısız, logosuz, temiz arka plan üretici.
Stil: rustik Anadolu çiftliği, doğal gün ışığı, sıcak toprak tonları,
shallow depth of field, food photography, fotojenik ama doğal.
```

### 1. Günlük Taze Süt

```
Kare 1500×1500 görsel. Cam süt şişesi (eski tip, kapaksız veya bez bağlı),
içinde taze köy sütü. Rustik ahşap masa üstünde. Yanında bir kase yulaf,
ya da tarçınlı süt köpüğü, ya da sade. Arka plan: yumuşak bulanık çiftlik
manzarası veya ahşap raf, ya da pencereden gelen sabah ışığı.

Varyasyon önerileri (50 ürettirip seç):
- Kahvaltı sahnesi içinde süt
- Tek başına şişe close-up
- Sürahide süt + bardak
- Sütçü bidonu + cam bardak
- Süt sağma sahnesi (uzaktan, soyut)
- Kahve/çay yanında süt servisi

[Genel format kurallarını ekle ↑]
```

### 2. Doğal Köy Tereyağı

```
Kare 1500×1500 görsel. Köy tereyağı bloğu — sarı, doğal, ev yapımı görünüm.
Ahşap kesme tahtası veya rustik tabak üstünde. Yanında ahşap bıçak
(ya da gümüş peynir bıçağı), bal kavanozu, taze ekmek, kekik dalı.
Arka plan: rustik mutfak, ahşap masa, yumuşak ışık.

Varyasyon önerileri:
- Tereyağı bloğu + bıçak (close-up)
- Ekmek üstüne sürülmüş tereyağı
- Kahvaltı sofrasında tereyağı tabağı
- Tereyağı yapım sahnesi (yayık, soyut)
- Kavanozda tereyağı + sıcak ekmek

[Genel format kurallarını ekle ↑]
```

### 3. Doğal Köy Peyniri

```
Kare 1500×1500 görsel. Beyaz peynir veya kaşar (orta sertlik) — köy yapımı,
doğal sarımsı/beyaz renk. Ahşap kesme tahtası üstünde dilimlenmiş halde.
Yanında zeytin, taze nane/kekik, domates, biber, taze ekmek.
Arka plan: kahvaltı sofrası, rustik mutfak, doğal pencere ışığı.

Varyasyon önerileri:
- Peynir blok + bıçak
- Dilimlenmiş peynir + zeytin
- Peynir tabağı + meze düzeni
- Peynir + ekmek + çay
- Peynir yapım sahnesi (telleme, soyut)
- Salata içinde peynir

[Genel format kurallarını ekle ↑]
```

### 4. Doğal Köy Yoğurdu

```
Kare 1500×1500 görsel. Çanak/seramik tabakta köy yoğurdu — kremamsı yüzey,
doğal süt yağ izi. Üstünde ahşap kaşık. Yanında bal kavanozu, ceviz,
taze meyve (çilek, böğürtlen) veya kuru meyve.
Arka plan: rustik mutfak, çiftlik atmosferi, yumuşak gün ışığı.

Varyasyon önerileri:
- Sade yoğurt + ahşap kaşık close-up
- Yoğurt + bal + ceviz (kahvaltı)
- Yoğurt + meyve (sağlıklı kahvaltı)
- Yoğurt çorbası (içi yoğurt)
- Yoğurt yapımı (mayalama, soyut)
- Cacık servisi

[Genel format kurallarını ekle ↑]
```

### 5. Doğal Çökelek

```
Kare 1500×1500 görsel. Çökelek peyniri — beyaz, dağılgan tekstür, köy
yapımı görünüm. Seramik tabakta veya ahşap kase içinde. Üzerinde taze
nane veya kekik dalları, yanında zeytinyağı şişesi, taze sebze.
Arka plan: rustik kahvaltı sofrası, ev yapımı atmosfer.

Varyasyon önerileri:
- Çökelek tabağı + sebze + zeytinyağı
- Çökelek + ekmek + kahvaltı
- Çökelek + roka/maydanoz salatası
- Çökelek yapım sahnesi (süzme, soyut)
- Çökelek kavanozu

[Genel format kurallarını ekle ↑]
```

### 6. Gezen Tavuk Yumurtası

```
Kare 1500×1500 görsel. Köy yumurtası — kahverengi/krem renk, doğal kabuk
dokusu. Hasır sepet veya seramik tabakta. Yanında taze yulaf, saman,
veya tarla çiçekleri. Bazılarında kırılmış yumurta sarısı görünür.
Arka plan: çiftlik avlusu, kümes (uzaktan), doğal toprak tonları.

Varyasyon önerileri:
- Sepet içinde yumurta + saman
- Tek yumurta close-up (saman zemin)
- Sahanda yumurta (kahvaltı)
- Çırpılmış yumurta (omlet)
- Yumurta + ekmek + tereyağı
- Tavuk + yumurta sahnesi (uzaktan, soyut)

[Genel format kurallarını ekle ↑]
```

### "Genel" havuz için (ürün-bağımsız)

Bu havuz cron'un ürünü bulamadığı durumlarda fallback. Genel çiftlik/köy
estetiği:

```
Kare 1500×1500 görsel. Anadolu çiftliği genel sahnesi.
Konular (her prompt'ta tek bir konu seç):
- İnek/koyun otlatma manzarası (uzaktan)
- Buğday tarlası, tahıl başakları
- Köy evi (taş duvar, ahşap kapı)
- Ahşap çiftlik araç-gereci (yayık, sepet, çapa)
- Sebze bahçesi
- Tarla çiçekleri (papatya, gelincik)
- Köy avlusu, tavuklar
- Saman balyaları, tarla
- Mevsim sahneleri (bahar yeşilliği, yaz hasadı, sonbahar yaprakları,
  kış karı)

[Genel format kurallarını ekle ↑]
```

### Pro tip: Toplu üretim stratejisi

Tarayıcıdan Nano Banana ile çalışırken:
1. Bir promptu kopyala, varyasyon önerilerinden bir tanesini ekle
2. Aynı promptla 4-8 görsel ürettir (Nano Banana batch desteği var)
3. En iyi 2-3'ünü indir
4. Bir sonraki varyasyona geç
5. Her ürün için ~30-50 görsel hedefle
6. Hepsini admin panele yükle

Yaklaşık emek/ürün:
- 30 görsel için ~30-45 dakika (üretim + indirme + yükleme)
- 7 ürün × 40 dakika = ~5 saat toplam
- Bir defalık iş, sonra cron çalışır

## CLAUDE.md uyumu

- ✅ `declare(strict_types=1)` her PHP dosyasında
- ✅ SoftDeletes, `$fillable` (no `$guarded = []`)
- ✅ PHP 8.3 typed properties, readonly, match, null safe
- ✅ Service iş mantığı, controller thin
- ✅ FormRequest validation (UploadMediaAssetRequest, UpdateMediaAssetRequest)
- ✅ `DB::transaction` (uploadAsset + deleteAsset atomik)
- ✅ Türkçe iletişim, İngilizce kod/identifiers
- ✅ Inline style yasak — `mlib-*` BEM class'ları
- ✅ jQuery / Vite / npm / Webpack YOK (Dropzone.js vanilla, self-host)
- ✅ Bootstrap 5.3.8 utility-first
- ✅ Migration `down()`, index'ler
- ✅ AdminModal sil/onay (alert/confirm/prompt yasak)
- ✅ N+1 yok (eager: `with('creator')`)
- ✅ `Cache::remember` (statsByProduct 5 dk) + invalidation
- ✅ `exists()` not `count()` (placeholder fallback kontrolünde)
- ✅ Pagination (asset grid 24'lü)
- ✅ CSRF her formda + AJAX'ta (Dropzone meta'dan token alıyor)
- ✅ `{{ }}` escaped output
- ✅ `public/uploads/` + UploadService (asset()/storage:// YASAK)
- ✅ WebP convert + responsive variants (UploadService default)
- ✅ Görseller: `loading="lazy"` + `img-fluid` (responsive-image component)
- ✅ JS body sonunda (`defer` + `@stack('scripts')`)
- ✅ SEO: title, page_title, breadcrumb, alt text otomatik atama
- ✅ Front vs admin dosya ayrımı (CSS ve JS tamamen ayrı yollar)
- ✅ Admin tema kullanımı (`mlib-*` prefix mevcut `cl-`, `usr-` vb. paralelinde)
- ✅ Türkçe commit mesajları `[feat]:`, `[fix]:`

## Commit stratejisi (parça parça)

8 commit:

1. **Migration + MediaAsset modeli** → tablo + model
2. **MediaLibraryService** → pickFor, smartSelect, uploadAsset, statsByProduct
3. **MediaLibraryController + Routes + FormRequest** → CRUD endpoint'leri
4. **Dropzone.js self-host + media-library-upload.js** → vendor dosyaları + init JS
5. **Index view (ürün grid)** → ana sayfa, stat
6. **Show view + upload form (Dropzone entegrasyonu)** → CRUD UI
7. **BlogCoverImageService entegrasyon + web template (CSS aspect-ratio 1/1)** → kütüphane öncelik, blog_cover_source setting, blog kapak kareye uyarlama
8. **Sidebar nav + kullanıcı rehberi** → docs/media-library-kullanici-rehberi.md

Her commit kendi başına çalışır halde olacak — review kolay olsun.

## Test planı (canlıda)

```bash
php artisan migrate
```

1. `/admin/media-library` → 7 ürün kartı + "Genel" görünmeli (hepsi 0 görsel)
2. "Doğal Köy Tereyağı" kartına tıkla → boş galeri
3. "Yeni Görsel Ekle" → dropdown'da AI prompt settings'teki ürünler + "Genel"
4. Çoklu görsel yükle (3-5 adet) → galeride görünmeli, kullanım 0
5. Cron'u manuel tetikle: `php artisan schedule:run` (veya `php artisan blog:generate`)
6. Cron "Doğal Köy Tereyağı" seçtiyse → kütüphaneden bir görsel kapak olarak atanmalı
7. AiLog'da `selected_product = "Doğal Köy Tereyağı"`, BlogPost.image = kütüphaneden gelen görsel
8. MediaAsset kaydında usage_count=1, last_used_at güncel
9. Tekrar cron çalıştır → SAME ürün için → farklı görsel seçilmeli (LRU)
10. "Doğal Köy Tereyağı"nın tüm görsellerini sil → cron çalıştır → "Genel" havuzdan seçmeli
11. "Genel" de boşsa → placeholder kullanmalı (hata yok)

## Sonraki adımlar (PR sonrası)

- (Tek kare format kararı sonrası multi-aspect ihtiyacı kalktı)
- ZIP toplu import/export
- Kullanım analitiği dashboard
- Yetersiz görsel uyarısı ("Doğal Çökelek için sadece 2 görsel var, en az 10 öneririz")
- Sosyal medya entegrasyonu (Instagram/Facebook auto-publish — ayrı PR)
