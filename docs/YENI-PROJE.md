# Kit'ten Yeni Proje Türetme

Bu rehber, `laravelbase` kitini kopyalayıp **yeni bir projeye dönüştürürken**
neyi değiştireceğini anlatır.

> **`SETUP.md` ile karıştırmayın.** O belge "bu projeyi bir sunucuya kur ve
> çalıştır" rehberidir. Bu belge ise "bu kit artık başka bir proje olsun"
> rehberi. Sıra şudur: önce burası (proje kimliğini değiştir), sonra `SETUP.md`
> (sunucuya kur).

Rehberdeki her adım bu depoda **çalıştırılarak** doğrulandı; hangi komutun ne
ürettiği ölçüldü, tahmin edilmedi.

---

## Kısa cevap: aslında çok az yer değişiyor

Kit'in markası **koda gömülü değil**. Site adı, logo, favicon, iletişim
bilgileri, sosyal hesaplar ve 500'den fazla arayüz metni veritabanından geliyor
ve panelden düzenleniyor. Yani "yeni proje" işinin büyük kısmı kod düzenlemek
değil, **panele girip doldurmak**.

Kodda değişmesi gereken şeyler bir avuç: paket adı, README, renk değişkenleri
ve birkaç yorum satırı.

---

## 1. Kopyalama ve git geçmişi

```bash
cp -R laravelbase yeni-proje
cd yeni-proje
```

Kit'in git geçmişi yeni projeye taşınmamalı — yoksa yeni projenin ilk commit'i
"Laravel 13 yükseltmesi" olur:

```bash
rm -rf .git
git init
git add -A
git commit -m "[feat]: proje kit'ten türetildi"
```

Geçmişi saklamak istiyorsan (kit güncellemelerini sonradan çekmek için) `.git`'i
bırakıp uzak adresi değiştir:

```bash
git remote set-url origin git@github.com:kullanici/yeni-proje.git
```

Kopyalanmaması gerekenler — yeni projede baştan oluşurlar:

```bash
rm -rf vendor bootstrap/cache/*.php storage/logs/*.log
rm -f database/database.sqlite .env
rm -rf public/uploads/*        # kit'in demo görselleri
```

---

## 2. `.env` — projenin kimliği

`.env.example`'ı kopyalayıp düzenle:

```bash
cp .env.example .env
```

Değiştirilmesi **zorunlu** olanlar:

| Anahtar | Kit'teki değer | Ne yapmalı |
|---|---|---|
| `APP_NAME` | `"Laravel Base"` | Projenin adı. **Seed'den önce yaz** — aşağıdaki nota bakın |
| `APP_URL` | `http://localhost` | Gerçek adres |
| `DB_DATABASE` | `laravelbase` | Yeni veritabanı adı |
| `DB_USERNAME` / `DB_PASSWORD` | — | Yeni kullanıcı |
| `SEED_PASSWORD` | `Demo*12345.` | Demo hesapların şifresi. Canlıya çıkmadan **mutlaka** değiştir |
| `MAIL_*` | — | Gönderen adresi ve SMTP |

> ### ⚠️ `APP_NAME`'i seed'den **önce** yazın
>
> `SettingSeeder`, `site_name` ve `site_title` ayarlarını `config('app.name')`
> değerinden üretiyor. Önce seed atıp sonra `APP_NAME`'i değiştirirseniz
> ayarlar "Laravel Base" olarak kalır — site başlığında, mail imzalarında ve
> panel kenar çubuğunda o görünür.
>
> Unuttuysanız telafisi kolay: **Admin → Ayarlar** ekranından site adını elle
> düzeltin. Kod değişikliği gerekmez.

İsteğe bağlı ama sık gereken:

| Anahtar | Kit'teki değer | Ne zaman değişir |
|---|---|---|
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `tr` | Varsayılan dil Türkçe değilse |
| `APP_TIMEZONE` | `Europe/Istanbul` | Başka saat dilimi |
| `APP_FAKER_LOCALE` | `tr_TR` | Test/demo verisi başka dilde üretilecekse |
| `SESSION_SECURE_COOKIE` | (yorumlu) | HTTPS'e çıkarken **açın** |

---

## 3. Kurulum — iki yol var, seçin

```bash
composer install
php artisan key:generate
php artisan migrate
```

Sonrasında iki seçenek var ve **ikisi de bu depoda test edildi**.

### Yol A — demo içerikle (kit'i tanımak için)

```bash
php artisan db:seed
```

Örnek sayfalar, blog yazıları, SSS ve menü gelir. Kit'in nasıl göründüğünü
görmek için iyi; **canlıya bu içerikle çıkılmaz**, panelden tek tek silinir.

Slider ve galeri görselleri bilerek dışarıda — isterseniz:

```bash
php artisan db:seed --class=DemoMediaSeeder
```

### Yol B — demo içerik olmadan (gerçek projeye başlamak için)

Yalnız çalışması **zorunlu** olanları tohumlayın:

```bash
php artisan db:seed --class=LanguageSeeder     # diller (tr varsayılan, en yayında)
php artisan db:seed --class=RoleSeeder         # roller
php artisan db:seed --class=PermissionSeeder   # izin matrisi
php artisan db:seed --class=UserSeeder         # yönetici hesabı
php artisan db:seed --class=SettingSeeder      # ayar anahtarları
php artisan db:seed --class=MenuSeeder         # üst menü iskeleti
```

Doğrulandı: bu altı tohumlayıcıyla site ayakta — ana sayfa, iletişim, blog,
galeri ve SSS sayfalarının hepsi `200` dönüyor (`/tr` ve `/en`).

> **Tek pürüz:** menüdeki **"Hakkımızda"** ögesi `hakkimizda` kısa adlı bir
> sayfaya işaret ediyor; `PageSeeder`'ı atladığınız için o sayfa yok ve
> bağlantı `404` veriyor. İki çözümden biri:
> - **Admin → Menü Yönetimi**'nden o ögeyi silin, ya da
> - **Admin → Sayfalar**'dan `hakkimizda` kısa adıyla kendi sayfanızı açın.

### Ortak son adım

```bash
php artisan cache:clear
```

> ### ⚠️ Ayarlar önbellekte tutulur
>
> Ayarları panel dışından değiştirdiğinizde (tohumlayıcı, doğrudan SQL,
> veritabanı değiştirme) **eski değeri görmeye devam edersiniz**. Bu rehber
> yazılırken tam olarak bu tuzağa düşüldü: veritabanındaki satır doğruydu,
> ekrana gelen değer önbellekten geliyordu.
>
> Panelden yapılan değişikliklerde bu sorun yok — kayıt önbelleği kendisi
> düşürüyor.

---

## 4. Marka — buradan sonrası panelde, kod yok

**Admin → Ayarlar** ekranından doldurun. Hiçbiri kod değişikliği istemez:

| Alan | Nereye yansır |
|---|---|
| Site adı | Sekme başlığı, panel kenar çubuğu, mail imzaları, `og:site_name` |
| Site açıklaması | Meta description, paylaşım kartları |
| Logo | Ön yüz üst bandı **ve** panel kenar çubuğu |
| Favicon | Tarayıcı sekmesi |
| İletişim (adres, telefon, e-posta) | İletişim sayfası, alt bant |
| Sosyal hesaplar | Alt bant ikonları |

> Logo yüklemezseniz panel, site adının baş harflerinden bir rozet üretir
> (`site_initials()`). Kit'te "LB" görmenizin sebebi budur — kodda yazılı
> değildir, site adından hesaplanır.

**Admin → API ve Servisler** ekranı da aynı mantıkta: Google/Apple giriş
anahtarları, Firebase, reCAPTCHA ve Analytics kimlikleri buradan girilir,
kaydettiğiniz anda geçerli olur. `.env`'e dokunmanız gerekmez.

---

## 5. Metinler

Ön yüzdeki sabit metinler (başlıklar, düğmeler, uyarılar) `lang/tr/site.php` ve
`lang/en/site.php` dosyalarında — toplam **505 anahtar**. Hepsi
**Admin → Dil Yazıları** ekranından düzenlenebilir; veritabanındaki değer dosyayı
her zaman yener, yani dosyaya dokunmadan metin değiştirebilirsiniz.

Yeni bir dil eklemek için **Admin → Diller**. Kod değişikliği gerekmez.

E-posta şablonları **Admin → Mail Şablonları**'nda, dil başına ayrı. Buradaki
değişiklik de Blade dosyalarını yener.

---

## 6. Renkler

Tek yerden değişir — CSS değişkenleri:

| Dosya | Değişken | Satır |
|---|---|---|
| `public/assets/admin/css/styles.css` | `--teal-primary` (panel ana rengi) | 34 (açık tema), 73 (koyu tema) |
| `public/css/app.css` | `--brand`, `--brand-2`, `--accent` | 15-19 (açık tema), 82-86 (koyu tema) |

**İki dosyada da renkler iki kez tanımlı** — açık ve koyu tema için ayrı. Yalnız
birini değiştirirseniz tema değiştirince eski renk geri gelir; ikisini birlikte
güncelleyin.

Değişken dışına renk yazmayın; kit boyunca her şey bu değişkenlerden besleniyor.
Zaten inline `style="..."` kullanımı proje kurallarında yasak ve bir testle
korunuyor (`InlineStylesAreForbiddenTest`).

---

## 7. Koddaki kit izleri

Fonksiyonu etkilemeyen ama "başka birinin projesi" hissi veren yerler:

| Dosya | Ne var |
|---|---|
| `composer.json` | `"name": "ozansonar/laravel-base"` |
| `README.md` | Başlık ve kit tanıtımı |
| `docs/openapi.json` | `"title": "Laravel Base — API v1"` |
| `public/css/app.css`, `public/js/app.js`, `public/js/theme.js` | Dosya başı yorumları |
| `.github/workflows/ci.yml` | Test veritabanı adı (`laravelbase_test`) — çalışır, sadece isim |

Bir seferde bulmak için:

```bash
grep -rn "Laravel Base\|laravelbase" --exclude-dir=vendor --exclude-dir=.git .
```

---

## 8. Kullanmayacağınız modülleri silmek

Kit geniş: blog, galeri, SSS, slider, popup, kampanya, abone listesi, push
duyuruları, SEO denetimi, raporlar… Projenizde kullanmayacağınız modülleri
silebilirsiniz.

**Ama tek tek silmeyin** — bir modül altı yere dokunuyor: rota, denetleyici,
servis, görünüm, izin ve yardım kılavuzu.

İyi haber: **bekçi testleri sizi tutar.** Bir modülü eksik silerseniz paket
kırmızıya döner ve tam olarak neyi unuttuğunuzu söyler:

| Test | Ne der |
|---|---|
| `AdminAuthorizationTest` | "Bu ekran yetki matrisinde yok" |
| `AdminHelpTest` | "Kenar çubuğunda var ama kılavuzu yazılmamış" |
| `ListScreensOfferExportTest` | "Tablo çiziyor ama dışa aktarma sunmuyor" |
| `ModelFactoriesTest` | "Model var ama factory yok" |

Yani silme işini şöyle yapın: sil → `php artisan test` → testin söylediği yeri
temizle → tekrar. Test yeşile döndüğünde iş bitmiştir.

> **Silmeden önce durun:** kullanılmayan bir modül panelde bir satır ve
> veritabanında birkaç boş tablodan ibaret; kimseye zarar vermiyor. Silmenin
> gerçek maliyeti, ileride ihtiyacınız olduğunda geri yazmaktır. Emin
> değilseniz bırakın.

---

## 9. Güvenlik — canlıya çıkmadan

```env
APP_DEBUG=false
APP_ENV=production
SESSION_SECURE_COOKIE=true
```

- **Demo hesapları silin veya şifrelerini değiştirin:** `admin@example.com`,
  `editor@example.com`, `user@example.com`. Üçü de `SEED_PASSWORD` ile geliyor.
- **`SEED_PASSWORD`'ü `.env`'den kaldırın** kurulum bittikten sonra.
- Ayrıntılar ve sunucu ayarları (cron, kuyruk) için `SETUP.md` bölüm 8.

---

## Kontrol listesi

Kopyaladıktan sonra sırayla:

- [ ] `.git` sıfırlandı ya da uzak adres değiştirildi
- [ ] `vendor`, `bootstrap/cache`, eski `.env`, `public/uploads` temizlendi
- [ ] `.env` yazıldı — **`APP_NAME` seed'den önce**
- [ ] `composer install` + `key:generate` + `migrate`
- [ ] Tohumlama yapıldı (Yol A ya da Yol B)
- [ ] `php artisan cache:clear`
- [ ] Panele girildi, **Ayarlar** dolduruldu (ad, logo, favicon, iletişim, sosyal)
- [ ] Renk değişkenleri projeye göre ayarlandı
- [ ] `composer.json`, `README.md`, `openapi.json` güncellendi
- [ ] Demo hesapların şifresi değiştirildi, `SEED_PASSWORD` kaldırıldı
- [ ] Yol B seçildiyse: "Hakkımızda" menü ögesi silindi ya da sayfası açıldı
- [ ] `php artisan test` yeşil
- [ ] Canlıya çıkarken `SETUP.md` bölüm 8 (cron + kuyruk) uygulandı

---

## İlgili belgeler

- **`SETUP.md`** — sunucuya kurulum, cron, kuyruk, sağlık kontrolü
- **`README.md`** — kit'in yetenekleri, mimari kararlar
- **`docs/SHARED-HOSTING.md`** — paylaşımlı hosting kısıtları
- **`docs/API.md`** — mobil uygulama için API
