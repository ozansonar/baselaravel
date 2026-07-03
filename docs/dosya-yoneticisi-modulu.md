# Dosya Yöneticisi (FileManager) Modülü

## Amaç

Admin'in **herhangi bir dosyayı** (PDF, Word, Excel, görsel, video, ZIP vb.)
yükleyip **public URL**'sini alabileceği WordPress-Media-Library benzeri
genel dosya yöneticisi.

Kullanım: blog yazılarında PDF link verme, sayfalarda Excel paylaşma,
sertifika/broşür linkleme, görsel galerisi vb.

## Mevcut sistemlerden farkı

| Modül | Kapsam | Hedef |
|---|---|---|
| **MediaLibrary** | Ürün-bazlı kare görseller | Cron blog kapağı için otomatik seçim |
| **UploadService** | Backend upload helper'ı | Programatik kullanım (servis katmanı) |
| **FileManager (yeni)** | Genel dosya yönetimi | Admin elle yükleyip linkliyeceği her tür dosya |

İsim çakışması olmasın diye: `media-library` → ürün görsel kütüphanesi,
`file-manager` → genel dosya kütüphanesi.

## Kullanım senaryoları

1. **Blog yazısında PDF link verme:**
   ```
   Tarif kitapçığını [buradan indirebilirsiniz](https://site.com/uploads/files/tarif-...pdf)
   ```
2. **Excel/CSV paylaşma:** "Aylık fiyat listesi" linki
3. **Word belgesi:** "Üretici sertifikası" PDF
4. **Görsel galerisi:** blog yazısının içine inline görsel
5. **Video/audio:** röportaj kaydı
6. **ZIP:** çoklu kaynak dosyası

## Veritabanı şeması

```php
Schema::create('uploaded_files', function (Blueprint $table) {
    $table->id();
    $table->string('original_name', 255);            // "tarif-kitapcigi.pdf"
    $table->string('stored_path', 255);              // "files/tarif-kitapcigi-a1b2c3d4.pdf"
    $table->string('mime_type', 100);                // "application/pdf"
    $table->string('extension', 10);                 // "pdf"
    $table->unsignedBigInteger('file_size');         // bytes
    $table->string('category', 30)->index();         // 'image' | 'document' | 'video' | 'audio' | 'archive' | 'other'
    $table->string('title', 191)->nullable();        // admin için açıklama
    $table->text('alt_text')->nullable();            // SEO/accessibility (görseller için)
    $table->string('hash', 64)->nullable()->index(); // duplicate kontrolü (md5/sha)
    $table->unsignedInteger('width')->nullable();    // sadece görseller için
    $table->unsignedInteger('height')->nullable();
    $table->unsignedInteger('download_count')->default(0); // analytics
    $table->boolean('is_public')->default(true);     // ileride private dosyalar için
    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['category', 'created_at']);
    $table->index('extension');
});
```

## Kategori sınıflandırma (otomatik)

Yükleme sırasında MIME'a göre kategori belirlenir:

| Category | MIME prefixes |
|---|---|
| `image` | image/* |
| `document` | application/pdf, application/msword, .docx, .xlsx, .pptx, text/* |
| `video` | video/* |
| `audio` | audio/* |
| `archive` | application/zip, .rar, .7z, .tar.gz |
| `other` | yukarıdakiler dışı |

## İzin verilen dosya türleri (kara liste yerine beyaz liste)

```php
// FormRequest
'mimes:' . implode(',', [
    // Görseller
    'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg',
    // Belgeler
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
    // Arşiv
    'zip',
    // Video/audio (opsiyonel — büyük dosya, hosting sınırı)
    'mp4', 'mp3',
]),
'max:51200',  // 50 MB tavan (PHP upload_max_filesize ile uyumlu)
```

> Dikkat: `php.ini`'deki `upload_max_filesize` ve `post_max_size`'a uygun
> olmalı. Hosting'de en az 50M ayarlanmalı, bilgi notu admin sayfasına eklenir.

## Model — `App\Models\UploadedFile`

- SoftDeletes ✅
- `$fillable` tanımlı
- Cast'ler: `is_public:bool`, `file_size:int`, `width:int`, `height:int`
- Helper'lar:
  - `publicUrl(): string` — `asset('uploads/' . stored_path)`
  - `humanSize(): string` — "2.3 MB"
  - `iconClass(): string` — Bootstrap Icons (PDF için bi-file-earmark-pdf, Excel bi-file-earmark-excel vb.)
  - `isImage(): bool`
  - `markDownloaded(): void` (download_count++)

## Service — `App\Services\FileManagerService`

```php
public function upload(UploadedFile $file, ?string $title, ?string $altText, ?int $userId): \App\Models\UploadedFile
// DB::transaction:
//   1. Hash hesapla (sha256), duplicate varsa mevcut kaydı dön
//   2. UploadService::uploadFile (görselse uploadImage, WebP varyant istemezsek preserveFormat=true)
//   3. Mime → category map
//   4. Görselse getimagesize → width/height
//   5. DB::create

public function delete(UploadedFile $asset): void
// DB::transaction:
//   1. UploadService::deleteImage veya deleteFile (mime'a göre)
//   2. Soft delete

public function search(string $term, ?string $category = null, int $perPage = 24): LengthAwarePaginator

public function statsByCategory(): array
// Cache::remember 5dk
```

## Controller — `App\Http\Controllers\Admin\FileManagerController`

| Method | Route | İş |
|---|---|---|
| `index()` | GET `/admin/files` | Liste + filtre + arama |
| `upload()` | POST `/admin/files/upload` | Dropzone tek dosya (JSON) |
| `show()` | GET `/admin/files/{file}` | Detay + URL kopyala |
| `update()` | PATCH `/admin/files/{file}` | Title + alt_text |
| `destroy()` | DELETE `/admin/files/{file}` | Soft delete |

## FormRequest — `UploadFileRequest` + `UpdateFileRequest`

```php
// UploadFileRequest
'file'  => ['required', 'file', 'mimes:...', 'max:51200'],
'title' => ['nullable', 'string', 'max:191'],
'alt_text' => ['nullable', 'string', 'max:500'],
```

## Admin UI — `/admin/files`

### Liste sayfası

```
┌────────────────────────────────────────────────────┐
│ Dosya Yöneticisi                  [+ Dosya Yükle] │
├────────────────────────────────────────────────────┤
│ [Arama: ad/açıklama]  [Tip: Tümü ▾]  [Tarih ▾]    │
├────────────────────────────────────────────────────┤
│ Kategori chip'leri: Tümü | Görsel | Belge | ...   │
├────────────────────────────────────────────────────┤
│ Grid (24'lü pagination):                           │
│ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐                    │
│ │ img │ │ pdf │ │ xlsx│ │ img │                    │
│ │thumb│ │icon │ │icon │ │thumb│                    │
│ │     │ │     │ │     │ │     │                    │
│ │ 📋  │ │ 📋  │ │ 📋  │ │ 📋  │ ← URL kopyala     │
│ │ ✏  │ │ ✏  │ │ ✏  │ │ ✏  │ ← düzenle           │
│ │ 🗑  │ │ 🗑  │ │ 🗑  │ │ 🗑  │ ← sil               │
│ └─────┘ └─────┘ └─────┘ └─────┘                    │
└────────────────────────────────────────────────────┘
```

- **Görsel** → thumbnail + boyut
- **PDF/Word/Excel** → tip ikonu (Bootstrap Icons) + dosya adı
- **Video** → bi-camera-reels ikonu + süre (varsa)
- **Hover'da** üç buton: URL kopyala / Düzenle / Sil

### Üst stat kartları
- Toplam dosya
- Toplam boyut (MB/GB)
- Bu ay yüklenen
- En sık indirilen kategori

### Dropzone yükleme alanı
- Mevcut Dropzone vendor reuse
- Çoklu yükleme (max 20 dosya/seans)
- Türkçe mesajlar
- Validation client-side (dosya tipi + boyut)

### Detay/edit modal
- Büyük preview (görsel için thumb, diğer için ikon + dosya adı)
- URL alanı (readonly + Kopyala butonu)
- Title düzenleme
- Alt text düzenleme (sadece görsellerde aktif)
- Boyut, MIME, yüklenme tarihi, indirme sayısı
- "İndirme Linki" + "Sayfada Kullanmak için" iki ayrı kopyala butonu:
  - Sade URL: `https://site.com/uploads/files/...`
  - HTML link: `<a href="...">Dosya Adı</a>`
  - Görselse Markdown image: `![alt](url)`

## URL kopyalama akışı

```
Detay modal → 3 farklı format:
┌──────────────────────────────────────────────────┐
│ Sade URL                                         │
│ [https://orhanbabaninciftligi.com/uploads/...]   │
│                                       [📋 Kopyala]│
├──────────────────────────────────────────────────┤
│ HTML Link                                        │
│ <a href="...">tarif-kitapcigi.pdf</a>            │
│                                       [📋 Kopyala]│
├──────────────────────────────────────────────────┤
│ Markdown (görselse)                              │
│ ![alt text](url)                                 │
│                                       [📋 Kopyala]│
└──────────────────────────────────────────────────┘
```

`navigator.clipboard.writeText` + `document.execCommand('copy')` fallback.

## Dosya tipi ikonları (Bootstrap Icons)

```php
match ($extension) {
    'pdf'  => 'bi-file-earmark-pdf',
    'doc', 'docx' => 'bi-file-earmark-word',
    'xls', 'xlsx', 'csv' => 'bi-file-earmark-excel',
    'ppt', 'pptx' => 'bi-file-earmark-slides',
    'zip' => 'bi-file-earmark-zip',
    'mp4' => 'bi-camera-reels',
    'mp3' => 'bi-music-note-beamed',
    'txt' => 'bi-file-earmark-text',
    default => 'bi-file-earmark',
}
```

Renklerle birlikte kategori bazlı:
- Image → mavi
- Document → kırmızı/turuncu (PDF) / mavi (Word) / yeşil (Excel)
- Video → mor
- Archive → gri

## Duplicate kontrolü

Yükleme sırasında dosyanın `sha256` hash'i hesaplanır. Aynı hash varsa
mevcut kayıt döner (yeniden yüklenmez):

```php
$hash = hash_file('sha256', $file->getRealPath());
$existing = UploadedFile::where('hash', $hash)->first();
if ($existing) {
    return $existing;  // duplicate — JSON response'da "duplicate: true"
}
```

UI'da bilgi notu: "Bu dosya zaten yüklü, mevcut kayıt kullanılacak."

## Editor entegrasyonu (ayrı PR — gelecek)

CKEditor / TinyMCE / Trumbowyg gibi rich-text editor varsa **dosya seçici**
plugin'i eklenebilir. Editor'den "Dosya Ekle" butonu → modal açılır →
FileManager listesinden seçilir → URL editöre yapışır.

Bu PR'da yapılmaz — manuel kopyala-yapıştır akışı yeterli ilk versiyon için.

## CLAUDE.md uyumu

- ✅ `declare(strict_types=1)` her PHP dosyasında
- ✅ SoftDeletes + `$fillable`
- ✅ FormRequest validation (UploadFileRequest, UpdateFileRequest)
- ✅ Service iş mantığı, thin controller
- ✅ `DB::transaction` (upload + delete)
- ✅ `Cache::remember` (statsByCategory 5dk) + invalidation
- ✅ `public/uploads/files/` (asset() YASAK, sadece UploadService)
- ✅ Inline style YOK — `fmgr-` prefix BEM
- ✅ AdminModal sil onayı (alert/confirm yasak)
- ✅ Dropzone vendor mevcut (jQuery yok)
- ✅ Pagination 24'lü
- ✅ Türkçe iletişim, İngilizce kod

## CSS prefix sistemi

`fmgr-` prefix BEM:
- `fmgr-grid`, `fmgr-tile`, `fmgr-tile__icon`, `fmgr-tile__name`
- `fmgr-tile--image`, `fmgr-tile--document`, `fmgr-tile--video`
- `fmgr-tile__actions`, `fmgr-url-box`, `fmgr-stat-card`

## Sidebar nav

"Görsel Kütüphanesi"'nin altına: **"Dosyalar"** menü öğesi (bi-folder ikon).

## Commit stratejisi (parça parça)

7 commit:

1. **Migration + UploadedFile modeli + helper'lar** (humanSize, iconClass, vs.)
2. **FileManagerService** (upload, delete, search, stats, duplicate hash)
3. **Controller + Routes + FormRequest** (5 endpoint)
4. **Dropzone init JS** (`file-manager-upload.js`)
5. **Index view** (grid + filtreler + stat cards)
6. **Detay modal** (URL kopyalama 3 format + edit)
7. **Sidebar nav + kullanıcı rehberi** (`docs/dosya-yoneticisi-kullanici-rehberi.md`)

## Test planı

```bash
php artisan migrate
```

1. `/admin/files` → boş grid + "Henüz dosya yok" empty state
2. "Dosya Yükle" → Dropzone — PDF + Excel + görsel ürün karışık 5 dosya yükle
3. Hash kontrolü: aynı PDF'i tekrar yükle → "Duplicate" uyarısı + mevcut kayıt
4. Filtre: "Belge" sekmesi → sadece PDF/Word/Excel görünmeli
5. Arama: dosya adıyla → eşleşmeli
6. Detay: PDF kartına tıkla → modal aç → URL kopyala → tarayıcıda yapıştır → çalışıyor mu
7. HTML Link kopyala → blog yazısı düzenleme'ye yapıştır → link aktif olmalı
8. Sil: AdminModal.confirm → soft delete + dosya filesystem'den silinir

## Risk noktaları

1. **PHP upload limit** — Hosting'de `upload_max_filesize=2M` ise 50MB istek fail eder. Hata mesajı net olmalı: "Hosting upload_max_filesize değerini en az 50M yapın."

2. **Disk dolması** — 50MB × 1000 dosya = 50GB. İleride disk usage uyarısı eklenebilir (ayrı feature).

3. **Public file güvenliği** — `public/uploads/files/` her dosya web'den erişilebilir. SVG dosyalar XSS riski (içinde JS olabilir). İlk versiyonda SVG'yi yasaklayalım veya server-side `<script>` kontrolü yap.

4. **Filename injection** — `Str::slug()` + `Str::random()` kullanılıyor (UploadService default), bu güvenli.

5. **Hash collision** — sha256 collision pratik olarak imkansız, sorun değil.

## Yol haritası

- **PR 1 (bu plan):** Temel FileManager — yükle, listele, kopyala, sil
- **PR 2 (gelecek):** CKEditor / Trumbowyg entegrasyonu (file picker)
- **PR 3 (gelecek):** Klasör/etiket sistemi, gelişmiş arama
- **PR 4 (gelecek):** Disk usage dashboard + cleanup tool
