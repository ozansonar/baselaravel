# Laravel Base Kit

Kurumsal web sitesi ve admin paneli için yeniden kullanılabilir başlangıç altyapısı.
Yeni bir proje bu depoyu klonlayıp üstüne kendi modüllerini ekleyerek başlar; blog,
galeri, menü, ayarlar, mail şablonu/logu, yedekleme, analitik ve yetkilendirme gibi
her projede tekrar eden işler hazır gelir.

**Stack:** PHP 8.4 · Laravel 13 · Blade · MySQL 8 · Bootstrap 5.3.8 · Vanilla JS

> **Build tool yok.** Vite, npm, Node.js, Webpack kullanılmaz. Tüm vendor
> kütüphaneleri `public/assets/vendor/` altında hazır dosya olarak durur ve
> `asset()` ile dahil edilir. Cache busting için `versioned_asset()` dosyanın
> kendi `mtime` değerini ekler; bundler hash'ine gerek yok.
>
> Bu kural `NoBuildToolchainTest` ile korunuyor — klonlanan bir projeye
> `package.json` veya `vite.config.js` geri girdiği anda suite kırılır.

---

## Gereksinimler

| | Sürüm |
|---|---|
| PHP | 8.4+ (`composer.json` platform pini: 8.4.2) |
| Composer | 2.x |
| Veritabanı | MySQL 8 (geliştirmede SQLite de çalışır) |
| PHP eklentileri | Laravel 13'ün standart seti + `gd` veya `imagick` (görsel işleme) |

Node.js **gerekmez**.

---

## Kurulum

```bash
git clone <depo-adresi> proje-adi && cd proje-adi
composer setup
```

`composer setup` şunları sırayla yapar:

1. `composer install`
2. `.env` yoksa `.env.example`'dan kopyalar
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed --force`

Kurulum bittiğinde panele girebileceğin hazır kullanıcılar oluşur:

| E-posta | Rol |
|---|---|
| admin@example.com | Yönetici |
| editor@example.com | Editör |
| user@example.com | Kullanıcı |

Üçünün şifresi `.env` içindeki `SEED_PASSWORD` değeridir; tanımlı değilse
`config/seeding.php` içindeki varsayılan (`Demo*12345.`) kullanılır. Kurulumdan
önce `.env` dosyana kendi şifreni yaz:

```env
SEED_PASSWORD=kendi-guclu-sifren
```

> **Canlıya almadan önce admin şifresini panelden değiştir ve ihtiyacın olmayan
> demo hesapları sil.** Depodan gelen varsayılan şifre herkesçe bilinir.

### Yeni projeye uyarlama

Projeye özgü isim tek yerden gelir — `.env` içindeki `APP_NAME`. Seeder
site adını, site başlığını ve footer telif metnini bundan üretir; logo
yüklenene kadar sidebar'da gösterilen monogram da site adından hesaplanır
(`Acme Yazılım` → `AY`).

Geri kalan her şey admin panelindeki **Ayarlar** ekranından yönetilir: logo,
favicon, iletişim bilgileri, sosyal medya, SEO/OG etiketleri, mail sunucusu,
mail teması, Telegram bildirimleri, dil ve saat dilimi.

Ayrıca değiştirmek isteyebileceklerin:

- `composer.json` → `name` ve `description`
- `resources/views/admin-theme/` → tema referans dosyaları (opsiyonel, silinebilir)
- Seeder'lardaki örnek içerik: `PageSeeder`, `BlogSeeder`, `FaqSeeder`, `DemoMediaSeeder`

### Sunucuyu çalıştırma

```bash
composer dev
```

`php artisan serve` çalıştırır. Varsayılan adres: http://127.0.0.1:8000

---

## Zamanlanmış görevler (cron)

Hosting panelinde **tek bir** cron tanımlanır, her dakika çalışacak şekilde:

```
php /home/KULLANICI/public_html/artisan schedule:run >> /dev/null 2>&1
```

Laravel scheduler hangi görevin zamanı geldiğine kendisi karar verir; her görev
için ayrı cron tanımlamaya gerek yoktur.

| Saat | Görev |
|------|-------|
| her dakika | Kuyruk işlerini işler |
| her 5 dakika | Toplu mail gönderimi (`campaigns:dispatch`) |
| 02:00 | Günlük ziyaret istatistiklerini toplar |
| 03:00 | 90 günden eski IP'leri maskeler (KVKK) |
| 03:30 Pazar | 90 günden eski denetim kayıtlarını siler |
| 04:00 Pazar | 365 günden eski ziyaret kayıtlarını siler |
| 05:00 | Veritabanı + uploads yedeği alır |

Kontrol:

```bash
php artisan schedule:list
```

> **Önemli:** Bu proje shared hosting'de alt süreç açamıyor. Zamanlanmış
> görevler `Schedule::command()` ile **tanımlanamaz** — öyle tanımlanan bir
> görev hata vermeden hiç çalışmaz. Ayrıntı ve kurallar:
> [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md)

## Yazma izinleri

```bash
chmod -R 775 storage bootstrap/cache public/uploads
```

Dosya yüklemeleri `storage/` altına değil **`public/uploads/`** altına yapılır;
`App\Services\UploadService` görselleri WebP'ye çevirir ve responsive varyantlar
üretir.

---

## Dizin yapısı

```
app/
├── Console/Commands/   Artisan komutları (yedek, analitik, log temizliği)
├── Enums/              Sabit seçenek listeleri — hardcoded liste yasak
├── Helpers/            Global fonksiyonlar (upload_url, site_initials, ...)
├── Http/
│   ├── Controllers/    İnce; iş mantığı yok
│   ├── Middleware/     Admin, yönlendirme, bakım modu, güvenlik başlıkları
│   └── Requests/       FormRequest doğrulama
├── Models/             Eloquent — hepsinde SoftDeletes
├── Observers/          Model olayları + cascade soft delete
├── Policies/           Yetkilendirme
├── Rules/              Özel doğrulama kuralları
└── Services/           İş mantığı BURADA

resources/views/
├── admin/              Panel ekranları
├── admin-theme/        Hazır HTML tema referansları
├── components/         Blade component'leri (enum-select, responsive-image)
├── emails/             Mail şablonları
├── layouts/            app (front), admin, auth
└── partials/           Ortak parçalar

public/
├── assets/admin/       Panel CSS + JS
├── assets/vendor/      Self-hosted kütüphaneler
├── css/ js/            Front CSS + JS
└── uploads/            Yüklenen dosyalar
```

**Front ve admin varlıkları tamamen ayrıdır**, aynı dosya iki taraf için kullanılmaz.

---

## Mimari

```
Route → Controller (ince) → FormRequest (doğrulama)
                          → Policy (yetkilendirme)
                          → Service (iş mantığı)
                          → Model (+ Observer)
```

Uyulması zorunlu kurallar `CLAUDE.md` dosyasında. Özet:

- Her PHP dosyasında `declare(strict_types=1)`
- Controller'da iş mantığı yok, her şey Service katmanında
- Her modelde `SoftDeletes` ve `$fillable` (`$guarded = []` yasak)
- Sabit seçenek listeleri `app/Enums/` altında backed enum olur, Blade'e
  hardcoded `<option>` yazılmaz
- Cascade silme foreign key ile değil Observer ile yönetilir
- `alert()` / `confirm()` yerine `AdminModal` (panel) veya modal (front)
- N+1 sorgu yasak, eager loading kullan

### Yeni admin sayfası ekleme

`resources/views/admin-theme/` altında hazır HTML tasarımlar var. Yeni bir panel
sayfası yaparken önce `admin-theme/README.md` içindeki **Sidebar Full Navigation
Tree** bölümünden hangi sayfanın hangi HTML dosyasına karşılık geldiğine bak ve
o dosyayı birebir Blade'e çevir. Detaylı rehber: `.claude/skills/admin-panel/`.

---

## Roller ve yetkiler

Yetkilendirme **veritabanı tabanlıdır**: kullanıcı rolleri taşır, roller izinleri
taşır, Policy ve Gate'ler kullanıcıya "şu izne sahip mi?" diye sorar. Bir rolün
ne yapabileceğini değiştirmek için kod değiştirmek gerekmez — panelde
**Roller & İzinler** ekranındaki matris yeterlidir.

- İzinlerin tek kaynağı `app/Enums/PermissionKey.php`. Yeni bir yetenek eklemek
  için oraya bir case eklenir; seeder ve matris ekranı kendiliğinden takip eder.
- İzinler dört grupta toplanır: İçerik, Medya & Dosya, İletişim, Sistem.
- Panele giriş, en az bir izne sahip olmakla belirlenir; hangi ekranı göreceği
  ise ekran bazında Policy'lere bağlıdır.

Kurulumla gelen roller ve varsayılan yetkileri:

| Rol | Varsayılan yetki |
|---|---|
| Yönetici (`admin`) | Tüm izinler. Bu rolün izinleri kilitlidir; matristen kısılamaz, aksi hâlde panele giriş tamamen kapanabilirdi. |
| Editör (`editor`) | İçerik, medya, analitik, mesaj yanıtlama, yorum moderasyonu. Silme yetkisi yok. |
| Moderatör (`moderator`) | Mesaj yanıtlama ve yorum moderasyonu. |
| Kullanıcı (`user`), İzleyici (`viewer`) | Panel yetkisi yok. |

`UserRole` enum'undaki roller **sistem rolüdür**: yeniden adlandırılabilir ama
silinemez ve anahtarları değiştirilemez. Panelden istediğin kadar **özel rol**
ekleyip izinlerini matristen verebilirsin.

## Çok dilli yapı

Site birden fazla dilde yayınlanabilir. Diller **Admin → Diller** ekranından
yönetilir; yeni dil eklemek için kod değiştirmek gerekmez. Kurulumla Türkçe
(varsayılan) ve İngilizce yayında, Almanca/Fransızca/İtalyanca pasif gelir.

### Diller ekranı

| İşlem | Nasıl |
|---|---|
| **Ekle** | "Dil Ekle" → iki harfli kod (`de`), ad, kendi dilindeki ad, bayrak emojisi |
| **Güncelle** | Satırdaki kalem düğmesi |
| **Yayına al / kaldır** | Düzenleme penceresindeki "Yayında" anahtarı |
| **Varsayılan yap** | Satırdaki yıldız düğmesi |
| **Sil** | Satırdaki çöp kutusu |

Liste her dil için şunları gösterir: yayında mı, **arayüz çeviri dosyası var mı**
(`lang/{kod}/`) ve o dilde kaç içerik kaydı olduğu — bir dili kaldırmadan önce
neyin gizleneceğini görürsün.

**Tam olarak bir varsayılan dil** olur ve bu kural arayüzde de uygulanır:
varsayılan dilin silme ve varsayılan-yapma düğmeleri hiç görünmez, "Yayında"
anahtarı kapatılamaz. Başka bir dili varsayılan yapmak öncekinin işaretini
kaldırır; pasif bir dili varsayılan yaparsan otomatik yayına alınır. Son dil
silinemez.

> Yeni bir dil eklediğinde **içerik** hemen panelden dil sekmeleriyle girilebilir.
> **Arayüz metinleri** için `lang/tr/` klasörünü yeni dil koduyla kopyalayıp
> çevirmen gerekir; o zamana kadar arayüz varsayılan dilde görünür (ham anahtar
> basılmaz). Ekran hangi dillerde bu dosyaların eksik olduğunu işaretler.

### Ziyaretçi hangi dili görür

1. Sağ üstteki seçiciden seçtiği dil
2. Tarayıcısının `Accept-Language` başlığındaki en iyi eşleşme
   (`de-AT` → Almanca)
3. Varsayılan dil

### İçerik

Her içerik **dil başına ayrı satır** olarak saklanır; aynı içeriğin farklı
dillerdeki sürümleri ortak bir `lang_group_id` ile bağlıdır. Bunun pratik
sonuçları:

- **Görsel de dile aittir.** Üzerinde Türkçe yazı olan görseli Türkçe sekmesine,
  İngilizcesini İngilizce sekmesine yüklersiniz.
- Slug yalnızca kendi dili içinde benzersizdir; Türkçe ve İngilizce ikisi de
  `contact` kullanabilir.
- Kategoriler de çevrilir, İngilizce içerik İngilizce kategoriye bağlanır.
- Henüz çevrilmemiş içerik siteden kaybolmaz, varsayılan dilden gösterilir.

### Formlar

İçerik formları her aktif dil için bir sekme açar. **Yalnızca varsayılan dil
zorunludur**, diğerlerini sonra doldurabilirsiniz. Boş bırakılan bir sekme
mevcut çeviriyi silmez. Çevirisi olmayan dil sekmede "Çeviri yok" rozetiyle
işaretlenir.

Yeni bir modülü çok dilli yapmak için `App\Services\Concerns\SyncsTranslations`
trait'ini servise ekleyin, formu `<x-language-tabs>` ile sarın ve alanları
`translations[{locale}][field]` olarak isimlendirin.

### Arayüz metinleri

İçerik veritabanından gelir, **arayüz metinleri** (buton, başlık, form etiketi,
placeholder, `aria-label`) `lang/{kod}/site.php` dosyalarından. Kurulumla `tr`
ve `en` dosyaları gelir.

Bu metinler **Admin → Dil Yazıları** ekranından düzenlenebilir. Çalışma şekli:

- Dosya **varsayılan** olarak kalır, veritabanı yalnızca **değiştirdiklerini**
  tutar
- Değiştirilen metin dosyanın üzerine biner, geri kalan her şey dosyadan gelir
- "Varsayılana Dön" ile bir dilin tüm değişiklikleri silinir, dosya hâline döner
- Bir alanı boşaltmak da o metni varsayılana döndürür

Neden dosyaya yazmıyor: **deploy `git pull` ile yapılıyor.** Değişiklikler
`lang/` dosyalarına yazılsaydı her deploy hepsini sessizce silerdi. Dosyayı
varsayılan tutmak ayrıca "varsayılana dön"ü mümkün kılıyor.

**Performans:** bir dilin tüm değişiklikleri tek dizi olarak
`Cache::rememberForever` ile tutulur ve Laravel çeviri grubunu istek başına bir
kez yüklerken üzerine bindirilir. Yani ısınmış bir sayfa render'ı **sıfır**
sorgu atar — test bunu doğruluyor.

Yeni dil eklediğinde `lang/tr/site.php` dosyasını yeni kodun klasörüne
kopyalayıp değerleri çevir; dosya yoksa arayüz varsayılan dile düşer, ham
anahtar (`site.nav.home`) sayfaya basılmaz. Panelden de girebilirsin ama
dosyaya yazmak sürüm kontrolüne girdiği için tercih edilir.

> Menüdeki bağlantı etiketleri buradan değil, **Menü Yönetimi**'nden gelir —
> onlar veritabanı içeriğidir.

`InterfaceTranslationTest` iki şeyi bekçilik eder: dil dosyalarının anahtar
kümesi birebir aynıdır (eksik anahtar sayfayı sessizce yarı çevrilmiş gösterir)
ve Blade'de kullanılan her anahtar tanımlıdır.

### Navigasyon

Menüler de dile aittir: her dilin kendi menüsü ve kendi öğe ağacı vardır, yani
bir dil daha az bağlantı veya farklı bir sıra gösterebilir. Menüsü olmayan bir
dil varsayılan dilin menüsüne düşer, site hiçbir zaman navigasyonsuz kalmaz.

**Menü Yönetimi** ekranında her menü kartında bir çeviri butonu vardır; menüyü
tüm öğe ağacıyla birlikte başka bir dile kopyalar. Kopya yapıyı ve bağlantıları
korur, geriye yalnızca etiketleri çevirmek kalır. Sayfa bağlantıları kopyalanırken
hedef dilin slug'ına çevrilir; o dilde çevirisi yoksa slug olduğu gibi kalır.

---

## Toplu mail (kampanyalar)

**Admin → Mail Kampanyaları**'ndan üyelere, bülten listesine, Excel'den
yüklediğin kişilere veya elle yazdığın adreslere toplu mail gönderilir.

### Gönderim hızı

Mail sağlayıcıları listeyi tek seferde boşaltan hesapları kısıtlar veya
kara listeye alır. Bu yüzden gönderim saate yayılır:

```
cron her 5 dakikada bir çalışır  →  saatte 12 tur
saatlik limit 100                →  tur başına ceil(100/12) = 9 mail
```

Saatlik limit **son 60 dakikada gerçekten gönderilen** mail sayısından
hesaplanır, saate bakılarak değil — cron kaçarsa veya iki kez çalışırsa limit
yine aşılmaz. Ayarlar → Mail altından değiştirilir:

| Ayar | Anlamı |
|---|---|
| `mail_hourly_limit` | Saatte gönderilecek en fazla mail (0 = gönderim durur) |
| `mail_batch_max` | Tur başına sabit adet. 0 ise limitten hesaplanır |
| `mail_max_attempts` | Başarısız bir adres kaç kez denenir |
| `newsletter_enabled` | Ön yüzdeki abonelik formunu gösterir/gizler |

Cron kurulumu için [Zamanlanmış görevler](#zamanlanmış-görevler-cron) bölümüne
bak; `campaigns:dispatch` komutu scheduler'a bağlıdır.

### Akış

1. **Taslak oluştur** — konu, içerik (TinyMCE), ekler, alıcı kitlesi
2. **Onay ekranı** — kaç kişiye gideceği, alıcılardan örnek, cron ne zaman
   çalışacak, tahmini bitiş
3. **Onayla** — alıcı listesi o an dondurulur, kampanya sıraya girer
4. **İzle** — gönderilen / sırada / gönderilemeyen sayıları, ilerleme çubuğu,
   sıradaki cron için geri sayım. Gönderim sürerken duraklat veya iptal et.

Göndermeden önce kendine test maili atabilirsin; konusu `[TEST]` ile işaretlenir
ve listeye gitmez.

### Alıcı kaynakları

- **Site üyeleri** — rol, aktiflik ve e-posta doğrulaması ile filtrelenir
- **Mail listesi** — ön yüz formundan veya panelden eklenen aboneler
- **Excel / CSV** — `.xlsx` ve `.csv`. Başlık satırı `Ad` / `E-posta` olarak
  eşleştirilir, başlık yoksa adres sütunu otomatik bulunur. Panelde **örnek
  şablon indirme** düğmesi vardır. Türkçe Excel'in noktalı virgüllü CSV'si ve
  BOM'u desteklenir.
- **Elle giriş** — her satıra bir kişi:
  `Ad Soyad <mail@ornek.com>`, `Ad Soyad;mail@ornek.com` veya yalnızca adres

Aynı adres birden fazla kaynakta olsa da tek mail alır.

### İçerik

`{name}`, `{email}` ve `{site_name}` her alıcı için ayrı doldurulur.

**Görseller mailin içine gömülür** (CID), bağlantı olarak eklenmez: mail
programlarının çoğu uzak görselleri varsayılan olarak engeller ve mail
iletildiğinde ya da çevrimdışı okunduğunda bağlantılı görsel tamamen kaybolur.
Sitenin dışındaki görseller olduğu gibi bırakılır.

### Abonelikten çıkma

Her mail alıcıya özel bir çıkış bağlantısı taşır — hem gövdede hem
`List-Unsubscribe` başlığında, yani mail programı kendi "abonelikten çık"
düğmesini gösterir. Bağlantı giriş gerektirmez.

Elle girilen ve Excel'den yüklenen alıcılar da kendi çıkış anahtarını alır.
Çıkan adres `subscribers` tablosuna engelleme kaydı olarak yazılır ve sonraki
kampanyaların hiçbirine dahil edilmez.

### Yetkiler

Taslak yazmak ile göndermek ayrı yetkilerdir: `campaigns.manage` içerik
hazırlar, `campaigns.send` tüm listeye ulaşan gönderimi başlatır. Kurulumla
editör rolü yalnızca taslak hazırlayabilir.

---

## E-posta doğrulama

Kayıt olan kullanıcıya doğrulama bağlantısı gönderilir ve `/hesabim` alanı
doğrulanana kadar kapalıdır (`verified` middleware). Bağlantı 60 dakika geçerli
imzalı bir URL'dir.

Mail, Laravel'in kendi bildirimini değil projenin mail altyapısını kullanır:
şablon **Mail Şablonları** ekranından düzenlenebilir (`verify_email`) ve gönderim
mail loglarına düşer.

Doğrulamayı zorunlu tutmak istemiyorsan `routes/web.php` içindeki hesap grubundan
`verified` middleware'ini çıkarman yeterli:

```php
Route::middleware(['auth', 'verified'])->prefix('hesabim')  // → ['auth']
```

---

## Testler

```bash
composer test
```

`tests/Feature` altında kuralların kendi kendini koruduğu testler var:

- `SoftDeleteRetentionTest` — her modelde SoftDeletes olduğunu reflection ile
  doğrular, saklama süresi temizliklerinin satırları gerçekten sildiğini kontrol eder
- `AdminAuthorizationTest` — rol/ekran yetki matrisi
- `ObserverCascadeTest` — cascade'in observer'lardan geldiğini, foreign key'de
  CASCADE kalmadığını doğrular
- `EnumDrivenOptionsTest` — enum case'lerinin ekranlara düştüğünü doğrular
- `RedirectTargetValidationTest` — açık yönlendirme koruması
- `CampaignDispatchTest` — saatlik limit ve tur kotası matematiği, kotanın
  pencere kayınca serbest kalması, duraklat/sürdür/iptal, alıcı listesinin
  dondurulması, tekrar denemenin başarısızlık sayılmaması
- `CampaignPanelTest` — taslak→onay→gönderim akışı, Excel/elle giriş, yetki
  ayrımı, abonelik ve çıkış
- `CampaignMailContentTest` — görsellerin CID olarak gömülmesi, dış görsellere
  dokunulmaması, dizin dışına çıkan yolun reddi, CSV/XLSX okuma
- `NoBuildToolchainTest` — `package.json`, `vite.config.js`, `node_modules`,
  `resources/js` gibi build tool kalıntılarının geri girmediğini ve hiçbir
  view'ın `@vite` / `mix()` kullanmadığını doğrular
- `ImageUploadTest` — WebP dönüşümü, responsive varyantlar, silme/değiştirmede
  varyantların da temizlenmesi, `url()` / `srcset()` çözümlemesi
- `MailDeliveryTest` — şablon render'ı, gönderim/kuyruk loglama, hata durumunda
  loglanıp fırlatılmaması, her mail sınıfının panelde bir şablonu olması
- `InterfaceTranslationTest` — dil dosyalarının anahtar denkliği, Blade'de
  kullanılan her anahtarın tanımlı olması, arayüzün tarayıcı diline uyması
- `LocalizedMenuTest` — dile göre navigasyon, menüsü olmayan dilin varsayılana
  düşmesi, menünün başka bir dile kopyalanması
- `LanguagePanelTest` — dil ekleme/güncelleme/silme ekranı, "tek varsayılan"
  kuralının her değişiklikten sonra korunması, yetki ayrımı
- `TranslationOverrideTest` — arayüz metinlerinin panelden düzenlenmesi,
  varsayılana eşit değerin saklanmaması, ısınmış sayfanın sıfır sorgu atması

---

## Kod stili

`laravel/pint` bağımlılık olarak gelir ancak **`pint` fix modunda çalıştırılmaz.**
Kod tabanı dizi ve atamalarda hizalama kullanır, Pint'in varsayılan Laravel
preset'i bu hizalamayı bozar. Stil kontrolü için yalnızca `pint --test`.

---

## Ek dokümanlar

- `CLAUDE.md` — proje kuralları (zorunlu)
- `docs/PROJE-DURUMU.md` — mevcut durum, bilinen eksikler, yapılacaklar
- `docs/SHARED-HOSTING.md` — cron, kuyruk ve hosting kısıtlamaları (zorunlu)
- `resources/views/admin-theme/README.md` — tema referansı
