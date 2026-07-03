# Sosyal Medya Görsel Sistemi — Yol Haritası

## Sorun

AI görsel modelleri (Gemini Nano Banana, Imagen, DALL-E vs.) **yazı render
etmekte güvenilmez**. Pratik sonuç: prompt'a "telefon numarası ve web sitesi
ekle" yazılırsa AI "[NUMARANIZ]" gibi placeholder'ı kelimesi kelimesine basar,
veya bozuk harflerle taklit eder. Marka logosu, marka adı, slogan, telefon —
hiçbiri AI'ya bırakılamaz.

## Çözüm Mimarisi

```
Marka Profili (admin'in tek sefer doldurduğu bilgiler)
        │
        ▼
AI Görsel Üretici  ◄── prompt zenginleştirme + "yazı/logo BASMA" direktifi
        │
        ▼ (sadece arka plan/sahne)
Overlay Composer  ◄── Marka Profilinden logo/telefon/web/slogan
        │
        ▼
Hazır sosyal medya görseli (paylaşıma hazır)
```

3 PR'a bölünmüş yol haritası — her PR kendi başına işe yarar.

| PR | İçerik | Durum |
|----|--------|-------|
| **PR 1** | Marka Profili sayfası + AI prompt zenginleştirme | **🟡 Plan hazır** |
| PR 2 | Overlay Composer + 3 başlangıç şablonu | ⏸ PR 1 sonrası |
| PR 3 | Şablon yönetimi + ek şablonlar | ⏸ PR 2 sonrası |

---

# PR 1 — Marka Profili (bu PR)

## Hedef

Sistemde **tek bir marka profili** olsun (singleton row). Her AI görsel üretim
çağrısı bu profilden:
1. **Stil yönergesini** (rustik/minimalist/vs) prompt'a otomatik enjekte etsin
2. **"Yazı/logo/numara basma"** direktifini her prompt'a eklensin (kritik —
   `[NUMARANIZ]` gibi placeholder'ların önüne geçer)
3. PR 2'ye veri kaynağı sağlasın (logo, telefon, web, slogan, renkler)

PR 1 sonunda **görseller temiz arka plan olarak çıkacak** (yazısız) — overlay
PR 2'de gelecek. Ara dönemde mevcut promptlar zaten paylaşılabilir kalitede
çıkar (bozuk text yok, sadece görsel sahne).

## Kapsam (sadece PR 1)

### 1. Migration: `brand_profiles` tablosu

```php
Schema::create('brand_profiles', function (Blueprint $table) {
    $table->id();
    $table->string('name', 120);                  // "Orhan Babanın Çiftliği"
    $table->string('tagline', 200)->nullable();   // "Çiftlikten Sofranıza..."
    $table->string('logo_path', 255)->nullable(); // public/uploads/brand/logo.png
    $table->string('phone', 30)->nullable();      // "+90 555 ..."
    $table->string('whatsapp', 30)->nullable();
    $table->string('website', 191)->nullable();   // "orhanbabaninciftligi.com"
    $table->string('instagram_handle', 60)->nullable(); // "@orhanbabaninciftligi"
    $table->string('primary_color', 9)->default('#2d6a4f');   // HEX
    $table->string('secondary_color', 9)->default('#f4a261'); // HEX
    $table->string('font_family', 60)->default('Poppins');    // Google Fonts adı
    $table->text('style_directive')->nullable();              // AI prompt için stil tarifi
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

`style_directive` örnek değer:
> "Rustik Anadolu çiftliği estetiği, doğal gün ışığı, sıcak toprak tonları,
> shallow depth of field, food/farm photography stili, fotojenik ama doğal."

### 2. Model: `App\Models\BrandProfile`

- `$fillable` tanımlı
- SoftDeletes
- `current(): ?self` — aktif kayıt (cache: `Cache::remember('brand_profile.current', 3600, ...)`)
- `logoUrl(): ?string` — `upload_url($logo_path)` veya null
- `aiPromptContext(): string` — AI prompt'a enjekte edilecek hazır metin

### 3. Service: `App\Services\BrandProfileService`

- `get(): BrandProfile` (yoksa default singleton oluştur)
- `update(array $data): BrandProfile`
- `updateLogo(UploadedFile $file): string` (UploadService kullanır,
  `public/uploads/brand/` altına PNG → WebP'ye çevirme YOK çünkü transparency
  korunmalı, `preserveFormat=true`)
- `removeLogo(): void`
- Cache invalidation: her update'te `Cache::forget('brand_profile.current')`

### 4. Controller: `App\Http\Controllers\Admin\BrandProfileController`

- `edit(): View` — tek sayfa form
- `update(BrandProfileRequest): RedirectResponse`
- `removeLogo(): RedirectResponse`

### 5. FormRequest: `App\Http\Requests\Admin\BrandProfileRequest`

```php
'name'             => ['required', 'string', 'max:120'],
'tagline'          => ['nullable', 'string', 'max:200'],
'phone'            => ['nullable', 'string', 'max:30'],
'whatsapp'         => ['nullable', 'string', 'max:30'],
'website'          => ['nullable', 'string', 'max:191'],
'instagram_handle' => ['nullable', 'string', 'max:60'],
'primary_color'    => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'secondary_color'  => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
'font_family'      => ['required', 'string', 'max:60'],
'style_directive'  => ['nullable', 'string', 'max:1000'],
'logo'             => ['nullable', 'image', 'mimes:png', 'max:2048'], // PNG, max 2MB
```

### 6. Routes (admin)

```php
Route::prefix('brand-profile')->name('brand-profile.')->group(function () {
    Route::get('/',          [BrandProfileController::class, 'edit'])->name('edit');
    Route::put('/',          [BrandProfileController::class, 'update'])->name('update');
    Route::delete('/logo',   [BrandProfileController::class, 'removeLogo'])->name('logo.remove');
});
```

### 7. View: `admin/brand-profile/edit.blade.php`

Tek form sayfası — admin-theme stiline uygun:

- **Bölüm: Kimlik** — Marka adı, slogan, logo upload (mevcut görsel preview + kaldır)
- **Bölüm: İletişim** — Telefon, WhatsApp, Web, Instagram handle
- **Bölüm: Görsel Kimlik** — Primary color (color picker), secondary color, font seçici
  (Google Fonts'tan dropdown: Poppins, Montserrat, Roboto, Playfair Display, Inter)
- **Bölüm: AI Stil Yönergesi** — Textarea, açıklama: "Bu metin her AI görsel
  promptuna otomatik eklenir. Boş bırakırsan default kullanılır."
- Submit: Kaydet
- Footer'da "PR 2 sonrası bu bilgiler sosyal medya görsellerine otomatik
  basılacak" bilgi notu

### 8. Sidebar nav

`Marka Profili` menü öğesi → admin sidebar'da, Ayarlar grubunun üstünde.

### 9. AI Generator entegrasyonu (kritik kısım)

`App\Services\Ai\AiImageGenerator::generate()` içinde prompt enrichment:

```php
$brand = BrandProfile::current();
$styleHint = $brand?->style_directive ?? self::DEFAULT_STYLE;

$enrichedPrompt = $prompt . "\n\n"
    . "Style: {$styleHint}\n"
    . "STRICT RULES: Generate a clean photographic background ONLY. "
    . "DO NOT include any text, letters, numbers, captions, watermarks, "
    . "logos, brand names, phone numbers, URLs, hashtags, placeholders "
    . "(like [NUMARANIZ], [NAME], [TEXT]), buttons, badges or written "
    . "elements of any kind on the image. Text/logo overlays will be "
    . "added separately by the system after generation.";
```

Bu tek değişiklik **mevcut [NUMARANIZ] gibi saçmalık problemini çözer**.

PR 2'de bu metin overlay'a hazır temiz arka plana ihtiyaç duyacak — şimdiden
"yazısız" üretmeye başlamamız gerekiyor.

### 10. Maliyet bütçesi (yeni — kritik)

AI görsel üretimi $0.04/görsel maliyetli. Carousel 10 slayt + sık üretim
sürpriz fatura riski. Bütçe kontrolü:

**Settings:**
- `ai_image_monthly_budget_usd` (default: `10.00`) — aylık tavan
- `ai_image_budget_alert_threshold` (default: `80`) — %80'e ulaşınca uyar
- `ai_image_budget_action` (default: `block`) — `block` (üretimi durdur) veya `warn` (sadece log)

**Service:** `App\Services\Ai\AiImageBudgetGuard`
```php
public function canGenerate(): array
// Returns: ['allowed' => bool, 'used_usd' => float, 'limit_usd' => float, 'reason' => string]

public function currentMonthUsage(): float
// SUM(cost_usd) FROM ai_generated_images WHERE created_at >= startOfMonth
```

**Entegrasyon:** `AiImageGenerator::generate()` içinde, AI çağrısından **önce**
guard çağır:
```php
$check = $this->budgetGuard->canGenerate();
if (! $check['allowed']) {
    return $this->failResult($image, "Aylık AI görsel bütçesi aşıldı: "
        . "${$check['used_usd']} / ${$check['limit_usd']}. "
        . "Admin → Ayarlar → AI Görsel'den bütçeyi yükselt veya ayın 1'ini bekle.");
}
```

**UI:** Settings sayfasında AI Görsel bölümüne 3 yeni alan + dashboard widget'ı:
"Bu ay: $X.XX / $Y.YY (NN%)"

**Alert:** %80'e ulaşınca admin email'ine uyarı (mevcut MailLog sistemi).

### 11. Kullanıcı rehberi

`docs/marka-profili-kullanici-rehberi.md` — admin için adım adım:
1. Marka Profili sayfasını aç
2. Logo yükle (PNG, transparan, ideal 500×500)
3. Telefon, web, IG handle gir
4. Marka renklerini seç (color picker)
5. Yazı tipi tercih et
6. AI stil yönergesi yaz (örnek prompt'lar verilecek)
7. Kaydet
8. Test: AI görsel üret, "no text" direktifinin etkisini gör

Rehber ekran görüntüleriyle desteklenir (PR'la birlikte hazırlanır).

### 12. Default record + seeder

İlk migrate'te boş profil olmasın diye migration'ın up()'ında veya seeder'da:

```php
BrandProfile::firstOrCreate(['id' => 1], [
    'name'            => 'Orhan Babanın Çiftliği',
    'tagline'         => 'Çiftlikten Sofranıza Taze Lezzet',
    'phone'           => null,
    'website'         => 'orhanbabaninciftligi.com',
    'primary_color'   => '#2d6a4f',
    'secondary_color' => '#f4a261',
    'font_family'     => 'Poppins',
    'style_directive' => 'Rustik Anadolu çiftliği estetiği, doğal gün ışığı, '
                       . 'sıcak toprak tonları, shallow depth of field, '
                       . 'food/farm photography stili, fotojenik ama doğal.',
    'is_active'       => true,
]);
```

## CLAUDE.md uyumluluğu

- ✅ `declare(strict_types=1)`
- ✅ SoftDeletes
- ✅ `$fillable` (no `$guarded = []`)
- ✅ İş mantığı Service'te, controller thin
- ✅ FormRequest validation
- ✅ Türkçe iletişim, İngilizce kod/identifiers
- ✅ Inline style yasak — admin-theme class'ları
- ✅ Cache::remember (brand profile için 1 saat)
- ✅ Migration `down()` yazılı, index'ler doğru
- ✅ AdminModal (logo silme onayı)
- ✅ `loading="lazy"`, `img-fluid` (logo preview)

## Test planı (canlıda)

```bash
php artisan migrate
```

1. `/admin/brand-profile` aç → form gelmeli
2. Logo yükle (PNG transparan) → preview görünsün
3. Renk seçicilerle primary/secondary değiştir → kaydet → DB'de tutulsun
4. `/admin/instagram-posts/create` → "AI Görsel Üret" ile bir story üret →
   bu sefer **yazı/placeholder OLMAMALI**, sadece temiz arka plan gelmeli
5. `/admin/ai-generate` (blog kapak) — aynı şey, yazı/logo basmamalı
6. Logo silmeyi dene → AdminModal onay → silinsin
7. `/admin/ai-image-logs` → yeni üretim kayıtlarında prompt sonuna stil
   direktifi + "no text" kuralı eklenmiş olmalı (request_payload kontrol)

## Commit stratejisi (parça parça)

PR 1 içinde 7 commit:

1. **Migration + Model + Seeder** → `[feat]: brand_profiles tablosu + BrandProfile modeli`
2. **Service + FormRequest** → `[feat]: BrandProfileService + validation request`
3. **Controller + Routes** → `[feat]: BrandProfileController + admin route'ları`
4. **View + Sidebar** → `[feat]: Marka Profili admin sayfası + sidebar nav`
5. **AI Generator entegrasyonu** → `[feat]: AI prompt'una marka stili + no-text direktifi`
6. **Bütçe sistemi** → `[feat]: AiImageBudgetGuard + aylık limit + dashboard widget`
7. **Kullanıcı rehberi** → `[docs]: marka profili kullanıcı rehberi`

Her commit kendi başına çalışır halde olacak — review kolay olsun.

---

# PR 2 — Overlay Composer + 3 Başlangıç Şablonu

## Hedef

PR 1'de elde ettiğimiz **temiz arka plan** üzerine, marka profilinden okuyarak
logo + metin + iletişim bilgilerini **piksel-mükemmel** şekilde basmak. Çıkan
sonuç doğrudan Instagram'a yüklenebilecek kalitede olacak.

## Kapsam

### 1. Migration: `social_media_compositions` tablosu

Üretilen her sosyal medya görselinin metadatasını saklar (re-render, paylaşım
takibi, şablon analitiği).

```php
Schema::create('social_media_compositions', function (Blueprint $table) {
    $table->id();
    $table->string('template_key', 60)->index();        // 'story_promo', 'feed_hero', 'story_announcement'
    $table->string('platform', 20)->default('instagram'); // 'instagram', 'facebook' (ileride)
    $table->string('media_format', 20);                 // 'story', 'feed', 'reel_cover'
    $table->string('aspect_ratio', 10);                 // '9:16', '1:1', '4:5'
    $table->foreignId('ai_image_id')->nullable()->constrained('ai_generated_images')->nullOnDelete();
    $table->string('background_path', 255)->nullable(); // AI üretilmiş arka plan
    $table->string('output_path', 255)->nullable();     // overlay'li final
    $table->json('text_layers');                        // başlık, alt yazı, CTA — array of {key, value}
    $table->json('template_config')->nullable();        // şablon override'ları (renk, pozisyon)
    $table->string('status', 20)->default('pending');   // pending | rendering | completed | failed
    $table->text('error_message')->nullable();
    $table->unsignedInteger('width')->nullable();
    $table->unsignedInteger('height')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index('created_at');
});
```

### 2. Şablon sistemi — `App\Services\Social\Templates`

Her şablon **kod** olarak yazılır (PHP class), DB'de tutulmaz. Bu PR'da 3 hazır
şablon. PR 3'te kullanıcı kendi şablonunu tanımlayabilecek (DB'ye taşınır).

#### Soyutlama

```php
abstract class SocialMediaTemplate
{
    abstract public function key(): string;            // 'story_promo'
    abstract public function label(): string;          // 'Hikâye — Tanıtım'
    abstract public function aspectRatio(): string;    // '9:16'
    abstract public function dimensions(): array;      // [1080, 1920]
    abstract public function requiredTextLayers(): array; // ['title', 'subtitle', 'cta']
    abstract public function render(GdImage $background, array $context): GdImage;
    // $context: ['brand' => BrandProfile, 'text' => [...], 'config' => [...]]
}
```

#### 3 Başlangıç Şablonu

**A) `StoryPromoTemplate`** — 9:16, 1080×1920
```
┌──────────────────────────┐
│ [LOGO]              .ig  │ ← üst sol logo, üst sağ Instagram handle
│                          │
│   ╔══════════════════╗   │
│   ║   BAŞLIK         ║   │ ← üst-orta yarı saydam blok
│   ║   Alt yazı       ║   │
│   ╚══════════════════╝   │
│                          │
│  [AI ARKA PLAN ALANI]    │ ← görselin hero kısmı görünür
│                          │
│   ╔══════════════════╗   │
│   ║  📞 +90 ...       ║   │ ← alt CTA bloğu, marka renginde
│   ║  🌐 site.com      ║   │
│   ╚══════════════════╝   │
│      slogan              │
└──────────────────────────┘
```
Text layers: `title` (zorunlu), `subtitle`, `cta_text` (default: "Sipariş Ver")

**B) `FeedHeroTemplate`** — 1:1, 1080×1080
```
┌──────────────────────────┐
│  [LOGO]                  │
│                          │
│      [AI ARKA PLAN]      │
│         (hero)           │
│                          │
│ ════════════════════════ │ ← marka rengi alt şerit
│  BAŞLIK              .ig │
│  📞 numara | 🌐 site     │
└──────────────────────────┘
```
Text layers: `title` (zorunlu), `subtitle`

**C) `StoryAnnouncementTemplate`** — 9:16, 1080×1920
```
┌──────────────────────────┐
│       [BIG LOGO]         │ ← merkez üst, büyük logo
│                          │
│   ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓   │ ← marka rengi vurgu çizgisi
│      BÜYÜK BAŞLIK        │ ← font 80pt
│      Alt mesaj           │
│   ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓   │
│                          │
│   [küçük arka plan]      │ ← AI arka plan kısmen görünür
│                          │
│        @handle           │
│   📞 numara              │
└──────────────────────────┘
```
Text layers: `headline` (zorunlu), `body`

### 3. Service: `App\Services\Social\SocialMediaImageComposer`

Tek giriş noktası — şablonu seçer, AI'dan arka plan ister, overlay basar, kaydeder.

```php
public function compose(
    string $templateKey,                    // 'story_promo'
    string $aiPrompt,                       // kullanıcının AI prompt'u
    array $textLayers,                      // ['title' => 'Bahar Geldi', ...]
    ?int $userId = null,
): SocialMediaComposition
```

Akış:
1. Şablon nesnesini al (`TemplateRegistry::get($templateKey)`)
2. Composition row create (status=pending)
3. AiImageGenerator::generate() ile arka plan üret (PR1'deki "no text" direktifi)
4. AI başarısız → composition status=failed, hata logla, dön
5. AI başarılı → arka plan dosyasını GD'ye yükle
6. Şablonun `render()` metodunu çağır (background + brand + text)
7. Sonucu `public/uploads/social-media/{template}/{slug}-{timestamp}.png` olarak kaydet
8. Composition status=completed, output_path set, dön

### 4. Şablon registry: `App\Services\Social\TemplateRegistry`

```php
final class TemplateRegistry
{
    /** @var array<string, class-string<SocialMediaTemplate>> */
    private const TEMPLATES = [
        'story_promo'         => StoryPromoTemplate::class,
        'feed_hero'           => FeedHeroTemplate::class,
        'story_announcement'  => StoryAnnouncementTemplate::class,
    ];

    public function all(): array;       // hepsini listele (UI için)
    public function get(string $key): SocialMediaTemplate;
    public function exists(string $key): bool;
}
```

### 5. Yardımcı service: `App\Services\Social\GdTextRenderer`

Her şablon kendi GD kodunu yazmasın diye — ortak text/logo helper'ları:

```php
public function drawText(GdImage $img, string $text, array $opts): void;
// opts: x, y, width (auto-wrap), font_path, font_size, color, align, line_height

public function drawTextBox(GdImage $img, string $text, array $opts): void;
// arka planı yarı saydam blok ile

public function drawLogo(GdImage $img, string $logoPath, int $x, int $y, int $maxWidth): void;
// PNG transparency korunur

public function fitTextToWidth(string $text, int $maxWidth, string $fontPath, int $startSize): int;
// Auto-fit font size

public function hexToRgb(string $hex): array;
```

### 6. Font dosyaları

`public/assets/admin/fonts/` altına TTF dosyaları:
- `Poppins-Regular.ttf`, `Poppins-Bold.ttf`
- `Montserrat-Regular.ttf`, `Montserrat-Bold.ttf`
- `Playfair-Regular.ttf`, `Playfair-Bold.ttf`

Marka profilindeki `font_family` değerine göre seçilir.

> **Lisans:** Tümü Google Fonts (SIL Open Font License) — ticari kullanım serbest,
> commit edilebilir. `docs/fonts-license.md` ile lisans referansları yazılır.

### 7. Controller: `App\Http\Controllers\Admin\SocialMediaController`

```php
public function create(): View;
// Şablon seçici + AI prompt + text layer alanları (şablona göre dinamik)

public function generate(GenerateSocialMediaRequest): JsonResponse;
// AJAX: composer çağırır, output_path + url döner

public function index(): View;
// Geçmiş kompozisyonların listesi (paginate)

public function show(SocialMediaComposition): View;
// Detay: önizleme + AI prompt + text layers + re-render butonu

public function destroy(SocialMediaComposition): RedirectResponse;
// Composition + output dosyası sil

public function regenerate(SocialMediaComposition): JsonResponse;
// Aynı text + prompt ile YENİDEN AI çağırır (farklı arka plan dener)
```

### 8. FormRequest: `GenerateSocialMediaRequest`

```php
'template_key' => ['required', 'string'],
'prompt'       => ['required', 'string', 'min:5', 'max:2000'],
'text_layers'  => ['required', 'array'],
'text_layers.*' => ['nullable', 'string', 'max:200'],
```

Şablon-spesifik zorunlu alanlar `prepareForValidation` veya custom rule ile.

### 9. Routes (admin)

```php
Route::prefix('social-media')->name('social-media.')->group(function () {
    Route::get('/',           [SocialMediaController::class, 'index'])->name('index');
    Route::get('/create',     [SocialMediaController::class, 'create'])->name('create');
    Route::post('/generate',  [SocialMediaController::class, 'generate'])->name('generate');
    Route::get('/{social}',   [SocialMediaController::class, 'show'])->name('show');
    Route::post('/{social}/regenerate', [SocialMediaController::class, 'regenerate'])->name('regenerate');
    Route::delete('/{social}', [SocialMediaController::class, 'destroy'])->name('destroy');
});
```

### 10. Views

**`admin/social-media/create.blade.php`** — Ana üretim sayfası:
- Sol kolon: şablon kart seçici (3 şablonun küçük preview'leri)
- Sağ kolon: seçilen şablona göre dinamik form (text layers, prompt)
- Alt: "Üret" butonu (loading state), sonra preview alanı (üretilen görsel)
- "Yeni Arka Plan ile Tekrar Dene" butonu
- "İndir" + "Instagram'a Gönder" butonları (Instagram entegrasyonu PR sonrası iş)

**`admin/social-media/index.blade.php`** — Liste:
- Grid layout: thumb + template label + tarih + sil/yeniden üret butonları
- Filtreler: template_key, status, tarih aralığı

**`admin/social-media/show.blade.php`** — Detay:
- Büyük preview
- Tüm metadata (prompt, text layers, kullanılan şablon)
- "Yeniden Üret" butonu
- AI image log linki (PR1'deki ai-image-logs sayfasına)

### 11. Sidebar nav

`AI Görsel Logları`'nın altına `Sosyal Medya Görselleri` menü öğesi.

### 12. Carousel desteği (yeni — Instagram'ın en güçlü formatı)

Tek post = max 10 slayt. PR 2'de **carousel-aware** mimari:

**Migration genişletmesi:**
```php
$table->foreignId('parent_composition_id')->nullable()
      ->constrained('social_media_compositions')->cascadeOnDelete();
$table->unsignedTinyInteger('slide_index')->default(1);    // 1, 2, 3, ..., 10
$table->unsignedTinyInteger('slide_count')->default(1);    // 1 = standalone, 2-10 = carousel
$table->index(['parent_composition_id', 'slide_index']);
```

**Composer API:**
```php
public function composeCarousel(
    string $templateKey,
    array $slides,                  // [['prompt' => ..., 'text_layers' => [...]], ...]
    ?int $userId = null,
): array                            // [parent => Composition, slides => Composition[]]
```

Akış:
1. Parent composition oluştur (`slide_count = N`, `slide_index = 0`)
2. Her slayt için ayrı composition (parent_id set, slide_index 1..N)
3. AI üretimi paralel YOK (rate limit) — seri üret, her birini logla
4. Final: parent.output_path = ilk slayt'ın URL'i (galeri thumb için)

**Şablon carousel-aware olabilmeli:** Layer'larda `{slide_index}/{slide_count}`
interpolation desteği (örn. "1/5" rozeti). Optional layer: `slide_badge`.

**3 başlangıç şablonuna carousel desteği:**
- Story Promo: standalone (carousel desteklenmez — story zaten 1 slayt)
- Feed Hero: carousel destekli — slayt rozeti opsiyonel
- Story Announcement: standalone

**Yeni şablon:** `FeedCarouselTemplate` — özel carousel layout (slide rozeti
sabit alt sağ köşede)

**UI:** Composer create sayfasında carousel-destekli şablon seçilince:
- "Slayt sayısı" seçici (1-10)
- Her slayt için ayrı text_layer + AI prompt formu (akordion)
- Üretim butonu "5 görseli üret (~2 dk, ~$0.20)" — maliyet net gösterilir

**Bulk download:** Carousel post için ZIP indirme (Instagram'a 10 görsel ayrı
ayrı yüklenir, mobil app desteklemez native upload, manuel sıralama gerek).

### 13. Instagram Posts entegrasyonu (opsiyonel ama kritik)

`/admin/instagram-posts/create` formuna yeni buton: **"Şablonlu Görsel Üret"**.
Tıklayınca social-media composer modal'da açılır, sonuç `ai_image_path` olarak
form'a yapışır. Mevcut "AI Görsel Üret" butonu (sadece arka plan) korunuyor —
kullanıcı seçer.

## Şablon yapısı detayı (kritik teknik nokta)

Her şablon `render()` metodu **deterministik** olmalı:
- Aynı input (background, brand, text) → aynı output piksel
- Hata throw etmemeli — eksik text layer ise default değer kullanmalı
  (örn. `cta_text` boşsa "Sipariş Ver" bas)
- Text overflow olursa font size auto-fit ile küçültülsün

Örnek `StoryPromoTemplate::render()` iskeleti:

```php
public function render(GdImage $background, array $context): GdImage
{
    /** @var BrandProfile $brand */
    $brand = $context['brand'];
    $text = $context['text'];

    $canvas = imagecreatetruecolor(1080, 1920);
    imagecopyresampled($canvas, $background, ...); // tam arka plan

    // 1. Logo top-left
    if ($brand->logo_path) {
        $this->renderer->drawLogo($canvas, $brand->logoUrl(), 60, 60, 200);
    }

    // 2. Instagram handle top-right
    if ($brand->instagram_handle) {
        $this->renderer->drawText($canvas, $brand->instagram_handle, [
            'x' => 1080 - 60, 'y' => 70, 'align' => 'right',
            'font_path' => $this->fontPath('Regular'),
            'font_size' => 28, 'color' => '#ffffff',
        ]);
    }

    // 3. Üst başlık bloğu (yarı saydam arka plan)
    $this->renderer->drawTextBox($canvas, $text['title'] ?? '', [
        'x' => 60, 'y' => 200, 'width' => 960, 'padding' => 40,
        'bg_color' => '#000000', 'bg_opacity' => 0.55,
        'font_path' => $this->fontPath('Bold'),
        'font_size' => 72, 'color' => '#ffffff', 'align' => 'center',
    ]);

    // 4. Alt CTA bloğu (marka renginde)
    $this->renderer->drawTextBox($canvas, $this->ctaText($brand, $text), [
        'x' => 60, 'y' => 1500, 'width' => 960, 'padding' => 40,
        'bg_color' => $brand->primary_color, 'bg_opacity' => 0.95,
        'font_path' => $this->fontPath('Bold'),
        'font_size' => 48, 'color' => '#ffffff', 'align' => 'center',
    ]);

    // 5. En altta slogan
    $this->renderer->drawText($canvas, $brand->tagline ?? '', [
        'x' => 540, 'y' => 1820, 'align' => 'center',
        'font_path' => $this->fontPath('Regular'),
        'font_size' => 32, 'color' => '#ffffff',
    ]);

    return $canvas;
}
```

## CLAUDE.md uyumluluğu (PR 2)

- ✅ `declare(strict_types=1)` her dosyada
- ✅ SoftDeletes, $fillable
- ✅ Composer service iş mantığı, controller thin
- ✅ FormRequest validation
- ✅ Türkçe iletişim, İngilizce kod
- ✅ Inline style yasak — admin-theme class'ları
- ✅ Migration `down()`, index'ler
- ✅ AdminModal (sil onayı, regenerate onayı)
- ✅ N+1 yok (composition listesinde with('aiImage', 'author') eager load)

## Test planı (canlıda)

```bash
php artisan migrate
```

1. Marka Profili dolu olmalı (PR 1) — değilse uyarı çıksın
2. `/admin/social-media/create` → 3 şablon kartı görünsün
3. Story Promo seç → başlık + alt yazı + AI prompt yaz → Üret
4. Üretim ~15-20 sn sürer (AI + GD compose)
5. Önizlemede:
   - Logo sol üst (transparan korunmuş)
   - Başlık üstte (okunaklı)
   - Telefon + web altta (gerçek bilgiler, NETSE)
   - Slogan en altta
   - **HİÇBİR `[NUMARANIZ]` veya placeholder yok**
6. "Yeniden Üret" → farklı arka plan, aynı text
7. İndir → 1080×1920 PNG dosyası inmeli
8. `/admin/social-media` → liste görünsün, sil/regen çalışsın

## Risk ve dikkat noktaları

### 1. GD performansı
1080×1920 piksel görsel + 4-5 text layer ≈ 2-3 saniye render. Background AI
zaten ~15 saniye, sorun yok ama composer'ı queue'ya alma fikrini PR 3'te
düşünelim.

### 2. Font dosyalarının boyutu
Her TTF ~150-300KB. 6 dosya ≈ 1.5MB git'e eklenir. Kabul edilebilir, alternatif
CDN'den dinamik indirme — daha kırılgan, gerek yok.

### 3. AI'nın "no text" direktifine uyma garantisi
PR 1'deki direktif çoğu durumda iş görür ama Gemini bazen yine de küçük yazı
sokar. Çözüm: composer render'dan önce arka planda **OCR** yapıp text bulursa
prompt'u tekrar dener? Aşırı mühendislik. Şimdilik direktif yeterli, kullanıcı
sorun yaşarsa PR 3'te ele alınır.

### 4. Logo PNG değilse
PR 1 validation ile PNG zorunlu. Yine de runtime'da
`imagecreatefrompng` false dönerse defansif: logo'suz render et + log warning.

### 5. Çok uzun başlık
`fitTextToWidth` ile font auto-fit. Min font size 24pt — daha küçük olursa
trim + "..." ekle.

### 6. Composition cleanup
Soft delete sonrası output dosyası diskte kalır. Cron job (haftalık) eski
soft-deleted composition output'larını siler — PR 3'te.

## Commit stratejisi (parça parça, PR 2 içinde)

9 commit:

1. **Migration + Composition modeli** → tablo + model (carousel kolonları dahil)
2. **Template soyutlaması + Registry** → SocialMediaTemplate base, TemplateRegistry
3. **GdTextRenderer** → drawText/drawTextBox/drawLogo/fitText
4. **3 başlangıç şablonu** → StoryPromo + FeedHero + StoryAnnouncement
5. **FeedCarousel şablonu** → carousel-aware (slide_index rozeti)
6. **SocialMediaImageComposer service** → compose() + composeCarousel()
7. **Controller + Routes + FormRequest** → admin endpoint'leri
8. **View'lar + Sidebar + Font dosyaları** → UI tamamlanır
9. **Kullanıcı rehberi** → `docs/sosyal-medya-uretici-kullanici-rehberi.md`

---

# PR 3 — Şablon Yönetimi + Entegrasyonlar

## Hedef

PR 2'deki sabit kod-tabanlı 3 şablonu kullanıcı tarafından **özelleştirilebilir
ve genişletilebilir** hale getirmek. Admin yeni şablon yaratabilsin, mevcutları
düzenleyebilsin, dışa aktarsın, paylaşsın. Ayrıca composition akışını mevcut
sistemlere (Instagram post oluşturma) gömerek pratik kullanım için kapı açmak.

## Kapsam

### 1. Migration: `social_media_templates` tablosu

Şablonlar artık DB'de — kod tabanlı 3 şablon "system template" olarak işaretlenir
(silinemez, düzenlenemez ama kopyalanabilir).

```php
Schema::create('social_media_templates', function (Blueprint $table) {
    $table->id();
    $table->string('key', 80)->unique();             // 'story_promo', 'custom_xyz123'
    $table->string('name', 120);                     // "Hikâye — Tanıtım"
    $table->string('description', 255)->nullable();
    $table->string('aspect_ratio', 10);              // '9:16'
    $table->unsignedInteger('width');                // 1080
    $table->unsignedInteger('height');               // 1920
    $table->json('layers');                          // tüm katman config (logo, text, shapes, AI bg area)
    $table->json('text_layer_keys');                 // ['title', 'subtitle', 'cta'] — required input keys
    $table->string('preview_path', 255)->nullable(); // generated thumbnail
    $table->boolean('is_system')->default(false);    // PR 2 hardcoded olanlar
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['is_active', 'sort_order']);
});
```

### 2. Layer JSON şeması

Her şablon `layers` array'i — sırayla render edilen katmanlar:

```json
[
  {
    "id": "bg",
    "type": "ai_background",
    "x": 0, "y": 0, "w": 1080, "h": 1920,
    "fit": "cover"
  },
  {
    "id": "logo",
    "type": "brand_logo",
    "x": 60, "y": 60, "max_width": 200,
    "anchor": "top-left"
  },
  {
    "id": "title_box",
    "type": "text_box",
    "x": 60, "y": 200, "w": 960,
    "padding": 40,
    "bg_color": "#000000",
    "bg_opacity": 0.55,
    "border_radius": 12,
    "text_key": "title",
    "font_family": "{brand.font}",
    "font_weight": "bold",
    "font_size_max": 72,
    "font_size_min": 36,
    "color": "#ffffff",
    "align": "center",
    "auto_fit": true
  },
  {
    "id": "cta_box",
    "type": "text_box",
    "x": 60, "y": 1500, "w": 960,
    "padding": 40,
    "bg_color": "{brand.primary_color}",
    "bg_opacity": 0.95,
    "text_key": "cta_text",
    "text_default": "Sipariş Ver",
    "format": "{value}\n📞 {brand.phone}\n🌐 {brand.website}",
    "font_size_max": 48,
    "color": "#ffffff",
    "align": "center"
  },
  {
    "id": "tagline",
    "type": "text",
    "x": 540, "y": 1820,
    "anchor": "center",
    "text_value": "{brand.tagline}",
    "font_size": 32,
    "color": "#ffffff"
  }
]
```

**Desteklenen layer türleri:**
- `ai_background` — AI üretilmiş arka plan (sadece bir tane)
- `brand_logo` — Marka logosu (PNG)
- `text` — Düz metin, sabit veya değişken (`{brand.*}` veya `{text_key}`)
- `text_box` — Yarı saydam blok + metin
- `shape` — Sabit renk dikdörtgen/daire/çizgi (vurgu için)
- `image` — Sabit görsel (örn. dekoratif çiçek)

**Değişken interpolasyonu:**
- `{brand.name}`, `{brand.tagline}`, `{brand.phone}`, `{brand.website}`,
  `{brand.instagram_handle}`, `{brand.primary_color}`, `{brand.secondary_color}`,
  `{brand.font}`
- `{text_key}` — kullanıcının girdiği değer
- `{cta_text|Default Değer}` — pipe ile fallback

### 3. PR 2 şablonlarını DB'ye taşı (seeder)

`database/seeders/SystemTemplateSeeder.php` — PR 2'deki 3 hardcoded şablonu
JSON layer formatına çevirip `is_system=true` ile insert eder.

`SocialMediaImageComposer` artık `TemplateRegistry`'den değil DB'den okur.
TemplateRegistry kaldırılır, yerine `SocialMediaTemplate::find($key)` kullanılır.

### 4. Yeni service: `App\Services\Social\TemplateRenderer`

JSON layer config'i alıp GD ile render eder. PR 2'deki sabit `render()` metotları
yerine **tek bir generic renderer**:

```php
public function render(SocialMediaTemplate $template, BrandProfile $brand,
                       GdImage $aiBackground, array $textValues): GdImage
```

Akış:
1. Canvas oluştur (`width × height`)
2. Layer'ları sırayla işle:
   - `ai_background` → resample + draw
   - `brand_logo` → PNG oku + alpha-aware copy
   - `text` / `text_box` → variable interpolation + GdTextRenderer çağrı
   - `shape` → `imagefilledrectangle`/`imagefilledellipse`
3. PNG olarak kaydet

### 5. Visual Editor sayfası: `/admin/social-media/templates/{template}/edit`

WYSIWYG drag-drop yok (aşırı mühendislik) — onun yerine **canvas önizlemeli
form editör**:

```
┌─────────────────┬────────────────────┐
│                 │  KATMANLAR         │
│   [PREVIEW]     │  ┌──────────────┐  │
│   1080×1920     │  │ AI Background │  │
│   (canvas)      │  │ Logo (top-left)│ │
│                 │  │ Title Box     │  │
│                 │  │ CTA Box       │  │
│                 │  │ Tagline       │  │
│                 │  └──────────────┘  │
│                 │  [+ Katman Ekle]   │
│                 │                    │
│                 │  SEÇİLİ KATMAN     │
│                 │  Tip: Text Box     │
│                 │  X: [60] Y: [200]  │
│                 │  W: [960]          │
│                 │  Renk: [#000000]   │
│                 │  Opasite: [0.55]   │
│                 │  ...               │
└─────────────────┴────────────────────┘
[Önizle] [Kaydet] [Sil] [Dışa Aktar]
```

- Sol: küçültülmüş (örn. 540×960) canvas önizleme — her input değişikliğinde
  AJAX ile render edilen PNG döner
- Sağ: katman listesi (sırayı drag-drop ile değiştirme — JS basit, jQuery yok)
- Seçili katmanın özelliklerini düzenleme formu (tip-bazlı dinamik alanlar)
- "Kaydet" → DB'ye yazar
- "Önizle" → mock brand + mock text ile tam boyut render

### 6. Şablon galerisi: `/admin/social-media/templates`

Grid layout:
- Her şablon kartı: preview thumb (PR 2'deki fontlar + mock veri ile pre-rendered),
  ad, "Kullan" butonu (composer create sayfasına yönlendir), "Düzenle", "Kopyala", "Sil"
- System şablonlar düzenlenemez/silinemez ama "Kopyala" ile özel sürüm yapılır
- Filtreler: aspect ratio, kullanım sıklığı

### 7. Şablon export/import

**Export:** Şablon detay sayfasında "Dışa Aktar (JSON)" butonu — `.json` dosya
indirir (layers + metadata, preview path hariç).

**Import:** Galeri sayfasında "Şablon İçe Aktar" butonu — JSON yüklenir,
validate edilir, yeni şablon oluşturulur (key yeni `Str::random(8)` ile).

**Use case:** Kullanıcı bizden şablon almak isterse paylaşılabilir, agency'lerin
müşterilere şablon dağıtması mümkün.

### 8. Composition cleanup cron

`app/Console/Commands/CleanupSocialMediaCompositionsCommand.php`:
- Soft-deleted composition'ların output dosyalarını siler (>30 gün)
- Failed composition'ları kalıcı sil (>7 gün)
- Schedule: günlük 04:30
- Artisan komutu: `php artisan social:cleanup`

### 9. Instagram Posts entegrasyonu (nihai)

`/admin/instagram-posts/create` formundaki mevcut "AI Görsel Üret" butonu
**genişletilir**:

```
┌──────────────────────────────┐
│  Görsel Kaynağı:             │
│  ○ Yükle  ● AI Üret  ○ URL   │
│                              │
│  AI Üretim Modu:             │
│  ○ Sadece arka plan          │
│  ● Şablonlu (önerilen)       │
│                              │
│  Şablon: [Story Promo ▾]     │
│  Başlık: [Bahar Geldi]       │
│  Alt yazı: [...]             │
│  CTA: [Sipariş Ver]          │
│  AI Prompt: [bahar...inekler]│
│                              │
│  [Görseli Üret]              │
└──────────────────────────────┘
```

Üretim sonrası `ai_image_path` form'a otomatik bağlanır (mevcut akış).
Ek bilgi: `social_media_composition_id` instagram_post.meta json'a yazılır
(takip için).

### 10. Bulk import & otomatik üretim

`/admin/instagram-posts/bulk-import` (mevcut)'a yeni mod: **"Şablonlu üretim"**.
Excel/CSV'de:
- `template_key` kolonu
- `text_title`, `text_subtitle`, `text_cta` kolonları
- `ai_prompt` kolonu

Bulk import job (mevcut `ProcessBulkImportRowJob`) bu modda
SocialMediaImageComposer'ı çağırır → toplu görsel üretimi.

### 11. Caption + hashtag entegrasyonu (yeni — eksik halka)

Şu an akış kopuk:
- AI görsel üretimi: Composer
- Caption üretimi: `InstagramCaptionAiService` (ayrı sayfa)

PR 3 ile **tek paket**:

**Composer create sayfasına yeni alan grubu:**
```
☑ Caption + hashtag de üretsin
   Tonalite: ○ Samimi  ● Profesyonel  ○ Eğlenceli  ○ Nostaljik
   Ürün/konu bağlamı: [otomatik prompt'tan al]
```

**Service:** `SocialMediaImageComposer::compose()` parametre genişletmesi:
```php
public function compose(
    string $templateKey,
    string $aiPrompt,
    array $textLayers,
    ?int $userId = null,
    bool $generateCaption = false,         // YENİ
    ?string $captionTone = null,            // YENİ
): SocialMediaComposition
```

`generateCaption=true` ise:
1. Görsel üretildikten sonra `InstagramCaptionAiService::generateFromTopic()` çağır
2. Result composition'a yazılır: yeni kolon `caption` (text), `hashtags` (json)
3. UI'da composition detay sayfasında caption + hashtag gösterilir
4. "Kopyala" butonu ile caption + hashtag birlikte clipboard'a kopyalanır

**Carousel için:** Caption sadece parent composition'a yazılır (Instagram
zaten carousel'a tek caption koyar).

**Migration ekleme** (PR 3'te social_media_compositions tablosuna):
```php
$table->text('caption')->nullable();
$table->json('hashtags')->nullable();
$table->string('caption_tone', 30)->nullable();
```

**Instagram-posts entegrasyonunda:** Composer'dan instagram-posts'a aktarımda
caption + hashtag de aktarılır → form alanları auto-fill.

### 12. Analitik (mini)

`/admin/social-media/templates/{template}` detay sayfasında:
- Bu şablonla üretilen toplam composition sayısı
- Son 30 günde kullanım grafiği (basit chart)
- Ortalama üretim süresi
- Başarı oranı (completed / total)

### 13. Sidebar nav

`Sosyal Medya Görselleri` öğesinin altına alt-link:
- `Şablonlar` (yeni)
- `Üret` (mevcut PR 2)
- `Geçmiş` (mevcut PR 2)

## Teknik karar: WYSIWYG vs Form-based editor

**Form-based editor seçildi**, WYSIWYG drag-drop YOK. Sebep:
- Drag-drop için canvas-fabric.js veya konva.js gerek → ek 200KB JS, jQuery
  istemediğin için vanilla bağımlılık
- Form tabanlı editörle koordinatlar manuel ama **net kontrol** sağlar
- Önizleme AJAX ile her değişiklikte yenilenir → canlı görsel feedback yine var
- Çoğu kullanıcı (sen) "Story Promo'yu kopyala, rengi değiştir" yapacak —
  drag-drop'a gerek yok

PR 4'te (gelecekteki PR) drag-drop eklenebilir.

## Risk ve dikkat noktaları

### 1. Layer JSON validasyonu
Kullanıcı bozuk JSON yüklerse (import) sistem patlamamalı. Validation:
- Top-level array zorunlu
- Her layer'da `type` ve `id` zorunlu
- `type` enum'a uygun
- Koordinatlar pozitif int
- Renk hex regex
- Şablon kayıtta validate, render anında second-pass validate

### 2. System template'leri korumak
`is_system=true` olan şablonlara update/delete request'i gelirse 403. Kopyalanabilir
ama orijinal değişmez. Aksi halde "PR 2 default'ları"'nı kullanıcı bozar, sıfırlama
yolu kalmaz.

### 3. Preview thumbnail
Her şablon listesi için pre-rendered thumb gerek. İlk insert'te ve update'te
otomatik üret (job ile, async). Mock brand + mock text kullanır.

### 4. Performance — galeride 50+ şablon
Pagination + lazy load thumbs. Thumb'lar `public/uploads/social-media/templates/`
altında WebP ile cache'lenir.

### 5. Renk pipe interpolation güvenliği
`{brand.primary_color}` resolved → string. Hex regex ile validate, regex
fail'erse `#000000` fallback. XSS yok çünkü GD'ye geçiyor değer.

### 6. CLAUDE.md uyumu
- ✅ strict_types, SoftDeletes, $fillable
- ✅ FormRequest validation (TemplateRequest, ImportRequest)
- ✅ Service iş mantığı, controller thin
- ✅ AdminModal sil onayı
- ✅ Inline style yasak — visual editor'da CSS classes
- ✅ Cache::remember (template list)
- ✅ Migration down(), index'ler
- ✅ Eager loading (templates with creator, compositions count)
- ✅ exists() not count() (template silinmeden önce kullanım kontrolü)

## Commit stratejisi (parça parça, PR 3 içinde)

10 commit:

1. **Migration + SocialMediaTemplate modeli + layer JSON validation rules**
2. **System template seeder** (PR 2'nin 3 şablonunu DB'ye taşı)
3. **TemplateRenderer service** (JSON layer → GD render generic)
4. **SocialMediaImageComposer refactor** (TemplateRegistry → DB-backed)
5. **TemplateController + Routes + FormRequest** (CRUD + import/export)
6. **Şablon galerisi + visual editor view'ları**
7. **Caption + hashtag entegrasyonu** (composition tablo migrate + composer parametre + UI)
8. **Cleanup command + Instagram-posts entegrasyonu + analitik widget**
9. **Bulk import şablon modu + sidebar nav**
10. **Kullanıcı rehberi** → `docs/sablon-yonetimi-kullanici-rehberi.md`

## Test planı (canlıda)

```bash
php artisan migrate
php artisan db:seed --class=SystemTemplateSeeder
```

1. `/admin/social-media/templates` → 3 system şablon görünmeli
2. Bir tanesini "Kopyala" → yeni şablon (`is_system=false`) → düzenle
3. Editor'de bir text_box rengini değiştir → önizleme güncellensin
4. Kaydet → Üretici sayfasına git → kopyalanan şablon görünsün → kullan
5. Üretilen görselde değişiklik (renk) yansısın
6. Şablonu sil → AdminModal onay → DB'den soft delete
7. JSON export → import → yeni şablon olarak gelsin
8. Instagram-posts/create → yeni "şablonlu üretim" akışı çalışsın
9. Cron: `php artisan social:cleanup --pretend` çalışıp ne sileceğini söylesin

## Sonraki adımlar (PR 3 sonrası)

Bu yol haritası tamamlandığında **3 PR'lık sosyal medya görsel sistemi tamam**.
İleride yeni feature'lar için:
- Drag-drop visual editor (canvas-konva.js)
- Video şablonları (Reels için, FFmpeg)
- A/B test (aynı text iki şablonda üret, hangisi daha iyi engagement aldı)
- Otomatik içerik takvimi (cron'la haftada N görsel üret)
- AI metin üretimi entegrasyonu (text_layer'lar AI'dan gelsin)
- Marka kit yönetimi (birden fazla profil — multi-brand)

Bu PR 4+ olarak gelecek talepler dosyaya eklenir.
