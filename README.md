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

## Ters vekil / CDN arkasında

Site Cloudflare, nginx reverse proxy veya bir yük dengeleyici arkasındaysa
`.env` içindeki `TRUSTED_PROXIES` **mutlaka** doldurulmalı:

```env
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12
```

Doldurulmazsa bağlantı proxy'den geldiği için her ziyaretçi aynı IP'den
görünür. Bunun üç sonucu var ve hiçbiri hata vermez:

- Giriş, iletişim ve kayıt formlarındaki hız sınırları tek kovaya düşer — bir
  kişinin başarısız denemeleri herkesi kilitler, gerçek saldırgan yavaşlamaz
- Ziyaretçi istatistikleri ve denetim kayıtları proxy'nin adresini yazar
- TLS proxy'de sonlandığı için istek HTTP görünür ve HSTS başlığı hiç çıkmaz

Sunucuya yalnızca proxy üzerinden erişilebiliyorsa `TRUSTED_PROXIES=*`
kullanılabilir. Sunucu kendi IP'sinden de cevap veriyorsa kullanılmamalı:
saldırgan proxy'yi atlayıp başlığı kendisi yazar.

Siteye doğrudan erişiliyorsa değer **boş bırakılır** — varsayılan budur.

---

## Çerez rızası

Ziyaretçi karar vermeden **hiçbir izleme çalışmaz**: Google Analytics, Google
Tag Manager ve projenin kendi ziyaret kaydı rızaya bağlıdır. Betikler sayfaya
konup susturulmaz, hiç basılmaz — bir etiket yüklendiği anda istek atar ve
çerezini kurar.

Üç kategori var: **zorunlu** (oturum, güvenlik, dil ve tema — kapatılamaz),
**analitik** (ziyaret kaydı + Google Analytics), **pazarlama** (Tag Manager).

Band JavaScript olmadan da çalışır ve tercihi değiştirmek için de betik
gerekmez: alt bilgideki "Çerez tercihleri" bağlantısı bandı yeniden açar.

Karar `consents` tablosuna da yazılır — çerez tercihi hatırlamak için, tablo
ispat için. KVKK'da açık rıza ispat yükü veri sorumlusundadır ve ziyaretçinin
silebildiği bir çerez bunu kanıtlamaz. Ret de kaydedilir.

Metin değişirse `App\Services\ConsentService::VERSION` artırılır; eski rıza
yeni metne verilmiş sayılmaz ve ziyaretçiye bir kez daha sorulur.

---

## Arama motorları

`robots.txt` ve `sitemap.xml` ikisi de **rota**, `public/` altında dosya değil.
`robots.txt`'in yasak listesi rota tanımlarından, yayındaki dillerden ve
panelden açılmış adreslerden üretilir; elle güncellenmesi gerekmez.

`public/robots.txt` diye bir dosya oluşturulmamalı — web sunucusu var olan
dosyayı rotadan önce basar ve liste o anda donar. `RobotsTest` bunu bekçilik
eder.

Canlı olmayan kurulumlar (`APP_ENV` production değilse) tümüyle kapalı gelir:

```
User-agent: *
Disallow: /
```

---

## Yedekler

**Admin → Yedekler** veritabanını ve `public/uploads` klasörünü tek ZIP
dosyasında toplar; gecelik cron da aynı işi yapar.

**Geri yükleme** listedeki her yedeğin satırındadır. Sırasıyla: arşiv
doğrulanır, **mevcut durumun yedeği alınır**, site bakım moduna geçer,
veritabanı ve dosyalar uygulanır, bakım modundan çıkılır. Güvenlik yedeği
alınamazsa geri yükleme hiç başlamaz.

Bilmeniz gerekenler:

- Veritabanı yedekteki hâline döner — **kullanıcı hesapları ve şifreler de**.
  Kendi oturumunuz kapanabilir; yedekteki bilgilerle yeniden girersiniz.
- Yedekten sonra eklenen dosyalar **silinmez**, arşivdekiler üzerlerine yazılır.
- Geri alınamaz: MySQL şema değişikliklerini işlem içine alamaz. Geriye dönüş
  yolu, işlemden önce otomatik alınan güvenlik yedeğidir.

**Yedek Yükle** düğmesi başka bir sunucudan indirilmiş bir arşivi listeye
ekler; sunucusu gitmiş bir kurulumu ayağa kaldırmanın yolu budur. Dosyanın
gerçekten bir yedek olduğu, arşiv açılıp içeriğine bakılarak doğrulanır.

> Yedek arşivi veritabanının tamamını taşır. `storage/` altında durur ve web
> sunucusu tarafından servis edilmez; oraya taşımayın.

---

## Kuyruk

**Admin → Kuyruk** bekleyen ve başarısız işleri gösterir. Mail gönderimi
kuyruktan geçtiği için "doğrulama maili gelmedi" tipi şikâyetlerin cevabı
genelde buradadır: her başarısız işin tam hata metni saklanır.

Ekrandaki en önemli sayı **en eski işin yaşı**. Bekleyen iş sayısı tek başına
normaldir; birikip yaşlanması kuyruğu boşaltan cron'un çalışmadığını söyler.
10 dakikayı geçtiğinde ekran bunu kırmızı bir uyarıyla bildirir.

Başarısız bir iş yeniden denenebilir, tek tek ya da toplu silinebilir; kuyruk
cron'u beklemeden elle de işlenebilir. Her işlem denetim izine düşer.

İzinler `queue.view` (görüntüleme) ve `queue.manage` (yeniden deneme ve silme);
ikisi de kurulumla yalnızca yöneticide olur, matristen başka rollere
verilebilir.

---

## Hata bildirimi ve loglar

İşlenmeyen bir hata (500) iki yere birden düşer: **Telegram** (Ayarlar → Telegram
açıksa) ve panelin **bildirim merkezi**. Beklenen hatalar — 404, 403, 419, 429,
doğrulama, kimlik — bildirime hiç girmez. Aynı hata için 10 dakikada bir mesaj
gelir, yani döngüye giren bir sayfa telefonu kilitlemez.

Loglar günlük döner ve `LOG_DAILY_DAYS` gün sonra silinir:

```env
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=error
```

`LOG_STACK=single` kullanılırsa `laravel.log` hiç dönmez ve zamanla diski
doldurur; dolduğunda yükleme, yedekleme ve oturum yazımı da durur. **Sistem
Sağlık** ekranı bu durumu log dizini büyümeye başlar başlamaz bildirir.

> Telegram ayarları veritabanından okunduğu için veritabanının kendisi
> düştüğünde bildirim gönderilemez; o senaryoda geriye dosya logu kalır.

---

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
│   ├── Controllers/    İnce; iş mantığı yok (Api/V1/ altında API uçları)
│   ├── Middleware/     Admin, yönlendirme, bakım modu, güvenlik başlıkları
│   ├── Requests/       FormRequest doğrulama
│   ├── Resources/      API'nin dışarı açtığı alanlar (beyaz liste)
│   └── Responses/      API yanıt zarfı (success / message / data)
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

### Denetim izi

**Admin → Aktivite Logları** kim ne zaman ne değiştirdi sorusunu cevaplar. Üç
kaynaktan beslenir ve hiçbiri elle çağrı gerektirmez:

- **Model değişiklikleri** — ayarlar, kullanıcılar, roller, yönlendirmeler,
  panelden açılan adresler, mail şablonları ve diller. Yeni bir kritik model
  eklemek `AppServiceProvider` içindeki listeye tek satır eklemektir.
- **Kimlik doğrulama** — giriş, çıkış ve başarısız giriş denemesi. Denenen
  şifre hiçbir biçimde kaydedilmez.
- **Toplu ve pivot işlemleri** — izin matrisi, kullanıcı rolleri, toplu silme
  ve geri yükleme, şifre sıfırlama. Bunlar model olayı doğurmadığı için ilgili
  servis kaydı kendisi düşer.

İçerik modelleri (sayfa, blog, galeri) bilinçli olarak **dışarıdadır**: her
kaydetmede satır üretip 90 günlük saklama süresi içinde asıl aranan kaydı
bulunamaz hâle getirirlerdi.

Şifre, token ve API anahtarı gibi alanlar `AuditLogger` tarafından maskelenir.

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

**E-posta adresi değişirse doğrulama sıfırlanır.** Damga adrese aittir, hesaba
değil: adres değişip damga yerinde kalsaydı kullanıcı sahibi olmadığı bir adrese
geçip "doğrulanmış" kalabilirdi ve doğrulamaya bakan her yer kanıtlanmamış bir
adrese güvenirdi. Kural `UserObserver` içinde, yani adresi değiştiren her yol
için geçerli — ön yüzdeki profil formu, API'nin profil ucu ve panelden kullanıcı
düzenleme. Yeni adrese kendiliğinden taze bir doğrulama bağlantısı gider; buna
mecbur, çünkü bağlantının imzası e-postanın kendisinden türüyor ve adres
değiştiği anda eski bağlantı zaten çalışmaz hâle geliyor.

Yönetici bir kullanıcının adresini değiştirdiğinde de aynısı olur ve ekranda
bunu söyleyen bir uyarı çıkar — kullanıcı kendisine sorulmadan doğrulanmamış
duruma geçtiği için.

**Aynı anda eski adrese bir güvenlik uyarısı gider** (`email_changed` şablonu).
Hesabı ele geçiren kişinin ilk yaptığı şey çoğu zaman adresi değiştirmektir: o
andan sonra şifre sıfırlama bağlantısı da bildirimler de saldırgana gider ve
gerçek sahibin hesaptan haberi tamamen kesilir. Yeni adrese giden doğrulama
maili bu senaryoda saldırganın kendi kutusuna düşer, yani kimseyi uyarmaz — eski
adrese giden uyarı sahibin durumu öğrenebileceği tek şeydir ve gönderilebileceği
son an değişiklik anıdır.

Uyarıda yeni adres maskeli yazılır (`s***n@baska.com`): tamamen gizlenseydi
sahibi neyin olduğunu anlatamaz, olduğu gibi yazılsaydı bu mail bir adresi
başkasına sızdırmanın yolu olurdu.

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
- `InactiveUserSessionTest` — pasife alınan kullanıcının bir sonraki istekte
  oturumdan düşmesi, açık oturum satırlarının ve `remember_token`'ın silinmesi,
  toplu silmenin de aynı işi yapması
- `TrustedProxyTest` — varsayılanda hiçbir proxy'ye güvenilmemesi, güvenilen
  proxy arkasında gerçek ziyaretçi adresinin geçmesi, HSTS'in iletilen şemayla
  çıkması, aynı proxy arkasındaki iki ziyaretçinin ayrı hız sınırı kovasına
  düşmesi
- `RobotsTest` — yasak listesinin rota tanımlarından üretilmesi, yeni dil
  yayına alınınca genişlemesi, sitemap satırının bu siteyi göstermesi, canlı
  olmayan kopyanın kapalı gelmesi ve `public/robots.txt`'in geri gelmemesi
- `ExceptionNotificationTest` — işlenmeyen hatanın bildirime düşmesi, beklenen
  HTTP hatalarının düşmemesi, aynı hatanın pencerede bir kez bildirilmesi ve
  bildirim kanalı patlasa bile hatanın loga yazılmaya devam etmesi
- `LogHealthCheckTest` — log dizini boyutu ve günlük dönüşün açık olup
  olmadığının Sistem Sağlık ekranında bildirilmesi
- `AuditTrailCoverageTest` — izlenen her modelin denetim izine düşmesi, içerik
  modellerinin bilinçli olarak dışarıda kalması, giriş/çıkış/başarısız
  denemenin kaydı ve denenen şifrenin ize hiç girmemesi
- `QueueMonitorTest` — kuyruk ekranının yetki ayrımı, tıkanan kuyruğun
  bildirilmesi, iş adının yükten çıkarılması, yeniden deneme (okunamayan yük
  dahil), silme ve her işlemin denetim izine düşmesi
- `SqlStatementReaderTest` — SQL dökümünün ifadelere ayrılması: metin içindeki
  noktalı virgül, kaçırılmış tırnak, yorumlar ve parça sınırına denk gelen
  kalıplar
- `BackupRestoreTest` — arşiv doğrulama, Zip Slip koruması, güvenlik yedeğinin
  önce alınması, dosyaların geri yazılması ve **veritabanı dökümü alınamayan
  bir yedeğin başarılı sayılmaması**
- `LikeSearchIsPortableTest` — serbest metin aramasının iki veritabanında da
  aynı davranması: joker karakterin harf sayılması ve MySQL'de sözdizimi hatası
  veren kaçış biçiminin geri gelmemesi
- `CookieConsentTest` — rıza alınmadan hiçbir izleme betiğinin basılmaması ve
  izleme uç noktasının kayıt tutmaması, kararın (kabul, ret, seçmeli)
  kaydedilmesi ve metin sürümü değişince yeniden sorulması
- `Api/ApiAuthTest` — jetonla kayıt/giriş/çıkış, silinmiş hesabın adresinin
  yeniden kullanılabilmesi, pasife alınan hesabın jetonunun bir sonraki istekte
  ölmesi, çıkışın yalnız o cihazı düşürmesi ve kaba kuvvetin sınıra takılması
- `Api/ApiPublicEndpointsTest` — menü ağacı, sayfalama tavanı, taslak yazının
  görünmemesi, listede N+1 olmaması, iletişim formunun yönetim alanlarını
  sızdırmaması ve **`/settings` ucunun SMTP parolasını, reCAPTCHA gizli
  anahtarını, Telegram jetonunu hiçbir koşulda yayınlamaması**
- `EmailValidationTest` — ziyaretçiden alınan e-posta kuralının tek yerde
  durması, üretimde alan adı denetiminin (`dns`) yerinde kalması ve suite'in
  hiçbir sınamada canlı DNS sorgusuna bağımlı olmaması
- `EmailChangeSecurityTest` — adres değişince doğrulama damgasının düşmesi,
  **eski adrese uyarı gitmesi** (yeni adres maskeli), iki mailin iki ayrı
  adrese gitmesi, adres değişmeden yapılan kaydetmenin hiçbirini tetiklememesi,
  posta yolu tıkalıyken bile değişikliğin tamamlanması ve kuralın üç yoldan da
  (ön yüz formu, API ucu, panel) geçerli olması
- `Api/ApiTokenAbilityTest` — dar yetkili jetonun yalnız istediğini
  yapabilmesi, tanınmayan yetkinin (`*` dahil) reddedilmesi — parametre
  yükseltme yüzeyi olmamalı —, dar jetonun her zaman kendini iptal
  edebilmesi ve çerçevenin İngilizce iç metninin ziyaretçiye ulaşmaması
- `Api/ApiCachingTest` — ETag ile 304 dönmesi ve gövdenin hiç inmemesi, içerik
  değişince etiketin değişmesi, `Vary` ile iki dilin aynı önbelleği
  paylaşmaması, içerik listelerinin ve hata yanıtlarının önbelleklenmemesi
- `Api/ApiDeviceTest` — açık oturumların listelenmesi, "bu cihaz"ın tam olarak
  bir satır olması, jetonun hiçbir koşulda sızmaması, başkasının oturumuna
  dokunulamaması (404, 403 değil), "diğerlerinden çık"ın mevcut oturumu
  koruması ve süresi dolmuş jetonların listelenmemesi
- `Api/ApiContentEndpointsTest` — açılış ekranının tek istekte gelmesi, slider
  buton adresinin isteğin diline göre çözülmesi, yalnız onaylı yorumların ağaç
  olarak listelenmesi, yorumun e-posta ve IP'sinin dışarı çıkmaması, yayında
  olmayan yazıya yorum yazılamaması ve tekrar abone olmanın satır
  çoğaltmaması
- `Api/ApiPasswordResetTest` — kodun hash'li saklanması, tek kullanımlık olması,
  süresi dolduğunda reddedilmesi, sıfırlamanın bütün jetonları düşürmesi,
  kayıtlı olmayan adresin ayırt edilememesi ve **kodu kıramaz kılan hız
  sınırının yerinde durması**
- `Api/ApiAccountTest` — profil güncelleme, şifre değiştirmenin mevcut şifreyi
  istemesi, avatarın aynı istekte yüklenmesi ve hesap uçlarının doğrulanmamış
  e-postaya kapalı olması (ön yüzdeki `/hesabim` ile aynı kapı)
- `Api/ApiContractTest` — yanıt zarfının sabitliği (boş `errors` bile nesne),
  bilinmeyen API adresinin HTML yönlendirme değil JSON 404 dönmesi, dilin
  `Accept-Language` / `?lang=` / `X-Locale` ile çözülmesi, desteklenmeyen dilin
  hata değil varsayılana düşüş olması ve CORS ön uçuşunun yanıtlanması

---

## Kod kalitesi

Üç kontrol var ve üçü de her push'ta GitHub Actions'ta koşar:

```bash
composer check      # üçünü sırayla
composer lint       # kod stili  (pint --test)
composer analyse    # statik analiz (phpstan)
composer test       # testler
```

**Kod stili.** `pint.json` projenin kendi biçimini tanımlar: dizi ve atamalardaki
hizalama korunur, birleştirmede boşluk kullanılır, `!` sonrası boşluk bırakılır.
Laravel'in varsayılan preset'i bunların tersini dayattığı için 459 dosya sapıyor
görünüyordu ve çıktı hiçbir işe yaramıyordu; artık **sapma sıfır** ve
`./vendor/bin/pint` (fix modu) güvenle çalıştırılabilir — yapılandırma
hizalamaya dokunan kuralları kapalı tutar.

**Statik analiz.** `phpstan.neon`, Larastan ile seviye 1. Seviye seçiminin
gerekçesi ve yukarı çıkmanın yolu dosyanın kendi yorumlarında.

Komut `-a phpstan-bootstrap.php` ile çalışır ve bu bayrak isteğe bağlı değildir.
Larastan stub dosyalarını Laravel sürümüne göre süzerken `LARAVEL_VERSION`
sabitini okuyor; sabiti tanımlayansa Larastan'ın kendi bootstrap dosyası. PHPStan
bu ikisini her zaman aynı sırada çalıştırmıyor — sonuç önbelleği belirli bir
durumdayken stub listesi bootstrap'tan önce isteniyor ve analiz

```
Undefined constant "Larastan\Larastan\LARAVEL_VERSION"
```

diyerek düşüyor. `phpstan-bootstrap.php` sabiti PHPStan'ın kabı kurulmadan
tanımlayarak yarışı ortadan kaldırıyor; gerekçenin tamamı dosyanın kendi
yorumunda.

> Bu hatayı yine de görürseniz (`-a` bayrağı olmadan çalıştırıldığında),
> `./vendor/bin/phpstan clear-result-cache` geçici olarak kurtarır.
>
> Hatanın **uygulamanın açılamamasından** kaynaklandığı ayrı bir durum daha var
> ve o zaman gerçek sebep çıktının **başında** basılır (`Error: ...` ve yığın
> izi) — `tail` ile bakılırsa kaçırılır. O durumda `php artisan about`
> uygulamanın açılıp açılmadığını söyler; en sık sebebi paket eklendikten sonra
> geride kalan otomatik yükleyicidir (`composer dump-autoload`).

**Testler CI'da MySQL 8'e karşı koşar**, yerelde SQLite'a karşı. İkisi aynı şeyi
kabul etmiyor — bu iş akışı kurulduğu gün SQLite'ın sakladığı altı hata çıktı,
biri arama yapan her ekranı üretimde 500'e düşürüyordu. Yerelde de MySQL'e karşı
koşmak için `DB_CONNECTION` ve `DB_DATABASE` değişkenlerini komut satırında
vermek yeter; `phpunit.xml` içindeki değerler var olan ortam değişkenini ezmez.

---

## API (mobil ve harici istemciler)

Site, Blade ile üretilen web arayüzünün yanında **Laravel Sanctum** jetonuyla
konuşan istemcilere de (Flutter uygulaması, harici bir SPA) hizmet verir. İki
taraf aynı Service katmanını kullanır: panelden değiştirilen bir menü, ayar veya
yazı ikisinde birden değişir.

```
GET  /api/v1/home                 Açılış ekranı: slider + son yazılar + galeri
GET  /api/v1/languages            Yayındaki diller
GET  /api/v1/settings             Dışarı açılan ayarlar (gruplara göre)
GET  /api/v1/translations         Arayüz metinleri
GET  /api/v1/menus                Menüler, ağaç hâlinde, adresleri çözülmüş
GET  /api/v1/pages                Yayındaki sayfalar (menü için)
GET  /api/v1/pages/{slug}         Sayfa içeriği — gizlilik, KVKK, hakkımızda
GET  /api/v1/blog/posts           Yazılar (sayfalı) — ?category, ?per_page
GET  /api/v1/blog/posts/{slug}    Yazı detayı
GET  /api/v1/blog/categories      Kategoriler
GET  /api/v1/blog/posts/{slug}/comments  Onaylı yorumlar (ağaç)
POST /api/v1/blog/comments        Yorum gönder — onay bekler
GET  /api/v1/gallery              Galeri — ?category, ?type=photo|video
GET  /api/v1/gallery/categories   Galeri kategorileri
GET  /api/v1/sliders              Ana sayfa görsel şeridi
GET  /api/v1/faqs                 Sıkça sorulan sorular
POST /api/v1/contact              İletişim formu
POST /api/v1/newsletter/subscribe Bülten aboneliği

POST /api/v1/auth/register        Kayıt (jeton döner)
POST /api/v1/auth/login           Giriş (jeton döner)
POST /api/v1/auth/password/forgot Altı haneli sıfırlama kodu gönderir
POST /api/v1/auth/password/reset  Kodla şifreyi değiştirir
POST /api/v1/auth/logout          Bu cihazın jetonunu siler    [jeton gerekli]
GET  /api/v1/auth/me              Giriş yapmış kullanıcı       [jeton gerekli]
POST /api/v1/auth/email/resend    Doğrulama bağlantısı         [jeton gerekli]
GET  /api/v1/auth/devices         Açık oturumlar               [jeton gerekli]
DEL  /api/v1/auth/devices/{id}    Tek oturumu kapatır          [jeton gerekli]
DEL  /api/v1/auth/devices         Bu cihaz hariç hepsi         [jeton gerekli]
PUT  /api/v1/account/profile      Profil + avatar + şifre      [jeton + doğrulanmış]
```

Her yanıt aynı zarfı taşır:

```json
{ "success": true, "message": "İşlem başarılı.", "data": { } }
{ "success": false, "message": "...", "errors": { "email": ["..."] } }
```

Dil `Accept-Language` (ya da `?lang=` / `X-Locale`) ile seçilir; sitede olmayan
bir dil hata değil, varsayılana düşüş sebebidir. Seçilen dil `Content-Language`
ile bildirilir.

Şifre sıfırlama mobilde **bağlantı değil altı haneli kod** ile çalışır —
uygulama tarayıcıya hiç çıkmaz. Kodun güvenliği hız sınırına bağlıdır
(`API_RATE_LIMIT_PASSWORD`); gerekçesi `App\Services\PasswordResetCodeService`
içinde yazılı.

`/settings` ucu **tabloyu olduğu gibi basmaz**: yayınlanacak gruplar ve elenen
anahtarlar `config/api.php` içinde beyaz liste olarak durur, tipi `password`
olan ya da adında `secret` / `token` / `password` geçen hiçbir satır dışarı
çıkmaz.

Kurulum için `.env`'e eklenecekler ve tüm uçların ayrıntısı: **`docs/API.md`**.

---

## Ek dokümanlar

- `CLAUDE.md` — proje kuralları (zorunlu)
- `docs/PROJE-DURUMU.md` — mevcut durum, bilinen eksikler, yapılacaklar
- `docs/SHARED-HOSTING.md` — cron, kuyruk ve hosting kısıtlamaları (zorunlu)
- `docs/API.md` — mobil ve harici istemciler için API (v1) referansı
- `resources/views/admin-theme/README.md` — tema referansı
