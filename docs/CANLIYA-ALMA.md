# Canlıya Alma ve Yeniden Dağıtım

Bu belge **yayındaki** bir kurulumla ilgilenir: ilk yayın günü, sonraki her
güncelleme, bir şey bozulduğunda geri alma ve yayın sonrası izleme.

> **`SETUP.md` ile karıştırmayın.** O belge boş bir sunucuya **ilk kurulumu**
> anlatır (izinler, `.env`, bağımlılıklar, cron, web sunucusu). Burası oradan
> sonrasıdır. İlk kurulum yapılmamışsa önce `SETUP.md`.

Kurulum adımları burada **tekrar edilmez**, ilgili bölüme bağlantı verilir.

---

## 1. İlk yayın günü — son kontrol

Kurulum bitti, site ayakta, alan adını yönlendirmeden önceki son bakış.

### 1.1 `.env` — üç satır

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alanadiniz.com
SESSION_SECURE_COOKIE=true
```

> **`APP_DEBUG=false` en kritik satır.** `true` kalırsa herhangi bir hata
> sayfası ziyaretçiye `.env` içeriğini, veritabanı şifresini ve yığın izini
> gösterir. Tek satır, tüm sırlar.

Değiştirdiyseniz önbelleği tazeleyin — yoksa hiçbiri geçerli olmaz:

```bash
php artisan optimize:clear && php artisan optimize
```

### 1.2 Demo hesaplar

Kurulum üç hesap oluşturur: `admin@example.com`, `editor@example.com`,
`user@example.com` — üçü de `.env`'deki `SEED_PASSWORD` ile.

- Kendi yönetici hesabınızı açın.
- Demo hesapları **silin** (Admin → Kullanıcılar).
- `SEED_PASSWORD` satırını `.env`'den kaldırın.

### 1.3 Sunucu sertleştirme

`SETUP.md` [bölüm 10](../SETUP.md#10-web-sunucusu-notları)'daki web sunucusu
yapılandırması iki şeyi kapatıyor — uygulanmış olduğunu doğrulayın:

| Ne | Neden |
|---|---|
| Nokta ile başlayan dosyalara erişim (`.env` dahil) | Aksi hâlde `alanadiniz.com/.env` sırları düz metin verir |
| `public/uploads/` altında PHP çalıştırma | Yüklenen bir dosya kod olarak koşamamalı |

Tarayıcıdan sınayın — ikisi de **404 ya da 403** dönmeli:

```
https://alanadiniz.com/.env
https://alanadiniz.com/uploads/deneme.php
```

### 1.4 Cron gerçekten çalışıyor mu

Kuyruk, zamanlanmış mailler, yedekler ve temizlik görevlerinin **hepsi** crona
bağlı ([SETUP.md 8.1](../SETUP.md#81-cron-görev-zamanlayıcı)). Tanımlı değilse
hiçbiri hata vermez — sadece hiç çalışmaz.

**Admin → Sistem Sağlık** ekranındaki *Queue Worker* satırı yeşilse cron
çalışıyor demektir. Kırmızıysa cron tanımlı değildir.

### 1.5 Haber alma kanalını açın

**Admin → Ayarlar → Telegram** sekmesinden bildirimleri açın; aynı sekmedeki
*Test Mesajı Gönder* düğmesiyle doğrulayın. Açık olmazsa sunucu hatasını ancak biri
şikâyet edince öğrenirsiniz.

### 1.6 İlk yedeği elle alın

```bash
php artisan backup:run
```

Sonrasında her gece kendiliğinden çalışır. **Admin → Yedekler** ekranından
görünür ve indirilebilir.

### Yayın öncesi kontrol listesi

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` https ile
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `optimize:clear && optimize` çalıştırıldı
- [ ] Kendi yönetici hesabı açıldı, demo hesaplar silindi
- [ ] `SEED_PASSWORD` `.env`'den kaldırıldı
- [ ] `/.env` ve `/uploads/deneme.php` tarayıcıdan erişilemiyor
- [ ] Sunucuda `chmod 600 .env` uygulandı (paylaşımlı hosting'de kritik)
- [ ] Sistem Sağlık ekranında *Queue Worker* yeşil
- [ ] *PHP Modülleri* yeşil — "GD yüklü ama WebP yok" derse görsel yükleme çalışmaz
- [ ] Bir görsel yüklenip görüntülendi (Admin → Dosya Yöneticisi)
- [ ] Telegram bildirimleri açık
- [ ] İlk yedek alındı
- [ ] Test e-postası gönderildi (Admin → Ayarlar → **E-posta (SMTP)** → *Test E-postası Gönder*)
- [ ] Telegram test mesajı ulaştı (Admin → Ayarlar → **Telegram** → *Test Mesajı Gönder*)
- [ ] Demo içerik (örnek sayfa, blog, SSS) silindi ya da kendi içeriğinizle değiştirildi

---

## 2. Yeniden dağıtım — sıra önemli

İkinci ve sonraki her güncelleme. **Adımların sırası keyfi değil**, her biri
bir öncekine bağlı.

```bash
# 1. Yedek — geri dönüş noktası
php artisan backup:run

# 2. Bakım moduna al (bkz. bölüm 3)

# 3. Yeni kodu çek
git pull origin main

# 4. Bağımlılıklar — YENİ GÖÇLERDEN ÖNCE
composer install --optimize-autoloader --no-dev

# 5. Veritabanı şeması
php artisan migrate --force

# 6. Önbellekleri yenile — SIRA BÖYLE
php artisan optimize:clear
php artisan optimize

# 7. Bakım modundan çık

# 8. Doğrula
```

### Neden bu sıra

| Adım | Atlanırsa / yer değiştirirse |
|---|---|
| **Yedek en başta** | Göç yarıda kalırsa geri dönecek nokta olmaz |
| **Bakım modu göçten önce** | Ziyaretçi yarı göç edilmiş şemaya çarpar; sipariş, form ve oturum kaybolur |
| **`composer install` göçten önce** | Yeni göç yeni bir sınıfa dayanıyorsa `Class not found` ile düşer |
| **`--no-dev`** | Geliştirme paketleri (Debugbar dahil) canlıya iner |
| **`optimize:clear` `optimize`'dan önce** | Eski config önbelleği yerinde kalır; `.env` değişikliğiniz geçerli olmaz. **En sık yapılan hata bu** |
| **`--force`** | Komut onay sorar, cron ya da betik içinde sonsuza kadar bekler |

### Kuyruk hakkında

Kuyruk bu projede cron'la sürülüyor, arka planda sürekli çalışan bir işçi yok
([SHARED-HOSTING.md](SHARED-HOSTING.md)). Dolayısıyla dağıtım sırasında
öldürülecek bir süreç de yok. Dağıtım cron tetiklenmesiyle çakışırsa iş bir
sonraki turda alınır — veri kaybı olmaz.

---

## 3. Bakım modu — iki seçenek var

| Yöntem | Nasıl | Ne zaman |
|---|---|---|
| **Panel** (önerilen) | Admin → Ayarlar → **Görünüm** sekmesi → *Bakım Modu* | Olağan dağıtım |
| **Laravel** | `php artisan down` / `php artisan up` | Veritabanına dokunan riskli iş |

**Farkı bilin:** panel bakım modu, "bakımdayız" bilgisini **veritabanından**
okuyor. Veritabanı düşerse ya da göç ortasında erişilemez hâle gelirse o mod
kendini gösteremez. `php artisan down` ise dosya tabanlı, veritabanı olmadan da
çalışır.

Pratik kural: **şema göçü içeren dağıtımlarda `php artisan down`**, içerik/kod
güncellemelerinde panel yeter.

Panel bakım modunun iki kolaylığı var:
- `/admin` ve `/giris` adresleri **her zaman açık** — kendi sitenize
  kilitlenmezsiniz.
- Aynı bölümdeki *İzin Verilen IP'ler* alanına adres yazabilirsiniz; o adreslerden
  gelen ziyaretçi siteyi normal görür. Dağıtım sonrası kontrolü siz yaparken
  ziyaretçi bakım sayfasını görür.

---

## 4. Bir şey bozulduysa — geri alma

Sırayla deneyin; ilki en ucuz, sonuncusu en ağır.

### 4.1 Yalnız önbellek bozulduysa

Site bembeyaz ya da eski hâlini gösteriyorsa:

```bash
php artisan optimize:clear
php artisan optimize
```

### 4.2 Kodu geri al

```bash
git log --oneline -5              # çalışan sürümü bulun
git reset --hard <commit>
composer install --optimize-autoloader --no-dev
php artisan optimize:clear && php artisan optimize
```

### 4.3 Göçü geri al

Bu projede her göç `down()` yazmak zorunda (proje kuralı), yani geri alma
çalışır:

```bash
php artisan migrate:rollback --step=1
```

> **Dikkat:** `down()` tabloyu ya da sütunu **siler**. İçindeki veri gider.
> Göç veri taşıyorsa geri alma yerine yedekten dönmek daha güvenlidir.

### 4.4 Yedekten dön

**Admin → Yedekler** ekranından ilgili yedeğin *Geri Yükle* düğmesi. Veritabanı
ve `public/uploads` birlikte döner.

> Geri yükleme **mevcut veriyi değiştirir**. Geri yüklemeden önce o anki hâlin
> yedeğini alın — bozuk da olsa, iki kötü seçenek bir kötü seçenekten iyidir.

---

## 5. Yayın sonrası — nereye bakılır

| Ekran | Ne söyler |
|---|---|
| **Sistem Sağlık** | Veritabanı, cron, PHP eklentileri, disk, OPcache, son yedek |
| **Hata Kayıtları** | Sunucu hataları: ne, nerede, **kaç kez**. İlk bakılacak yer |
| **Bildirimler** | Kritik olaylar; Telegram açıksa aynısı telefona düşer |
| **Kuyruk** | Başarısız arka plan işleri (mail, rapor) |
| **Mail Logları** | Giden her mail ve akıbeti |
| **Yedekler** | Son yedeğin tarihi — *tarihe bakın*, listenin dolu olması yetmez |

**Hata Kayıtları'nda tekrar sayısına bakın.** Bir kez olan hata talihsizlik,
binlerce kez olan kusur. Bildirimler kısılmış hâlde gelir (10 dakikada bir), o
liste ise her tekrarı sayar.

Loglar `storage/logs` altında **14 gün** tutulur (`LOG_DAILY_DAYS`). Disk
sıkışıyorsa bu sayı düşürülebilir.

---

## 6. Bakım takvimi

| Sıklık | İş |
|---|---|
| **Otomatik (cron)** | Yedek, kuyruk, zamanlanmış mailler, eski kayıt temizliği |
| **Haftalık** | Sistem Sağlık ekranına bir bakış; Hata Kayıtları'nda açık madde var mı |
| **Aylık** | Yedeklerden birini **gerçekten geri yükleyin** (tercihen ayrı bir kurulumda). Denenmemiş yedek, yedek değildir |
| **Üç aylık** | `composer update` → `php artisan test` → yeşilse dağıtın. Güvenlik yamaları burada gelir |

---

## Tek blok — olağan dağıtım

Panel bakım modu açıkken:

```bash
cd /yol/proje
php artisan backup:run
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Şema göçü içeren riskli dağıtımda:

```bash
cd /yol/proje
php artisan backup:run
php artisan down
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan up
```

Sonrasında **Sistem Sağlık** ekranını açın ve ana sayfayı bir kez ziyaret edin.

---

## İlgili belgeler

| Dosya | İçerik |
|---|---|
| [`SETUP.md`](../SETUP.md) | Boş sunucuya ilk kurulum |
| [`docs/YENI-PROJE.md`](YENI-PROJE.md) | Kit'ten yeni proje türetme |
| [`docs/SHARED-HOSTING.md`](SHARED-HOSTING.md) | Cron ve kuyruğun neden böyle kurulduğu |
