# Kurulum Rehberi

Bu proje web sitesi, mobil API ve yönetim panelini birlikte taşıyan bir **base
kit**. Bu dosya, sıfırdan bir sunucuya kurarken ya da yeni bir projeye
klonlarken izlenecek adımların tamamı — sırasıyla, kopyala-yapıştır komutlarla.

> **Kit'ten yeni bir proje mi türetiyorsunuz?** Önce
> [`docs/YENI-PROJE.md`](docs/YENI-PROJE.md) okuyun — projenin kimliğini
> (ad, marka, demo içerik, kit izleri) değiştirmeyi o belge anlatıyor. Sonra
> buraya dönüp sunucuya kurun.

> **Zaten yayında olan bir kurulumu mu güncelliyorsunuz?** Buraya değil,
> [`docs/CANLIYA-ALMA.md`](docs/CANLIYA-ALMA.md)'ya bakın — yeniden dağıtımın
> sırası burada anlatılmıyor ve sıra önemli.

Baştan sona **15 dakika** sürer. Atlanması hiçbir şeyi hemen bozmayan ama
haftalar sonra "mailler neden gitmiyor" diye aratan tek adım **8. bölümdeki
cron**; oraya gelene kadar hiçbir şeyi atlamayın.

> **Mimariye dair üç kural.** Bu proje paylaşımlı hosting'de çalışacak şekilde
> kurulmuş ve bu üç şey bilinçli:
>
> - **`queue:work` ve Supervisor kullanılmıyor.** Kuyruğu cron sürüyor. Sebebi
>   ve doğrusu → [8.2](#82-kuyruk-arka-plan-işleri)
> - **`php artisan storage:link` gerekmiyor.** Yüklenen dosyalar
>   `public/uploads/` altına iniyor → [6](#6-yükleme-dizini-storagelink-değil)
> - **Node.js / npm / Vite yok.** Derlenecek bir varlık yok; CSS ve JS hazır.

**İçindekiler**

1. [Ön gereksinimler](#1-ön-gereksinimler)
2. [Klonlama ve dosya izinleri](#2-klonlama-ve-dosya-izinleri)
3. [Çevre değişkenleri (.env)](#3-çevre-değişkenleri-env)
4. [Bağımlılıklar ve uygulama anahtarı](#4-bağımlılıklar-ve-uygulama-anahtarı)
5. [Veritabanı ve seed](#5-veritabanı-ve-seed)
6. [Yükleme dizini](#6-yükleme-dizini-storagelink-değil)
7. [Performans ve önbellek](#7-performans-ve-önbellek)
8. [Kritik sunucu ayarları](#8-kritik-sunucu-ayarları--asla-atlamayın)
9. [Kurulum sonrası kontrol](#9-kurulum-sonrası-kontrol)
10. [Web sunucusu notları](#10-web-sunucusu-notları)
11. [Mobil uygulama için ek kurulum](#11-mobil-uygulama-için-ek-kurulum)
12. [Sık karşılaşılan sorunlar](#12-sık-karşılaşılan-sorunlar)

---

## 1. Ön gereksinimler

| Bileşen | Sürüm | Not |
|---|---|---|
| PHP | **8.4+** | Proje 8.4 özelliklerini kullanıyor (property hooks, asimetrik görünürlük), alt sürümde açılmaz |
| MySQL | **8.0+** | MariaDB 10.6+ de çalışır; CI MySQL 8'e karşı koşuyor |
| Composer | 2.x | |
| Web sunucusu | Apache veya Nginx | Nginx'te ek bir kural var → [10](#10-web-sunucusu-notları) |

**Zorunlu PHP eklentileri** — panelin Sistem Sağlık ekranı bunları tek tek
denetliyor:

```
pdo  pdo_mysql  gd  mbstring  intl  zip  json  curl  openssl
```

Kontrol:

```bash
php -m | grep -iE '^(pdo_mysql|gd|mbstring|intl|zip|json|curl|openssl)$'
```

`gd` görsel işleme (WebP dönüşümü), `zip` yedekleme, `intl` tarih ve para
biçimlendirme, `openssl` mobil bildirim ve sosyal giriş imzaları için gerekiyor.

**Önerilen ayarlar** (`php.ini`):

```ini
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 52M
max_execution_time = 120
opcache.enable = 1
```

> `post_max_size`, `upload_max_filesize`'dan büyük olmalı — aksi hâlde büyük
> dosya sessizce boş gelir. OPcache kapalıyken sayfa açılışı istek başına
> birkaç yüz milisaniye yavaşlar ve bu hiçbir yerde görünmez; Sistem Sağlık
> ekranı bunu ayrıca uyarıyor.

---

## 2. Klonlama ve dosya izinleri

```bash
git clone <REPO_URL> /var/www/proje
cd /var/www/proje
```

Yazılabilir olması gereken **iki** dizin var — üçüncüsü yükleme dizini:

```bash
chmod -R 775 storage bootstrap/cache public/uploads
```

Web sunucusu kullanıcısını sahibi yapın (Ubuntu/Debian'da `www-data`,
CentOS/RHEL'de `apache`):

```bash
chown -R $USER:www-data storage bootstrap/cache public/uploads
```

**Paylaşımlı hosting'de** genelde `chown` yetkiniz yoktur ve zaten gerekmez —
dosyalar sizin kullanıcınıza ait olur, PHP de o kullanıcı olarak çalışır. Orada
yalnız `chmod` yeter:

```bash
chmod -R 775 storage bootstrap/cache public/uploads
```

> `public/uploads` içindeki `.htaccess` **silinmemeli**: yüklenen dosyaların
> çalıştırılmasını engelliyor.

---

## 3. Çevre değişkenleri (.env)

```bash
cp .env.example .env
```

`.env.example` her ayarın ne işe yaradığını satır satır anlatıyor; burada
yalnız **kurulumda mutlaka dokunulacaklar** var.

### 3.1 Uygulama

```env
APP_NAME="Proje Adı"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alanadi.com
APP_LOCALE=tr
APP_FALLBACK_LOCALE=tr
```

> **Saat dilimi `.env`'de değil, panelde.** Kurulumdan sonra
> `/admin/settings` → **Bölgesel** sekmesi → *Saat Dilimi*'nden seçin; oradaki
> değer `APP_TIMEZONE`'u ezer. `.env`'e yazmak yalnız panel ayarı hiç
> kaydedilmemişken işe yarar.

> **`APP_DEBUG=false` en kritik satırdır.** `true` bırakılırsa bir hata
> sayfası veritabanı şifrenizi, API anahtarlarınızı ve dosya yollarınızı
> ziyaretçiye gösterir. Gerçek dünyada en sık görülen sızıntı yolu budur.

### 3.2 Veritabanı

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=proje_db
DB_USERNAME=proje_user
DB_PASSWORD=güçlü-bir-şifre
```

Veritabanını **utf8mb4** ile açın, yoksa Türkçe karakterler ve emoji bozulur:

```sql
CREATE DATABASE proje_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3.3 Oturum, kuyruk, önbellek

Varsayılanlar paylaşımlı hosting için doğru; **dokunmayın**:

```env
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

HTTPS varsa `.env`'deki bu satırın **başındaki `#` işaretini kaldırın**:

```env
SESSION_SECURE_COOKIE=true
```

> Bu satır olmadan oturum çerezi HTTP üzerinden de gönderilir; araya giren biri
> onu okuyup oturumu devralabilir. Laravel'in kendi varsayılanı yok — yazılmadığı
> sürece kapalı sayılır.
>
> Yerelde açmayın: `http://localhost` üzerinden çerez hiç kurulmaz, giriş
> yapılamaz ve hata da vermez — form sessizce başa döner.

### 3.4 Mail

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.alanadi.com
MAIL_PORT=587
MAIL_USERNAME=noreply@alanadi.com
MAIL_PASSWORD=***
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@alanadi.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 3.5 Seed şifresi

```env
SEED_PASSWORD=Buraya-Guclu-Bir-Sifre-Yazin
```

> Seed edilen üç hesabın (admin, editor, user) şifresi budur. **Seed'i
> çalıştırmadan önce değiştirin**; varsayılanı bırakırsanız panelin şifresi
> herkesin bildiği bir dizge olur.

---

## 4. Bağımlılıklar ve uygulama anahtarı

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

> **Geliştirme makinesinde kısayol var.** `composer setup` tek komutla
> `composer install` + `.env` kopyası + `key:generate` + `migrate` + `db:seed`
> yapar. Üretimde kullanmayın: `--no-dev` uygulamaz ve `.env`'i düzenleme
> fırsatı bırakmadan seed eder — yani hesaplar varsayılan şifreyle kurulur.

> `--no-dev` üretim için şart: geliştirme paketleri (Debugbar dahil) kurulmaz.
> Geliştirme makinesinde `--no-dev` olmadan kurun.

`key:generate`, `.env` içindeki `APP_KEY`'i doldurur. **Bu anahtar oturumları
ve şifreli sütunları çözüyor** — kurulumdan sonra değiştirmeyin, yoksa herkes
çıkar ve iki adımlı doğrulama kurtarma kodları okunamaz hâle gelir.

---

## 5. Veritabanı ve seed

```bash
php artisan migrate --force --seed
```

`--force` üretimde onay sormasını engeller. Bu tek komut şunları kurar:

| Ne | Nereden |
|---|---|
| Bütün tablolar | migration'lar |
| **Diller** — 5 dil tanımlı, **tr (varsayılan) ve en etkin**; de/fr/it kapalı gelir | `LanguageSeeder` |
| **5 rol** (admin, editor, moderator, user, viewer) ve **78 izin** | `RoleSeeder`, `PermissionSeeder` |
| **3 varsayılan hesap** | `UserSeeder` |
| Site ayarları | `SettingSeeder` |
| Örnek sayfalar, SSS, blog, menüler | `PageSeeder`, `FaqSeeder`, `BlogSeeder`, `MenuSeeder` |
| **Mail şablonları** (tr + en, dil başına satır) | migration |
| **Arayüz dil yazıları** | `lang/tr/`, `lang/en/` dosyaları — veritabanı yalnız panelden değiştirilenleri tutar |

**Seed edilen hesaplar** (şifre: `.env` içindeki `SEED_PASSWORD`):

| E-posta | Rol |
|---|---|
| `admin@example.com` | Tam yetki |
| `editor@example.com` | İçerik yönetimi |
| `user@example.com` | Normal üye |

> **Canlıya çıkmadan önce** bu hesapların şifresini değiştirin ya da silin.

Yeni bir dil açmak isterseniz `/admin/languages` ekranından etkinleştirin; o
dilin arayüz metinleri için `lang/{kod}/` klasörü de gerekiyor (yoksa arayüz
varsayılan dilde görünür, içerik yine o dilde yönetilir).

Örnek slider ve galeri görselleri **bilerek** seed'e dahil değil (canlıda
istenmeyen içerik doğurmasın diye). İsterseniz:

```bash
php artisan db:seed --class=DemoMediaSeeder
```

---

## 6. Yükleme dizini (`storage:link` **değil**)

**`php artisan storage:link` çalıştırmanıza gerek yok.** Bu projede yüklenen
hiçbir dosya `storage/` altına gitmiyor; hepsi doğrudan `public/uploads/`
altına iniyor ve oradan servis ediliyor. Sembolik bağ gerekmiyor — üstelik
paylaşımlı hosting'lerin bir kısmı `symlink()` işlevini kapatıyor, yani o komut
orada zaten çalışmazdı.

Yapılması gereken tek şey dizinin var ve yazılabilir olması (2. adımda yaptık):

```bash
mkdir -p public/uploads && chmod -R 775 public/uploads
ls -la public/uploads/.htaccess   # bu dosya durmalı
```

> Komutu yine de çalıştırırsanız zarar vermez, sadece kullanılmayan bir
> `public/storage` bağı oluşturur.

---

## 7. Performans ve önbellek

**Yalnız üretimde** çalıştırın:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Tek satırda:

```bash
php artisan optimize
```

> **Geliştirme makinesinde `config:cache` çalıştırmayın.** Önbelleğe alınmış
> yapılandırmada `.env` artık okunmaz; bir ayarı değiştirip "neden etkisi yok"
> diye saatler harcarsınız.

**Her dağıtımdan sonra** önbellekler tazelenmeli:

```bash
php artisan optimize:clear && php artisan optimize
```

---

## 8. Kritik sunucu ayarları — **asla atlamayın**

Bu bölüm atlanırsa site açılır, sayfalar çalışır, hiçbir hata görünmez — ama
**tek bir e-posta gitmez**, yedek alınmaz, kampanya gönderilmez, bildirim
ulaşmaz. Sessizce bozulur.

### 8.1 Cron (görev zamanlayıcı)

**Tek bir cron satırı yeter.** Laravel'in zamanlayıcısı her dakika çağrılır ve
hangi görevin sırası geldiğine kendisi karar verir.

```bash
crontab -e
```

Şu satırı ekleyin (yolu kendi kurulumunuzla değiştirin):

```
* * * * * cd /var/www/proje && php artisan schedule:run >> /dev/null 2>&1
```

**cPanel / Plesk** gibi panellerde "Cron Jobs" bölümünden *her dakika* seçip
şu komutu yazın:

```
php /home/KULLANICI/public_html/artisan schedule:run >> /dev/null 2>&1
```

> Bazı hosting'lerde sistem PHP'si eski olur. `php -v` 8.4 göstermiyorsa tam
> yolu yazın: `/usr/local/bin/php84 /home/.../artisan schedule:run`

Bu tek satırın sürdüğü işler:

| Görev | Sıklık |
|---|---|
| **Kuyruk** (mail, bildirim, ağır işler) | her dakika |
| Toplu mail kampanyaları | 5 dakikada bir |
| Mobil bildirim gönderimi | her dakika |
| Zamanlanmış raporlar | saatlik |
| Analitik toplama, IP anonimleştirme | günlük |
| Yedekleme | günlük |
| Eski kayıt/dosya temizliği | günlük |

**Cron gerçekten çalışıyor mu?** Bir dakika bekleyip:

```bash
php artisan schedule:run
```

Çıktıda görev adları listeleniyorsa zamanlayıcı sağlıklı. Panelde
**Sistem Sağlık** ekranı da "Queue Worker" satırıyla bunu doğruluyor.

### 8.2 Kuyruk (arka plan işleri)

> ### ⚠️ Bu projede Supervisor ve `queue:work` **kullanılmıyor**
>
> Laravel'in standart yolu `queue:work` + Supervisor'dır. Bu proje bilerek
> başka bir yol izliyor ve **Supervisor kurmanıza gerek yok**:
>
> - `queue:work` **pcntl** eklentisini istiyor; paylaşımlı hosting'lerin
>   neredeyse hiçbirinde yok. Kurulsaydı komut sessizce hiç çalışmazdı.
> - Bunun yerine kuyruk, 8.1'deki cron tarafından her dakika boşaltılıyor:
>   `QueueRunner::drain()` işleri `Queue::pop()` + `fire()` ile doğrudan
>   işliyor. Turda en fazla **20 iş / 50 saniye** — bir sonraki cron dakikasına
>   taşmasın diye.
> - Yani **8.1'deki cron satırını eklediyseniz kuyruk zaten çalışıyor.**
>   Yapılacak başka bir şey yok.
>
> Bunu Laravel'in kendi kısıtı sanıp `queue:work` eklemeyin: `Schedule::command()`
> ve `->runInBackground()` de aynı sebeple yasak (alt süreç açılamıyor), ve bir
> test bunu koruyor (`tests/Feature/ScheduleUsesCallablesTest.php`).

**Kendi VPS'inizde daha yüksek hacim gerekiyorsa** — dakikada 20 işten fazlası
— Supervisor'a geçmek mümkün, ama bu projenin sınanmış yolu değil ve
`QueueRunner` ile çakışmaması için cron'daki `queue-worker` görevini
kaldırmanız gerekir. O durumda:

```ini
; /etc/supervisor/conf.d/proje-worker.conf
[program:proje-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/proje/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/proje/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start proje-worker:*
```

> Bu yolu seçerseniz `php -m | grep pcntl` çıktısının boş olmadığından emin
> olun. **Şüphedeyseniz seçmeyin** — cron yolu her yerde çalışıyor.

### 8.3 Yedekleme hedefi (isteğe bağlı ama önerilir)

Yedekler günlük alınıp `storage/app/backups` altında tutuluyor. Sunucunun
kendisi kaybolursa yedek de kaybolur; `.env` içindeki `BACKUP_OFFSITE_*`
ayarlarıyla ikinci bir yere kopyalayın.

---

## 9. Kurulum sonrası kontrol

Sırayla, hepsi bir dakika sürer:

**1. Site açılıyor mu**

```bash
curl -I https://alanadi.com
```

`200 OK` bekliyorsunuz. `500` alıyorsanız → [12. bölüm](#12-sık-karşılaşılan-sorunlar).

**2. İkinci dil çalışıyor mu**

```bash
curl -I https://alanadi.com/en
```

**3. API ayakta mı**

```bash
curl https://alanadi.com/api/v1/health
```

**4. Panele giriş**

`https://alanadi.com/admin` → `admin@example.com` + `.env`'deki `SEED_PASSWORD`

> Panelde iki adımlı doğrulama zorunlu tutulmuşsa ilk girişte kurulum ekranı
> açılır; `/admin/settings` → **Sistem** sekmesinden kapatılabilir.

**5. Sistem Sağlık ekranı** — kurulumun asıl sınavı

`https://alanadi.com/admin/sistem-saglik`

Bu ekran on başlığı tek tek denetliyor ve **hepsi yeşil olmalı**:

| Kontrol | Kırmızıysa |
|---|---|
| Veritabanı | `.env` bağlantı bilgileri |
| **Queue Worker** | **cron tanımlı değil** → [8.1](#81-cron-görev-zamanlayıcı) |
| PHP Modülleri | eksik eklenti → [1](#1-ön-gereksinimler) |
| Storage Yazma | izinler → [2](#2-klonlama-ve-dosya-izinleri) |
| OPcache | `php.ini` |
| Disk alanı | sunucu |
| Uygulama önbellekleri | [7](#7-performans-ve-önbellek) |
| Loglar | `storage/logs` içinde biriken hata var |
| Son yedek | [8.3](#83-yedekleme-hedefi-isteğe-bağlı-ama-önerilir) |

**6. Mail gerçekten gidiyor mu**

`https://alanadi.com/admin/settings` → **Mail** sekmesi → "Test maili gönder".
Mail kuyruğa girer ve **bir dakika içinde** (cron turunda) gider. Gelmiyorsa
`https://alanadi.com/admin/mail-logs` ekranında hatayı görürsünüz.

> Test mailinin bir dakika gecikmesi normaldir ve kuyruğun çalıştığının
> kanıtıdır. Hiç gelmiyorsa cron yoktur.

**7. Dosya yükleme**

`https://alanadi.com/admin/files` → bir görsel yükleyin. Yüklenmiyorsa `public/uploads`
izinleri; görünmüyorsa `APP_URL` yanlış.

---

## 10. Web sunucusu notları

**Belge kökü `public/` dizini olmalı.** Proje kökünü gösterirseniz `.env`
dosyanız internetten okunabilir hâle gelir.

### Nginx kullanıyorsanız — atlanmaması gereken kural

`public/uploads/.htaccess` yüklenen dosyaların çalıştırılmasını engelliyor,
**ama Nginx bu dosyayı hiç okumaz.** Aynı korumayı sunucu bloğuna ekleyin:

```nginx
location ^~ /uploads/ {
    location ~ \.(php|phar|phtml|cgi|pl|py|sh)$ { deny all; }
}
```

Standart Laravel bloğu:

```nginx
server {
    listen 443 ssl http2;
    server_name alanadi.com;
    root /var/www/proje/public;

    index index.php;
    charset utf-8;
    client_max_body_size 52M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ^~ /uploads/ {
        location ~ \.(php|phar|phtml|cgi|pl|py|sh)$ { deny all; }
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

### Apache

`mod_rewrite` açık olmalı. `public/.htaccess` gerisini hallediyor.

---

## 11. Mobil uygulama için ek kurulum

Web tarafı bunlar olmadan da tam çalışır; yalnız mobil uygulama yazacaksanız
gerekiyor.

> **Bunları `.env` yerine panelden de girebilirsiniz:**
> **Yönetim → API ve Servisler** (`/admin/api-ayarlari`). Her alanın altında
> anahtarın hangi konsoldan alınacağı adım adım yazıyor, girilen değer
> kaydedildiği anda geçerli oluyor ve gizli anahtarlar şifreli saklanıyor.
> Panelde boş bıraktığınız alan için aşağıdaki `.env` değerleri geçerli kalır.
> Yeni bir sunucuya taşırken `.env`'e yazmak yine de pratik olabilir; ikisi
> birlikte çalışıyor.

### Bildirimler (Firebase Cloud Messaging)

1. Firebase Console → **Proje ayarları → Hizmet hesapları**
2. **"Yeni özel anahtar oluştur"** → inen JSON'u `storage/app/` altına koyun
3. `.env`:

```env
PUSH_DRIVER=fcm
FCM_CREDENTIALS=storage/app/firebase-service-account.json
```

> Anahtar dosyası depoya **girmemeli** — `storage/app` zaten `.gitignore`'da.
> Eski `FCM_SERVER_KEY` yöntemi Google tarafından kapatıldı, kullanılmıyor.

### Google / Apple ile giriş

```env
GOOGLE_CLIENT_IDS=123-ios.apps.googleusercontent.com,123-android.apps.googleusercontent.com
APPLE_CLIENT_IDS=com.sirket.uygulama
```

Virgülle birden çok yazılabilir: iOS, Android ve web ayrı istemci kimliği
taşır, üçü de aynı hesaba girer. **Boş bırakılan sağlayıcı kapalıdır** — bu
bilinçli: istemci kimliği bilinmeden gelen jetonun kime düzenlendiği
doğrulanamaz.

Ayrıntı ve uç listesi: [`docs/API.md`](docs/API.md)

---

## 12. Sık karşılaşılan sorunlar

**Site 500 veriyor**

```bash
tail -50 storage/logs/laravel.log
```

En sık üç sebep: `APP_KEY` boş (`php artisan key:generate`), `storage`
yazılabilir değil (`chmod -R 775 storage`), `.env` içindeki veritabanı bilgisi
yanlış.

**Ayarı değiştirdim, hiçbir şey değişmedi**

Yapılandırma önbelleğe alınmış:

```bash
php artisan optimize:clear && php artisan optimize
```

**Mailler gitmiyor**

Cron yok. → [8.1](#81-cron-görev-zamanlayıcı). Panel → Mail Kayıtları ekranı
işin kuyrukta bekleyip beklemediğini gösterir.

**Görseller görünmüyor**

`APP_URL` yanlış ya da `public/uploads` yazılabilir değil. `storage:link` ile
ilgisi yok — bu projede gerekmiyor ([6](#6-yükleme-dizini-storagelink-değil)).

**`/en` sayfaları 404**

Diller seed edilmemiş:

```bash
php artisan db:seed --class=LanguageSeeder
```

**Panelde "yetkiniz yok" diyor**

İzinler seed edilmemiş:

```bash
php artisan db:seed --class=PermissionSeeder
```

**Bir migration MySQL'de düştü**

`down()` gidiş-dönüşü sınanmış olsa da yarım kalan bir göç `migrations`
tablosuna yazılmaz; sebebi düzeltip komutu tekrar çalıştırın. Şema değiştiren
bir göç yazdıysanız önce boş bir veritabanında deneyin.

---

## Hızlı kurulum — tek blok

Yukarıdakileri okuduysanız, sonraki kurulumlarda bu blok yeter:

```bash
git clone <REPO_URL> /var/www/proje && cd /var/www/proje
chmod -R 775 storage bootstrap/cache public/uploads
cp .env.example .env
# .env'i düzenleyin: APP_URL, APP_DEBUG=false, DB_*, MAIL_*, SEED_PASSWORD
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force --seed
php artisan optimize
crontab -l | { cat; echo "* * * * * cd /var/www/proje && php artisan schedule:run >> /dev/null 2>&1"; } | crontab -
```

Sonra **mutlaka**: `https://alanadi.com/admin/sistem-saglik` → hepsi yeşil mi?

---

## İlgili belgeler

| Dosya | İçerik |
|---|---|
| [`docs/YENI-PROJE.md`](docs/YENI-PROJE.md) | Kit'ten yeni proje türetme: neyi değiştirmeli, demo içeriği nasıl atlamalı |
| [`docs/CANLIYA-ALMA.md`](docs/CANLIYA-ALMA.md) | Yayın günü kontrolü, yeniden dağıtım sırası, geri alma, izleme |
| [`docs/SHARED-HOSTING.md`](docs/SHARED-HOSTING.md) | Paylaşımlı hosting kısıtları, cron ve kuyruğun gerekçesi |
| [`docs/API.md`](docs/API.md) | Mobil API uçları, kimlik doğrulama, sosyal giriş, bildirimler |
| [`CLAUDE.md`](CLAUDE.md) | Proje kuralları ve kırmızı çizgiler |
| `.env.example` | Her ayarın satır satır açıklaması |
