# Laravel Base Kit

Kurumsal web sitesi ve admin paneli için yeniden kullanılabilir başlangıç altyapısı.
Yeni bir proje bu depoyu klonlayıp üstüne kendi modüllerini ekleyerek başlar; blog,
galeri, menü, ayarlar, mail şablonu/logu, yedekleme, analitik ve yetkilendirme gibi
her projede tekrar eden işler hazır gelir.

**Stack:** PHP 8.3 · Laravel 13 · Blade · MySQL 8 · Bootstrap 5.3.8 · Vanilla JS

> **Build tool yok.** Vite, npm, Node.js, Webpack kullanılmaz. Tüm vendor
> kütüphaneleri `public/assets/vendor/` altında hazır dosya olarak durur ve
> `asset()` ile dahil edilir.

---

## Gereksinimler

| | Sürüm |
|---|---|
| PHP | 8.3+ (`composer.json` platform pini: 8.3.30) |
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

| E-posta | Şifre | Rol |
|---|---|---|
| admin@example.com | `password` | Yönetici |
| editor@example.com | `password` | Editör |
| user@example.com | `password` | Kullanıcı |

> **Canlıya almadan önce bu üç kullanıcının şifresini değiştir veya
> gereksizleri sil.**

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
- Seeder'lardaki örnek içerik: `PageSeeder`, `BlogSeeder`, `FaqSeeder`, `SliderSeeder`

### Sunucuyu çalıştırma

```bash
composer dev
```

`php artisan serve` çalıştırır. Varsayılan adres: http://127.0.0.1:8000

---

## Zamanlanmış görevler (cron)

Sunucuya **tek bir cron satırı** eklemek yeterli:

```bash
* * * * * cd /proje/yolu && php artisan schedule:run >> /dev/null 2>&1
```

`routes/console.php` içinde tanımlı görevler:

| Görev | Sıklık | Açıklama |
|---|---|---|
| `queue-worker` | Her dakika | Kuyruktaki işleri işler |
| `analytics-aggregate-daily` | 02:00 | Ziyaret kayıtlarını günlük özete indirger |
| `analytics-anonymize-ips` | 03:00 | 90 günden eski IP'leri maskeler (KVKK) |
| `backup-daily` | 03:00 | Veritabanı + `public/uploads` → ZIP |
| `audit-logs-prune` | Pazar 03:30 | 90 günden eski aktivite loglarını siler |
| `analytics-prune-old` | Pazar 04:00 | 365 günden eski ham ziyaret kayıtlarını siler |

> **Kuyruk hakkında:** paylaşımlı hosting'de `pcntl` eklentisi bulunmadığı için
> `queue:work` kullanılmaz. Bunun yerine cron her dakika kuyruktan iş çekip
> çalıştırır. Supervisor kurabildiğin bir sunucudaysan `queue:work`'e geçebilirsin.

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

`AdminMiddleware` üç rolü panele alır; hangi alana kimin gireceğine Policy ve
Gate'ler karar verir.

| Alan | admin | editor | moderator |
|---|:---:|:---:|:---:|
| Dashboard, kendi profili, bildirimler | ✅ | ✅ | ✅ |
| İletişim mesajları, yorum moderasyonu | ✅ | ✅ | ✅ |
| Sayfa, blog, galeri, SSS, slider, popup, menü | ✅ | ✅ | — |
| Dosya yöneticisi, analitik | ✅ | ✅ | — |
| Ayarlar, kullanıcılar, yönlendirmeler | ✅ | — | — |
| Mail şablonları, mail logları, aktivite logları | ✅ | — | — |
| Yedekler, sistem sağlık | ✅ | — | — |
| Silme / geri yükleme (her modülde) | ✅ | — | — |

Yeni bir alan eklerken Policy veya Gate yazmayı unutma; `AdminMiddleware` tek
başına yetki kontrolü değildir.

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

---

## Kod stili

`laravel/pint` bağımlılık olarak gelir ancak **`pint` fix modunda çalıştırılmaz.**
Kod tabanı dizi ve atamalarda hizalama kullanır, Pint'in varsayılan Laravel
preset'i bu hizalamayı bozar. Stil kontrolü için yalnızca `pint --test`.

---

## Ek dokümanlar

- `CLAUDE.md` — proje kuralları (zorunlu)
- `docs/PROJE-DURUMU.md` — mevcut durum, bilinen eksikler, yapılacaklar
- `resources/views/admin-theme/README.md` — tema referansı
