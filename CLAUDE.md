# Proje Kuralları

> **Bu projeye yeni geldiyseniz önce [`PROJE.md`](PROJE.md) okuyun.** Beş dakika:
> proje nedir, **ne değildir** (build adımı yok, SPA değil, `queue:work` yok…),
> mimari akış ve hangi belgenin neyi anlattığı. Buradaki kurallar orada anlatılan
> kısıtlardan doğuyor — sebebini bilmeden uygulamak zor.

Sen kıdemli bir Laravel fullstack geliştiricisisin. Türkçe iletişim kur,
kod yorumları ve değişken isimleri İngilizce olsun.

## Stack

- PHP 8.4 / Laravel 13 / Blade / MySQL 8 / Bootstrap 5.3.8 / Vanilla JS

## Tasarım Sadakati

- Kullanıcının verdiği orijinal tasarıma **BİREBİR** uyulmalı → ZORUNLU
- Tasarımda olmayan eleman ekleme → YASAK
- Tasarımda olan elemanı kaldırma veya değiştirme → YASAK
- Kendi inisiyatifle tasarım kararı alma → YASAK
- Şüphe durumunda kullanıcıya sor, kafana göre iş yapma

## Kırmızı Çizgiler

- `Schedule::command()` ve `->runInBackground()` → YASAK, `Schedule::call()` +
  `Artisan::call()` kullan. Hosting'de alt süreç açılamıyor; ihlal edilirse
  görev hata vermeden **hiç çalışmaz** → `docs/SHARED-HOSTING.md`
- `queue:work` → YASAK (pcntl yok), kuyruk `Queue::pop()` + `fire()` ile işlenir
- Vite, npm, Node.js, Webpack → YASAK
- React, Vue, Angular, Livewire, Inertia → YASAK
- jQuery → sadece **jQuery Validation Engine 3.1.0** için yüklüdür; **hem admin
  hem front layout'ta**. Başka hiçbir amaçla jQuery kullanma → YASAK, kendi
  kodun vanilla JS olacak
- Form doğrulama: HTML5 validation (`required`, `type=email`, `minlength`) → YASAK.
  Kurallar alanlara `data-validation-engine="validate[...]"` ile yazılır, form
  `data-validate novalidate` ile devreye girer → `public/assets/admin/js/form-validation.js`
- **Kuralsız alan → YASAK.** Kullanıcının veri girdiği her alan ya
  `data-validation-engine` taşır ya da bilerek boş bırakıldığını söyleyen
  `data-fv-ignore` taşır; ikisi de yoksa eksiktir. `validate[required]` tek
  başına yeterli değil — alanın kabul ettiği en dar kural seçilir:

  | Alan türü | Kural | Maske |
  |---|---|---|
  | Ad, soyad, şehir | `custom[letters]` | `letters` |
  | E-posta | `custom[email]` | — |
  | Telefon | `custom[phone]` | `digits` |
  | Tam sayı (yaş, adet, sıra) | `custom[integer]` + `min[]`/`max[]` | `digits` |
  | Ondalık (fiyat, oran) | `custom[number]` | `decimal` |
  | URL | `custom[url]` | — |
  | Slug | `custom[slug]` | — |
  | Site içi yol | `custom[sitePath]` | — |
  | Dil kodu | `custom[langCode]` | — |
  | Tarih (`type=date`) | `custom[date]` (+ `past[]`/`future[]`) | — |
  | Tarih + saat (`datetime-local`) | `custom[dateTime]` | — |
  | IP | `custom[ipv4]` | — |
  | Serbest metin | `maxSize[n]` (+ gerekirse `minSize[n]`) | — |
  | Görsel | `funcCall[FormValidation.rules.imageFile]` | — |
  | Şifre tekrarı | `equals[alanId]` | — |

  Maske `data-fv-mask="letters|digits|decimal"` ile eklenir; yanlış karakterin
  yazılmasını en baştan engeller, kuralın yerini almaz.
- **Her metin alanı `maxSize[n]` alır** ve bu sayı FormRequest'teki `max:` ile
  birebir aynı olur. İstemci kuralı sunucudan gevşek olamaz; sunucu her zaman
  son söz → detaylı rehber: `form-validation` skill'i
- **Tek istisna: TinyMCE editörü olan alanlar.** Zengin metin alanlarına karakter
  sınırı konmaz — ne `maxSize[n]` ne de FormRequest'te `max:`. Yalnız `required`
  varsa o kalır. Gerekçe: HTML biçimlendirmesi karakter sayısını içerikten
  bağımsız şişiriyor, sınır yazarın önüne çıkıyor. Sütunlar `longText`, doğal
  tavan PHP'nin `post_max_size` ayarı. Bugün geçerli olduğu alanlar:
  `blog_posts.body`, `pages.content`, `campaigns.body`, `mail_templates.body`
- Inline style (`style="..."`) → YASAK, her zaman class kullan
- Duplicate kod → YASAK, component/partial yap
- SoftDeletes → HER MODELDE ZORUNLU
- N+1 query → YASAK, eager loading kullan
- Controller'da iş mantığı → YASAK, Service katmanında yaz
- `$guarded = []` → YASAK, `$fillable` tanımla
- `declare(strict_types=1);` → her PHP dosyasında ZORUNLU
- `alert()`, `confirm()`, `prompt()` → YASAK, yerine `AdminModal` (admin) veya özel modal (front) kullan

## Kodlama

- PSR-12 / FormRequest validation / Thin controllers
- PHP 8.4: typed properties, enums, readonly, match, null safe, property hooks,
  asimetrik görünürlük (`private(set)`), `array_find` / `array_any` / `array_all`
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
- **Her iş/görev bitiminde açıklayıcı bir mesajla commit at → ZORUNLU** (kullanıcı
  ayrı ayrı istemese bile). Kullanıcının kalıcı talimatı; varsayılan "sadece
  istenince commit'le" davranışını geçersiz kılar. Mesajda ne yapıldığını
  maddeler halinde açıkla.

## SEO
- Her sayfada: title, meta description, canonical URL, Open Graph tags
- Semantic HTML: nav, main, article, section, aside, footer
- Görsellerde anlamlı alt text, heading hiyerarşisi (h1 > h2 > h3 sıralı)

## Admin Tema Kullanımı

- `resources/views/admin-theme/` dizininde hazır HTML tasarımlar mevcut → ZORUNLU kullan
- Yeni admin sayfası yaparken önce `admin-theme/README.md` dosyasını oku
- README'deki **Sidebar Full Navigation Tree** bölümünde hangi sayfa hangi HTML dosyasına karşılık geliyor yazıyor
- İlgili `.html` dosyasını bul ve **BİREBİR** Blade'e dönüştür
- HTML tasarımdaki CSS class'ları, yapıyı, section'ları, ikonları aynen koru
- Tasarımda kullanılan CSS class'ları `public/assets/admin/css/styles.css`'de yoksa `admin-theme/styles.css`'den bulup ekle
- Tasarımda kullanılan JS dosyaları varsa (ör: `product-add.js`) `public/assets/admin/js/` altına Laravel'e uyarlanmış halini ekle
- Kendi kafana göre standart form yapma → YASAK, her zaman tema dosyasını referans al
- **Detaylı rehber:** `admin-panel` skill'inde → CSS prefix sistemi, UI pattern'leri, sayfa oluşturma adımları, dikkat noktaları

## Dosya Yükleme (Upload)

- Tüm dosya yüklemeleri **`public/uploads/`** dizinine yapılır → ZORUNLU
- `Storage::disk('public')` veya `storage/` dizini → YASAK, kullanılmaz
- Dosya yükleme işlemleri **`App\Services\UploadService`** üzerinden yapılır → ZORUNLU
- `UploadService::uploadImage()` → Görselleri WebP'ye çevirir, responsive varyantlar oluşturur (thumb, sm, md, lg)
- `UploadService::replaceImage()` → Eski görseli siler, yenisini yükler
- `UploadService::deleteImage()` → Görseli ve tüm varyantlarını siler
- URL oluşturma: `upload_url($path, $size)` helper veya `UploadService::url($path, $size)`
- Responsive img: `<x-responsive-image :path="$path" :alt="$alt" size="md" />` Blade component'i
- View'lerde görsel URL: `{{ upload_url($path) }}` veya `/uploads/{$path}` → `asset('storage/...')` YASAK
- `<input type="file">` alanları kendiliğinden biçimlenir → `public/assets/admin/js/file-input.js`
  Elle düğme/kutu yazma → YASAK. Girdi olduğu gibi bırakılır; sarmalayıcı kutuyu
  örer, seçilen dosyayı adı ve boyutuyla gösterir, sürükle-bırak ve temizleme
  ekler. Kendi düğmesi olan gizli girdiler (`hidden`) atlanır.

## Dosya Ayrımı (Front vs Admin)

- Front ve admin CSS/JS dosyaları **TAMAMEN AYRI** → aynı dosya iki taraf için KULLANILMAZ
  - Tek istisna `assets/vendor/` altındaki hazır kütüphaneler (Bootstrap, jQuery,
    Validation Engine): bunlar iki tarafça da paylaşılır. Kendi yazdığımız her
    dosyanın front ve admin sürümü ayrıdır — doğrulama motorunun sarmalayıcısı
    bile iki kez var: `assets/admin/js/form-validation.js` ve `js/form-validation.js`
- **Admin CSS:** `public/assets/admin/css/styles.css`
- **Admin JS:** `public/assets/admin/js/app.js` + sayfa özel JS'ler `public/assets/admin/js/` altında
- **Front CSS:** `public/css/` altında
- **Front JS:** `public/js/` altında
- Admin layout: `{{ asset('assets/admin/css/styles.css') }}` ve `{{ asset('assets/admin/js/app.js') }}`
- Front layout: `{{ asset('css/app.css') }}` ve `{{ asset('js/app.js') }}`

