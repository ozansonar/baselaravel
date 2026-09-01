# Proje Kaydı — Laravel Base Kit

**Son güncelleme:** 1 Eylül 2026 · **Dal:** `feat/laravel-13-upgrade` · **Son commit:** `9a62cda`

Bu belge, projenin dört ayrı kaydını **tek yerde** toplar. Dört belge birbirine
bağlıydı ama ayrı ayrı okunması gerekiyordu; bir maddenin durumunu öğrenmek için
hangisine bakılacağını bilmek gerekiyordu.

> **Veri kaybı yok.** Aşağıdaki A–D bölümleri kaynak dosyaların **tam ve
> değiştirilmemiş** içeriğidir — tek fark, başlık seviyelerinin bu belgenin
> hiyerarşisine oturması için bir kademe indirilmesi. Kaynak dosyalar da
> yerinde duruyor ve silinmedi.

| Bölüm | Kaynak | Ne anlatıyor |
|---|---|---|
| **A** | `PROJE-DURUMU.md` | *Ne var* — modül modül, tur tur |
| **B** | `YOL-HARITASI.md` | *Ne eksikti* — beş faz, kabul ölçütleriyle |
| **C** | `BOSLUK-ANALIZI.md` | *Hangi sırayla* — 31 Ağustos mimari denetimi, 15 bulgu |
| **D** | `PROJE-DURUMU-V2.md` | *Çalışıyor mu* — 1 Eylül denetimi, 16 bulgu |

Bu belgenin kapsamı **durum ve plan**. Referans belgeler ayrı kalıyor ve
birleştirilmedi, çünkü farklı iş görüyorlar:
`API.md` (API sözleşmesi), `openapi.json` (makine okunur şema),
`SHARED-HOSTING.md` (hosting kısıtları), `CLAUDE.md` (proje kuralları).

---

## İçindekiler

1. [Durum özeti](#1-durum-özeti)
2. [Ana durum tablosu](#2-ana-durum-tablosu) — her madde, kaynağı ve durumu
3. [Kalan işler ve plan](#3-kalan-i̇şler-ve-plan)
4. [Arşiv bölümleri](#4-arşiv-bölümleri) → A · B · C · D

---

## 1. Durum özeti

Kurumsal siteler, CRM/admin panelleri ve mobil uygulama API'leri için ortak
kullanılan bir Laravel 13 base kit. Build aracı yok (Vite/npm/Node yasak),
paylaşımlı hosting gerçeğine göre kurulu (pcntl yok, kuyruk ve cron buna göre).

### Rakamlar

| | | | |
|---|---|---|---|
| **39** model | **103** servis | **27** policy | **81** migration |
| **139** test dosyası | **1979** test | **7900** doğrulama | **35** dışa aktarma tanımı |
| **373** rota | **258** admin rotası | **48** API rotası | **2** dil (tr, en) |

### Kalite kapıları

| Kapı | Durum |
|---|---|
| Test paketi (`composer test`) | ✅ 1979 geçiyor, 9 gerekçeli atlama |
| Kod stili (`pint --test`) | ✅ sıfır sapma |
| Statik analiz (Larastan seviye 1) | ✅ sıfır hata |
| CI (GitHub Actions, MySQL 8'e karşı) | ✅ kurulu |
| Üretim önbellekleri (`config/route/view:cache`) | ✅ sorunsuz kuruluyor |
| `app/` içinde `env()` çağrısı | ✅ sıfır (config:cache güvenli) |

### Üç yüzün karşılaştırması

| Yetenek | Web | Mobil web | API |
|---|---|---|---|
| İçerik (blog, sayfa, galeri, SSS) | ✅ | ✅ | ✅ |
| Çok dillilik | ✅ | ✅ | ✅ |
| SEO (sitemap, hreflang, JSON-LD, RSS) | ✅ | ✅ | — |
| **SEO denetleyici** | ✅ | ✅ | — |
| Kimlik (kayıt, giriş, sıfırlama, doğrulama) | ✅ | ✅ | ✅ |
| Profil ve şifre değiştirme | ✅ | ✅ | ✅ |
| Cihaz / oturum yönetimi | ✅ | ✅ | ✅ |
| İki adımlı doğrulama (TOTP) | ✅ | ✅ | ✅ giriş **+ kurulum** |
| Hesap kapatma + veri indirme (KVKK) | ✅ | ✅ | ✅ |
| Bildirim tercihleri | ✅ | ✅ | ✅ |
| Yorumlarım | ✅ | ✅ | ✅ |
| Kurulabilirlik (PWA, çevrimdışı) | — | ✅ | — |
| Push bildirim | — | ✅ tercih anahtarı | ✅ jeton + gönderim + panel ekranı |
| Sürüm / sağlık ucu | — | — | ✅ |

---

## 2. Ana durum tablosu

Dört belgedeki **bütün maddeler** burada. "Kaynak" sütunu, ayrıntının hangi
arşiv bölümünde olduğunu söylüyor.

### 2.1 Temel turlar — hepsi kapalı

| # | Madde | Kaynak | Durum |
|---|---|---|---|
| 4 | Eski proje kalıntılarının temizliği (15 kalem) | A §4 | ✅ |
| 5 | Yetkilendirme ve rol matrisi | A §5 | ✅ |
| 5c | Açık yönlendirme koruması | A §5c | ✅ |
| 5d | SoftDeletes — her modelde | A §5d | ✅ |
| 5e | Çok dilli yapı | A §5e | ✅ |
| 5f | Arayüz çevirisi | A §5f | ✅ |
| 5g | Çok dilli navigasyon | A §5g | ✅ |
| 5h | Mail ve upload yolları (3 kusur kapatıldı) | A §5h | ✅ |
| 5i | Toplu mail / kampanyalar | A §5i | ✅ |
| 5j | Shared hosting uyumu (kritik hata) | A §5j | ✅ |
| 5k | Diller ekranı | A §5k | ✅ |
| 5l | Dil yazıları ekranı | A §5l | ✅ |
| 5m | Bölgesel ayarlar (2 kusur) | A §5m | ✅ |
| 5n | Pasif kullanıcı oturumu + güvenilen proxy | A §5n | ✅ |
| 5o | `robots.txt` dosyadan rotaya | A §5o | ✅ |
| 5p | Hata bildirimi + log rotasyonu | A §5p | ✅ |
| 5r | Denetim izi — tek modelden kritik kümeye | A §5r | ✅ |
| 5s | Kuyruk izleyici | A §5s | ✅ |
| 5t | Ölü Telegram ayarı + kaydedilmeyen başarısız işler | A §5t | ✅ |
| 5u | Yedek geri yükleme | A §5u | ✅ |
| 5v | CI ve statik analiz (6 gizli hata çıkardı) | A §5v | ✅ |
| 5y | Çerez rızası | A §5y | ✅ |
| 5z | API katmanı (v1) | A §5z | ✅ |
| 5ab | Arama (blog + site geneli) | A §5ab | ✅ |

### 2.2 Yol haritası fazları

| # | Madde | Kaynak | Durum |
|---|---|---|---|
| 1.1 | Web'de cihaz ve oturum yönetimi | B Faz 1 | ✅ |
| 1.2 | İki adımlı doğrulama (TOTP) | B Faz 1 | ✅ |
| 1.3 | Hesap kapatma + veri indirme (KVKK/GDPR) | B Faz 1 | ✅ |
| 1.4 | API hesap uçlarının tamamlanması | B Faz 1 | ✅ |
| 1.5 | Bildirim tercihleri | B Faz 1 | ✅ |
| 2.1 | PWA manifest | B Faz 2 | ✅ |
| 2.2 | Servis çalışanı ve çevrimdışı sayfa | B Faz 2 | ✅ |
| 2.3 | Mobil kullanım denetimi (360 px) | B Faz 2 | ✅ |
| 2.4 | Erişilebilirlik taban çizgisi | B Faz 2 | ✅ |
| 3.1 | Raporlar ekranı | B Faz 3 | ✅ |
| 3.2 | Genel içerik listesi | B Faz 3 | ✅ |
| 3.3 | Yardım ekranı | B Faz 3 | ✅ |
| 4.1 | Push bildirim altyapısı | B Faz 4 | ✅ **panel ekranı da tamam (1 Eylül)** |
| 4.2 | Sürüm ve sağlık ucu | B Faz 4 | ✅ |
| 4.3 | Kullanıcının kendi yorumları | B Faz 4 | ✅ |
| 4.4 | Şemanın hizada kalması | B Faz 4 | ✅ |
| 5.1 | Yedeğin dış kopyası | B Faz 5 | ✅ |
| 5.2 | `jenssegers/agent` bağımlılığından çıkış | B Faz 5 | ✅ |
| 5.3 | Test paketinin bellek bütçesi | B Faz 5 | ✅ |
| 5.4 | Sertleştirme: `cache.serializable_classes` | B Faz 5 | ✅ |
| 5.4 | Sertleştirme: `session.serialization = json` | B Faz 5 | ✅ *(1 Eyl)* — geçiş modu bakım penceresi ihtiyacını kaldırdı |

### 2.3 Boşluk analizi bulguları (S-01 … S-15)

| # | Bulgu | Alan | Durum |
|---|---|---|---|
| S-01 | Pasife alınan kullanıcı oturumdan düşmüyor | Güvenlik | ✅ |
| S-02 | Proxy güveni tanımsız — rate limit tek kovada | Güvenlik | ✅ |
| S-03 | İşlenmeyen istisna kimseye ulaşmıyor, log sınırsız | Operasyon | ✅ |
| S-04 | Audit trail yalnızca tek modeli izliyor | Uyumluluk | ✅ |
| S-05 | Content-Security-Policy yok | Güvenlik | ✅ *(1 Eyl)* |
| S-06 | `robots.txt` statik, eski projenin alan adı | SEO | ✅ |
| S-07 | Yedek tek diskte, geri yükleme yok | Operasyon | ✅ |
| S-08 | Kuyruk görünmez | Operasyon | ✅ |
| S-09 | Çerez rızası alınmadan izleme başlıyor | Uyumluluk | ✅ |
| S-10 | Parola politikası zayıf, 2FA yok | Güvenlik | ✅ |
| S-11 | 966 test var, hiçbiri otomatik koşmuyor | Kalite | ✅ |
| S-12 | Analitik cache temizliği tüm cache'i siliyor | Performans | ✅ *(1 Eyl)* |
| S-13 | Cache anahtarları 30 ayrı yerde elle temizleniyor | Bakım | ✅ *(1 Eyl)* |
| S-14 | Ön yüzde çıktı cache'i yok | Performans | ✅ *(1 Eyl)* |
| S-15 | Site içi arama yok | Ürün | ✅ |

### 2.4 v2 denetimi bulguları (1 Eylül)

| # | Bulgu | Durum |
|---|---|---|
| 1 | Dışa aktarmada CSV biçimi yoktu | ✅ |
| 2 | Üç liste ekranında dışa aktarma hiç yoktu | ✅ |
| 3 | Dışa aktarma modülünün hiçbir testi yoktu | ✅ 81 test |
| 4 | Zamanlanmış raporlarda CSV seçilemiyordu | ✅ |
| 5 | Editörün dosya seçicisi boş kurulumda 404 veriyordu | ✅ |
| 6 | Kuralsız alan bekçisi panelin hiçbir formunu görmüyordu | ✅ |
| 7 | Bekçinin tarayıcısı nitelikleri yanlış okuyordu | ✅ |
| 8 | Satır içi stil yasağının bekçisi yoktu (13 ihlal) | ✅ |
| 9 | Rol matrisi 12 modül geride kalmıştı | ✅ |
| 10 | Panel duman testi 26 rotaya bakıyordu (55 ekran var) | ✅ |
| 11 | Ön yüzün duman testi hiç yoktu | ✅ |
| 12 | Çeviri eşliği yalnız `site.php`'de sınanıyordu | ✅ |
| 13 | `lang/tr/validation.php` dokuz kuralı taşımıyordu | ✅ |
| 14 | Profil ekranında tarayıcının `alert()` kutusu | ✅ |
| 15 | Çerez rızası kutuları kuralsız ve işaretsizdi | ✅ |
| 16 | Stok config dosyalarında `strict_types` yoktu | ✅ |

### 2.5 Modül önerileri (boşluk analizinden)

| Modül | Efor | Durum |
|---|---|---|
| Denetim izi genişletmesi | Küçük | ✅ |
| Kuyruk & iş izleyici | Küçük | ✅ |
| Oturum & cihaz yönetimi | Küçük | ✅ |
| İki aşamalı doğrulama (TOTP) | Orta | ✅ |
| Çerez rızası yöneticisi | Orta | ✅ |
| Yedek geri yükleme + dış kopya | Orta | ✅ |
| Site içi arama | Orta | ✅ |
| Raporlama ekranı | Orta | ✅ |
| API katmanı (Sanctum) | Orta | ✅ |
| **SEO denetleyici** | Orta | ✅ *(1 Eyl)* |
| Satır içi olay işleyicilerini JS'e taşımak | Orta | ✅ *(1 Eyl)* |
| **İçerik sürümleme (revisions)** | Orta | ✅ *(1 Eyl)* |
| **Dinamik form oluşturucu** | Büyük | ⬜ **açık** |

### 2.6 Son turda eklenenler (1 Eylül, bu belgeye yeni giren)

| Madde | Commit | Durum |
|---|---|---|
| Dışa aktarmaya CSV, üç ekran, 81 test | `f9cfff6` | ✅ |
| Proje kurallarının bekçileri, dört sessiz kusur | `edc6df9` | ✅ |
| v2 raporu, yetki matrisi, çeviri boşluğu | `6bd21e7` | ✅ |
| Boşluk analizi arşive alındı, S-05/12/13/14 kapatıldı | `c40c427` | ✅ |
| **SEO denetleyici modülü** (9 kural, 2 yüzey, 43 test) | `748254a` | ✅ |
| CSP satır içi işleyici kusuru + kapak görseli kusuru | `03e2bba` | ✅ |
| Dört durum belgesi tek kayıtta toplandı | `4aeb75d` | ✅ |
| **219 satır içi işleyici JS'e taşındı, CSP tavizi kalktı** | `97d0ae3` | ✅ |
| **Oturum serileştirmesi JSON'a alındı (geçiş moduyla)** | `9a62cda` | ✅ |
| **Panelden push duyurusu gönderme ekranı** (3 ekran, cron gönderimi, 29 test) | `62499c0` | ✅ |
| Belge taraması: açık maddelerin koda karşı doğrulanması | `8ebf1ae` | ✅ |
| Yol haritası gerçeğe getirildi + belgelerdeki test göndermelerinin bekçisi | — | ✅ |
| Tarayıcı kutuları kaldırıldı, iki bekçi, belge borcu | `c982c45` | ✅ |
| Sistem sağlığına OPcache + uygulama önbelleği kontrolleri | `55a54d3` | ✅ |
| **API'de iki adımlı doğrulama kurulumu** (4 uç, 18 test) | — | ✅ |
| **İçerik sürümleme** (sayfa + blog, 20 sürüm, dil başına, 24 test) | — | ✅ |

---

## 3. Kalan işler ve plan

Bilinen **tek açık madde** kaldı: dinamik form oluşturucu. İçerik sürümleme
ve API'de iki adımlı doğrulama kurulumu 1 Eylül'de kapandı.

Belge taramasından çıkan dört küçük kalem ve altı belge borcunun **dokuzu 1
Eylül'de kapatıldı**; geriye yalnız izlemedeki K-4 kaldı (bir koşuda tek
seferlik düşen `ModelFactoriesTest`, beş koşuda tekrarlamadı). Kapatma turunda
iki şey daha çıktı: haritanın kabul ölçütlerinde **var olmayan altı test
dosyasına gönderme**, ve tarayıcı kutularının ikide değil **dört** yerde
durduğu. İkisi de düzeltildi, ikisinin de bekçisi yazıldı.

### Öncelik sırası ve gerekçesi

| Sıra | İş | Efor | Neden bu sırada |
|---|---|---|---|
| ~~1~~ | ~~Satır içi olay işleyicilerini JS'e taşımak~~ | Orta | ✅ **1 Eylül'de tamamlandı** — aşağıya bakın |
| ~~1~~ | ~~İçerik sürümleme (revisions)~~ | Orta | ✅ **1 Eylül'de tamamlandı** — aşağıya bakın |
| ~~2~~ | ~~API'de iki adımlı doğrulama kurulumu~~ | Küçük | ✅ **1 Eylül'de tamamlandı** — aşağıya bakın |
| **1** | Dinamik form oluşturucu | Büyük | Her projede en az bir form isteniyor ve her seferinde elle kodlanıyor |
| ~~3~~ | ~~Panelden push bildirim gönderme ekranı~~ | Küçük | ✅ **1 Eylül'de tamamlandı** — aşağıya bakın |
| ~~4~~ | ~~`session.serialization = json`~~ | Küçük | ✅ **1 Eylül'de tamamlandı** — aşağıya bakın |

---

### 3.0 Canlı ortam ölçümü (1 Eylül) — yavaşlığın sebebi kod değil

Demo sunucuda blog sayfaları ~5 saniyede açılıyordu ve şüphe son eklenen
özelliklerdeydi. Ölçüldü, değillermiş.

#### Ölçüm

| İstek | Sunucu boştayken |
|---|---|
| Statik dosya (`/css/app.css`, PHP çalışmıyor) | **42 ms** |
| `/_debugbar/assets` — Laravel açılıyor, Debugbar kendini hariç tutuyor | **406 ms** |
| `/robots.txt` — Laravel + Debugbar, sayfa mantığı yok denecek kadar az | **409 ms** |
| `/tr/blog` — Laravel + Debugbar + sayfa | **635 ms** |

Üç sonuç birden çıkıyor:

- **Çerçeve açılışı tek başına ~365 ms.** Hiçbir sayfa mantığı çalışmadan.
- **Debugbar'ın maliyeti ölçülebilir düzeyde değil** — kendini hariç tuttuğu
  yol 406 ms, tutmadığı yol 409 ms. Demo ortamında açık kalması hızın sebebi
  değil.
- **Blog sayfasının kendi işi ~230 ms.** Yani toplamın üçte biri; geri kalanı
  her rotada aynı şekilde ödenen sabit maliyet.

Debugbar'ın kendi sayacı da aynı şeyi söylüyordu: 621 ms toplam, **23 ms
SQL** (30 sorgu). Veritabanı suçlu değil. Laravel'in kendi saydığı 621 ms ile
tarayıcının gördüğü ~1000 ms arasındaki fark, Laravel'in sayacı başlamadan
önce geçen süre — PHP açılışı ve dosya derlemesi, yani **OPcache imzası.**

Arka arkaya istek geldiğinde aynı sayfa 635 ms'den 3 saniyeye çıkıyor: derleme
CPU'ya bağlı olduğu için sunucu en hafif eşzamanlılıkta bile bozuluyor. "5
saniye" tam olarak bu.

#### Karşılaştırma

Aynı sayfa yerel makinede **19 ms** (SQLite, Debugbar açık, OPcache açık:
isabet %99.9, 1282 dosya). Aradaki fark koddan değil ortamdan geliyor.

#### Yapılan

Sunucu ayarını buradan değiştiremiyoruz ama **panelin bunu söylememesi bir
eksikti**: site yavaş açılır, sorgular hızlıdır, sebep hiçbir ekranda yazmaz.
Sistem Sağlık ekranına iki kontrol eklendi:

- **`opcache`** — açık mı, isabet oranı, önbellekteki dosya sayısı, bellek
  kullanımı. Açık olmak yetmediği için iki tuzak ayrıca sınanıyor: bellek
  dolduğunda ve dosya tavanına dayanıldığında OPcache dosya atmaya başlıyor,
  kazanç sessizce kayboluyor. `opcache.restrict_api` yüzünden okunamadığında
  "bilmiyorum" diyor — varsaymıyor.
- **`app_cache`** — config ve rota önbelleği kurulu mu. Görünüm önbelleği
  bilerek dışarıda: Blade ilk çizimde kendiliğinden derlendiği için derlenmiş
  dizine bakmak "önden derlendi" ile "birisi o sayfayı bir kez açtı"yı ayırt
  edemiyor, ölçemediğimiz şeyi yeşil göstermek hiç göstermemekten kötü.

`docs/SHARED-HOSTING.md`'ye "Hız" bölümü eklendi (ölçüm, `opcache.ini`
değerleri, `php artisan optimize`) ve deploy kontrol listesine OPcache,
`DEBUGBAR_ENABLED=false` ve `--no-dev` maddeleri girdi.

Test: `SystemHealthPageTest` — üç yeni test (iki kontrolün varlığı, önbellek
uyarısının düzeltme komutunu söylemesi, OPcache kontrolünün her ortamda
istisna atmadan cevap vermesi).

#### Sunucuda yapılacaklar

```bash
# hosting panelinden PHP eklentileri → OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=20000
```

```bash
php artisan optimize
```

Beklenen: ~365 ms'lik sabit maliyet 40-80 ms'ye iner.

#### Yol üzerinde görülen, sebep olmayanlar

- **Alt bilgi istek başına 8 sorgu açıyor** (her sayfa bağlantısı için önce
  `lang_group_id`, sonra `slug`). Toplam ~4 ms; SQL zaten 23 ms olduğu için
  bugün ölçülebilir bir kazanç yok. Alt bilgi bilerek parça önbelleğine
  alınmamıştı (bülten formu CSRF taşıyor).
- **Canlıdaki sürüm eski.** `/admin/seo` ve `/admin/push-duyurulari` 404
  dönüyor, yanıtta `Content-Security-Policy` başlığı yok — 1 Eylül'ün hiçbir
  işi sunucuda değil. "Son eklenenler yavaşlattı" ihtimali zaten fiilen
  mümkün değildi.

---

### 3.2 İkinci belge taraması (1 Eylül, akşam) — "başka ne kaldı"

Yedi belge bir kez daha, bu sefer **öneri diliyle** tarandı: yalnız durum
işaretleri değil, metnin içine gömülü "önerilir / yapılabilir / şimdilik /
sonraki tur / kalan" ifadeleri de arandı. Her bulgu koda karşı doğrulandı.

Sonuç: **bir tane gerçek eksik çıktı**, bir belge borcu, bir de yanlış teşhis
düzeltildi.

#### ✅ Kapandı — API'de iki adımlı doğrulama kurulumu yoktu

`API.md`'de bir cümle olarak duruyordu: *"İki adımlı doğrulamanın **kurulumu**
şimdilik yalnız web'de."* Üç yüz tablosunda da nitelikli bir işaretle:
**"✅ giriş"**. İkisi de aynı şeyi söylüyor ama hiçbir yerde açık iş olarak
listelenmemiş.

Somut hâli şu: web tarafında dört uç var — kurulum başlat, QR onayla, kapat,
kurtarma kodlarını yenile. **API tarafında sıfır.** Yani yalnız mobil
uygulamadan giren bir kullanıcı 2FA'yı hiç açamıyor; açmak için tarayıcı
bulup siteye girmesi gerekiyor.

Bu, mobil uygulama API'lerini de hedefleyen bir kit için gerçek bir boşluk.
İş kapsanabilir görünüyor: `TwoFactorService` ve `TwoFactorChallengeService`
zaten yazılı ve web denetleyicisi onları çağırıyor; eksik olan API
denetleyicisi, rotalar, OpenAPI girdileri ve testler.

| Yüz | Kurulum başlat | Onayla | Kapat | Kurtarma kodları | Girişte kullan |
|---|:---:|:---:|:---:|:---:|:---:|
| Web | ✅ | ✅ | ✅ | ✅ | ✅ |
| API | ✅ | ✅ | ✅ | ✅ | ✅ |

**Yapıldı (1 Eylül).** Dört uç `/account/two-factor` altında, hepsi web'deki
güvenlik ekranıyla **aynı servisten** geçiyor (`TwoFactorService`) — iki yüz
ayrı mantık yazsaydı biri ötekinden sapardı ve sapma ancak bir kullanıcı
kilitlendiğinde görünürdü.

| Yöntem | Adres | Yetenek | Ne yapar |
|---|---|---|---|
| `GET` | `/account/two-factor` | `profile:read` | Durum |
| `POST` | `/account/two-factor` | `profile:write` | Kurulumu başlat |
| `POST` | `/account/two-factor/confirm` | `profile:write` + hız sınırı | İlk kodla tamamla |
| `DELETE` | `/account/two-factor` | `profile:write` + şifre + hız sınırı | Kapat |
| `POST` | `/account/two-factor/recovery-codes` | `profile:write` + şifre + hız sınırı | Kodları yenile |

**Kurulum iki isteğe bölünmüş** — web'deki gerekçenin aynısı: tek istekte
açılsaydı kareyi okutmayı beceremeyen kişi kendi hesabından kilitlenirdi.

**Başlatma ucu üç biçim birden dönüyor:** `otpauth_uri` (kimlik doğrulayıcıyı
doğrudan açmak için), `secret` (kareyi okutamayan kullanıcının elle girmesi
için) ve `qr_svg` (~8 KB satır içi SVG). Üçüncüsü fazlalık gibi duruyor ama
değil: kullanıcı çoğu zaman kodu **başka** bir cihazdaki uygulamayla okutuyor
ve o durumda kareyi uygulamanın kendisi çizmek zorunda.

**Durumda `pending` alanı var** — anahtar üretilmiş ama ilk kod girilmemiş.
İstemci bunu bilmezse kullanıcıyı baştan başlatır ve okuttuğu kare geçersiz
olur.

**Kapatma ve kod yenileme şifre istiyor**, ve yönetici zorunluluğu açıkken
yönetici kendi ikinci adımını kaldıramıyor (422) — web'deki kuralın aynısı,
yoksa ayar bir kural değil öneri olurdu.

**Anahtar hiçbir okuma ucunda geçmiyor**; ayrıca sınanıyor.

Test: `Api/ApiTwoFactorSetupTest` — 18 test. Sınavların çoğu "açılıyor mu"dan
çok **açılmaması gereken durumlara** bakıyor: yanlış kodla açılmamalı, kurulum
başlatılmadan onaylanmamalı, şifresiz kapatılmamalı, salt okunur jetonla
değiştirilememeli.

Uçtan uca tarayıcı/istemci denemesi: API'den kurulum yapıldıktan sonra
`POST /auth/login` gerçekten ikinci adımı istedi ("Girişi tamamlamak için iki
adımlı doğrulama kodu gerekiyor"), kapatıldıktan sonra jetonu doğrudan verdi.
Şifre onaylı uçlar art arda çağrıldığında hız sınırı devreye girdi (429) —
`throttle:api-password`, dakikada beş istek.

#### Belge borcu — B-7

`PROJE-DURUMU.md`'nin yedekleme bölümünde *"Kalan yarı: yedeğin dış kopyası
hâlâ yok — sonraki tur"* yazıyordu. O sonraki tur geldi: yol haritası 5.1'de
kapandı (dış hedef, yükleme sonrası doğrulama, başarısızlıkta bildirim,
ayrı saklama süresi; `BackupOffsiteTest`). Metin düzeltildi.

#### Yanlış teşhis düzeltildi — `X-XSS-Protection`

Boşluk analizi bu başlığın kaldırılmasını öneriyordu (güncel hiçbir tarayıcı
desteklemiyor, bazı eski sürümlerde filtrenin kendisi XSS'i kolaylaştırıyor).
Canlı sitenin yanıtında başlık **hâlâ duruyor** — ilk bakışta sunucu
yapılandırmasından geliyor sandım.

Değilmiş: `SecurityHeaders` middleware'ı onu **basmıyor** ve yerinde neden
basmadığını anlatan bir yorum duruyor. Kaldırıldığı commit `c40c427` — yani
1 Eylül'ün işlerinden biri. Canlıdaki sürüm o günden eski olduğu için başlık
orada görünmeye devam ediyor. **Deploy edildiğinde kendiliğinden gidecek**,
yapılacak bir iş yok.

#### Bilerek kapsam dışı — karar verilmiş, iş değil

Yol haritasının kendi listesi; tekrar tartışılmasın diye buraya da yazıldı:
e-ticaret (ürün/sipariş/ödeme), sosyal giriş (Google/Apple) ve çok kiracılı
yapı. Üçü de "unutuldu" değil, "bilerek dışarıda".

#### Taranıp temiz çıkanlar

- `BOSLUK-ANALIZI.md`'deki bütün "Öneri (denetim)" blokları uygulanmış
  (nonce tabanlı CSP, `CacheKeys`, önek bazlı önbellek temizliği, parça
  önbelleği). On beş bulgunun tamamı kapalı.
- Modül önerileri tablosundaki on üç modülün on biri ✅; kalan ikisi zaten
  bildiğimiz iki büyük iş.
- API yüzeyi web'e göre eksiksiz sayılır: iletişim formu, bülten aboneliği,
  yorum gönderme, arama, ayarlar, çeviriler, hesap uçları, cihaz yönetimi,
  push jetonları, avatar yükleme — hepsi var. Tek fark yukarıdaki 2FA
  kurulumu.
- SEO ve PWA'nın API'de olmaması boşluk değil: sitemap ve servis çalışanı
  tarayıcının işi.

---

### 3.1 Belge taraması (1 Eylül, push ekranından sonra)

Yedi belge yeniden tarandı ve her açık madde **koda karşı doğrulandı** — belge
"açık" diyor diye açık sayılmadı, "kapandı" diyen de sınandı. Sonuç: iki büyük
iş dışında kalan her şey ya kapanmış ya da küçük kalem.

#### Gerçek açık işler

| # | Madde | Efor | Not |
|---|---|---|---|
| ~~1~~ | ~~İçerik sürümleme (revisions)~~ | Orta | ✅ **1 Eylül'de tamamlandı** |
| 2 | Dinamik form oluşturucu | Büyük | Kapsam kararı bekliyor |

#### Küçük kalemler — koda karşı doğrulanmış

| # | Bulgu | Nerede | Durum |
|---|---|---|---|
| ~~K-1~~ | Tarayıcı kutusu **dört** yerde duruyormuş (ikisi sonradan çıktı) | `bulk-actions.js`, `inline-actions.js`, `app.js` (×2) | ✅ **kapandı** — aşağıya bakın |
| ~~K-2~~ | `alert` / `confirm` / `prompt` yasağının bekçisi yok | — | ✅ **kapandı** — `BrowserDialogsAreForbiddenTest` |
| ~~K-3~~ | "Her liste ekranında dışa aktarma" kuralının bekçisi yok | — | ✅ **kapandı** — `ListScreensOfferExportTest` |
| K-4 | `ModelFactoriesTest` bir koşuda tek seferlik düştü | — | 👀 **izlemede** — ardından beş koşuda tekrarlamadı, sebebi bulunamadı |

#### Belge borcu — belgeler gerçeği anlatmıyor

Aşağıdakiler kodda **kapanmış** ama belgede hâlâ açık duruyor. Birleştirme
turunda özgün dosyalar bilerek silinmedi; sonraki üç iş yalnız bu kayda
işlendi ve özgün dosyalar geride kaldı.

| # | Belge | Satır | Ne diyor | Gerçek |
|---|---|---|---|---|
| ~~B-1~~ | `YOL-HARITASI.md` | 174 | "4.1 Push — panel ekranı bekliyor" | ✅ **kapandı** — aşağıya bakın |
| ~~B-2~~ | `YOL-HARITASI.md` | 230 | "Kalan: bağımlılığı `composer.json`'dan düşürmek" | ✅ **kapandı** — aşağıya bakın |
| ~~B-3~~ | `PROJE-DURUMU.md` | 523, 543, 2051 | "bilerek ertelenmiş iki madde" (push ekranı + session json) | ✅ **kapandı** — aşağıya bakın |
| ~~B-4~~ | `PROJE-DURUMU-V2.md` | 607 | "Açık kalan iki madde" | ✅ **kapandı** — rapor tarihli olduğu için bulgusu korundu, altına "sonradan" notu düşüldü |
| ~~B-5~~ | `PROJE-KAYDI.md` arşivi + `PROJE-DURUMU.md` | 893 / 181 | "Hâlâ duran ölü kod" — üç madde de geçersiz | ✅ **kapandı** — kaynak dosyada düzeltildi, arşivde "bugün geçersiz" notu |
| ~~B-6~~ | `API.md` | 487 | Örnek gövde yalnız `comment_updates` gösteriyor | ✅ **kapandı** — örnek genişletildi, türlerin sabit olmadığı yazıldı |

**Arşiv notu.** B-5'in `PROJE-KAYDI.md` kopyası **arşiv bölümünde** (Bölüm A).
Arşiv metni olduğu gibi bırakıldı, yanına "bugün geçersiz" notu düşüldü;
düzeltme kaynak dosyada (`PROJE-DURUMU.md`) yapıldı.

#### ✅ K-1 · K-2 · K-3 ve B-3…B-6 kapatıldı (1 Eylül)

**K-1 — tarayıcı kutuları.** İki değil **dört** yerde duruyormuş; ikisi ilk
taramada gözden kaçmıştı çünkü satır numarasını yorumları silerek hesaplamıştım
ve numaralar kaymıştı. Yorumları silmek yerine **aynı uzunlukta boşlukla
değiştirince** dördü de çıktı:

| Yer | Neydi |
|---|---|
| `assets/admin/js/bulk-actions.js` | toplu işlem onayında `window.confirm()` |
| `assets/admin/js/inline-actions.js` | `data-confirm-submit` yolunda `window.confirm()` |
| `public/js/app.js` — `showConfirmModal` | `confirm()` |
| `public/js/app.js` — `showResultModal` | `alert()` |

Dördü de "modal yüklenmemişse son çare" gerekçesiyle yazılmıştı. Gerekçe
makuldü, **dayanağı yanlıştı**: modal işaretlemesi de betiği de her iki
layout'a koşulsuz basılıyor, yani o dallara hiç girilmiyordu. Ölü kod olarak
durup kuralı çiğniyorlardı.

Yerlerine "işlemi yapma ve sebebini konsola yaz" kondu. Yön bilinçli: **onay
alamadan silmektense hiç silmemek** doğru taraf. Sessizce `return` etselerdi
tıklanan ama hiçbir şey yapmayan bir düğme kalırdı, sebebi de bilinmezdi.

**K-2 — bekçi.** `BrowserDialogsAreForbiddenTest` üç şeyi birden sınıyor:

1. Kendi JavaScript'imizde `alert(` / `confirm(` / `prompt(` yok.
   `AdminModal.confirm(` ve `confirmDelete(` gibi adlar dışarıda kalıyor
   (nokta ya da harf öncesi olmayan çıplak çağrı aranıyor), `window.` öneki
   ayrıca aranıyor çünkü o da noktayla geliyor ama kutunun ta kendisi.
2. **Yasağın dayanağı**: kutuların yerini alan modal partial'ları her iki
   layout'a basılıyor **ve** betiklerin `getElementById` ile aradığı kimlikler
   (`globalConfirmModal`, `globalStatusModal`, `confirmModal`, `resultModal`)
   o partial'larda gerçekten duruyor. Bu olmadan birinci madde, onay penceresiz
   bir silme düğmesine dönüşebilirdi.
3. Kutuyu bulamayan yolların sebebi yazdığı.

Bekçi kendi üzerinde sınandı: yasak çağrı geri konduğunda dosya ve satır
numarasıyla düşüyor, aynı satırdaki iki ihlalin ikisini birden raporluyor.

**K-3 — dışa aktarma bekçisi.** `ListScreensOfferExportTest`. Kapsam elle
yazılmış listeden değil görünümlerin kendisinden besleniyor: `<table` çizen her
panel görünümü sınava giriyor (bugün 29 ekran). Kabul edilen iki işaret var —
`<x-export-menu>` bileşeni ve `route('admin.export'` (rapor merkezi kendi
düğmelerini basıyor, çünkü hangi raporun indirileceği adres satırındaki `type`
ile geliyor).

Üç istisna, her biri gerekçesiyle: `analytics/index` (grafik panosu, ham liste
`visits` ekranında), `analytics/live` (saniyede değişen canlı ekran — dosyaya
dökülen şey indirildiği anda eskimiş olur) ve `audit-logs/show` (tek kaydın
detayı, liste değil).

**İstisna listesinin bayatlaması ayrıca sınanıyor** — bekçinin kör noktası tam
olarak orası: listedeki dosya silinmişse satır ölüdür, ekrana sonradan dışa
aktarma eklenmişse istisna yanlış yere bakıyordur. İkisi de listeyi sessizce
güvenilmez yapar. Üçüncü bir sınav da kapsamın boş kalmadığını doğruluyor:
tarayıcı bir gün yanlış dizine bakarsa sessizce yeşil kalırdı.

Bekçi iki yönde de kanıtlandı: dışa aktarması olmayan bir liste ekranı
eklendiğinde düştü, bir istisnaya dışa aktarma eklendiğinde de düştü.

**B-3…B-6 — belge borcu.** Her belgeye türüne göre davranıldı:

- **`PROJE-DURUMU.md`** ("ne var" belgesi, yaşayan) — ertelenmiş iki madde
  kapandı olarak yazıldı, üç yüz tablosunda push satırı güncellendi, ölü kod
  bölümü koda karşı doğrulanmış hâliyle düzeltildi, tamamlananlar listesine
  1 Eylül'ün üç işi eklendi, başlık notu yaşayan belge politikasına çevrildi.
- **`PROJE-DURUMU-V2.md`** (tarihli denetim raporu) — **bulgusuna
  dokunulmadı.** Denetimin o gün ne bulduğu, sonradan ne olduğundan ayrı bir
  bilgi; geriye dönük düzeltmek raporu değersizleştirirdi. Altına "sonradan"
  notu düşüldü: ikisinde de engel sanılan şey engel değilmiş — push ekranı
  uydurulmadan (kampanya tasarımı uyarlanarak), oturum biçimi bakım penceresi
  olmadan (`migrate` moduyla) çözüldü.
- **`PROJE-KAYDI.md` arşivi** — arşiv metni olduğu gibi bırakıldı, yanına
  "bugün geçersiz" notu düşüldü.
- **`API.md`** — örnek gövdeye `push_announcements` eklendi ve **türlerin sabit
  olmadığı** yazıldı: uygulama listeyi `types` üzerinden çizmeli, kendi içine
  gömmemeli. OpenAPI şeması zaten anahtar-bağımsız (`additionalProperties`),
  yani sözleşme bozulmamıştı — eksik olan örnekti.

---

#### ✅ B-1 ve B-2 kapatıldı — yol haritası gerçeğe getirildi

**Durum:** ✅ kapandı (1 Eylül 2026)

B-1'i açarken belgenin tamamı okundu ve **B-1'den büyük bir kusur** çıktı:
haritanın kabul ölçütlerinde anılan **altı test dosyası yoktu.**

| Belgede yazan | Gerçek dosya |
|---|---|
| `Api/ApiPushTokenTest` | `Api/ApiPushAndHealthTest` |
| `Api/ApiHealthTest` | `Api/ApiPushAndHealthTest` |
| `Api/ApiAccountDeletionTest` | `Api/ApiAccountDataTest` |
| `Api/ApiAccountCommentsTest` | `AccountCommentsTest` (web ve API aynı dosyada) |
| `PwaManifestTest` | `PwaTest` |
| `ServiceWorkerTest` | `PwaTest` |

Hiçbiri yanlış yazılmış değildi: testler yazılırken başka dosyalarda
birleştirilmiş, belge yerinde kalmıştı. Sonuç, maddenin sınanıp sınanmadığını
kimsenin doğrulayamaması — kabul ölçütü var, ölçüte giden yol yok. Kural
yazılıydı ("her maddenin en az bir testi var"), bekçisi yoktu.

**Yapılanlar.**

- **4.1 Push** — ✅ bitti olarak yazıldı; ne yapıldığı (üç ekran, cron gönderimi
  ve gerekçesi, üç süzgeç, yetki ayrımı, tasarımın kampanyadan uyarlanması),
  kabul ölçütü ve iki testi eklendi.
- **Faz 4 başlığı** — "✅ TAMAMLANDI" oldu; 4.1 açıkken faz da açıktı.
- **Faz 5 başlığı** — "(bir madde bilerek ertelendi)" kaldırıldı; o madde
  (`session.serialization`) 1 Eylül'de kapandı.
- **5.2** — bayat "Kalan: bağımlılığı düşürmek" satırı kaldırıldı; bağımlılık
  `composer.json`'da yok, kodda kalan tek gönderme bir tarihçe yorumu (B-2).
- **5.4** — "🟡 biri yapıldı" → "✅ ikisi de yapıldı"; kabul ölçütü ve iki testi
  yazıldı (eskiden hiç testi anılmıyordu).
- **4.4** — şemadaki uç sayısı 38'den 43'e çekildi.
- **Altı test göndermesi** gerçek dosya adlarıyla eşlendi (aynı düzeltme
  Bölüm B arşiv kopyasında da yapıldı; gerekçesi arşiv başlığında yazılı —
  hiçbir yere çıkmayan bir gönderme kimseye bir şey anlatmıyor).
- **Belgenin kendi politikası** düzeltildi: başlıktaki not "içeriği değişmedi"
  diyordu ama 5.4 zaten elle güncellenmişti. Artık açıkça yazıyor — bu dosya
  faz planı olarak yaşıyor ve maddeler kapandıkça güncelleniyor; donmuş bir "ne
  eksik" listesi, eksik olmayan şeyleri eksik göstererek yanıltıyor.
- **Giriş bölümü** "dört faz" diyordu, beş faz var; düzeltildi ve bugünkü
  durum ("beşinin beşi de kapandı") yazıldı.

**Bekçi.** `DocsCiteRealTestsTest` — `docs/*.md` ve `README.md` içindeki her
`Test: \`X\`` göndermesini tarıyor ve adı geçen dosyanın `tests/` altında
gerçekten bulunduğunu doğruluyor. Bekçinin kendisi de sınandı: belgeye
uydurma bir test adı konduğunda dosya ve satır numarasıyla düşüyor.

Bekçi elle yazılmış listeye bakmıyor — belgelerin kendisinden besleniyor, yani
yarın eklenen bir madde kapsama kendiliğinden giriyor. Serbest bırakılan tek
ifade `Test: mevcut suite` (5.3'ün ölçütü suite'in kendisi).

#### Doğrulanan ama sağlam çıkanlar

Tarama sırasında sınanıp **sorun bulunmayan** kurallar — bir sonraki turda
tekrar bakılmasın diye yazılı:

- `declare(strict_types=1)`: `app`, `database`, `routes`, `config`, `tests`
  altındaki **her** PHP dosyasında var (sıfır eksik).
- `SoftDeletes`: 38 modelin hepsinde.
- `$guarded`: hiçbir modelde yok, hepsi `$fillable`.
- `down()`: 80 migration'ın hepsinde.
- `Storage::disk('public')` / `asset('storage/...')`: sıfır kullanım.
- Kendi kodumuzda jQuery: yalnız doğrulama motoru ve Select2 sarmalayıcıları.
- Kod içinde `TODO` / `FIXME` / `HACK`: sıfır.
- Atlanan 8 test: hepsi gerekçeli ve gerekçesi doğru — arkasında tablo olmayan
  sekiz dışa aktarma listesi (`backups`, `translations`, `system-health`,
  `reports`, `campaign-recipients`, `content-list`, `failed-jobs`,
  `seo-audit`). Fabrikası olmadığı için atlanan **hiçbir** liste yok.
- OpenAPI kapsamı: `api/v1` altındaki bütün uçlar belgeli, belgede uydurma uç
  yok (`OpenApiSpecTest` sekiz açıdan sınıyor). `POST /api/analytics/track`
  bilerek dışarıda: o mobil sözleşmenin değil ön yüzün izleme ucu.

---

### ✅ Tamamlandı — Satır içi olay işleyicilerini JS'e taşımak

**Durum:** ✅ kapandı (1 Eylül 2026) · **Tür:** güvenlik borcu

**Sorundu.** Nonce tabanlı CSP, `onclick`/`onchange`/`oninput` niteliklerini
engelliyor — nitelik değeri betiğin kendisi olduğu için oraya nonce
konulamıyor. Panelde bunlardan **219 tane** vardı, **50 dosyada**. Geçici çözüm
olarak `script-src-attr 'unsafe-inline'` yönergesi duruyordu; politikada açık
kalan tek taviz oydu.

**Yapıldı.** Davranış `data-*` kancalarına ve merkezi bir bağlayıcıya taşındı
(`public/assets/admin/js/inline-actions.js`). Kancalar:

| Kanca | Ne yapıyor | Sayı |
|---|---|---|
| `data-submit-form` | Süzgeç formunu gönderir | 75 |
| `data-scroll-to` / `data-scroll-select` | Uzun formda bölüme atlar | 55 |
| `data-action` + beyaz liste | Modül eylemleri (silme onayı, moderasyon, rapor) | ~45 |
| `data-settings-tab` | Ayarlar sekmesi | 11 |
| `data-char-counter` / `data-seo-preview` | Karakter sayacı, arama önizlemesi | 13 |
| `data-click-target` | Gizli dosya girdisini tetikler | 7 |
| `data-toggle-class` / `data-hint-target` | Anahtar ve açıklama alanı | 6 |
| `data-confirm-submit` | Onay isteyip formu gönderir | 1 |
| `data-share-window` (ön yüz) | Paylaşımı ayrı pencerede açar | 4 |

**Politika sıkılaştı:** `script-src-attr 'unsafe-inline'` → **`'none'`**. Artık
enjekte edilen bir `onerror=` niteliği de çalışmıyor.

**Tasarım kararı.** Bağlayıcı kanca değerinden fonksiyon adı türetmiyor
(`window[el.dataset.fn]` yok): öyle olsaydı CSP'yi kaldırıp yerine aynı kapıyı
açardık. Eylem haritası sabit ve her çağrı yazılı (`cagir(window.openDeleteModal, …)`).
Bunu bir test bekliyor — ve yazarken **kendi bekçim beni yakaladı**: ilk
sürümde `window[ad]` kullanıyordum.

**Bekçi.** `InlineHandlersAreForbiddenTest` — üç test: görünüm ağacında satır
içi işleyici kalmadığı, bağlayıcının her panel sayfasında yüklendiği, ve
işaretlemeden fonksiyon çözümlemediği.

**Doğrulandı (tarayıcı).** Süzgeç seçicisi formu gönderiyor, ayarlar sekmesi
geçiş yapıyor (`stg-general` → `stg-social`), karakter sayacı 0→19 güncelleniyor,
bölüm gezinme aktifleşiyor, anahtar kutuyu gizliyor, gizli dosya girdisi
tetikleniyor, `data-action` doğru fonksiyona doğru argümanla gidiyor
(`openMessage(19)`), ön yüz paylaşımı `600×400` penceresi açıyor. On iki
ekranda **sıfır** satır içi nitelik, konsol temiz.

**Yol üzerinde.** İki test eski biçimi (`confirmCommentAction('approve'`)
arıyordu; sınadıkları şey değişmedi, kanca biçimine çevrildi. Ayrıca
`ListExportTest`'in sorgu bütçesi ölçümü tam paket içinde bir kez kaydı —
ölçüm artık kendi bilinen önbellek durumundan başlıyor ve üç ardışık koşuda
kararlı.

**Değişen dosyalar:** `public/assets/admin/js/inline-actions.js` (yeni),
`public/js/share-window.js` (yeni), 50 görünüm,
`app/Services/ContentSecurityPolicy.php`, `resources/views/layouts/admin.blade.php`,
`tests/Feature/InlineHandlersAreForbiddenTest.php` (yeni),
`tests/Feature/ContentSecurityPolicyTest.php`, `tests/Feature/BlogCommentAdminScreenTest.php`,
`tests/Feature/ListExportTest.php`

---

### ✅ Tamamlandı — İçerik sürümleme (revisions)

**Durum:** ✅ kapandı (1 Eylül 2026) · **Tür:** yetenek

**Sorundu.** Denetim izi *neyin* değiştiğini gösteriyor ama geri
döndüremiyor. Yanlışlıkla silinen bir paragrafın tek karşılığı, onu
hatırlayan birinin yeniden yazmasıydı.

**Üç karar.** Açık sorular kullanıcıya soruldu ve en dar kapsam seçildi —
kitin geri kalanında işe yaramış desen:

| Soru | Karar | Gerekçe |
|---|---|---|
| Hangi modeller? | **Sayfa + blog yazısı** | En çok düzenlenen, kaybı en pahalı ikisi. Galeri/SSS/slider/popup'ta düzenleme genelde tek alanlık |
| Kaç sürüm? | **Son 20** | Sabit sayı, zaman aralığı değil: disk böyle tahmin edilebilir oluyor |
| Dile mi, dil grubuna mı? | **Dile bağlı** | İki dili iki ayrı kişi düzenlediğinde biri ötekinin işini silmemeli |

**Nasıl çalışıyor.** Her kaydetmede o anki hâl `content_revisions` tablosuna
yazılıyor; listenin başındaki sürüm **her zaman** içeriğin şu anki hâli.
Geri yükleme yeni satır açmıyor, mevcut kaydı güncelliyor — adres, kimlik ve
bağlantılar korunuyor — ve **kendisi de bir sürüm doğuruyor**, yani "yanlış
sürüme döndüm" diyen kişi bir öncekine dönebiliyor.

**Sürüm ne zaman yazılmaz.** Yalnız `config/revisions.php`'de listelenen
alanlardan biri değiştiğinde yazılıyor. Karar Eloquent'in değişiklik izine
değil, **son sürümle karşılaştırmaya** bakıyor — ve bu, yazarken ölçülen bir
hatanın sonucu:

> Hiçbir alanı kirletmeyen bir `save()` çağrısında Eloquent güncelleme
> sorgusunu atlıyor ve `getChanges()` bir önceki kaydın değerlerini taşımaya
> devam ediyor. `wasChanged()` o anda "evet" diyor. İlk kurgu buna
> dayanıyordu ve alakasız bir alanı kaydetmek sahte bir sürüm doğuruyordu;
> hatayı yazılan sınav yakaladı, kurgu değiştirildi.

Bunun ikinci bir kazancı da var: blog yazısının `views` sayacı her ziyarette
artıyor ve `increment()` model olayı doğuruyor. Sayaç tetikleyici sayılsaydı
popüler bir yazının yirmi sürümlük geçmişi bir günde dolar, gerçek
düzenlemeler listeden düşerdi. Ayrıca sınanıyor.

**Yazılan dosyalar.**

| Katman | Dosya |
|---|---|
| Yapılandırma | `config/revisions.php` — tavan ve sürümlenen alanlar, tek kaynak |
| Şema | `content_revisions` (polimorfik + `locale`, hedef indeksi) |
| Model | `ContentRevision` (+ fabrika) |
| Trait | `HasRevisions` — `Page` ve `BlogPost`'a takılı |
| Servis | `ContentRevisionService` — yakala / buda / geri yükle |
| Ekran | `admin/revisions/index.blade.php` — iki içerik türü için tek ekran |
| JS | `content-revisions.js` (önizleme + geri yükleme onayı) |
| Dışa aktarma | `ContentRevisionExport` + kayıt defterine bir satır |
| Test | `ContentRevisionTest` — 24 test |

**Budama kalıcı.** Yumuşak silme trait'i projedeki kurala uyuyor ama tavanı
aşan sürümler `forceDelete()` ile gidiyor: tavanın var olma sebebi disk ve
yumuşak silinen satır diskte durmaya devam ederdi. İçerik kalıcı silindiğinde
geçmişi de gidiyor; yumuşak silmede duruyor, çünkü geri alınan içerik
geçmişiyle birlikte dönmeli.

**Ekran nerede.** Ayrı bir sayfada (`/admin/surumler/{tür}/{id}`), düzenleme
formunun içinde değil: o form zaten yedi bölümlü ve geçmiş listesi dil
sekmelerinin içine sıkışmıyordu. Düzenleme ekranının başlığına bir
**Sürümler** düğmesi eklendi — mevcut düğme grubunun `btn-glass` biçimiyle
aynı, yeni bir tasarım öğesi değil.

**Yol üzerinde kendi bekçim yakaladı.** Yeni ekran tablo çiziyor ama dışa
aktarma sunmuyordu; aynı gün yazılan `ListScreensOfferExportTest` düştü.
Kural kullanıcının kendi koyduğu kuraldı ("bütün liste ekranlarına export"),
istisna yazmak yerine `content-revisions` tanımı eklendi — geçmiş artık
Excel/CSV/PDF olarak da inebiliyor.

**Doğrulama.** 1979 test yeşil (24'ü yeni), Pint sapmasız, PHPStan temiz.
Şema MySQL 8'de kuruldu, `down()` gidiş-dönüşü yapıldı, ilgili testler MySQL'e
karşı da koştu. Tarayıcıda: liste ekranı, sürüm önizlemesi, geri yükleme
onayı ve gerçek geri yükleme (başlık eski hâline döndü, önceki hâl listenin
başına yeni sürüm olarak yazıldı), üç biçimde dışa aktarma ve düzenleme
ekranındaki düğme tek tek denendi; konsol temiz.

---

### İş 1 — Dinamik form oluşturucu

**Durum:** ⬜ açık · **Efor:** büyük · **Tür:** yetenek

**Neden.** Her kurumsal projede iletişim dışında en az bir form isteniyor
(başvuru, teklif, bayilik) ve her seferinde kod yazılıyor.

**Neyin üstüne oturur.** FormRequest deseni, `data-validation-engine` kural
tablosu, `ContentFile` ekleri, mevcut mail altyapısı.

**Açık sorular.** Alan türleri kümesi, gönderilerin nerede saklanacağı, dosya
eki sınırları, formun çok dilli olup olmayacağı, spam koruması (reCAPTCHA
mevcut).

---

### ✅ Tamamlandı — Panelden push bildirim gönderme ekranı

**Durum:** ✅ kapandı (1 Eylül 2026) · **Tür:** eksik modül

**Sorundu.** Sunucu tarafı aylardır hazırdı — jeton kaydı, cihaz eşleştirme,
sağlayıcıdan bağımsız gönderim servisi, ölü jetonun düşmesi — ama bildirimi
yazıp gönderecek bir ekran yoktu. Duyuru göndermek için tinker'dan servis
çağırmak gerekiyordu; yani pratikte hiç gönderilmiyordu.

Ekranın yapılmamasının gerekçesi tasarımdı: admin temada bu ekranın karşılığı
yok, `notifications.html` yalnız tercih anahtarları içeriyor ve tasarımda
olmayan bir ekranı uydurmak proje kuralına aykırı.

**Çözüldüğü yer.** Uydurma yerine **kampanya modülünün tasarımı uyarlandı.**
İki ekran aynı işi yapıyor — başlık, metin, hedef kitle, gönder, sonuç — ve
kampanya ekranının tasarımı temada mevcut. Kullanılan düzen, sınıflar ve
bileşenler kampanyanınkiyle birebir aynı: `cmp-choice` hedef kartları,
`cmp-bar` ilerleme çubuğu, `cl-status-tabs` durum sekmeleri, `rdr-meta` künye
satırları. Tek yeni CSS sınıfı yok.

**Gönderim neden istekte değil.** Kampanya ile aynı gerekçe ve aynı desen:
paylaşımlı hosting'de alt süreç açılamıyor, `queue:work` çalıştırılamıyor.
"Sıraya Al" düğmesi kaydı `queued` durumunda açıyor, `push:dispatch` komutu beş
dakikada bir kaldığı yerden devam ediyor (`PushNotificationDispatcher`,
turda 200 cihaz). Tek istekte göndermek beş yüz cihazlı bir kurulumda beş yüz
HTTP çağrısı demekti: tarayıcı zaman aşımına düşer, yönetici gönderimin olup
olmadığını bilmez ve düğmeye bir kez daha basar.

**Hedef üç süzgeçten geçiyor.** Kitle (herkes / rol / tek kullanıcı), hesabın
açık olması, ve kullanıcının duyuruları kapatmamış olması. Üçüncüsü için
`NotificationPreference::PushAnnouncements` eklendi: hesap ekranındaki
"Uygulama duyuruları" anahtarı gerçekten bir şey yapıyor, kapatan kişiye
duyuru gitmiyor. Tercih kaydı olmayan kullanıcı açık sayılıyor — varsayılan
kapalı olsaydı özellik, varlığından haberi olmayan kimseye ulaşmazdı.

**Yazılan dosyalar.**

| Katman | Dosya |
|---|---|
| Enum | `PushNotificationStatus`, `PushAudience` |
| Şema | `2026_09_01_120000_create_push_notifications_table` |
| Model | `PushNotification` (+ `PushNotificationFactory`) |
| Servis | `PushBroadcastService` (liste/kayıt/iptal), `PushNotificationDispatcher` (gönderim) |
| Yetki | `PushNotificationPolicy` + üç `PermissionKey` (`view` / `send` / `delete`) |
| İstek | `StorePushNotificationRequest` |
| Denetleyici | `Admin\PushNotificationController` |
| Ekran | `admin/push-notifications/{index,create,show}.blade.php` |
| JS | `push-notifications.js` (liste/detay), `push-notification-form.js` (form) |
| Cron | `push:dispatch` + `routes/console.php` girdisi (5 dk) |
| Dışa aktarma | `PushNotificationExport` + `config/export.php` girdisi |
| Kayıt | sidebar, `config/help.php`, `PermissionSeeder` matrisi, rol matrisi testi |
| Test | `PushNotificationPanelTest` (29 test) |

**Yetki ayrımı.** Editör duyuruyu görebiliyor ama gönderemiyor — kampanyadaki
gerekçenin aynısı: cihaza ulaşmış bildirim geri alınamıyor. Silme üçüncü ve
ayrı bir yetki.

**Formda üç yardım.** Yazılan metin cihazda nasıl görünecek (canlı önizleme),
kaç karakter kaldı (sayaç), ve hedefe kaç cihaz denk geliyor (sunucudan
sorulan sayı — kullanıcının tercihi ve hesabının açık olması ekranda yok).

**Yol boyunca çıkan iki kusur.**

1. **`cursor` null kalıyordu.** Sütunun veritabanı varsayılanı 0 ama o yalnız
   satır yazıldıktan sonra geçerli; yeni kurulan örnekte alan null kalıyor ve
   gönderim `id > null` diye sorgu kurmaya çalışıyordu. Model artık
   `$attributes` ile sayaçların başlangıcını kendisi taşıyor.
2. **`@js()` HTML niteliğinin içindeydi.** Satır içi işleyicilerin taşındığı
   gün sekiz ekrana birden bu biçimde girmiş: `data-label="@js($ad)"`. `@js()`
   değeri JavaScript'e gömmek için üretiyor ve dizgeyi tırnak içinde veriyor,
   nitelik değerine konduğunda o tırnaklar değerin parçası oluyor — silme
   onayında kampanyanın adı `'Bahar kampanyası'` diye görünüyordu. On yerde
   `{{ }}` ile değiştirildi ve `InlineHandlersAreForbiddenTest`e bekçisi
   eklendi.

**Doğrulama.** 1923 test yeşil (29'u yeni), Pint sapmasız, PHPStan temiz.
Şema MySQL 8'de kuruldu, `down()` gidiş-dönüşü yapıldı, test paketi MySQL'e
karşı da koştu. Tarayıcıda: liste/form/detay ekranları, doğrulama motorunun
tetiklenmesi, canlı önizleme ve sayaç, hedef değişince cihaz sayısının
güncellenmesi, kullanıcı arama, gerçek gönderim akışı (`push:dispatch`),
iptal onayı ve üç biçimde dışa aktarma (Excel 5,4 KB / CSV 227 B / PDF 31 KB)
tek tek denendi; konsol temiz, CSP ihlali yok.

---

### ✅ Tamamlandı — `session.serialization = json`

**Durum:** ✅ kapandı (1 Eylül 2026) · **Tür:** güvenlik sertleştirmesi

**Sorundu.** Oturum verisi PHP'nin `serialize()` biçiminde yazılıyordu.
`unserialize()` veri okumakla kalmıyor, **nesne kuruyor** — saklanan dizgeyi
değiştirebilen biri uygulamadaki sınıflardan bir zincir kurup kod
çalıştırabiliyor. JSON'da bu yüzey yok; okunan şey yalnız veri.

Karar verilmişti ama **uygulanmamıştı**: ayarı çevirmek o anda açık olan bütün
oturumları okunamaz hâle getiriyor ve çerçeve sessizce boş bir oturum veriyor —
yani herkes aynı anda çıkış yapıyor. Çalışan bir kurulumda bu bir bakım
penceresi kararıydı.

**Yapıldı — bakım penceresi ihtiyacı kalktı.** `session.serialization` ayarına
üçüncü bir değer eklendi:

| Ayar | Yazma | Okuma |
|---|---|---|
| `php` | serialize | serialize |
| **`migrate`** | **JSON** | **JSON, tutmazsa serialize** |
| `json` | JSON | JSON |

`migrate` modunda açık oturumlar bir sonraki isteklerinde sessizce yeni biçime
geçiyor; kimse düşmüyor. Oturum ömrü kadar süre geçtikten sonra ayar `json`'a
alınıyor ve `unserialize()` yolu tamamen kapanıyor.

**Kit varsayılanı `json`** — yeni kurulumda ortada oturum olmadığı için geçiş
diye bir şey yok.

**Geçiş dönemi bile nesne kurmuyor.** Eski biçimi okurken `unserialize()`
sınırlı çağrılıyor (`allowed_classes: false`): eski bir oturumda nesne bulunsa
da kurulmuyor. Geçişin amacı o yüzeyi kapatmaktı; geçiş boyunca açık bırakmak
amacı boşa çıkarırdı.

**Önce doğrulandı, sonra yazıldı.** Projenin oturuma yazdığı her şey tarandı:
dil kodu (dizge), 2FA bekleme kaydı (id, bool, zaman damgası) ve flash
mesajları — hepsi skaler, JSON'a uygun. Tek nesne çerçevenin doğrulama hataları
torbası ve onu çerçeve zaten JSON için özel olarak çeviriyor
(`prepareErrorBagForSerialization` / `marshalErrorBag`).

**Doğrulama (gerçek tarayıcı oturumuyla).** Giriş yapılmış canlı bir oturumun
dosyası elle eski biçime çevrildi:

- `migrate` modunda istek → **200, oturum ayakta** ve dosya **kendiliğinden
  JSON'a taşındı**
- Karşıt durum testte kanıtlı: saf `json` deposu aynı oturumu okuyamıyor

Ayrıca doğrulama hataları ekranda göründü ("Geçerli bir e-posta adresi girin"),
giriş ve panel çalıştı, diskteki taze oturum dosyaları JSON.

**Test.** `SessionSerializationTest` — 13 test: varsayılan, üç modun doğru
depoyu kurması, eski oturumun okunması, JSON'a taşınması, nesne kurulmaması,
giriş akışı, doğrulama hataları, flash, dil seçimi. Ayrıca bir bekçi: oturuma
nesne yazan kod yok (JSON yalnız veri saklar).

**Değişen dosyalar:** `app/Session/` (4 yeni dosya), `config/session.php`,
`app/Providers/AppServiceProvider.php`, `.env.example`,
`tests/Feature/SessionSerializationTest.php` (yeni)

---

### İzlemede

- **`ModelFactoriesTest`** — bir koşuda tek seferlik bir hata görüldü, ardından
  dört ayrı koşuda tekrarlanmadı. Sebebi bulunamadı, kayda geçirildi.

### Kapsam dışı (bilerek)

- **E-ticaret** (ürün, sipariş, ödeme) — `ab57deb`'de sökülmüştü, base kit genel
  kalmalı. Temada duran `orders.html` / `products.html` bu yüzden boş.
- **Sosyal giriş** (Google/Apple) — her projede farklı sağlayıcı ve onay süreci.
- **Çok kiracılı yapı (multi-tenant)** — mimarinin tamamını değiştirir.

---

## 4. Arşiv bölümleri

Aşağıdaki dört bölüm, kaynak belgelerin tam içeriğidir. Yukarıdaki tablolarda
bir maddenin **ne olduğunu**, aşağıda **neden öyle yapıldığını** bulursun.


---
---

# BÖLÜM A — Proje Durumu

> **Arşiv.** Bu bölüm `docs/PROJE-DURUMU.md` dosyasının **tam ve değiştirilmemiş**
> içeriğidir; yalnız başlık seviyeleri bir kademe indirildi ki bu belgenin
> hiyerarşisine otursun. Modül modül ne var, hangi tur neyi kapattı, yol üzerinde hangi kusurlar çıktı. Projenin en uzun ve en ayrıntılı kaydı.
>
> Kaynak dosya yerinde duruyor ve okunmaya devam edebilir.


### Proje Durumu

**Son güncelleme:** 2026-08-31 (yol haritasının beş fazı tamamlandıktan sonra)
**Branch:** `feat/laravel-13-upgrade` — `main`'e göre 36 commit önde
**Kalan iş listesi:** [`YOL-HARITASI.md`](YOL-HARITASI.md)
**Stack:** PHP 8.4 · Laravel 13.26.1 · Blade · MySQL 8 · Bootstrap 5.3.8 (self-hosted) · Vanilla JS

---

#### 1. Proje Nedir?

Yeniden kullanılabilir bir **kurumsal site + admin panel base kit**'i. Projeye özgü
modüller (ürün, sipariş, e-ticaret) `ab57deb` commit'inde sökülüp genel altyapı
bırakıldı. Amaç: yeni bir kurumsal proje başlarken bu iskeleti klonlayıp üstüne
inşa etmek.

Build tool yok — Vite/npm/Node kullanılmıyor, tüm vendor kütüphaneleri
`public/assets/vendor/` altında hazır dosya olarak duruyor.

##### Rakamlar

| | Adet | | Adet |
|---|---|---|---|
| Model | 37 | Route | 356 (43'ü API) |
| Service | 82 | Migration | 79 |
| Controller | 86 (41 admin, 21 API) | Seeder | 11 |
| FormRequest | 86 | Blade view | 196 |
| Policy | 26 | Enum | 34 |
| Observer | 11 | Artisan command | 9 |
| Factory | 37 | Test dosyası | 126 |
| Test | 1711 | Assertion | 6303 |

**Suite durumu:** hepsi yeşil, ~50 saniye, PHP'nin stok 128 MB sınırıyla da
koşuyor (gereken sınır `phpunit.xml`'de bildiriliyor). Pint sapması sıfır,
PHPStan temiz.

**Composer bağımlılıkları (7):** `php`, `laravel/framework`, `laravel/sanctum`,
`laravel/tinker`, `mpdf/mpdf`, `openspout/openspout`, `bacon/bacon-qr-code`.
`jenssegers/agent` kaldırıldı (bkz. 5x).

---

#### 2. Mimari

Katmanlar net ayrılmış ve tutarlı uygulanmış:

```
Route → Controller (thin) → FormRequest (validation)
                          → Policy (authorization)
                          → Service (iş mantığı)
                          → Model (+ Observer)
```

- **Controller'da iş mantığı yok** — hepsi Service katmanında
- Her PHP dosyasında `declare(strict_types=1)`
- Modellerde `$fillable` (asla `$guarded = []`), `casts()` metodu, PHP 8.4 enum cast
- `HasSlug` trait'i slug üretimini merkezileştiriyor
- Admin route'ları `bootstrap/app.php` içinde ayrı yükleniyor (`admin` prefix + middleware)

##### Öne çıkan tasarım kararları

- **Shared hosting'e göre kurgulanmış:** `queue:work` yerine `routes/console.php`
  içinde cron'la her dakika 20 job çeken manuel worker (pcntl yok)
- **Upload:** `Storage` facade kullanılmıyor — her şey `public/uploads/`,
  `UploadService` üzerinden WebP dönüşümü + 4 responsive varyant (thumb/sm/md/lg)
- **Front ve admin varlıkları tamamen ayrı:** `public/css|js/` (front, açık tema)
  vs `public/assets/admin/` (admin, koyu tema)
- **`alert()`/`confirm()` yasak:** admin'de `AdminModal`, front'ta
  `resultModal`/`confirmModal`
- **Admin tema:** `resources/views/admin-theme/` altında 40+ hazır HTML referans
  tasarımı; yeni admin sayfaları buradan Blade'e çevriliyor

---

#### 3. Mevcut Modüller

##### İçerik yönetimi
| Modül | Admin | Front | Not |
|---|---|---|---|
| Sayfalar | ✅ CRUD + restore | ✅ `/{slug}` | Başlık/içerik/görsel/SEO — dil sekmeli |
| Blog | ✅ Yazı + kategori + yorum moderasyonu | ✅ liste/kategori/detay | RSS feed dahil |
| Galeri | ✅ Öğe + kategori | ✅ `/galeri` | Görsel + video türü |
| SSS | ✅ CRUD | ✅ `/sikca-sorulan-sorular` | |
| Slider | ✅ CRUD | ✅ anasayfa | |
| Popup/Modal | ✅ CRUD | ✅ sayfa bazlı | Tarih aralığı + sayfa hedefleme |
| Menü | ✅ Drag-drop sıralama | ✅ navbar | Sortable.js, konum bazlı (header) |

##### Sistem
| Modül | Durum | Not |
|---|---|---|
| Kullanıcı & Rol | ✅ | 5 rol: admin, editor, moderator, user, viewer |
| Ayarlar | ✅ | Grup bazlı, `Cache::remember` ile 24s cache |
| İletişim mesajları | ✅ | Cevaplama + okundu takibi |
| Redirect yönetimi | ✅ | Middleware ile 301/410 |
| Mail sistemi | ✅ | Şablon editörü (TinyMCE) + gönderim logu + resend |
| Analytics | ✅ | Kendi ziyaretçi takibi, KVKK IP maskeleme, günlük agregasyon |
| Audit log | ✅ | Şu an sadece `Setting` modelinde aktif |
| Bildirim merkezi | ✅ | Admin bell dropdown |
| File manager | ✅ | PDF/Word/Excel/görsel, Dropzone |
| Backup | ✅ | DB + uploads → ZIP, gecelik cron |
| System health | ✅ | Disk/DB/cache/queue kontrolü |

##### Front kullanıcı alanı (`/hesabim`)
Sadece **dashboard + profil düzenleme** var. Şifre değiştirme, e-posta doğrulama,
adres/tercih yönetimi yok.

##### Zamanlanmış görevler (`routes/console.php`)
- Her dakika: queue worker (manuel pop/fire)
- 02:00: analytics günlük agregasyon
- 03:00: IP anonimleştirme (KVKK, 90 gün) + gecelik backup
- Haftalık: eski page_views temizliği (365 gün), audit log temizliği (90 gün)

---

#### 4. Eski Proje Kalıntıları — ✅ Temizlendi

`ab57deb` refactor'unda ürün/sipariş modülü sökülürken geride kalan referanslar
temizlendi. Aşağıdaki tablo ne yapıldığını kayıt altına alır.

##### Hoş geldin e-postası

Yeni kayıt olan her kullanıcıya gıda/e-ticaret metni gidiyordu ve "Ürünleri Keşfet"
butonu var olmayan `/urunler` rotasına link veriyordu (catch-all `/{slug}` yakalayıp
404 döndürüyordu).

Metin **üç ayrı yerde** duruyordu, üçü de düzeltildi:

| Dosya | Rolü | Yapılan |
|---|---|---|
| `resources/views/emails/welcome.blade.php` | DB şablonu yoksa fallback | 4 özellik satırı genel metinle değiştirildi |
| `app/Services/MailTemplateService.php` | "Varsayılana Sıfırla" butonunun yazdığı içerik | Aynı şekilde düzeltildi + `{products_url}` butonu kaldırıldı |
| `database/migrations/2026_03_12_000000...mail_templates` | Yeni kurulumların seed'i | Kalan tek eski satır düzeltildi |

> **Not:** `MailTemplateService::getDefaults()` migration seed'iyle senkron değildi.
> Yani "Varsayılana Sıfırla" butonu şablonu **eski e-ticaret metnine geri
> döndürüyordu**. Artık ikisi aynı.

`app/Mail/WelcomeMail.php` içindeki `'products_url' => url('/urunler')` değişkeni
kaldırıldı. Yeni özellik satırları:

- 👤 Profil bilgilerinizi yönetin
- 📄 İçeriklerimizi keşfedin
- 📢 Yeni yazılardan haberdar olun
- ✉️ Bizimle iletişimde kalın

Mevcut veritabanındaki `welcome` satırı için
`2026_08_25_120000_clean_product_references_from_mail_templates` migration'ı
yazıldı. Admin'in özelleştirmesini ezmemek için **yalnızca** eski metin parçasını
değiştiriyor; `down()` işlemi geri alıyor (ikisi de test edildi).

##### Diğer temizlenenler

| # | Konum | Sorun | Yapılan |
|---|---|---|---|
| 1 | `MailTemplateService` | `order_confirmation`, `order_status_updated` şablonları (gövdeleri boş, DB'ye hiç seed edilmemiş, Mail sınıfları yok) | Kaldırıldı |
| 2 | `app/Models/MailLog.php` | `OrderConfirmationMail`, `OrderStatusUpdatedMail` etiketleri | Kaldırıldı |
| 3 | `admin/users/index.blade.php` | `orders_count` — ilişki yok, daima **0** gösteriyordu | Stat kaldırıldı, "Kayıt Tarihi" tek stat olarak bırakıldı (`space-around` ortalıyor, düzen bozulmuyor) |
| 4 | `database/seeders/RoleSeeder.php` | Editör = "İçerik ve **ürün** yönetimi" | "İçerik yönetimi yetkisi" |
| 5 | `admin/settings/index.blade.php` | "Yeni **sipariş** bildirimleri ve **Instagram** hata uyarıları" | "Sistem bildirimleri ve hata uyarıları" |
| 6 | `admin/popups`, `admin/sliders` (create+edit) | `placeholder="/urunler/..."` | `/blog/...` |
| 7 | `admin/redirects/index.blade.php` | `/urunler/kuru-gida`, `/urunler` | `/eski-sayfa-adresi`, `/yeni-sayfa-adresi` |
| 8 | `admin/menus/items.blade.php` | `categorySlug=sut-urunleri` | `categorySlug=duyurular` |
| 9 | `admin/pages/_sections-about.blade.php` | "50 yıllık tecrübemizle hazırladığımız **ürünlerimizi**..." | "Uzman ekibimizle sunduğumuz hizmetleri..." |
| 10 | `admin/mail-templates/edit.blade.php` | `em-table` → "Tablo (**ürün** listesi vb.)" | "Tablo (liste, özet vb.)" |
| 11 | `app/Services/TelegramNotifier.php` | Docblock: "yeni **sipariş**", "Yeni **Sipariş** #1234" | "yeni kayıt/mesaj", "Yeni İletişim Mesajı" |
| 12 | `app/Helpers/helpers.php` | Docblock örneği `products/sut-a1b2c3d4e5.webp` | `blog/ornek-gorsel-...` |
| 13 | `uploaded_files` migration | "**MediaLibrary** modülünden BAĞIMSIZ — o **ürün-bazlı**..." (MediaLibrary da yok) | Yorum güncel yapıya göre yeniden yazıldı |
| 14 | `public/uploads/products/`, `public/uploads/testimonials/` | Kodda 0 referans, boş dizinler | Silindi |
| 15 | `mail_templates` migration | Değişken örnekleri: "Ürün Bilgisi", "ürünleriniz hakkında" | "Bilgi Talebi", "hizmetleriniz hakkında" |

**Doğrulama:** `WelcomeMail` render edilip kontrol edildi — `/urunler`,
`{products_url}` ve sipariş/ürün kelimeleri yok. Kullanıcı kartı render edilip
`orders_count` ve "Sipariş" içermediği, "Kayıt Tarihi" içerdiği doğrulandı.
Kod tabanında kalan tek eşleşme temizlik migration'ının kendi arama metni.

##### ⬜ Hâlâ duran ölü kod (sipariş/ürünle ilgisiz)

Bunlar ayrı bir temizlik turu ister:

- **`app/Enums/UserRole.php`** — kod tabanında **0 referans**. Roller `roles`
  tablosu + `Role` modeli üzerinden yönetiliyor.
- **`resources/views/vendor/pagination/custom.blade.php`** — hiçbir yerden
  referans verilmiyor; sayfalama `pagination::bootstrap-5` kullanıyor.
- **`.gitignore`** — `/storage/app/google/*.json` kuralı duruyor ama dizin yok.

> **Bugün geçersiz (1 Eylül 2026).** Arşiv metni olduğu gibi bırakıldı ama üç
> maddenin üçü de artık doğru değil; koda karşı tek tek doğrulandı.
> `UserRole` "0 referans" değil, **yedi dosyadan** çağrılıyor: rol tohumlaması
> slug'ları oradan okuyor, izin matrisi rolleri oradan eşliyor, `RoleService`
> sistem rolünü oradan tanıyor — silinecek değil, kaynak niteliğinde bir enum.
> `vendor/pagination/custom.blade.php` dosyası yok. `.gitignore`'da google
> satırı yok. Kaynak dosyada (`PROJE-DURUMU.md`) bölüm düzeltildi.

---

#### 5. Yetkilendirme — ✅ Kapatıldı

Daha önce 26 admin controller'ın 13'ü `authorize()` çağırmıyordu; tek koruma
`AdminMiddleware`'di ve o da `admin`, `editor`, `moderator` rollerinin üçünü
birden içeri alıyordu. Yani bir **editör** veritabanı yedeğini indirebiliyor,
şifre sıfırlama e-postalarının gövdesini okuyabiliyordu.

##### Rol matrisi (artık uygulanıyor)

| Alan | admin | editor | moderator |
|---|:---:|:---:|:---:|
| Dashboard, kendi profili, bildirimler | ✅ | ✅ | ✅ |
| İletişim mesajları (görüntüleme + yanıtlama) | ✅ | ✅ | ✅ |
| **Yorum moderasyonu** (onay / red) | ✅ | ✅ | ✅ |
| Sayfa, blog, galeri, SSS, slider, popup | ✅ | ✅ | — |
| Menü yönetimi | ✅ | ✅ | — |
| Dosya yöneticisi · CKEditor upload | ✅ | ✅ | — |
| Analitik | ✅ | ✅ | — |
| Ayarlar | ✅ | — | — |
| Kullanıcılar | ✅ | — | — |
| **Yönlendirmeler** | ✅ | — | — |
| Mail şablonları | ✅ | — | — |
| **Mail logları** | ✅ | — | — |
| Aktivite logları | ✅ | — | — |
| **Yedekler** | ✅ | — | — |
| Sistem sağlık | ✅ | — | — |
| Silme / geri yükleme (her modülde) | ✅ | — | — |

> **Yönlendirmeler neden sadece admin?** Hedef adres ziyaretçinin gönderileceği
> yer olduğu için yönlendirme yönetimi trafik üzerinde doğrudan kontrol demek.
> Yetki daraltmasının yanına açık yönlendirme doğrulaması da eklendi — aşağıya
> bakın.

> **Moderatör neden yorum ve mesaj yönetebiliyor?** `RoleSeeder` bu rolü
> "Mesaj ve yorum yönetimi yetkisi" diye tanımlıyordu ama `BlogCommentPolicy`
> moderatörü dışlıyordu; yani rol tanımlıydı ama hiçbir şey yapamıyordu.
> Policy rol tanımına uyduruldu. Silme yetkisi admin'de kaldı.

##### Yapılanlar

**7 yeni Policy** (Laravel isim kuralıyla otomatik keşfediliyor, kayıt gerekmiyor):

| Policy | Yetki |
|---|---|
| `RedirectPolicy` | admin |
| `MailLogPolicy` | admin |
| `AuditLogPolicy` | admin |
| `MenuPolicy` | admin + editor |
| `MenuItemPolicy` | admin + editor (silme/geri yükleme admin) |
| `UploadedFilePolicy` | admin + editor (silme admin) |
| `AdminNotificationPolicy` | üç rol (silme admin) |

**4 yeni Gate** — model'i olmayan alanlar için, `AppServiceProvider` içinde:
`manage-backups`, `view-system-health` (admin) · `view-analytics`,
`upload-editor-media` (admin + editor)

**11 controller'a `authorize()` eklendi:** Backup, MailLog, AuditLog, Health,
Redirect, Menu, MenuItem, FileManager, Notification, Analytics, Upload.

`DashboardController` ve `ProfileController` bilinçli olarak dışarıda — biri
panele girebilen herkese açık genel özet, diğeri kullanıcının kendi profili.

**Arayüz yetkiye uyduruldu.** Sidebar 13 `@can` bloğuyla sarıldı; topbar'daki
Ayarlar linki ve dashboard'daki blog kısayolları da koşullandırıldı. Aksi hâlde
her yasak alan kullanıcı için ölü bir link olurdu.

**`User::hasRole()` / `hasAnyRole()` optimize edildi.** Önce her çağrı ayrı bir
`exists()` sorgusu atıyordu; `authorize()` çağrıları çoğaldığı için bu istek
başına onlarca sorguya çıkacaktı. Artık `roles` ilişkisi üzerinden okuyor —
istek başına tek sorgu.

##### Doğrulama

`tests/Feature/AdminAuthorizationTest` — 7 test:

- 21 rotanın üç rol için beklenen durum kodu (200/403) matrisi
- Editör yedek indiremiyor
- Editör mail log gövdesi okuyamıyor (şifre sıfırlama linki içeren gerçek kayıtla)
- Editör ve moderatör sidebar'ında yasak linkler görünmüyor
- Admin sidebar'ında her şey görünmeye devam ediyor
- Panel rolü olmayan kullanıcı 403 alıyor
- Moderatör yorum onaylayabiliyor ama silemiyor

---

#### 5c. Açık Yönlendirme — ✅ Kapatıldı

`HandleRedirects` middleware'i `new_url` değerini doğrudan `redirect()`
fonksiyonuna veriyor. `old_url` için `starts_with:/` kuralı vardı ama
**`new_url` için hiçbir kısıt yoktu** — yani kayıt oluşturabilen biri site
trafiğini istediği harici adrese yollayabiliyordu.

`app/Rules/SafeRedirectTarget` yazıldı ve iki FormRequest'e de bağlandı.
Kabul ettikleri:

- Site içi yollar (`/yeni-sayfa`)
- `APP_URL` host'una ait mutlak adresler
- `config('redirects.allowed_hosts')` içinde listelenen host'lar
  (`.env` → `REDIRECT_ALLOWED_HOSTS=eski-alan-adi.com`)

Reddettikleri — hepsi test edilmiş durumda:

| Vektör | Örnek | Neden tehlikeli |
|---|---|---|
| Mutlak harici adres | `https://evil.test/phishing` | Doğrudan site dışı |
| Protokole bağlı URL | `//evil.test` | Tarayıcı site dışı olarak yorumlar |
| Ters bölü hilesi | `/\evil.test` | Bazı tarayıcılar `\` → `/` çevirir |
| `javascript:` şeması | `javascript:alert(1)` | XSS |
| `data:` şeması | `data:text/html,...` | XSS |
| Satır sonu kaçırma | `/ok\nhttps://evil.test` | Header/hedef kaçırma |

`config/redirects.php` eklendi, `.env.example` güncellendi, admin formundaki
yardım metni kuralı açıklıyor.

`tests/Feature/RedirectTargetValidationTest` — 11 test: 6 saldırı vektörü
reddediliyor, site içi yol / kendi host'u / izinli host kabul ediliyor, 410
kaydı hedefsiz oluşturulabiliyor ve kayıtlı yönlendirme hâlâ çalışıyor.

---

#### 5d. SoftDeletes — ✅ Her Modelde

CLAUDE.md "SoftDeletes → HER MODELDE ZORUNLU" diyordu ama 3 model dışarıdaydı:
`AdminNotification`, `AnalyticsDailyStat`, `AuditLog`. Üçüne de eklendi;
**23 modelin tamamı** artık SoftDeletes kullanıyor.

`2026_08_25_130000_add_soft_deletes_to_log_tables` migration'ı `deleted_at`
kolonlarını ekliyor. `audit_logs` ve `admin_notifications` her panel isteğinde
sorgulandığı için trait'in eklediği `deleted_at is null` koşuluna ayrı index
verildi. `up()` ve `down()` ayrı ayrı test edildi.

##### Bu değişikliğin bozacağı 3 yer önceden düzeltildi

SoftDeletes `->delete()` çağrısının anlamını değiştirdiği için körü körüne
eklemek sessiz hatalara yol açardı:

| Yer | Sorun | Çözüm |
|---|---|---|
| `AuditLogger::pruneOlderThan` | Saklama süresi temizliği `->delete()` kullanıyordu. SoftDeletes ile yalnızca `deleted_at` dolar, satır kalır; üstelik sonraki çalıştırmalar global scope yüzünden o satırları hiç görmez ve **tablo süresiz büyür**. | `withTrashed()->forceDelete()` |
| `NotificationCenter::pruneOlderThan` | Aynı sorun | `withTrashed()->forceDelete()` |
| `AggregateDailyStatsCommand` | `analytics_daily_stats.date` **unique**. O tarihe ait satır soft delete edilmişse `updateOrCreate` onu göremez, INSERT dener ve **gecelik cron unique ihlaliyle patlar**. | `withTrashed()->whereDate(...)` ile bul, `trashed()` ise `restore()` et |

`PruneOldPageViewsCommand` zaten `forceDelete()` kullanıyordu — projedeki
doğru pattern buydu, diğer ikisi ona hizalandı.

Ek olarak agregasyon araması `whereDate` ile yapılıyor; önceki eşitlik
karşılaştırması sürücü `date` kolonuna tam zaman damgası yazdığında
eşleşmiyordu.

##### Doğrulama

`tests/Feature/SoftDeleteRetentionTest` — 6 test:

- **Reflection ile tüm `app/Models` taranıyor**, SoftDeletes kullanmayan model
  varsa test kırılıyor. Kural artık kendi kendini koruyor.
- Üç tabloda `deleted_at` kolonu var
- Bildirim silmek satırı tabloda bırakıp listeden gizliyor
- Audit log temizliği satırları **tablodan gerçekten siliyor** (önceden soft
  silinmiş bir satır da dahil)
- Bildirim temizliği satırları gerçekten siliyor
- Aynı tarihe ait soft silinmiş satır varken gecelik agregasyon patlamıyor,
  kaydı geri yüklüyor

Gerçek MySQL veritabanında `analytics:aggregate-daily` ve `audit-logs:prune`
komutları da çalıştırılıp doğrulandı.

---

#### 5e. Çok Dilli Yapı — ✅ Kuruldu

Site birden fazla dilde yayınlanabiliyor. Diller panelden yönetiliyor, içerik
dil başına ayrı satır olarak tutuluyor ve aynı içeriğin farklı dillerdeki
sürümleri ortak bir `lang_group_id` ile birbirine bağlı.

##### Diller

`languages` tablosu panelden yönetiliyor; yeni dil eklemek deploy gerektirmiyor.
Kurulumla Türkçe (varsayılan) ve İngilizce aktif, Almanca/Fransızca/İtalyanca
pasif hazır geliyor.

`LanguageService` **tam olarak bir varsayılan dil** kuralının sahibi:

- Varsayılanı taşımak öncekini tek işlemde temizliyor, ikinci varsayılan oluşamıyor
- Varsayılan dil pasife alınamıyor ve silinemiyor
- Pasif bir dil varsayılan yapılırsa otomatik aktifleşiyor
- Sistemdeki ilk dil zorunlu olarak varsayılan oluyor

##### Ziyaretçi hangi dili görüyor

1. Seçiciden seçtiği dil (oturumda)
2. `Accept-Language` başlığından en iyi eşleşme — q değerleri dikkate alınıyor,
   bölgesel varyant taban dile eşleniyor (`de-AT` → `de`)
3. Varsayılan dil

Yalnızca aktif diller sayılıyor. Seçici sağ üstte, aktif dil bayrak ve kodla
gösteriliyor; tek dil aktifse hiç render edilmiyor. `<html lang>`, `hreflang`
etiketleri ve `og:locale` aktif dilden geliyor.

##### İçerik nasıl saklanıyor

Sekiz içerik tablosu (`pages`, `blog_posts`, `blog_categories`,
`gallery_categories`, `gallery_items`, `faqs`, `sliders`, `popups`) `locale` ve
`lang_group_id` kolonlarını taşıyor.

- `(locale, lang_group_id)` benzersiz — aynı içeriğin bir dilde iki sürümü olamıyor
- Slug benzersizliği global değil `(locale, slug)`; Türkçe ve İngilizce ikisi de
  `contact` kullanabiliyor
- **Görsel dahil her kolon dile ait.** Üzerinde Türkçe yazı olan görsel TR
  satırına, İngilizcesi EN satırına yükleniyor.
- Kategoriler de çevrildiği için ilişkiler dile bağlı: İngilizce yazı İngilizce
  kategoriye bağlanıyor, form o sekmede yalnızca o dilin kategorilerini sunuyor

`HasTranslations` trait'i `translations()`, `translation($locale)`,
`hasTranslation()`, `missingLanguages()`, `scopeLocale()` ve
`scopeLocaleWithFallback()` sağlıyor. Fallback scope'u sayesinde **henüz
çevrilmemiş içerik siteden kaybolmuyor**, varsayılan dilden geliyor.

##### Admin formları

Her aktif dil için bir sekme açılıyor, her sekmede o dile ait alanlar var.

- Varsayılan dil rozetli, çevirisi olmayan dil "Çeviri yok" ile işaretli
- Doğrulama hatası olan sekme kırmızı ve otomatik öne geliyor
- **Yalnızca varsayılan dil zorunlu** — çevirmen diğerlerini sonra doldurabiliyor
- **Boş bırakılan sekme mevcut çeviriyi silmiyor**, sadece atlanıyor
- Yeni dosya eklenmeden yapılan düzenleme o dilin görselini korumuyor değil,
  koruyor
- Kendi görseli olmayan yeni bir çeviri varsayılan dilin görselini devralıyor;
  çeviri görsel hazır olmadan da eklenebiliyor, sonradan yüklenince o dile
  özel oluyor
- Blog yazılarında yayın durumu ve yazar dil bloklarının dışında; yayınlamak
  yazı hakkında bir karar, tek çevirisi hakkında değil

##### Cache

Ön yüz sorguları dile göre cache'leniyor. Anahtarlar dil içermeseydi ilk
ziyaretçinin dili, süre dolana kadar herkese servis edilirdi. `LocalizedCache`
trait'i anahtarları dile göre üretiyor ve içerik değişince tüm dillerin
anahtarını temizliyor.

##### Yol üzerinde bulunan hata

`resources/views/pages/show.blade.php` içinde
`@section('meta_description', $page->meta_description ?? $page->excerpt)` vardı.
İkisi de boş olduğunda Blade `@section('x', null)` çağrısını blok formu sanıp
`ob_start()` açıyor ve kapanış hiç gelmiyor. Yani meta açıklaması ve özeti
olmayan **her sayfa görüntülemesi bir çıktı tamponu sızdırıyordu**. PHPUnit'in
"risky" işareti sayesinde yakalandı, bölüm artık yalnızca içerik varsa
tanımlanıyor.

##### Testler

`LanguageManagementTest` (12), `ContentTranslationTest` (13),
`LocaleResolutionTest` (13), `TranslatedPageFormTest` (13),
`TranslatedContentFormsTest` (19), `FrontLocaleContentTest` (17).

---

#### 5f. Arayüz Çevirisi — ✅ Tamamlandı

İçerik çok dilliydi ama arayüz değildi: buton, başlık, form etiketi ve
`aria-label`'lar Blade içinde Türkçe sabit yazılıydı. İngilizce ziyaretçi
İngilizce içeriği Türkçe bir arayüzün içinde okuyordu.

Tüm arayüz metinleri `lang/tr/site.php` ve `lang/en/site.php` dosyalarına
taşındı (166 anahtar, iki dilde birebir aynı anahtar kümesi).

Kapsam: anasayfa, blog listesi/detayı, galeri, SSS, iletişim, sayfa detayı,
hesap paneli, profil, giriş/kayıt/şifre/e-posta doğrulama; navbar, footer, dil
seçici, sosyal paylaşım, yorum formu, onay/sonuç/popup modalları; 403, 404, 410,
419, 429, 500 hata sayfaları ve bakım modu (503).

Dikkat edilen noktalar:

- `aria-label` ve `placeholder` metinleri de çeviriye dahil — ekran okuyucu
  kullanıcısı da sayfayı kendi dilinde duyuyor
- Anasayfa hero başlığı işaretleme taşıyor ve `{!! !!}` ile basılıyor, böylece
  vurgulanan kelime her dilde ayrı seçilebiliyor
- Ayar (Setting) varsayılanları da çeviriden okunuyor: `site_description` ve
  `footer_text` boşsa aktif dilde görünüyor
- 503 sayfası bakım modunda middleware'siz render edildiği için `<html lang>`
  değerini artık aktif dilden alıyor
- Admin paneli bilinçli olarak tek dilli (Türkçe) bırakıldı — arka ofis, ziyaretçi
  yüzeyi değil

`InterfaceTranslationTest` (10) bekçilik ediyor: iki dil dosyasının anahtar
kümesi birebir aynı mı, hiçbir değer boş değil mi, Blade'de kullanılan her
anahtar tanımlı mı (tanımsız anahtar sayfaya ham `site.nav.home` olarak basılır),
tarayıcı diline göre arayüz dili, dil seçimiyle değişen navigasyon ve çeviri
dosyası olmayan dilde varsayılana düşme.

---

#### 5g. Çok Dilli Navigasyon — ✅ Tamamlandı

Arayüz çevrildikten sonra tarayıcı testinde çıktı: navbar hâlâ Türkçeydi. Menü
öğeleri veritabanı içeriği ve `menus`/`menu_items` çeviri tablolarına dahil
edilmemişti — İngilizce ziyaretçi hâlâ "Anasayfa · Hakkımızda · İletişim"
okuyordu.

İki tablo da diğer içerik tabloları gibi `locale` + `lang_group_id` taşıyor.
**Bir menü tek bir dile ait ve kendi öğe ağacını taşıyor**, yani bir dil
meşru olarak farklı bir navigasyon gösterebiliyor (daha az sayfa, farklı sıra).

- Menüsü olmayan dil varsayılan dilin menüsüne düşüyor, site navigasyonsuz kalmıyor
- Menü cache'i dile göre ayrıldı (`LocalizedCache`)
- Panelde her menü kartı hangi dile ait olduğunu bayrakla gösteriyor
- **"Başka bir dile kopyala"** aksiyonu menüyü tüm öğe ağacıyla klonluyor;
  yapı ve bağlantılar korunuyor, çevirmene yalnızca etiketler kalıyor. Her öğe
  kaynağına `lang_group_id` ile bağlı kalıyor.
- Öğe formu yalnızca menünün kendi dilindeki sayfaları sunuyor

Yol üzerinde bulunan tuzak: menü sayfa **slug'ı** saklıyor, sayfa id'si değil.
Bu yüzden hem yedeğe düşen hem de kopyalanan menü yanlış çeviriyi açıyordu.
`ResolvesLocalizedSlugs` trait'i slug'ı aktif dilin slug'ına çeviriyor — hem
kopyalama anında (veriyi doğru yazmak için) hem render anında (emniyet ağı).
Karar öğenin kendi diline değil, **slug'ın hangi dile ait olduğuna** bakıyor;
iki yol da tek kontrolle kapanıyor.

Navigasyon her sayfada iki kez render edildiği (masaüstü + mobil çekmece) ve öğe
başına slug sorgusu gerektirdiği için `MenuService` ve `MenuItemService` singleton
olarak bağlandı; istek ömrü boyunca çözülen slug'lar hafızada tutuluyor.

`LocalizedMenuTest` (14) kapsıyor.

---

#### 6. Kalan Yapılacak İşler

[`YOL-HARITASI.md`](YOL-HARITASI.md)'nin beş fazı da tamamlandı. Geriye
bilerek ertelenmiş iki madde ve bir gözlem kaldı.

##### Üç yüzün karşılaştırması

| Yetenek | Web | Mobil web | API |
|---|---|---|---|
| İçerik (blog, sayfa, galeri, SSS) | ✅ | ✅ | ✅ |
| Çok dillilik | ✅ | ✅ | ✅ |
| SEO (sitemap, hreflang, JSON-LD, RSS) | ✅ | ✅ | — |
| Kimlik (kayıt, giriş, şifre sıfırlama, doğrulama) | ✅ | ✅ | ✅ |
| Profil ve şifre değiştirme | ✅ | ✅ | ✅ |
| Cihaz / oturum yönetimi | ✅ | ✅ | ✅ |
| İki adımlı doğrulama (TOTP) | ✅ | ✅ | ✅ giriş |
| Hesap kapatma + veri indirme (KVKK) | ✅ | ✅ | ✅ |
| Bildirim tercihleri | ✅ | ✅ | ✅ |
| Yorumlarım | ✅ | ✅ | ✅ |
| Kurulabilirlik (PWA, çevrimdışı) | — | ✅ | — |
| Push bildirim | — | — | ✅ jeton kaydı + gönderim servisi |
| Sürüm / sağlık ucu | — | — | ✅ |

##### ⬜ Bilerek ertelenenler

- **Panelden push bildirim gönderme ekranı.** Sunucu tarafı hazır (jeton
  kaydı, sağlayıcıdan bağımsız gönderim, ölü jetonun düşmesi). Admin temada bu
  ekranın tasarımı yok — `notifications.html` yalnız tercih anahtarları
  içeriyor — ve tasarımda olmayan bir ekranı uydurmak proje kuralına aykırı.
  Tasarım geldiğinde ya da onay verildiğinde yapılacak.
- **`session.serialization = json`.** Çevirmek o anda açık olan bütün
  oturumları düşürüyor; çalışan bir kurulumda bu bakım penceresi gerektiren
  bir karar, kod değil zamanlama meselesi. `cache.serializable_classes` ise
  yapıldı (bkz. 5z).

##### 🔍 MySQL doğrulaması

Bu turun dokuz migration'ı ve bütün suite MySQL 8'e karşı da koşuldu (yerel
`lb_migtest`). Dört senaryo geçildi: sıfırdan kurulum, mevcut veriyle göç,
`down()` gidiş-dönüşü ve tohumlama.

Yol üzerinde **SQLite'ın sakladığı bir kusur** çıktı: `LanguageService`
varsayılan dilin yokluğunu istek boyunca hatırlıyordu; bir kez null çözülünce
varsayılan dil `config('app.locale')` değerine düşüyor ve dile duyarlı bütün
sorgular yanlış dile bakıyordu. API'de yayında olan bir yazının yorum ucu 404
dönüyordu. Düzeltildi — yalnız gerçekten bulunan dil hatırlanıyor.

##### 👀 İzlemede

- Bir koşuda `ModelFactoriesTest`'te tek seferlik bir hata görüldü; ardından
  dört ayrı koşuda tekrarlanmadı. Sebebi bulunamadı, kayda geçirildi.

##### 🔎 v2 denetimi (2026-09-01)

Yol haritasının beş fazı kapandıktan sonra bir denetim turu koştu: yapıldı
denilenlerin gerçekten çalıştığı doğrulandı ve kimsenin bakmadığı yerde ne
biriktiği arandı. On altı kusur çıktı — on ikisi "kural yazılı ama bekçisi ya
yok ya da elle yazılmış dar bir listeye bakıyor" desenindeydi. Hepsi kapatıldı
ve her biri için liste yerine kaynağından beslenen bir bekçi kuruldu.

Aynı turda, 31 Ağustos tarihli **Base Kit Boşluk Analizi** de depoya alındı
([`BOSLUK-ANALIZI.md`](BOSLUK-ANALIZI.md)) — o güne kadar yalnız bir Artifact
olarak duruyordu. On beş bulgusundan dördü hâlâ açıktı (CSP, cache hijyeni
üçlüsü); dördü de kapatıldı, artık tamamı kapalı.

Ayrıntılar: [`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md).

##### ✅ Bu turda kapananlar

Faz 1 (hesap ve kimlik), Faz 2 (mobil web), Faz 3 (panel ekranları),
Faz 4 (API olgunluğu), Faz 5 (dayanıklılık) — ayrıntılar bölüm 5t–5z ve
yol haritasında.

---

#### 5h. Mail ve Upload Yolları — ✅ Test Edildi ve Üç Kusur Kapatıldı

Diske ve SMTP'ye dokunan yollar kodu okuyarak doğrulanamıyordu. Test yazılırken
üçü de sessizce çalışan üç kusur çıktı.

##### 1. Upload kökü iki farklı yerden okunuyordu

Yazma `config('uploads.path')` kullanıyordu ama **okuma altı yerde
`public_path('uploads')` sabitliyordu** — `UploadService::url()`,
`srcset()`, `getOriginalWidth()`, `BaseMail` (mail logosu), `BackupService`,
`HealthCheckService` ve `FileManagerService`.

Üretimde ikisi aynı klasöre denk geldiği için görünmüyordu, ama upload yolu
yapılandırıldığı anda: her varyant araması ışınlanıp boşa düşüyor ve **her görsel
sessizce tam boy orijinaline geri dönüyordu**; yedekleme boş klasörü yedekliyor,
sağlık kontrolü yanlış klasörü raporluyordu.

`UploadService::basePath()` tek kaynak oldu, yedi çağrı yeri ona bağlandı.

##### 2. `contact_reply` şablonu hiç seed edilmemişti

`ContactMessageReplyMail::templateKey()` her zaman `'contact_reply'` döndürüyordu
ve `MailTemplateService` bunun varsayılanını biliyordu, ama **veritabanına hiç
satır eklenmemişti**. Sonuç: Mail Şablonları ekranı bu şablonu hiç listelemiyordu,
iletişim mesajına panelden verilen yanıt sessizce Blade view'ına düşüyordu —
yani yöneticinin en çok kendi cümleleriyle yazmak isteyeceği mail, düzenleyemediği
tek mail'di. `2026_08_25_210000` migration'ı ile seed edildi.

##### 3. Şablon drift'i (yanlış alarm, doğrulandı)

`resetToDefault()` ile migration seed'i arasında dört şablonda fark vardı, ama
normalize edilince farkın **tamamen biçimsel** olduğu görüldü (girinti ve etiket
içi boşluk). İçerik regresyonu yok. Test bu yüzden boşluğa duyarsız karşılaştırma
yapıyor — biçim değil, kullanıcının okuduğu kelimeler korunuyor mu diye bakıyor.

##### Bekçiler

- `test_every_mail_template_key_has_a_row_in_the_panel` — her mail sınıfının
  `templateKey()` değeri panelde bir satıra karşılık geliyor mu. `contact_reply`
  boşluğunu tam olarak bu yakaladı; seed migration'ı geçici olarak kaldırılıp
  testin gerçekten kırıldığı doğrulandı.
- `test_resetting_a_template_restores_the_shipped_content` — altı şablonun
  tamamı için varsayılana dönüş kontrolü (e-ticaret metninin geri gelmesi gibi
  bir regresyonu yakalar)
- `ImageUploadTest` — silme ve değiştirme işlemlerinin varyantları da temizlediği,
  kaynaktan büyük varyant üretilmediği, reddedilen yüklemenin diske hiçbir şey
  yazmadığı

---

#### 5i. Toplu Mail (Kampanyalar) — ✅ Kuruldu

Üyelere, bülten listesine, Excel'den yüklenen veya elle girilen adreslere toplu
mail gönderimi. Cron ile arka planda, saatlik limite göre yayarak.

##### Gönderim motoru

İki limit birlikte çalışıyor:

- **Saatlik tavan** sert sınır. Saate bakılarak değil, **son 60 dakikada
  gerçekten gönderilen** satır sayılarak uygulanıyor; kaçan veya iki kez
  çalışan bir cron limiti aşamıyor.
- **Tur kotası** yalnızca yayma amaçlı. 5 dakikalık cron → saatte 12 tur →
  100 limitinde tur başına `ceil(100/12) = 9` mail. Amaç listeyi başlar
  başlamaz boşaltmamak; mail sağlayıcıları bunu kısıtlama sebebi sayıyor.

Kampanya "yayma kapalı" işaretlenirse tur kotası atlanıyor ama saatlik tavan
yine geçerli.

Alıcılar `campaign_recipients` tablosuna satır satır yazılıyor. Kampanya tek
seferde "gönderilmiyor"; başlarken kitle donduruluyor ve cron bu tabloyu azar
azar boşaltıyor. Saatlik limit, çökme sonrası kaldığı yerden devam ve kişi
bazlı teslim durumu ancak bu sayede mümkün.

##### Akış

Form asla doğrudan göndermiyor: taslak kaydediliyor, **onay ekranı** gerçek
alıcı sayısını, alıcılardan örneği, cron'un ne zaman çalışacağını ve tahmini
bitişi gösteriyor, gönderim ancak açık onaydan sonra başlıyor.

##### Görseller

**CID olarak gömülüyor**, bağlantı olarak değil. Mail programlarının çoğu uzak
görselleri varsayılan engelliyor; bağlantılı görsel mail iletildiğinde veya
çevrimdışı okunduğunda tamamen kayboluyor. Site dışındaki görseller olduğu gibi
bırakılıyor — gönderim döngüsünden üçüncü parti URL'e istek atılmıyor.

##### Excel

`openspout/openspout` kullanılıyor (akış tabanlı; tüm sayfayı belleğe almıyor,
paylaşımlı hostingte on binlerce satır güvenli). Başlık satırı isimle
eşleştiriliyor, başlık yoksa adres sütunu bulunuyor. Türkçe Excel'in noktalı
virgüllü CSV'si ve BOM'u destekleniyor. Panelde örnek şablon indirme var.

##### Yol üzerinde bulunan üç kusur

1. **`emailBody` sessizce boşalıyordu.** Laravel, mailable'ın public
   özelliklerini `Content(with:)` verisinden **sonra** uyguluyor; `BaseMail`'in
   `public ?string $emailBody = null` alanı geçilen gövdeyi eziyordu. Kampanya
   mailleri boş gövdeyle gidiyordu.
2. **`CampaignMail` logoyu gömmüyordu.** Logo gömme `BaseMail::content()`
   içindeydi; onu override eden alt sınıf `cid:mail-logo` referansını
   bırakıyor ama eki eklemiyordu. Gömme ayrı bir metoda çıkarıldı.
3. **Tekrar denenecek alıcı başarısız sayılıyordu.** `failed_count` her
   denemede artıyor, ilerleme çubuğu olmayan bir ilerlemeyi gösteriyordu.
   Artık yalnızca hakkı tükenen alıcı başarısız sayılıyor.

Ayrıca: alıcı listesi boşken onaylanan kampanya `Scheduled` durumunda kalıyor,
cron sonsuza kadar başlatmayı deniyordu — zamanlama ve başlatma tek transaction'a
alındı.

##### Abonelikten çıkma

Her mail alıcıya özel çıkış bağlantısı taşıyor (gövde + `List-Unsubscribe`
başlığı). Elle girilen ve Excel'den yüklenen alıcılar da kendi anahtarını
alıyor — ilk kurulumda yalnızca abonelerde vardı, yani listede olmayan kişinin
çıkış yolu yoktu. Çıkan adres `subscribers` tablosuna engelleme kaydı olarak
yazılıyor ve sonraki kampanyalara dahil edilmiyor.

##### Testler

`CampaignDispatchTest` (28), `CampaignPanelTest` (25),
`CampaignMailContentTest` (17).

---

#### 5j. Shared Hosting Uyumu — ✅ Kritik Hata Düzeltildi

Kullanıcının hosting kuralları belgesi (`cron-rules.md`) incelendiğinde
projedeki **yedi zamanlanmış görevin altısının hiç çalışmadığı** ortaya çıktı.

`Schedule::command()` komutu ayrı bir süreçte çalıştırmaya çalışıyor; hosting'de
`exec()` ve türevleri kapalı olduğu için alt süreç açılamıyor. Kritik olan:
**hata fırlatmıyor.** Görev `schedule:list` çıktısında görünüyor, sırası
geliyor, hiçbir şey olmuyor.

Sessizce çalışmayanlar:

- `backup:run` — gece yedeği (yedek olmadığı, yedeğe ihtiyaç duyulunca anlaşılır)
- `analytics:anonymize-ips` — KVKK gereği IP maskeleme
- `analytics:aggregate-daily`, `analytics:prune-old`
- `audit-logs:prune`
- `campaigns:dispatch` — yeni yazılan toplu mail modülü (yani hiç mail gitmezdi)

Yalnızca `Schedule::call()` ile yazılmış kuyruk işleyicisi çalışıyordu.

##### Yapılan

Hepsi `Schedule::call(fn () => Artisan::call(...))` biçimine çevrildi —
aynı süreçte çalışır, alt süreç veya özel uzantı gerektirmez.

Bundan doğan üç ayrıntı:

1. **İsim zorunlu oldu.** `withoutOverlapping()` kilidi görev adına bakıyor;
   `Schedule::command()` adı komuttan alıyordu, `call()` alamıyor.
2. **Hata izolasyonu eklendi.** Tüm görevler tek PHP sürecini paylaştığı için
   birinde çıkan istisna geri kalanını da düşürürdü; her komut `try/catch`
   içine alındı ve hata loglanıyor.
3. **Saatler ayrıştırıldı.** Aynı dakikaya denk gelen görevler tek süreçte arka
   arkaya çalışıyor. Yedekleme (en yavaş iş) 03:00'ten 05:00'e alındı.

##### Doküman

`cron-rules.md` kök dizinden `docs/SHARED-HOSTING.md`'ye taşındı ve genişletildi:
mevcut görev takvimi, cron'un çalıştığını doğrulama, deploy sonrası kontrol
listesi, mail ve upload kısıtlamaları. CLAUDE.md'ye kırmızı çizgi olarak,
README'ye cron bölümüne bağlandı.

##### Bekçi

`ScheduleUsesCallablesTest` (11): hiçbir görev `Schedule::command()` ile
tanımlanmamış, `runInBackground()` kullanılmamış, her görevin adı var, beklenen
yedi görev kayıtlı, mail gönderim aralığı `CampaignDispatcher::RUN_INTERVAL_MINUTES`
ile uyumlu. Yasak çağrı kontrolü kaynak kodun token'larından yapılıyor —
dosya bu çağrıları neden yasak olduklarını anlatmak için zaten adıyla anıyor.

---

#### 5k. Diller Ekranı — ✅ Eksik Tamamlandı

Çok dilli yapı kurulurken `LanguageService` tüm kuralları taşıyordu ve servis
seviyesinde test edilmişti, ama **panelde arayüzü hiç yapılmamıştı**. Diller
yalnızca seeder'dan geliyordu; yeni dil eklemek veya çıkarmak için veritabanına
elle dokunmak gerekiyordu.

**Admin → Diller** ekranı eklendi: listeleme, ekleme, güncelleme, yayına
alma/kaldırma, varsayılan yapma, silme.

Ekran her dil için yayın durumunu, **arayüz çeviri dosyasının var olup
olmadığını** (`lang/{kod}/site.php`) ve o dilde kaç içerik kaydı bulunduğunu
gösteriyor — bir dil kaldırılmadan önce neyin gizleneceği görünüyor.

"Tek varsayılan" kuralı arayüzde de uygulanıyor: varsayılan dilin silme ve
varsayılan-yapma düğmeleri render edilmiyor, "Yayında" anahtarı disabled.
Sunucu tarafı bunlara güvenmiyor, `LanguageService` aynı kuralları yeniden
uyguluyor.

##### Yol üzerinde bulunan hata

`$request->boolean('is_active', true)` işaretlenmemiş kutuyu da `true`
yapıyordu — işaretsiz checkbox istekte hiç yer almadığı için varsayılan devreye
giriyor ve **hiçbir dil formdan pasife alınamıyordu.** Varsayılan `false` oldu.

##### Testler

`LanguagePanelTest` (21): ekran listeleme, çeviri dosyası eksikliği uyarısı,
ekleme (kod doğrulama, büyük harf normalizasyonu, tekrar reddi), güncelleme,
yayına alma/kaldırma, varsayılanın kapatılamaması, dört ardışık varsayılan
değişikliğinden sonra hâlâ tek varsayılan olması, silme kısıtları, yetki
ayrımı, değişikliğin ön yüz dil seçicisine ve `hreflang` etiketlerine yansıması.

---

#### 5l. Dil Yazıları Ekranı — ✅ Kuruldu

Arayüz metinleri yalnızca `lang/` dosyalarındaydı; değiştirmek için kod
düzenlemek gerekiyordu. **Admin → Dil Yazıları** ekranı eklendi: 231 metin,
dile göre sekmeler, bölümlere ayrılmış form, anlık arama.

##### Neden dosyaya değil veritabanına yazıyor

İki seçenek vardı ve seçim performansla ilgili değil:

- **Dosyaya yazmak** okuma açısından en hızlısı (opcache), ama deploy `git pull`
  ile yapılıyor — her deploy kullanıcının tüm düzenlemelerini sessizce silerdi.
  Ayrıca `lang/` dizininin üretimde yazılabilir olması gerekirdi.
- **Veritabanı + cache** deploy güvenli. Dosya varsayılan kalıyor, tablo yalnızca
  değiştirilenleri tutuyor. "Varsayılana dön" ancak bu ayrım sayesinde anlamlı.
  Mail şablonlarındaki desenin aynısı.

Performans farkı yok: bir dilin değişiklikleri tek dizi olarak
`rememberForever` ile cache'leniyor ve Laravel çeviri grubunu istek başına bir
kez yüklerken üzerine biniyor. Isınmış sayfa render'ı **sıfır** sorgu atıyor;
test bunu ölçüyor.

##### Nasıl çalışıyor

`DatabaseOverrideLoader`, Laravel'in dosya yükleyicisini sarıyor. Her `__()`
çağrısı ve her Blade `@lang` olduğu gibi kalıyor — tek satır view değişmedi.

Ekrandaki anahtar listesi **varsayılan dilin dosyasından** okunuyor, yani kodda
yeni bir metin eklendiğinde panelde kendiliğinden beliriyor.

Varsayılana eşit değer override olarak saklanmıyor, siliniyor: aksi hâlde metin
donar ve ileride dosyadaki varsayılan değişse bile siteye ulaşmazdı.

##### Yol üzerinde bulunan üç sorun

1. **`TranslationService` singleton değildi.** Yükleyici kendi örneğini tutuyor
   ve istek içi hafızası vardı; ikinci bir örnek kaydederken kendi hafızasını
   temizlerken yükleyici bayat değeri sunmaya devam ediyordu.
2. **Kaydetme mesajı yanıltıcıydı:** "228 metin varsayılana döndü" diyordu, oysa
   hiçbir şey geri alınmamış, sadece dokunulmamış alanlar varsayılana eşitti.
   Artık yalnızca gerçekten geri alınanlar sayılıyor.
3. **`Schema::hasTable()` her soğuk yüklemede fazladan sorgu atıyordu** — yalnızca
   ilk migration öncesi var olan bir durumu korumak için, sonsuza kadar. Zaten
   var olan try/catch bunu bedelsiz kapsıyor.

##### Testler

`TranslationOverrideTest` (21): çözümleme, dil kapsamı, yer tutucuların
korunması, varsayılana eşit değerin saklanmaması, boş değerin varsayılana
dönmesi, tanımsız anahtarın yazılamaması, sayaçların yalnızca gerçek
değişikliği bildirmesi, ısınmış sayfanın sıfır sorgu atması, panel akışı ve
yetki ayrımı.

---

#### 5m. Bölgesel Ayarlar Temizliği — ✅ İki Kusur Kapatıldı

Ayarlar → Bölgesel ekranı incelenirken iki sorun çıktı.

##### 1. Dil alanı hiçbir işe yaramıyordu

`app_locale` ayarı kaydediliyordu ama **hiçbir yerde okunmuyordu.** Dili
belirleyen zincir şu: oturumdaki seçim → `Accept-Language` → `languages`
tablosundaki varsayılan. Bu ayara zincirin hiçbir adımı bakmıyordu.

Yanıltıcıydı: altında "Uygulamanın arayüz dili" yazıyordu ama gerçek varsayılan
Diller ekranındaki yıldızdı. Üstelik dropdown yalnızca `tr` ve `en` gösteriyordu
çünkü eski `AppLocale` enum'undan besleniyordu — panelden eklenen bir dil orada
görünmüyordu bile.

Bu, çok dilli yapı kurulurken bırakılan bir artıktı: `app_locale` `languages`
tablosundan önce vardı, dil kaynağı oraya taşınınca işlevsiz kaldı.

Alan kaldırıldı, `AppLocale` enum'u silindi (başka kullanıcısı yoktu), bölüm
"Bölgesel" yerine **"Saat Dilimi"** oldu ve yerine dilin nereden yönetildiğini
söyleyen bir bilgi kutusu kondu (Diller ve Dil Yazıları ekranlarına bağlantılı).

##### 2. Saat dilimi cron'da uygulanmıyordu

Saat dilimi `SetLocale` **middleware**'inde uygulanıyordu — yani yalnızca web
isteklerinde. Scheduler konsolda çalışır ve middleware oraya hiç uğramaz.

Sonuç: yönetici saat dilimini değiştirdiğinde web istekleri yeni saat dilimini,
cron'un yazdığı her şey (yedek dosya adları, kampanya `sent_at` değerleri,
analitik toplama) `config` varsayılanını kullanıyordu. **Aynı kolonlara iki
farklı saat diliminde zaman damgası yazılıyordu.**

Uygulama `AppServiceProvider::boot()`'a taşındı; hem web hem konsol için
çalışıyor. Geçersiz bir değer ve ayar tablosu henüz yokken (taze klon, migration
ortası) sessizce config varsayılanına düşüyor.

##### Tek nokta kuralı

Artık her kavramın tek bir yeri var:

| Ne | Nerede |
|---|---|
| Diller, varsayılan dil | Diller ekranı |
| Arayüz metinleri | Dil Yazıları ekranı |
| Saat dilimi | Ayarlar → Saat Dilimi |

##### Testler

`TimezoneSettingTest` (9): ayarın uygulanması, konsolda da geçerli olması, web
ve konsolun aynı değeri kullanması, ayar yokken config varsayılanının
korunması, geçersiz değerin yok sayılması, ekranda dil dropdown'ının
bulunmaması ve yönlendirmenin yetkiye göre link/düz metin olması.

---

#### 5n. Pasif Kullanıcı Oturumu ve Güvenilen Proxy — ✅ İki Sessiz Açık Kapatıldı

Base kit'in production'a hazırlık denetiminde çıkan iki bulgu. İkisinin de ortak
özelliği **hata vermeden yanlış davranmaları**: kod çalışıyor, test yeşil, ekran
doğru sonucu gösteriyor — ama iş yapılmıyor.

##### 1. Pasife alınan kullanıcı oturumundan düşmüyordu

`is_active` kod tabanında **tek bir yerde** okunuyordu: `AuthService::login()`.
Yani bayrak yalnızca "kim oturum **açabilir**" sorusunu cevaplıyordu. Zaten açık
bir oturum hiç sorgulanmıyordu.

Sonuç: panelden bir hesabı pasife almak, o kişi o an oturumdaysa **hiçbir şey
yapmıyordu**. Oturum ömrü kadar (varsayılan 120 dakika), "beni hatırla"
işaretliyse `remember_token` geçerli olduğu sürece panelde kalmaya devam
ediyordu. Düğmeye basan yöneticinin hesabın kapandığına inanmak için her
sebebi vardı.

İzinlerde bu sorun hiç olmadı: `AdminMiddleware` her istekte veritabanına
soruyor, rol kaldırmak anında etkili oluyor. `is_active` hiçbir şeyin yeniden
kontrol etmediği tek bayraktı.

**İki katman eklendi, ikisi de gerekli:**

| Katman | Ne yapıyor | Neden tek başına yetmiyor |
|---|---|---|
| `EnsureUserIsActive` middleware | Pasif kullanıcıyı bir sonraki istekte çıkarıyor: `Auth::logout()` + oturum geçersizleştirme, JSON isteğinde 403 | Oturum satırı ve `remember_token` yerinde kalır |
| `SessionRevoker` servisi | Kullanıcının açık oturum satırlarını siliyor ve `remember_token`'ı boşaltıyor | Oturum sürücüsü `database` değilse satır bulunamaz |

Middleware `web` grubuna `SetLocale`'den **sonra** eklendi — uyarının
ziyaretçinin dilinde çıkması gerekiyor. Admin rotaları `web` grubunun üstüne
kurulduğu için panel ve ön yüz aynı kontrolden geçiyor.

`SessionRevoker` üç yerden çağrılıyor:

- `UserObserver::updated()` — `is_active` false'a döndüğünde
- `UserObserver::deleted()` — hesap silindiğinde
- `UserService::deleteMany()` — toplu silme sorgu kurucusundan gittiği için
  model olayı doğmuyor, servis işi kendisi yapıyor

##### Yol üzerinde bulunan iki kusur

**Force delete kullanıcıyı geri getiriyordu.** `revoke()` içindeki
`saveQuietly()` çağrısı, `forceDelete()` sonrası `exists = false` olan bir model
üzerinde çalıştığında UPDATE değil **INSERT** üretiyor ve silinen kullanıcıyı
geri yazıyordu. `exists` kontrolü eklendi; testi var.

**`UserFactory` `is_active` üretmiyordu.** Kolon varsayılanına bırakılmıştı, yani
satır aktif oluyordu ama `create()`'in döndürdüğü **modelde alan hiç yoktu** ve
`null` okunuyordu. Middleware modele sorduğu için suite'te 294 test birden
düştü. Fabrikaya `'is_active' => true` ve bir `inactive()` state'i eklendi.

##### 2. Güvenilen proxy tanımsızdı

`bootstrap/app.php` içinde `trustProxies()` çağrısı yoktu. Laravel'in
`TrustProxies` middleware'i global yığında zaten duruyor, ama güvenilecek proxy
listesi boş olduğu için iletilen başlıkların hiçbirine bakmıyordu.

Cloudflare, nginx reverse proxy veya yük dengeleyici arkasında bunun üç sonucu
var ve **üçü de sessiz**:

| Ne bozuluyor | Sonuç |
|---|---|
| `throttle:login`, `throttle:contact`, `throttle:register` | Hepsi IP'ye göre kova açıyor. Tek IP görüldüğü için tüm ziyaretçiler aynı kovayı paylaşır — bir kişinin başarısız girişleri **herkesi** kilitler, gerçek saldırgan ise hiç yavaşlamaz |
| `page_views.ip_address`, audit log IP'leri | Ziyaretçi değil proxy kaydedilir |
| `$request->secure()` | False döner, `SecurityHeaders` **HSTS başlığını hiç basmaz** |

`config/trustedproxy.php` eklendi — Laravel'in `TrustProxies` sınıfı bu anahtarı
kendiliğinden okuyor, `.env` → `TRUSTED_PROXIES`. Virgülle ayrılmış adres/CIDR
listesi ya da `'*'` kabul ediyor. **Varsayılan boş**: siteye doğrudan
erişiliyorsa doğrusu budur, çünkü bir proxy'ye güvenmek onun gönderdiği
`X-Forwarded-For` değerine güvenmek demektir.

Güvenilen başlık kümesi `bootstrap/app.php` içinde bilinçli olarak Laravel'in
varsayılanından **dar** tutuldu: `FOR | HOST | PORT | PROTO`. `AWS_ELB` ve
`PREFIX` dışarıda — bu proje o başlıkları üreten hiçbir katmanın arkasında
çalışmıyor.

> Ayar `config()` üzerinden okunuyor, `bootstrap/app.php` içinde `env()` ile
> değil: middleware yapılandırması uygulama önyüklenmeden çalışıyor ve orada
> `config()` henüz yok. Liste istek anında çözülüyor.

##### Testler

`InactiveUserSessionTest` (11): aktif kullanıcının oturumunun korunması, pasife
alınan kullanıcının panelde ve ön yüzde bir sonraki istekte çıkarılması, JSON
isteğinde 403, misafirin etkilenmemesi, `remember_token`'ın düşmesi, aktif kalan
kullanıcının token'ının korunması, oturum satırlarının silinmesi (yalnızca o
kullanıcınınki), silme ve toplu silme yollarının ikisi de, force delete'in
kullanıcıyı geri getirmemesi.

`TrustedProxyTest` (12): varsayılanda hiçbir proxy'ye güvenilmemesi, başlık
kümesinin dar tutulduğunun reflection ile doğrulanması, `TRUSTED_PROXIES`
ayrıştırması (liste / `'*'` / boş), güvenilmeyen kaynağın adresi ve şemayı
sahteleyememesi, güvenilen proxy arkasında gerçek adresin geçmesi, HSTS'in
yalnızca iletilen şema güvenildiğinde çıkması ve **aynı proxy arkasındaki iki
ziyaretçinin ayrı rate limit kovasına düşmesi**.

Her iki düzeltme de geri alınıp testlerin gerçekten kırıldığı doğrulandı:
middleware kaldırılınca 3, observer kaldırılınca 4, `exists` kontrolü ve toplu
silme çağrısı kaldırılınca 2, config dosyası kaldırılınca 4 test düşüyor.

##### Bilinen ortam kaynaklı hata

Suite'te 5 test ağ erişimi olmayan ortamda düşüyor:
`CampaignPanelTest` (3), `FrontFormInputRulesTest` (1), `SubscriberListTest` (1).
Sebep `email:rfc,dns` kuralının DNS sorgusu; bu değişikliklerden önce de aynı
şekilde düşüyorlardı, kodla ilgisi yok.


---

#### 5o. robots.txt — ✅ Dosyadan Rotaya Taşındı

`public/robots.txt` sabit bir dosyaydı ve iki ayrı sebeple yanlıştı.

**Kopyalanabilir olması.** Dosya depoyla birlikte geliyordu, yani bu base
kit'ten türeyen **her proje** arama motorlarına şu satırı veriyordu:

```
Sitemap: https://orhanbabaninciftligi.com/sitemap.xml
```

Yani yeni projenin sitemap'i hiç bildirilmiyor, bildirilen adres başka bir siteyi
gösteriyordu. Aynı dosyada sökülmüş modüllerden kalan `Disallow: /*/sepet` ve
`/*/siparis` satırları da duruyordu. Hiçbiri hata vermiyordu.

**Eskimesi.** Adresler artık panelden açılıyor (`custom_routes`) ve diller
panelden yayına alınıyor. Elle yazılmış bir liste bu iki ekranın arkasından
kaçınılmaz olarak geride kalıyordu: yeni bir dil yayına alındığında o dilin
`/de/giris` adresi robots'ta hiç görünmüyordu.

##### Şimdi nasıl çalışıyor

`RobotsService` listeyi **rotaların kendisinden** üretiyor:

| Kaynak | Ne veriyor |
|---|---|
| Rota adları (`login`, `account.dashboard`, …) | Yolun gerçek hâli. Rota yolu değişirse robots kendiliğinden takip ediyor |
| `LanguageService::activeCodes()` | Her yayındaki dil için ayrı satır — joker (`/*/`) yerine gerçek önekler |
| `CustomRouteService::map()` | Panelden özel bir alana açılmış adresler. `/en/login` açılırsa o da yasaklanıyor — asıl adresi yasaklayıp takma adını açık bırakmak hiçbir işe yaramaz |
| `route('sitemap')` | Sitemap satırı bu sitenin adresini gösteriyor |

Ek olarak iki uç nokta listeye girdi: dil değiştirici (`/dil/`) ve **bülten
çıkış bağlantısı** (`/bulten/cikis/`). İkincisi giriş istemeyen, durum
değiştiren bir GET — bir tarayıcı robotunun izlemesi gereken en son bağlantı ve
eski dosyada hiç yoktu.

**Canlı olmayan kopya tümüyle kapalı.** `APP_ENV !== production` ise gövde
yalnızca `Disallow: /`. Staging kopyası da `Allow: /` deseydi aynı içerik iki
alan adında dizine girer ve canlı siteyle kopya içerik çakışması üretirdi.

**Önbelleğe alınmıyor.** Dayandığı iki kaynak (aktif diller, özel adres
haritası) zaten kendi önbelleklerinden geliyor; üçüncü bir önbellek yalnızca
geçersizleştirilecek bir yüzey daha eklerdi.

##### Fazla satır basılmıyor

Robots kuralları önek eşleştirdiği için `/tr/hesabim` yazıldıktan sonra
`/tr/hesabim/profil` fazladan satır. Üretilen liste tekrarları atıyor ve kısa
öneki olan uzun yolları düşürüyor.

##### Bakım modu

`/robots.txt` `web` grubunda, yani bakım modunda `CheckMaintenanceMode` 503
dönüyor. Bu bilinçli: arama motorları robots.txt'e gelen 5xx'i "şimdilik hiçbir
şeyi tarama" diye okur, bakım penceresinde istenen davranış tam olarak budur.

##### Yol üzerinde bulunan şey

Giriş/kayıt/şifre sayfalarına `@section('robots', 'noindex, nofollow')` eklemeye
kalkıldı — gereksizdi: `layouts/auth.blade.php` `noindex` etiketini zaten sabit
basıyor ve o layout böyle bir section yield etmiyor. Eklenen satırlar ölü kod
olacaktı, geri alındı. (`auth/verify-email.blade.php:5` içinde aynı sebeple ölü
duran bir section var; zararsız olduğu için dokunulmadı.)

##### Testler

`RobotsTest` (14): statik dosyanın geri gelmemesi (gelirse web sunucusu onu
basar ve rota ölü koda döner), canlı olmayan kopyanın kapalı olması, sitemap
satırının bu siteyi göstermesi, başka projenin alan adının kalmaması, sökülmüş
modül yollarının gitmiş olması, panelin yasaklı olması, her yayındaki dilin
kendi satırlarını alması, **yeni bir dil yayına alındığında listenin
genişlemesi**, özel adresin yasaklanması, herkese açık özel adresin
(`/en/contact`) yasaklanmaması, dil öneki öncesi eski adresler, dil taşımayan
uç noktalar, önek tekrarının basılmaması ve yolların rota tanımından okunduğu.

Düzeltme geri alınıp doğrulandı: statik dosya geri konunca 1, sitemap satırı
sabitlenip ortam kontrolü kaldırılınca 3 test düşüyor.


---

#### 5p. Hata Bildirimi ve Log Rotasyonu — ✅ Kapatıldı

Canlıda 500 veren bir sayfa **kimseye haber vermiyordu**. `bootstrap/app.php`
içindeki `withExceptions()` bloğu boştu; hata yalnızca `storage/logs` altına
düşüyordu ve oraya kimse bakmıyordu. Bir kullanıcı şikâyet edene kadar sitenin
bir bölümü günlerce kırık kalabilirdi.

Acı olan tarafı: projede çalışan bir bildirim kanalı **zaten vardı**.
`TelegramNotifier` ve `NotificationCenter` yazılmış, throttle'ı kurulmuş, panel
zilinde gösterimi hazırdı — yalnızca yedekleme komutu ve birkaç servis onu
çağırıyordu.

##### 1. İşlenmeyen hata artık yöneticiye ulaşıyor

`ExceptionNotifier` iki kanala birden düşürüyor: Telegram (açıksa) ve panel
bildirim merkezi (`type: exception`, kritik seviye).

Kapanış `report()` ile bağlandı ve **hiçbir şey döndürmüyor** — `false` dönseydi
hatanın loga yazılmasını da durdururdu. Bildirim logun yerine değil yanına
geçiyor; testi var.

**Gürültü yok.** Laravel 404, 403, 419, 429, doğrulama ve kimlik hatalarını
raporlamadan önce eliyor (`Handler::$internalDontReport`), yani buraya yalnızca
gerçekten beklenmedik olanlar geliyor. Ayrıca aynı hata için 10 dakikada bir
bildirim: parmak izi türü + dosya + satır, yani sıcak bir sayfadaki döngüsel
hata tek mesaj üretiyor ama farklı bir hata beklemeden haber veriyor. Sayaç
`Cache::add` ile atılıyor — okuma ve yazma tek işlemde, aynı anda gelen iki
istek iki bildirim üretemiyor.

**Bildirim yolu asıl hatayı gizleyemez.** `notify()` baştan sona `try/catch`
içinde: Telegram'a ulaşılamazsa ya da bildirim satırı yazılamazsa sessizce
dönüyor, yoksa loglanan şey asıl hata değil bildirim hatası olurdu.

Mesajda ne var: hatanın türü, mesajı, **proje köküne göre** dosya:satır (mutlak
yol satıra sığmıyor ve hosting kullanıcı adını mesaja taşıyor), istek adresi ve
metodu, varsa kullanıcı kimliği. Konsoldan gelen hatalar "zamanlanmış görev"
olarak işaretleniyor.

> **Bilinen sınır:** Telegram ayarları `settings` tablosundan okunuyor, yani
> veritabanının kendisi düştüğünde bildirim gönderilemez. O senaryoda geriye
> dosya logu ve Sistem Sağlık ekranı kalıyor. Ayarları veritabanı düşse de
> okunabilir tutmak isteyen kurulum `CACHE_STORE=file` kullanabilir. Bunun
> alternatifi Telegram token'ını `.env`'e taşımaktı; ayarın sahibi panel olduğu
> için ikinci bir doğruluk kaynağı açılmadı.

##### 2. Log dosyası artık dönüyor

`.env.example` `LOG_STACK=single` diyordu: tek dosya, rotasyon yok. Paylaşımlı
hostingde `laravel.log` zamanla gigabaytlara çıkar ve **disk dolduğunda yalnız
log yazımı değil yükleme, yedekleme ve oturum yazımı da durur.**

`LOG_STACK=daily` + `LOG_DAILY_DAYS=14` oldu (`config/logging.php` bu değişkeni
zaten okuyordu). `LOG_LEVEL` için de canlıda `error` önerisi yorum olarak
eklendi.

##### 3. Sistem Sağlık ekranına log kontrolü

Disk kontrolü sorunu ancak disk dolduğunda görüyor; yeni kontrol **sebebe**
bakıyor:

| Durum | Eşik |
|---|---|
| Kritik | Log dizini ≥ 1 GB |
| Uyarı | ≥ 250 MB |
| Uyarı | Dönüş kapalı **ve** ≥ 20 MB birikmiş |
| Sağlıklı | Diğer |

Dönüş kapalıyken sıfırdan uyarmak gürültü olurdu; 20 MB eşiği uyarının fark
edilmesi için bol zaman bırakıyor ama dosya büyümeden önce çalıyor. Ekrandaki
ipucu doğrudan çözümü söylüyor: `LOG_STACK=daily` ve `LOG_DAILY_DAYS=14`.

Kontrol dizini `storage/logs` **varsaymıyor**, kanalın kendi `path` değerinden
çözüyor — log başka bir yere yönlendirilmişse oraya bakıyor.

##### Testler

`ExceptionNotificationTest` (10): bildirimin merkeze düşmesi, konumun proje
köküne göre yazılması, Telegram açıkken gönderilmesi ve kapalıyken hiç
gönderilmemesi, aynı hatanın pencerede bir kez bildirilmesi, farklı hatanın
yutulmaması, bildirim kanalı patlarsa asıl hatanın yerini almaması, beklenen
HTTP hatalarının hiç raporlanmaması, gerçek bir isteğin patlamasında uçtan uca
bildirim ve **hatanın loga yazılmaya devam etmesi**.

`LogHealthCheckTest` (10): kontrolün ekranda görünmesi, sağlıklı hâl, dönüşün
kapalı olduğunun bildirilmesi, büyümüş dönmeyen logun uyarması, küçük dosyanın
uyarmaması, dönüş açıkken de boyut eşiklerinin çalışması, ipucunun çözümü
söylemesi, kontrolün yapılandırılmış log yolunu izlemesi ve eşiklerin sıralı
olması. Boyut eşikleri `ftruncate` ile üretilen seyrek dosyalarla sınanıyor:
`filesize()` istenen boyutu bildiriyor, diskte yer kaplamıyor.

Mutasyonla doğrulandı: `report()` kancası kaldırılınca 1, kapanış `false`
döndürülünce log testi, rotasyon tespiti sabitlenince 3, log dizini sabit
`storage/logs` yapılınca 5 test düşüyor.

##### Yol üzerinde görülen

`telegram_notify_level` ayarı panelde kaydediliyor ama **kodda hiçbir yerde
okunmuyor** — temizlenen `app_locale` ile aynı durumda. Bu turda dokunulmadı;
anlamı (iş yeniden deneme verbosity'si) hata bildirimiyle ilgisiz.


---

#### 5r. Denetim İzi — ✅ Tek Modelden Kritik Kümeye

Altyapı baştan iyi yazılmıştı: `audit_logs` tablosu, indeksleri, saklama süresi
temizliği, panel ekranı, süzgeçleri ve **hassas alan maskesi** hepsi yerindeydi.
Eksik olan tek şey neyin izlendiğiydi — `AuditObserver`
`AppServiceProvider.php:127`'de **tek bir modele** bağlıydı: `Setting`.

Yani "kim giriş yaptı", "kim başarısız giriş denedi", "kim hangi rolün iznini
değiştirdi", "kim kullanıcı sildi", "kim yönlendirme ekledi" sorularının
hiçbirinin cevabı yoktu. Kurumsal bir denetimin ilk sorduğu şeyler de bunlar.

##### Üç ayrı yol, çünkü tek gözlemci hepsini göremiyor

| Yol | Neyi yakalıyor | Neden ayrı |
|---|---|---|
| `AuditObserver` (model listesi) | `Setting`, `User`, `Role`, `Redirect`, `CustomRoute`, `MailTemplate`, `Language` | Satır değişikliklerini görür |
| `AuditAuthenticationEvents` (abone) | Giriş, çıkış, başarısız deneme | Bu olaylar hiçbir satırı değiştirmiyor |
| Servislerin kendi `AuditLogger::custom()` çağrıları | İzin matrisi, kullanıcı rolleri, toplu silme/geri yükleme, şifre sıfırlama | Pivot tablosunun modeli yok; toplu işlemler sorgu kurucusundan gidiyor ve model olayı doğurmuyor |

Model listesi dizi üzerinden geçiyor — yeni bir kritik model eklendiğinde tek
satır yetiyor.

##### Kapsam neden içerik modellerini almıyor

Sayfa, blog ve galeri her kaydetmede satır üretir. 90 günlük saklama süresiyle
denetim izi kendi gürültüsünde boğulur ve asıl aranan kayıt — bir yetkinin ne
zaman verildiği — bulunamaz hâle gelir. Buradaki liste erişimi, yetkiyi,
gönderilen mailleri ve ziyaretçinin nereye gideceğini belirleyenler. İçeriğin
geçmişi denetim izinin değil **sürümlemenin** işi (bkz. modül önerileri).

Testi var: `Page` oluşturmak denetim izine düşmüyor.

##### Ayrıntılar

**Şifre hiçbir biçimde ize girmiyor.** İki katman: `Failed` dinleyicisi
`$event->credentials`'tan yalnızca adresi alıyor (dizi şifreyi de taşıyor),
`AuditLogger`'ın maskesi de arkada duruyor. İkisinin de testi var.

**Boş kayıt yazılmıyor.** İzin matrisi ekranını açıp hiçbir şey değiştirmeden
kaydete basmak satır üretmiyor; yalnızca gerçekten eklenen/kaldırılan izinler
yazılıyor, rol bazında ayrı ayrı.

**Geri alma ve kalıcı silme de kayıtta.** Silmenin izi varken geri almanınki
olmasaydı denetim izi "silindi" diyen ama hâlâ yayında olan kayıtlar
gösterirdi.

**Etiket artık kimden söz ettiğini söylüyor.** `buildLabel` zinciri kullanıcıda
`name`/`title` bulamayıp "User #12 güncellendi" diyordu; ad-soyad birleşimi,
e-posta ve slug zincire eklendi.

**`Lockout` bilinçli olarak dinlenmiyor:** onu `ThrottlesLogins` trait'i
fırlatıyor, bu proje hız sınırını `throttle:login` ara katmanıyla kuruyor, yani
olay hiç doğmuyor. Dinlemek ölü kod olurdu.

**Ekranda değişiklik gerekmedi:** tür süzgeci `modelOptions()` ile tablodaki
gerçek verilerden üretiliyor, yeni modeller kendiliğinden listeye giriyor.

##### Yol üzerinde bulunan

`AuditLogPageTest` ve `AuditLogDetailTest` kendi kurdukları kayıtların sayısına
bakıyordu; `User` izlenmeye başlayınca sayfayı açan yöneticinin kendi izi
sonuçları kaydırdı ve 5 test düştü. Fikstür kurulumundan sonra tablo
sıfırlanıyor — testlerin niyeti "şu N kayıt verildiğinde özet şunu der",
kurulum gürültüsü değil.

##### Testler

`AuditTrailCoverageTest` (17): izlenen modellerin her biri için gerçekten kayıt
doğması, içerik modellerinin dışarıda kalması, şifrenin ize hiç girmemesi,
etiketin kullanıcıyı adıyla anması, giriş/çıkış/başarısız denemenin kaydı,
denenen şifrenin yazılmaması, sıfırlama bağlantısı isteği, izin matrisi
değişikliği, **değişmeyen kaydın yazılmaması**, kullanıcı rolleri, toplu silme
ve geri yükleme, geri alma ve kalıcı silme.

Bağlantı kaldırılıp doğrulandı: gözlemci listesi ve abone çıkarılınca 9 test
düşüyor.


---

#### 5s. Kuyruk İzleyici — ✅ Kuruldu

`failed_jobs` tablosu projede **tek bir yerde** okunuyordu:
`HealthCheckService.php:162`, o da yalnızca son 24 saatin *sayısını* alıyordu.
Listeleme, hatayı görme, yeniden deneme ve silme yoktu.

Bu proje için özellikle önemli: tüm mail gönderimi `MailService::queue()`
üzerinden kuyruğa giriyor. "Doğrulama maili gelmedi" şikâyetinin cevabı
`failed_jobs.exception` alanında duruyor ve o alana panelden bakmanın yolu
yoktu — kayıt tabloda sessizce birikiyordu.

##### Ekran

`Admin → Kuyruk` (`admin/kuyruk`). Üstte dört sayı, altta başarısız iş listesi.

| Sayı | Ne söylüyor |
|---|---|
| Bekleyen iş | Kuyrukta duran iş sayısı |
| **En eski işin yaşı** | "Cron çalışıyor mu" sorusunun en net cevabı |
| Son 24 saatte başarısız | Yeni çıkmış sorun |
| Toplam başarısız | Birikmiş kuyruk çöplüğü |

Bekleyen iş sayısı tek başına normal; birikip **yaşlanması** cron'un
çalışmadığı demek. 10 dakikayı geçtiğinde ekranın en üstünde kırmızı bir uyarı
çıkıyor ve nereye bakılacağını söylüyor.

Liste her iş için zaman, iş adı, kuyruk, deneme sayısı ve hatanın ilk satırını
gösteriyor; göz düğmesi tam yığın izini ayrı bir pencerede çekiyor. Yığın izi
her satırda taşınsaydı liste gereksiz şişerdi.

İşlemler: **yeniden dene**, **sil**, **listeyi temizle** ve **kuyruğu şimdi
işle** (cron dakikasını beklemeden). Hepsi `AdminModal` onayından geçiyor ve
denetim izine düşüyor — bir işin neden kaybolduğu sonradan sorulacak ilk şey.

##### Tasarım kararları

**Eloquent modeli yok.** `jobs` ve `failed_jobs` çerçevenin kendi tabloları;
projenin model kurallarına (SoftDeletes, `$fillable`) tabi değiller ve
`QueueRunner` ile `HealthCheckService` de aynı biçimde, sorgu kurucusuyla
okuyor. Model açmak `failed_jobs`'a `deleted_at` eklemeyi gerektirirdi —
`queue:flush` gibi çerçeve komutlarının beklemediği bir kolon.

Modeli olmadığı için yetkilendirme de Policy değil **Gate**: `view-queue` ve
`manage-queue`, `manage-backups` ile aynı desen. Görüntülemek ile silmek ayrı
izinler.

**İş adı yükten çıkarılıyor.** Kuyruğa giren her mail
`Illuminate\Mail\SendQueuedMailable` olarak görünüyor, yani `displayName` tek
başına "hangi mail patladı" sorusunu cevaplamıyor. Asıl sınıf yükün
serileştirilmiş gövdesinde; oradan okunabiliyorsa o gösteriliyor,
okunamıyorsa çerçevenin adı kalıyor — tahmin edilip yanlış ad basılmıyor.

**Yeniden deneme iki katmanlı.** Önce çerçevenin `queue:retry` komutu
(`Artisan::call` ile, aynı süreçte — alt süreç açılmıyor). O komut yükün
içindeki nesneyi açıp `retryUntil` damgasını tazeliyor; **iş sınıfı bir
deploy'da kaldırılmışsa ya da yük bozuksa bu adım patlıyor** ve ekran 500
veriyordu. O durumda deneme sayacı sıfırlanıp yük olduğu gibi kuyruğa
yazılıyor: işin geri konması damganın tazelenmesinden önemli. Testi var.

##### Yeni izinler mevcut kurulumlara nasıl gidiyor

İzinlerin tek kaynağı `PermissionKey` enum'u ama `PermissionSeeder` yalnızca
kurulumda çalışıyor. Deploy `git pull` + `migrate` ile yapıldığı için yeni bir
enum case'i satır karşılığı bulamaz ve **yönetici bile ekranı göremezdi**.
`2026_08_31_100000_seed_queue_permissions` migration'ı satırları ekliyor ve
yönetici rolüne veriyor.

##### Testler

`QueueMonitorTest` (20): yetki ayrımı (editör ve moderatör giremiyor,
kenar çubuğunda link görünmüyor, yeniden deneme/silme reddediliyor), sayılar,
boş kuyruk, yaşlanan kuyruğun tıkanmış sayılması, taze kuyruğun sayılmaması,
iş adının yükten çıkarılması ve çıkarılamayınca geri düşmesi, listenin
hatanın yalnızca ilk satırını taşıması, ayrıntı ucunun tam yığın izini
vermesi ve olmayan kayıtta 404, arama ve kuyruk süzgeci, yeniden deneme,
**okunamayan yükün yine de kuyruğa geri konması**, tekil silme, listeyi
temizleme, her yıkıcı işlemin denetim izine düşmesi ve kuyruğun elle
işlenmesi.

Tarayıcıda da doğrulandı: ekran render ediliyor, sayaç animasyonu çalışıyor,
ayrıntı penceresi yığın izini çekiyor ve yeniden deneme sonrası bekleyen iş
1→2, toplam başarısız 2→1 oluyor.


---

#### 5t. Ölü Telegram Ayarı ve Kaydedilmeyen Başarısız İşler — ✅ İkisi de Kapatıldı

`telegram_notify_level` ayarı panelde kaydediliyordu ama **kodda hiçbir yerde
okunmuyordu** — 5m'de temizlenen `app_locale` ile birebir aynı durum. Kararı
verirken çok daha büyük bir şey çıktı.

##### Ayar neden kaldırıldı, bağlanmadı

Enum'un sunduğu seçim şuydu: bildirim *her başarısızlıkta* mı gelsin, yoksa
*yalnız 3/3 deneme sonunda* mı? İki gerekçeyle bağlanmadı:

**1. Anlattığı mekanizma yok.** `QueueRunner::drain()` bir işi bir kez
çalıştırıyor; patlarsa doğrudan `$job->fail()` çağırıyor. Yeniden deneme
yok, `maxTries` kontrolü yok. "1., 2., 3. deneme" bu projede hiç yaşanmıyor —
ayarı bağlamak önce olmayan bir yeniden deneme mekanizması yazmayı
gerektirirdi. Testi var: patlayan iş kuyruğa geri konmuyor.

**2. Bildirim zaten gidiyor.** `QueueRunner` başarısızlıkta `report($e)`
çağırıyor ve bu, 5p'de kurulan `ExceptionNotifier`'a düşüyor: Telegram + panel
zili, parmak izine göre 10 dakikalık throttle ile. İkinci bir bildirim düğmesi
hangisinin kazandığını belirsizleştirmekten başka bir şey yapmazdı. Bunun da
testi var.

Kaldırılanlar: `TelegramNotifyLevel` enum'u, ayar ekranındaki açılır kutu,
`SettingController`'ın kaydettikleri listesindeki anahtar, `TelegramNotifier`
docblock'undaki satır ve `EnumDrivenOptionsTest` beklentisi. Kaydedilmiş satır
migration ile siliniyor — alan ekrandan kalktığı için bırakılsaydı kimsenin
okumadığı **ve kimsenin silemeyeceği** bir kayıt olarak kalırdı. `up()` ve
`down()` ayrı ayrı çalıştırılıp doğrulandı.

Alanın yerine ne olduğunu söyleyen bir bilgi kutusu kondu (`app_locale` için
yapılanla aynı desen): Telegram'a neyin gittiği, neyin gitmediği ve patlayan
işlerin listesinin Kuyruk ekranında olduğu.

##### Yol üzerinde bulunan asıl kusur: başarısız işler hiç kaydedilmiyordu

Kararı doğrulamak için patlayan bir işi kuyruğa koyup `QueueRunner`'ı
çalıştırdığımızda `failed_jobs` **boş kaldı.**

Sebep: tabloya yazma işini çerçevede `queue:work` yapıyor. `WorkCommand`
açılışta `JobFailed` olayına abone oluyor ve olayı `queue.failer`
sağlayıcısına aktarıyor. Bu proje `queue:work` çalıştıramıyor — pcntl yok,
`QueueRunner`'ın var olma sebebi bu — yani **o abone hiç kurulmuyordu.**
`Job::fail()` işi siliyor, işin kendi `failed()` metodunu çağırıyor ve
`JobFailed` olayını fırlatıyor; tabloya yazmıyor.

Sonuç: patlayan her iş sessizce yok oluyordu. `failed_jobs` her zaman boştu,
Sistem Sağlık ekranındaki "son 24 saatte başarısız" sayısı her zaman sıfırdı
ve **bir gün önce kurulan Kuyruk ekranı (5s) üretimde hiçbir zaman
dolmayacaktı.**

`LogFailedQueueJob` dinleyicisi eklendi: `JobFailed` olayını `queue.failer`'a
aktarıyor. `app/Listeners` altındaki dinleyiciler kendiliğinden bağlandığı için
kayıt gerekmiyor — `UpdateMailLogOnFailed` de aynı olayı zaten dinliyor.
Kayıt tutamamak işin kendisinden küçük bir sorun olduğu için `try/catch`
içinde: buradan fırlayan bir hata kuyruğun kalanını da durdururdu.

> `queue:work` bir gün çalıştırılabilir hâle gelirse bu dinleyici ile o komutun
> kendi abonesi aynı işi iki kez yazar. Projenin tüm kuyruk kurgusu
> `queue:work`'ün olmadığı varsayımına dayanıyor (`docs/SHARED-HOSTING.md`); o
> varsayım değişirse buranın da gözden geçirilmesi gerekir.

##### Ayrıca

Telegram bölümünün alt başlığı hâlâ **"Instagram paylaşımları başarısız
olduğunda..."** diyordu — sökülmüş modülden kalan ve artık düpedüz yanlış olan
bir metin. Tam da neyin bildirim ürettiğini anlatan kutunun üstünde durduğu
için düzeltildi.

##### Testler

`FailedJobRecordingTest` (5): patlayan işin `failed_jobs`'a düşmesi, kaydın
hata metnini ve yükü taşıması, işin yeniden denenmemesi, yöneticinin zaten
haberdar edilmesi ve başarılı işin geride kayıt bırakmaması.

`QueueMonitorTest`'e uçtan uca bir test eklendi: gerçek bir başarısızlık
Kuyruk ekranında görünüyor. Diğer testler satırı doğrudan yazıyordu, yani
zincirin kopuk halkasını göremezlerdi.

Dinleyici kaldırılıp doğrulandı: 3 test düşüyor.


---

#### 5u. Yedek Geri Yükleme — ✅ Kuruldu, Yol Üzerinde Sessiz Bir Kusur Çıktı

Yedek alınıyordu ama **geri dönüş yolu yoktu**: dosya indirilebiliyor, ama
uygulanabilmesi için sunucuda elle SQL çalıştırmak gerekiyordu. Hiç denenmemiş
bir yedek, olmayan bir yedektir.

##### Yol üzerinde bulunan asıl kusur: gövdesiz yedekler

Geri yüklemeyi gerçek bir MySQL veritabanında sınarken alınan yedek **133 KB
değil 398 bayt** çıktı. Arşivde `database.sql` **hiç yoktu** — ama `create()`
yine "Yedek alındı" diyordu.

Sebep: `phpSideDump` bağlantıyı elle kurulan bir DSN ile açıyordu ve o DSN
yalnız host ile portu biliyordu. Soket üzerinden kimlik doğrulayan bir sunucuda
(`unix_socket`) bağlantı reddediliyor, döküm `null` dönüyor ve `create()` bunu
sessizce geçip yalnız dosyaları arşivliyordu. Aynı sessiz sonuç yanlış kimlik
bilgisi, kapalı `mysqldump` ya da yetki sorunu için de geçerliydi.

Yani yönetici gövdesiz bir yedeğe güveniyor, bunu ancak geri yüklemeye
çalıştığı gün öğreniyordu. **Geri yükleme yokluğunun asıl bedeli buydu:
yedeklerin çalışıp çalışmadığını kimse denemiyordu.**

Üç düzeltme:

| Ne | Nasıl |
|---|---|
| Döküm alınamazsa yedek alınmıyor | MySQL'de `create()` artık hata dönüyor; SQLite gibi dökümü desteklenmeyen sürücüde sonuç bunu mesajında **açıkça söylüyor** |
| Bağlantı tek kaynaktan | `phpSideDump` elle DSN kurmuyor, uygulamanın kendi bağlantısını (`DB::connection()->getPdo()`) kullanıyor |
| Soket desteği | `mysqldump` ve `mysql` çağrıları `unix_socket` tanımlıysa `--socket` ile bağlanıyor |

##### Geri yükleme

`BackupRestoreService`. Sıra bilinçli:

1. **Arşivi doğrula** — açılabiliyor mu, yedek imzası (`backup-meta.json`) var
   mı, dizin dışına çıkan girdi var mı.
2. **Önce mevcut durumun yedeğini al.** Geri yükleme yanlış dosyayla da
   başlatılabilir. Bu adım başarısız olursa geri yükleme **hiç başlamıyor**.
3. **Bakım moduna geç** — yarı geri yüklenmiş siteyi ziyaretçi görmesin.
4. Veritabanını uygula, 5. yüklenen dosyaları aç, 6. bakım modundan çık.

**İşlem (transaction) yok, olamaz:** MySQL'de `DROP TABLE` / `CREATE TABLE`
örtük commit üretir, şema geri yüklemesi geri alınamaz. Güvenlik yedeğinin
varlık sebebi tam olarak budur.

**Yüklenen dosyalar silinmiyor, üzerine yazılıyor.** Gerçek bir aynalama
yedekten sonra eklenen dosyaları silerdi; kurtarma işleminin yan etkisi olarak
veri silmek, kurtarmanın kendisinden büyük risk.

Onay penceresi neyin uygulanacağını sayıyla yazıyor ve kullanıcı hesaplarının
da yedekteki hâline döneceğini — yani oturumun kapanabileceğini — söylüyor.
"Emin misiniz?" bu kararı verdirmeye yetmez.

##### SQL dökümünü ifadelere ayırma

Geri yüklemenin en sessiz kırılma noktası. Paylaşımlı hostingde `mysql`
istemcisi yok, yani döküm PHP tarafında ifadelere ayrılıp tek tek
çalıştırılıyor. Naif "noktalı virgülden böl" çözümü **yanlıştır**: bir metin
alanının içindeki noktalı virgül (`'Merhaba; dünya'`) ifadeyi ortasından keser
ve veri yarım döner, üstelik hata da vermez.

`App\Support\SqlStatementReader` karakter karakter ilerleyen bir durum
makinesi: tırnak içinde miyiz, ters bölü ile kaçırılmış mı, ikilenmiş tırnak
mı, yorumun içinde miyiz. `/*! ... */` çalıştırılabilir yorumu **atılmıyor** —
mysqldump karakter kümesi ve kısıt ayarlarını böyle yazıyor.

Dosya 64 KB'lık parçalar hâlinde okunuyor (yüz megabaytlık döküm belleğe
sığmayabilir) ve ileri-bakış gerektiren kalıplar parça sınırına denk geldiğinde
kaçmasın diye üç karakterlik pay bırakılıyor. Testi bu sınırı bilerek kalıbın
ortasına getiriyor.

##### Dışarıdan yedek yükleme

Sunucusu gitmiş bir kurulumu ayağa kaldırmanın tek yolu. Dosya önce **listeye
giriyor**, geri yükleme sonra aynı doğrulanmış yoldan yapılıyor — yükleyip
doğrudan uygulamak yerine iki adım, her biri ayrı doğrulanıyor.

Uzantı ve MIME doğrulaması ilk kapı ama dosyanın içeriği hakkında hiçbir şey
söylemiyor: arşiv gerçekten açılıp yedek imzası aranıyor. Diskteki ad da
sunucu tarafından belirleniyor, yüklenen dosyanın kendi adı hiç kullanılmıyor.

**Zip Slip** iki yerde birden eleniyor: `uploads/../../../.env` adlı bir girdi
açılırken hedef dizinin dışına yazar. Tek bir kötü girdi bulunduğunda arşivin
tamamı reddediliyor — kötü girdiyi atlayıp gerisini açmak saldırganın neyi
hedeflediğini gizler.

##### Yedek dizini artık yapılandırmadan geliyor

`config/backups.php` eklendi ve sınama takımı burayı geçici bir dizine
çeviriyor (`phpunit.xml`, yükleme dizini için zaten yapılan şey). Öncesinde
testler geliştiricinin **gerçek yedek dizinine** yazıyordu ve `create()` →
`rotate()` zinciri oradaki eski yedekleri silebilirdi.

##### Testler

`SqlStatementReaderTest` (15, birim): metin içindeki noktalı virgül,
kaçırılmış ve ikilenmiş tırnak, çift tırnak, geri tırnak içinde ters bölü,
satır ve blok yorumları, metin içindeki yorum işareti, çalıştırılabilir
yorumun korunması, parça sınırına denk gelen kalıplar ve gerçek bir döküm
biçimi.

`BackupRestoreTest` (19): arşiv doğrulama, yedek imzası olmayan zip, Zip Slip
(üç vektör), dizin dışına çıkan dosya adı, dosyaların geri yazılması, güvenlik
yedeğinin önce alınması, sonradan eklenen dosyalara dokunulmaması, bakım
modundan çıkılması, boş arşivin güvenlik yedeği alınmadan reddedilmesi,
denetim izi, yetki ayrımı, dışarıdan yükleme ve **gövdesiz yedeğin başarılı
sayılmaması**.

##### Gerçek MySQL'de doğrulama

Suite SQLite üzerinde koşuyor, veritabanı geri yüklemesi ise MySQL'e özgü.
Tam tur ayrı bir MySQL veritabanında elle yapıldı:

1. Migrate + seed → 3 sayfa, 3 kullanıcı, 49 ayar
2. Yedek al → `database.sql` 133 KB
3. Veriyi boz: tüm sayfaları sil, yeni kullanıcı ekle, bir görseli sil
4. Geri yükle → **563 SQL ifadesi, 1 dosya**
5. Doğrula: sayfa 3'e döndü, sonradan eklenen kullanıcı gitti, görsel geri
   geldi, bakım modu kapandı

**Aynı tur PDO yolu için de tekrarlandı** (`mysql` istemcisi bulunamıyormuş
gibi davranılarak): paylaşımlı hostingde çalışacak olan yol bu ve aynı 563
ifadeyi doğru uyguladı.

##### Kalan yarı

Yedeğin **dış kopyası** hâlâ yok: arşiv yedeklediği veriyle aynı diskte
duruyor. Geri yükleme artık mümkün olduğu için dosyanın başka bir yerde
durması da anlamlı hâle geldi — sonraki tur.


---

#### 5v. CI ve Statik Analiz — ✅ Kuruldu, Altı Gizli Hata Çıkardı

1282 test vardı ve **hiçbiri otomatik koşmuyordu**. Kırılmadığını doğrulamak
birinin elle `composer test` yazmasına bağlıydı; bu base kit'ten türeyen
projelerde ilk terk edilen alışkanlık.

##### Üç kontrol, iki iş

`.github/workflows/ci.yml`: push ve pull request'te koşuyor.

| İş | Ne yapıyor |
|---|---|
| **Testler** | MySQL 8 servisine karşı migration + tüm suite |
| **Kalite** | `pint --test` ve `phpstan analyse` |

`composer check` (lint + analyse + test) aynı üçünü yerelde koşuyor.

##### Testler neden MySQL'e karşı

Yerelde SQLite hızlı ama üretim MySQL 8 ve ikisi aynı şeyi kabul etmiyor. Bu iş
akışı kurulduğu gün **SQLite'ın sakladığı altı hata** çıktı:

**① Arama yapan her ekran MySQL'de 500 veriyordu.** İki servis LIKE koşulunu
`ESCAPE '\'` ile yazıyordu. MySQL ters bölüyü dizge içinde de kaçış saydığı
için kapanış tırnağı kaçırılmış oluyor ve sorgu **sözdizimi hatası** veriyordu.
Yönlendirmeler ve ziyaret kayıtları ekranlarında arama kutusuna bir şey yazan
herkes hata sayfası görüyordu — üretimdeki veritabanında, her seferinde.

**② İki servis de tersini yapıyordu:** kaçırılmış terimi `ESCAPE` bildirmeden
kullanıyorlardı; SQLite kaçışı hiç uygulamadığı için `%` arayan biri sessizce
tüm listeyi görüyordu.

**③ Bir test üretim şemasının kabul etmediği değeri yazıyordu:**
`sort_order` kolonu `unsignedInteger`, test ise sıralamayı denemek için `-1`
yazıyordu. SQLite kabul ediyor, MySQL reddediyor.

İlk ikisi aynı kökten: aynı mantık **altı serviste kopyalanmıştı** ve ikisinde
yanlış yazılmıştı. `App\Support\LikeSearch` ile tek yere toplandı; kaçış
karakteri ünlem, çünkü ters bölünün anlamı iki veritabanında farklı, ünlem
ikisinde de düz karakter. `LikeSearchIsPortableTest` hem kuralı hem
uygulanışını bekçilik ediyor ve sorguyu **gerçek veritabanına** atıyor, yani CI
onu iki sürücüde birden sınıyor.

##### Kod stili: gürültüden sinyale

`pint --test` **459 dosyada** sapma bildiriyordu ve bu yüzden çıktısı hiçbir işe
yaramıyordu — gerçek bir stil hatası o gürültüde görünmezdi. Sapmaların büyük
kısmı kod tabanının bilinçli tercihleriydi (dizi hizalaması, `. ` ile
birleştirme, `! ` boşluğu).

`pint.json` bu tercihleri tanımlıyor. Kalan 75 dosya artık üslup değil gerçek
tutarsızlıktı ve düzeltildi: **15 dosyada kullanılmayan import**, 34 dosyada
karışık `!` boşluğu, 8 dosyada karışık birleştirme, gövdesiz `if`'ler ve
yanlış girintilenmiş bir dizi.

Sonuç: **sapma sıfır.** Ve `pint` fix modu artık güvenle çalıştırılabilir —
yapılandırma hizalamaya dokunan kuralları kapalı tutuyor. (Bu, projenin eski
"pint --fix çalıştırma" kuralını geçersiz kılıyor; kural varsayılan preset
içindi.)

Uygulanan diff hizalamaya dokunmadığı satır satır doğrulandı: `=>` sütunu kayan
tek satır bile yok, değişen yalnızca `!$x` → `! $x`.

##### Statik analiz: seviye 1, sıfır tolerans

Larastan eklendi. Seviye 5'te 552 hata çıkıyor ama ezici çoğunluğu gerçek kusur
değil, Eloquent çıkarım sınırı (`selectRaw('count(*) as count')` sütunları,
jenerik `Model` üzerinden görünmeyen SoftDeletes). Seviye 2'de bile 279.

**Seviye 1 seçildi: temiz geçen en yüksek seviye.** Yükseltip taban dosyasıyla
susturmak borcu kapatmak değil gizlemek olurdu; sıfır toleranslı düşük bir
seviye, gürültülü yüksek bir seviyeden çok iş görüyor. Yukarı çıkmanın yolu
modellere `@property` blokları eklemek — ayrı ve büyük bir iş, `phpstan.neon`
içinde not düşüldü.

##### Seviye 0'ın çıkardığı üç şey

Seviye 0 hataları neredeyse her zaman gerçektir ve üçü de öyleydi:

**`LogOutgoingMail::handleFailed`** `Illuminate\Mail\Events\MessageFailed`
olayını dinliyordu — **o sınıf Laravel'de yok.** Olay hiç doğmadığı için
dinleyici hiç çalışmıyordu, üstelik çalışsaydı sürücünün gerçek hatası yerine
genel bir cümle yazacaktı. Başarısız gönderim zaten iki yerde kayda geçiyor
(senkron yolda `MailService`, kuyruk yolunda `UpdateMailLogOnFailed`), yani ölü
kod kaldırıldı.

**`SyncsTranslations` bildirmediği bir özelliğe dayanıyordu.** Trait
`$this->uploadService` okuyor ama onu kullanan sekiz servisin **üçünde** böyle
bir özellik yok. Bugün o üçü görselli yolu çağırmadığı için sorun çıkmıyor;
çağırdıkları gün ölümcül hata alırlardı. Trait artık kendi bağımlılığını kendi
çözüyor.

**`UploadService::limits()` çökebilirdi.** `min(...array_filter([...]))`
yazıyordu; `array_filter` sıfırları attığı için tüm adaylar sıfır olduğunda
`min()` argümansız çağrılıyor ve ölümcül hata veriyordu. Bugünkü çağıranlar
pozitif sabit geçiyor ama sıfır geçen ilk çağrı yükleme yolunu çökertirdi.

##### Testler

`LikeSearchIsPortableTest` (4): ters bölü kaçışının hiçbir serviste geri
gelmemesi, yardımcıyı kullanan her servisin koşulu da ondan alması, joker
karakterin harf sayılması ve kaçış karakterinin kendisinin de kaçırılması.
Son ikisi sorguyu gerçek veritabanına atıyor.

Bekçinin gerçekten yakaladığı mutasyonla doğrulandı — ilk yazımı kaynak metin
yerine çalışma zamanı değerini aradığı için yakalamıyordu, düzeltildi.


---

#### 5y. Çerez Rızası — ✅ Kuruldu

Hiçbir rıza mekanizması yoktu. Google Analytics ve Tag Manager ayar doluysa
**koşulsuz** yükleniyor, projenin kendi ziyaret kaydı da ilk istekten itibaren
IP ve oturum kimliği yazıyordu. IP maskeleme vardı ama 90 gün *sonra* devreye
giriyor — yani veri önce toplanıp sonra anonimleştiriliyordu. KVKK'da açık rıza
ispat yükü veri sorumlusunda; GDPR kapsamındaki bir ziyaretçi için de analitik
çerezler rızadan önce çalışamaz.

##### Üç kategori

`ConsentCategory` enum'u: **zorunlu** (oturum, güvenlik jetonu, dil ve tema —
kapatılamaz), **analitik** (kendi ziyaret kaydımız + Google Analytics),
**pazarlama** (Google Tag Manager).

Tag Manager'ın pazarlama sayılması bilinçli: bir kap içine ne konduğu koddan
görünmez, her etiketi yükleyebilir. Belirsiz olanı en dar kategoriye koymak
doğru varsayılan.

##### Karar verilmeden hiçbir şey yüklenmiyor

Betikler sayfaya konup "çalışmasın" denmiyor — **hiç basılmıyor**. Bir etiket
yüklendiği anda istek atıyor ve çerezini kuruyor; sonradan susturmak geç kalır.

Dört yol da kapalı: başlıktaki GA betiği, GTM betiği, `<noscript>` GTM
çerçevesi ve izleme betiği. Üstüne izleme uç noktası da rızayı **kendisi**
denetliyor: betik rıza olmadan yüklenmiyor ama uç nokta herkese açık, doğrudan
istek atan biri kaydı yine de oluşturabilirdi.

##### Betiksiz de çalışıyor

Band düz bir form, düğmeler gerçek submit. Hangi kategorilere izin verildiğini
sunucu `choice` alanından çözüyor (`all` / `necessary` / `custom`) — kutulardan
değil. Betiksiz durumda "Tümünü kabul et" yalnızca o an işaretli kutuları
gönderirdi, yani hiçbirini, ve düğme yazdığının tersini yapardı.

Tercihi sonradan değiştirmek de betik istemiyor: band karar verildikten sonra
DOM'da kalıyor, alt bilgideki bağlantı `#cookieConsent` adresine gidiyor ve
`:target` kuralı onu yeniden açıyor.

JavaScript'in tek işi "Ayarla" düğmesinin ayrıntıları açması. Yüklenmezse
ziyaretçi yine "tümünü kabul et" ile "yalnızca zorunlu" arasında seçim
yapabiliyor — hak kaybı yok.

**Reddetmek kabul etmek kadar kolay:** iki düğme aynı boyutta, aynı yerde ve
aynı sayıda tıkla ulaşılıyor.

##### İspat kaydı

Tercih iki yerde duruyor ve ikisi farklı işe yarıyor. **Çerez** kararı
hatırlamak için — ziyaretçi silebilir, silerse yeniden sorulur. **`consents`
tablosu** ispat için: zaman damgası, IP, tarayıcı ve metin sürümüyle,
ziyaretçinin silemeyeceği yerde.

Tercih değiştiğinde eski satır güncellenmiyor, yenisi yazılıyor — rızanın
geçmişi de kayıttır. Reddetmek de kaydediliyor: ispat yükü "izin verdi" kadar
"vermedi" için de geçerli.

Çerez `httpOnly` ve şifreli kalıyor. Kutuların durumunu sunucu bastığı için
JavaScript'in onu okumasına gerek yok; okuyabilseydi hem şifrelemeyi kapatmak
hem değeri betiklere açmak gerekirdi.

**Denetim izine yazılmıyor:** kayıt zaten `consents` tablosunda ve orası ispat
için doğru yer. Her ziyaretçinin tıklaması denetim izine düşseydi iz kendi
gürültüsünde boğulurdu — içerik modellerini izlemeye almama gerekçesinin
aynısı.

##### Metin sürümü

`ConsentService::VERSION`. Kategoriler ya da açıklamaları değişirse artırılıyor;
eski rıza yeni metne verilmiş sayılmıyor ve ziyaretçiye bir kez daha soruluyor.

##### Yol üzerinde bulunan sızıntı

Başlıktaki iki betik kapatıldıktan sonra test hâlâ GTM kimliğini buluyordu:
gövdedeki **`<noscript>` GTM çerçevesi** gözden kaçmıştı. Betiği kapatıp
çerçeveyi açık bırakmak, JavaScript'i kapalı ziyaretçiyi — tam da korunması
gereken kişiyi — rızasız izlemek olurdu.

##### Testler

`CookieConsentTest` (20): rıza öncesi bandın görünmesi ve hiçbir izleme
betiğinin basılmaması, uç noktanın kayıt tutmaması; analitik izninin GA ile
izleyiciyi açıp GTM'i açmaması ve tersi; kararın kaydedilmesi (kabul, ret ve
seçmeli), `choice` alanının kutuları geçersiz kılması, bilinmeyen kategorinin
ve eksik seçimin reddi, zorunlu kategorinin karar olarak saklanmaması, ispat
alanlarının dolu olması, tercih değişince yeni satır yazılması, sürüm
değişince yeniden sorulması ve bozuk çerezin karar sayılmaması.

Tarayıcıda uçtan uca doğrulandı: rıza öncesi `dataLayer` tanımsız ve hiçbir
Google betiği yok; "tümünü kabul et" sonrası GA, GTM ve izleyici yükleniyor,
band gizleniyor, kayıt yazılıyor; alt bilgi bağlantısı bandı JavaScript'siz
yeniden açıyor; mobilde taşma yok.


---

#### 5z. API Katmanı (v1) — ✅ Kuruldu

Mobil uygulamanın ve harici istemcilerin **aynı iş mantığından** beslendiği
katman. Kural şu: bir uç, ön yüzün kullandığı Service'i çağırır; kendi
sorgusunu yazmaz. Yazsaydı iki taraf zamanla farklı şeyler döndürürdü.

- **33 rota**, `/api/v1` önekinde (önek `bootstrap/app.php`'de). Sürüm önekte
  duruyor ki kırıcı bir değişiklikte v2 açılıp v1 bir süre ayakta kalabilsin —
  mağazadaki eski uygulama sözleşmeyi konuşmaya devam eder.
- **19 controller / 19 API Resource.** Zarf tek: `App\Http\Responses\ApiResponse`.
- **Dil** `Accept-Language` ya da `?lang=` ile çözülüyor (`SetApiLocale`),
  yanıtta `Content-Language` ile geri bildiriliyor, `Vary` ile araya giren
  önbelleklere duyuruluyor — olmasaydı ilk gelenin dili ötekilere servis
  edilirdi.
- **Kimlik** Sanctum kişisel erişim jetonu. Jeton ömrü ve hız sınırlarının
  hepsi `config/api.php` üzerinden, `.env`'den ayarlanabilir.
- **Dört kapı middleware'i:** `api.available` (bakım modu), `api.active`
  (pasife alınan kullanıcı), `api.verified` (e-posta doğrulaması),
  `abilities` (jeton yetkisi).
- **Bilinmeyen adres** JSON 404 dönüyor: `Route::fallback` olmadan
  `/api/v1/yanlis-adres` web tarafındaki fallback'e düşüp HTML sayfa
  döndürüyordu.

##### Kapsam

| Alan | Uçlar |
|---|---|
| Kimlik | kayıt, giriş, çıkış, `me`, e-posta doğrulama tekrarı |
| Şifre sıfırlama | altı haneli kod ile iste/doğrula (mobilde bağlantı tıklatmak zor) |
| Hesap | profil güncelleme (avatar aynı istekte, `multipart`) |
| Cihazlar | listele, tekini kapat, ötekilerin hepsini kapat |
| İçerik | açılış ekranı, slider, SSS, sayfalar, menüler, diller, ayarlar, çeviriler |
| Blog | kategoriler, yazılar, yazı detayı, yorumlar (oku + gönder) |
| Galeri | kategoriler, öğeler |
| Arama | site geneli tek uç |
| Formlar | iletişim, bülten aboneliği |

##### Jeton yetkileri ve önbellek başlıkları (`26fa4fd`)

Yetkiler enum'da: `profile:read`, `profile:write`, `devices:manage`. Çıkış
bilerek yetkisiz — bir jeton her zaman kendini iptal edebilmeli, yoksa dar
yetkili bir jeton ele geçtiğinde sahibi onu kapatamaz.

Seyrek değişen uçlar `ETag` ile dönüyor; istemci `If-None-Match` gönderince
içerik değişmemişse 304 alıyor ve gövde hiç inmiyor. En büyük kazanç çeviri
sözlüğünde (yüz kilobayta yaklaşabiliyor). İçerik listeleri bilerek dışarıda:
orada tazelik önbellekten değerli ve sayfalama ETag'i sürekli değiştiriyor.

##### Cihaz yönetimi (`471ea76`)

Kullanıcı kendi oturumlarını görüp kapatabiliyor. Doğrulanmış e-posta şartı
bilerek yok: hesabına şüpheli erişim olduğunu düşünen kişi, doğrulama adımını
tamamlayamamış olsa bile oturumları kapatabilmeli.

##### E-posta değişimi (`7873d89`, `fbabbaf`)

Adres değişince doğrulama sıfırlanıyor ve **eski adrese** güvenlik uyarısı
gidiyor — hesabı ele geçiren biri adresi değiştirse bile sahibi haberdar olur.

##### Makine okunur sözleşme (`782cea2`)

`docs/openapi.json` — OpenAPI 3.1. Kendi kendini denetliyor:
`Api/OpenApiSpecTest` şemayı rotalarla karşılaştırıyor, yeni bir uç şemaya
yazılmadan eklenirse test düşüyor. İkinci bir Postman koleksiyonu bilerek
tutulmuyor: ikinci dosya ikinci bayatlama kaynağı.

##### Testler

`tests/Feature/Api/` altında 11 sınıf: kimlik, şifre sıfırlama, hesap, cihaz,
jeton yetkileri, önbellek başlıkları, içerik uçları, herkese açık uçlar, blog
araması, site araması ve sözleşme denetimi.

---

#### 5ab. Arama — ✅ Kuruldu (blog + site geneli)

Üç commit'te büyüdü: önce blog araması (`08dcf33`), sonra blog sayfasındaki
kutu (`5013668`), sonunda blog + sayfa + SSS + galeriyi tek kutudan tarayan
site geneli arama (`de77f5e`).

- Tek servis: `SearchService`. Ön yüzdeki `/arama` ile API'deki
  `/api/v1/search` aynı sorguyu kullanıyor.
- Jokerler harf sayılarak eşleşiyor; `LIKE` deseni veri tabanından bağımsız
  (`LikeSearchIsPortableTest` bekçilik ediyor — SQLite'ta geçip MySQL'de
  düşen desenler bu kit'te bir kez yaşandı).
- Rota sırası önemli: `/arama`, `/{slug}` yakalayıcısından **önce** tanımlı;
  sonra gelseydi "arama" adlı bir sayfa varmış gibi aranırdı.
- Sonuçlar dile duyarlı (`localeWithFallback`) ve yalnız yayında olan içeriği
  gösteriyor.

---

#### 6a. Hesap ve Kimlik — ✅ Faz 1

Hesap alanı iki ekrandı (pano, profil). Bir base kit'in en çok kopyalanan
parçası burasıdır; eksik kalırsa her projede yeniden yazılır.

- **Cihazlarım** (`/hesabim/cihazlar`): açık tarayıcı oturumları **ve** bağlı
  uygulamalar. API'de yalnız jetonlar listeleniyordu; tek kaynağı göstermek
  "başka yerde açık oturum yok" demenin yanlış bir yolu. Toplu kapatma
  beni-hatırla damgasını da düşürüyor, yoksa kapatılan tarayıcı bir sonraki
  istekte kendini yeniden doğrulayıp geri dönerdi.
- **İki adımlı doğrulama** (TOTP, RFC 6238): harici servis yok, kod paylaşılan
  bir anahtardan ve saatten üretiliyor. QR sunucuda (bacon/bacon-qr-code, saf
  PHP, satır içi SVG) — anahtarı bir QR servisine göndermek onu üçüncü tarafa
  vermek olurdu. Kurulum iki adımda: kullanıcı ilk doğru kodu girene kadar
  açılmıyor, yoksa QR'ı okutamayan kişi kendi hesabından kilitlenirdi. Sekiz
  tek kullanımlık kurtarma kodu. Panelden "yöneticiler için zorunlu" ayarı ve
  onu uygulayan ara katman.
- **Verilerim** (KVKK/GDPR): veri indirme (JSON) ve hesap kapatma. Dosya
  kişinin bütün kaydını topluyor ama hiçbir anahtarını taşımıyor. Kapatma
  yumuşak silme; oturumlar, jetonlar ve 2FA anahtarı aynı anda düşüyor,
  e-posta serbest kalıyor. Mağazaların uygulama içi hesap silme şartının
  karşılığı.
- **Bildirim tercihleri**: kapatılabilir türler enum'da, gönderim öncesi tek
  kapı. Güvenlik postaları listede yok ve kapatılamıyor — kapatılabilseydi
  hesabı ele geçiren ilk iş onları susturur, sahibi habersiz kalırdı. Bülten
  anahtarı abone tablosunu okuyup yazıyor, kendi bayrağını tutmuyor.
- **API tarafı**: şifre değiştirme ayrı uç (profil güncelleme tam bir
  güncelleme; yalnız şifre değiştirecek istemcinin bütün profili taşıması
  gerekirdi), veri indirme, hesap kapatma, bildirim tercihleri, iki adımlı
  girişin ikinci isteği.

---

#### 6b. Mobil Web — ✅ Faz 2

Site duyarlıydı ama mobil değildi: telefona kurulamıyor, bağlantı kesildiğinde
tarayıcının kendi hata sayfası çıkıyordu.

- **PWA**: `/site.webmanifest`, `/sw.js` ve `/offline` — üçü de rotadan
  üretiliyor. Manifest panelden besleniyor; sabit dosya olsaydı her proje onu
  elle düzenlerdi. İkonlar kaynaktan 192 ve 512 piksellik kare PNG olarak
  üretiliyor: bildirilen ölçü ile dosyanın gerçek ölçüsü tutmazsa Chrome
  kurulumu sessizce reddediyor.
- Servis çalışanının önbellek sürümü varlıkların son değişme zamanından
  geliyor; elle yazılan sürüm numarası unutulur. Sayfalar "önce ağ" (içerik
  sitesinde önbellekten gelen sayfa bayat sayfadır), varlıklar önbellekten
  verilip arkada tazeleniyor. Panel, hesap alanı ve API hiç önbelleğe girmiyor.
- **Yol üzerinde çıkan kusur**: ön önbelleğe alınan adresler sayfanın istediği
  adreslerle aynı değildi (kendi dosyalarımız sürüm damgalı, vendor dosyaları
  damgasız) ve çevrimdışı sayfa stilsiz açılıyordu. Liste düzenin yazdığı
  biçime hizalandı; çevrimdışı sayfasının ikonu satır içi SVG'ye çevrildi —
  bağlantı yokken gereken sayfanın inmesi gereken bir fonta bağlı olması
  çelişkiydi.
- **Mobil denetim** (375 piksel, ön yüzde 7 + panelde 18 ekran): üç yatay
  taşma bulundu ve düzeltildi (iletişim sayfasının boşluğu, kullanıcılar
  ekranının araç çubuğu, analitik ekranının tarih düğmeleri). Slayt
  göstergeleri ve kapatma düğmesi kaba işaretçilerde 44 piksele çıkarıldı.
- **Erişilebilirlik**: denetimde ön yüzde adsız tek kontrol çıktı (bültenin
  gönder düğmesi). `AccessibilityBaselineTest` bunu bekçiliyor.

---

#### 6c. Panelin Eksik Ekranları — ✅ Faz 3

- **Rapor merkezi** (`/admin/raporlar`): altı rapor (trafik, içerik, kullanıcı,
  e-posta, kampanya, abone), her biri aynı yapıda (metrics + series + rows).
  Ekran, Excel/PDF çıktısı ve zamanlanmış gönderim tek kod yolunu paylaşıyor.
  Zamanlanmış raporlar cron'da üretilip e-postayla gidiyor; "bugün çalıştı mı"
  kontrolü tanımın kendisinde, yoksa dakikada bir uğrayan cron günlük raporu
  bin kez gönderirdi. Tasarımdaki dört e-ticaret kartının konusu kit'in
  ölçtüğü şeylerle değiştirildi (düzen ve sınıflar aynı).
- **Genel içerik listesi** (`/admin/icerikler`): dört tür tek sorguda
  birleşiyor (UNION). Durum iki değere indirgeniyor — blog/sayfada enum,
  galeri/SSS'de bayrak; indirgenmeseydi "yayında" demek türden türe farklı bir
  şey olurdu.
- **Yardım merkezi** (`/admin/yardim`): 33 modülün kılavuzu, kategorili SSS,
  sistem bilgisi ve destek iletişimi. İçerik `config/help.php`'de. Sidebar'a
  yeni bir modül eklenip kılavuzu yazılmazsa `AdminHelpTest` düşüyor.

---

#### 6d. API Olgunluğu — ✅ Faz 4

- **`GET /api/v1/health`**: jeton istemiyor ve bakım modunda da açık —
  uygulamanın bakımı öğrenebileceği tek yer burası. Asgari istemci sürümü
  panelden; eski sürüm "güncelle" cevabı alıyor. Sürümünü bildirmeyen istemci
  engellenmiyor.
- **Push altyapısı**: jeton kaydı yapılandırma istemiyor, gönderim
  sağlayıcıdan bağımsız. Taşıyıcı tanımsızken bildirim gönderilmiyor ama bu
  log'a düşüyor — sessizce kaybolmuyor. Jeton cihaza ait, hesaba değil: aynı
  telefondan başka bir hesaba girildiğinde kayıt o hesaba geçiyor.
- **Yorumlarım**: web ve API'de, onay bekleyenler dahil. Durum alanı yalnız bu
  uçlarda — herkese açık listede onay bekleyenin varlığı bile söylenmiyor.
- `docs/openapi.json` 38 uç; `OpenApiSpecTest` şemayı rotalarla karşılaştırıyor.

---

#### 6e. Dayanıklılık — ✅ Faz 5

- **Yedeğin dış kopyası**: `local` (ikinci disk, ağ klasörü) ve `ftp`. Kopya
  sonrası boyut karşılaştırılıyor; dış kopyanın saklama süresi var;
  başarısızlık panele bildirim düşürüyor. Dış kopya düşse bile yedek başarılı
  sayılıyor — yerel kopya alındıysa iş görülmüştür.
- **`jenssegers/agent` kaldırıldı**: paket ile kendi ayrıştırıcımız sekiz
  gerçek User-Agent'ta karşılaştırıldı, sonuçlar aynı ve iki yerde bizimki
  daha okunur. Eski analitik kayıtları yeni biçime taşındı.
- **Bellek bütçesi**: suite 141 MB tepe yapıyor; gereken sınır artık
  `phpunit.xml`'de bildiriliyor ve `composer test` stok 128 MB'lık PHP ile de
  koşuyor.
- **`cache.serializable_classes`** izin listesi kuruldu; yedi önbellekli yol
  iki geçişli testle (yaz + geri oku) kapsandı.

---

#### 7. Laravel 13 Upgrade Notları

`ef5042c` commit'inde 12.52.0 → 13.26.1 yükseltmesi yapıldı. Upgrade guide'daki
kırılmaların hiçbiri projeye dokunmadı. İki config değeri **bilinçli olarak
varsayılanda bırakıldı**:

| Ayar | Durum | Sebep |
|---|---|---|
| `session.serialization` | Tanımsız → `'php'` | `json`'a çevirmek tüm aktif oturumları düşürür. Güvenlik sertleştirmesi olarak sonradan açılabilir. |
| `cache.serializable_classes` | Tanımsız → `null` | `false` yapılırsa Eloquent Collection cache'leyen 5 servis (`SliderService`, `PageService`, `FaqService`, `PopupService`, `BlogCategoryService`) kırılır. Açılacaksa allow-list ile açılmalı. |

##### Kod stili uyarısı

~~`pint --test` ~180 dosyada sapma bildiriyor~~ — kapatıldı (bkz. bölüm 5v).
`pint.json` projenin kendi biçimini tanımlıyor, sapma sıfır ve fix modu artık
güvenle çalıştırılabiliyor.

---

#### 8. Tamamlananlar

##### Kapanan turlar

- [x] SoftDeletes, yetkilendirme, açık yönlendirme, moderatör rolü (5, 5c, 5d)
- [x] Ürün/sipariş kalıntılarının temizliği — 15 kalem (4)
- [x] Çok dilli yapı, arayüz çevirisi, çok dilli navigasyon (5e, 5f, 5g)
- [x] Mail ve upload yolları (5h) · Toplu mail (5i) · Shared hosting uyumu (5j)
- [x] Diller, dil yazıları, bölgesel ayarlar (5k, 5l, 5m)
- [x] Pasif kullanıcı oturumu, güvenilen proxy, robots.txt (5n, 5o)
- [x] Hata bildirimi, log rotasyonu, denetim izi (5p, 5r)
- [x] Kuyruk izleyici, başarısız işler, yedek geri yükleme (5s, 5t, 5u)
- [x] CI ve statik analiz (5v) · Çerez rızası (5y)
- [x] API katmanı v1 (5z) · Arama (5ab)
- [x] **Faz 1 — Hesap ve kimlik** (6a)
- [x] **Faz 2 — Mobil web** (6b)
- [x] **Faz 3 — Panelin eksik ekranları** (6c)
- [x] **Faz 4 — API olgunluğu** (6d)
- [x] **Faz 5 — Dayanıklılık** (6e)

##### Açık kalan iki madde

Bölüm 6'da: panelden push gönderme ekranı (tasarım bekliyor) ve
`session.serialization = json` (bakım penceresi bekliyor).


---
---

# BÖLÜM B — Yol Haritası

> **Arşiv — 31 Ağustos 2026 anlık görüntüsü.** Bu bölüm `docs/YOL-HARITASI.md`
> dosyasının o günkü içeriğidir; başlık seviyeleri bir kademe indirildi ki bu
> belgenin hiyerarşisine otursun. Beş fazın her maddesi: neden gerekli, kapsamı
> ne, kabul ölçütü ve testi ne.
>
> **Güncel durum burada değil.** Maddelerin bugünkü hâli bölüm 2 ve 3'te;
> kaynak dosya (`YOL-HARITASI.md`) da kapanan maddelerle birlikte güncelleniyor.
> Bu anlık görüntüde yalnız bir düzeltme yapıldı: kabul ölçütlerinde var olmayan
> altı test dosyasına gönderme vardı (`ApiPushTokenTest`, `ApiHealthTest`,
> `PwaManifestTest`, `ServiceWorkerTest`, `ApiAccountDeletionTest`,
> `ApiAccountCommentsTest`), gerçek dosya adlarıyla değiştirildi — hiçbir yere
> çıkmayan bir gönderme kimseye bir şey anlatmıyor.


### Yol Haritası — "Eksiksiz Base Kit"e Kalan Yol

**Çıkarıldığı tarih:** 2026-08-31
**Dal:** `feat/laravel-13-upgrade`
**Kapsam:** üç yüz birden — masaüstü web, mobil web, API

Bu belge *ne eksik* sorusunun cevabı. *Ne var* sorusunun cevabı
[`PROJE-DURUMU.md`](PROJE-DURUMU.md)'de; *yapıldı denilen gerçekten çalışıyor mu*
sorusunun cevabı [`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md)'de; *hangi sırayla
kapatılmalı* sorusunun cevabı [`BOSLUK-ANALIZI.md`](BOSLUK-ANALIZI.md)'de;
API sözleşmesi
[`API.md`](API.md) ve [`openapi.json`](openapi.json)'da.

---

#### Ölçüt: "tam donanımlı" ne demek

Bu kit'ten türeyen bir kurumsal proje, ilk günden şunları **yazmadan** bulmalı:

1. **Web** — içerik yönetimi, çok dillilik, SEO, kimlik, hesap alanı, formlar
2. **Mobil web** — aynı sitenin telefonda kurulabilir, çevrimdışı hata vermeyen,
   parmakla kullanılabilir hâli
3. **API** — mobil uygulamanın aynı iş mantığından beslendiği, sürümlü,
   sözleşmesi makine okunur bir katman
4. **İşletme** — panelden ölçme, raporlama, yedekleme, izleme, kurtarma

Aşağıdaki dört fazın sonunda dördü de kapanıyor. Her madde bir commit, her
maddenin bir kabul ölçütü ve en az bir testi var.

---

#### Faz 1 — Hesap ve Kimlik (web + API birlikte) — ✅ TAMAMLANDI

Bugün hesap alanı iki ekran: pano ve profil. API tarafı ondan bir adım önde
(cihaz yönetimi var), web tarafı geride. Bir base kit'in en çok kopyalanan
parçası burası; eksik kalırsa her projede yeniden yazılıyor.

##### 1.1 Web'de cihaz ve oturum yönetimi — ✅ bitti (`de77f5e` sonrası)
**Neden:** API'de var (`GET/DELETE /auth/devices`), web'de yok. Aynı kullanıcı
telefonda oturumunu kapatabiliyor, tarayıcıda kapatamıyor.
**Kapsam:** `/hesabim/cihazlar` — açık oturumlar (IP, tarayıcı, son görülme),
tek tek ve toplu kapatma. `SessionRevoker` zaten duruyor.
**Kabul:** Başka bir tarayıcıdaki oturum listede görünüyor, kapatıldığında o
tarayıcı bir sonraki istekte girişe düşüyor. Test: `AccountDeviceTest`.

##### 1.2 İki adımlı doğrulama (TOTP) — ✅ bitti
**Neden:** Panel yöneticisinin tek koruması şifre. Kurumsal müşterinin ilk
sorduğu şey; sonradan eklemek oturum ve API akışlarının ikisini birden
değiştiriyor, şimdi eklemek ucuz.
**Kapsam:** TOTP (Google Authenticator uyumlu, harici servis yok), QR kurulum,
tek kullanımlık kurtarma kodları, "yöneticiler için zorunlu" ayarı. Web giriş
akışı + API `POST /auth/login` iki aşamalı yanıt (`two_factor_required`).
**Kabul:** 2FA açık kullanıcı doğru şifreyle giriş yapamıyor, kod isteniyor;
kurtarma kodu bir kez çalışıp tükeniyor. Test: `TwoFactorTest`,
`Api/ApiTwoFactorTest`.

##### 1.3 Hesabı kapatma ve veri indirme (KVKK/GDPR) — ✅ bitti
**Neden:** Rıza kaydı var (`Consent`), ama kişinin *silme* ve *taşınabilirlik*
hakkının karşılığı yok. Mağazalar (App Store / Play) uygulama içi hesap silme
yolunu artık şart koşuyor — mobil uygulama bu olmadan yayınlanamıyor.
**Kapsam:** Web `/hesabim/veriler`: verilerimi indir (JSON+ZIP) ve hesabımı
kapat (şifre onaylı, gecikmeli kalıcı silme). API: `GET /account/export`,
`DELETE /account`.
**Kabul:** Kapatılan hesap giriş yapamıyor, jetonları iptal, e-postası
serbest kalıyor; dışa aktarma kişinin bütün kayıtlarını içeriyor.
Test: `AccountDataRightsTest`, `Api/ApiAccountDataTest`.

##### 1.4 API hesap uçlarının tamamlanması — ✅ bitti
**Neden:** Web'de olup API'de olmayan üç akış var: şifre değiştirme, e-posta
değiştirme doğrulaması, avatar kaldırma. Mobil uygulama bunlar için tarayıcı
açmak zorunda kalıyor.
**Kapsam:** `PUT /account/password` (mevcut şifre onaylı, diğer jetonları
düşürme seçeneği), e-posta değişiminde doğrulama akışının API karşılığı.
**Kabul:** Şifre değişince — istenirse — öteki cihazların jetonu düşüyor.
Test: `Api/ApiAccountTest` genişletmesi.

##### 1.5 Bildirim tercihleri — ✅ bitti
**Neden:** Kullanıcının aldığı e-postaları (bülten, yorum yanıtı, duyuru)
kapatabileceği tek yer bülten çıkış bağlantısı. Tercih tablosu olmadan her yeni
e-posta türü aynı sorunu tekrar doğuruyor.
**Kapsam:** `user_notification_preferences`, hesap ekranında anahtarlar, gönderim
öncesi tek kapıdan kontrol; API'de oku/yaz.
**Kabul:** Kapatılan tür o kullanıcıya gitmiyor ve `mail_logs`'a "tercih"
gerekçesiyle düşüyor. Test: `NotificationPreferenceTest`.

---

#### Faz 2 — Mobil Web (PWA + erişilebilirlik) — ✅ TAMAMLANDI

Site bugün duyarlı (responsive) ama *mobil* değil: telefona kurulamıyor,
bağlantı kesildiğinde tarayıcının kendi hata sayfasını gösteriyor.

##### 2.1 Uygulama bildirimi (`manifest.json`) — ✅ bitti
**Neden:** "Ana ekrana ekle" olmadan PWA'nın geri kalanı da anlamsız.
**Kapsam:** Rotadan üretilen manifest (ad, ikon, tema rengi panelden geliyor —
sabit dosya olsaydı her projede elle düzenlenirdi), 192/512 ikon üretimi
`UploadService` üzerinden, `apple-touch-icon` zinciri.
**Kabul:** Chrome ve Safari'de kurulabilir; kurulan uygulama panelde ayarlanan
adı ve rengi taşıyor. Test: `PwaTest`.

##### 2.2 Servis çalışanı ve çevrimdışı sayfa — ✅ bitti
**Neden:** Build tool yasağı yüzünden hazır PWA eklentileri kullanılamıyor;
elle yazılmış, küçük ve okunur bir servis çalışanı gerekiyor.
**Kapsam:** Kabuk + statik varlık önbelleği, sürüm damgası (dosya değişince
eski önbellek düşer), çevrimdışı sayfa, HTML için "önce ağ" stratejisi —
içerik bayatlamamalı.
**Kabul:** Uçak modunda site açılıyor ve çevrimdışı sayfa çıkıyor; yeni sürüm
yayınlandığında bir sonraki ziyarette güncel içerik geliyor.
Test: `PwaTest` (kayıt, kapsam, sürüm damgası).

##### 2.3 Mobil kullanım denetimi — ✅ bitti
**Neden:** 70 KB'lık ön yüz CSS'inde yalnız 10 medya sorgusu var; düzen
Bootstrap ızgarasına bırakılmış. Izgara düzeni çözer, dokunma hedefini ve
yatay taşmayı çözmez.
**Kapsam:** 360 px'te bütün ön yüz ve panel ekranlarının taranması; dokunma
hedefi ≥44 px, yatay kaydırma sıfır, panel tablolarında kaydırma kabı,
yapışkan başlık/eylem çubukları.
**Kabul:** Tarayıcıda 360×640'ta gezinti kanıtlanıyor; `document.body.scrollWidth`
taşmıyor. Test: yatay taşma bekçisi + görsel kanıt.

##### 2.4 Erişilebilirlik taban çizgisi — ✅ bitti
**Düzeltme:** Bu maddenin gerekçesi kısmen yanlıştı — içeriğe atlama bağlantısı
zaten vardı (`skip-to-content`). Denetim yapıldığında ön yüzde adsız tek bir
kontrol çıktı (bültenin gönder düğmesi) ve bütün form alanlarının etiketi
yerindeydi. Eksik olan şey bekçiydi: `AccessibilityBaselineTest`.
**Kapsam:** Skip link, odak halkaları, form etiket eşleşmeleri, ikon
düğmelerine erişilebilir ad, renk kontrastı, `prefers-reduced-motion`
(kısmen var).
**Kabul:** Klavyeyle bütün ön yüz gezilebiliyor, odak her zaman görünür.
Test: `AccessibilityBaselineTest` (etiketsiz girdi ve adsız ikon düğmesi yok).

---

#### Faz 3 — Panelin Eksik Ekranları — ✅ TAMAMLANDI

Temada tasarımı hazır olup kodu olmayan üç ekran. Tasarım dosyaları
`resources/views/admin-theme/` altında duruyor, birebir uyarlanacak.

##### 3.1 Raporlar (`reports.html`) — ✅ bitti
**Neden:** Veri panelde toplanıyor (ziyaret, içerik, mail, kullanıcı, kampanya)
ama tek bir yerden okunmuyor; yönetici sayıları beş ekrandan derliyor.
**Kapsam:** Tarih aralığı seçimli rapor ekranı — trafik, içerik üretimi, mail
gönderimi, kullanıcı büyümesi, kampanya başarımı; her rapor Excel/PDF çıktısı
(`app/Exports` altyapısı hazır).
**Kabul:** Seçilen aralık bütün kartlara ve dışa aktarmaya aynı şekilde
uygulanıyor. Test: `AdminReportsTest`.

##### 3.2 Genel içerik listesi (`content-list.html`) — ✅ bitti
**Neden:** Blog, sayfa, galeri ve SSS ayrı listelerde; "geçen ay ne yayınlandı"
sorusunun tek ekranlık cevabı yok. Site geneli arama servisi
(`SearchService`) bu birleşik görünümün sorgu tarafını zaten kuruyor.
**Kapsam:** Tür/dil/durum/tarih süzgeçli birleşik liste, toplu durum
değiştirme, kayda gitme.
**Kabul:** Dört tür de tek listede, süzgeçler birleşik çalışıyor, yetkisi
olmayan türü göremiyor. Test: `AdminContentListTest`.

##### 3.3 Yardım (`help.html`) — ✅ bitti
**Neden:** Panelde 30'dan fazla ekran var; devralan kişi için panel içi rehber
yok. Bu kit başkalarına teslim edilmek için var.
**Kapsam:** Modül modül kısa rehber, sık sorulanlar, sürüm ve ortam bilgisi,
destek iletişimi — içeriği çeviri dosyalarından, böylece projeye göre
değiştirilebilir.
**Kabul:** Her sidebar modülünün bir yardım başlığı var. Test: `AdminHelpTest`.

---

#### Faz 4 — API Olgunluğu

##### 4.1 Push bildirim altyapısı — 🟡 sunucu tarafı bitti, panel ekranı bekliyor
**Neden:** Mobil uygulamanın ilk isteyeceği şey; sunucu tarafı hazır değilse
uygulama ekibi bekliyor. Sağlayıcıdan bağımsız kurgulanabilir: jeton kaydı ve
gönderim kancası bizde, taşıyıcı (FCM/APNs) yapılandırmada.
**Kapsam:** `POST/DELETE /account/push-tokens`, cihaz eşleştirmesi, panelden
"bildirim gönder" ekranı, gönderim kuyruğa düşüyor.
**Yapılan:** Jeton kaydı, cihaz eşleştirme, sağlayıcıdan bağımsız gönderim
servisi (FCM sürücüsü + yapılandırılmamışken log), ölü jetonun düşmesi ve
oturum kapanınca jetonların silinmesi.
**Kalan:** Panelden bildirim yazıp gönderme ekranı. Admin temada bu ekranın
tasarımı yok (`notifications.html` yalnız tercih anahtarları içeriyor) ve
tasarımda olmayan ekranı uydurmak proje kuralına aykırı — tasarım geldiğinde
ya da onay verildiğinde yapılacak.
**Kabul:** Jeton kaydı cihazla eşleşiyor, oturum kapanınca jeton düşüyor.
Test: `Api/ApiPushAndHealthTest`.

##### 4.2 Sürüm ve sağlık ucu — ✅ bitti
**Neden:** Mağazadaki eski sürümü zorla güncellemenin yolu yok; bakım
penceresini uygulama önceden bilmiyor.
**Kapsam:** `GET /api/v1/health` — sürüm, asgari desteklenen istemci sürümü,
bakım durumu.
**Kabul:** Asgari sürüm ayarı yükseltilince eski istemci "güncelle" yanıtı
alıyor. Test: `Api/ApiPushAndHealthTest`.

##### 4.3 Kullanıcının kendi yorumları — ✅ bitti
**Neden:** Yorum gönderilebiliyor ama kişi kendi yorumlarını göremiyor,
silemiyor. Web'de de yok — ikisi birlikte yapılmalı.
**Kapsam:** `GET /account/comments`, `DELETE /account/comments/{id}`; web'de
hesap ekranında aynı liste.
**Kabul:** Sadece kendi yorumları, onay bekleyenler dahil.
Test: `AccountCommentsTest` (web ve API uçları aynı dosyada).

##### 4.4 Şemanın hizada kalması — ✅ sürüyor (38 uç şemada)
**Neden:** `openapi.json` kendi kendini denetliyor (`OpenApiSpecTest`); yeni
uçlar eklendikçe bu bekçi güncel kalmalı, yoksa sessizce bayatlar.
**Kapsam:** Faz 1–4'te eklenen her uç için şema girdisi ve `API.md` bölümü.
**Kabul:** `OpenApiSpecTest` yeşil ve rotalarla şema arasında fark yok.

---

#### Faz 5 — Dayanıklılık ve Bakım — ✅ TAMAMLANDI (bir madde bilerek ertelendi)

##### 5.1 Yedeğin dış kopyası — ✅ bitti
**Neden:** Arşiv, yedeklediği veriyle aynı diskte duruyor. Diski kaybeden
yedeği de kaybediyor — yedeklemenin var olma sebebi bu senaryoydu.
**Kapsam:** Yapılandırılabilir dış hedef (S3 uyumlu ya da FTP), yükleme sonrası
doğrulama, başarısızlıkta yöneticiye bildirim, dış kopyada saklama süresi.
**Kabul:** Yedek alındıktan sonra dış hedefte aynı boyutta dosya bulunuyor;
hedef erişilemezse iş "başarılı" sayılmıyor. Test: `BackupOffsiteTest`.

##### 5.2 `jenssegers/agent` bağımlılığından çıkış — ✅ bitti
**Neden:** 2020'den beri güncellenmiyor. Tek kullanım yeri `AnalyticsService`;
etki alanı dar olduğu için şimdi çıkmak ucuz, PHP 9'da mecbur kalmak pahalı.
**Kapsam:** Tarayıcı/işletim sistemi/cihaz türü tespiti için küçük bir iç
servis + kendi test kümesi (gerçek `User-Agent` örnekleriyle).
**Yapılan:** Ayrıştırma `UserAgentParser` servisine çıkarıldı (Faz 1.1 yolunda);
paket kararı artık tek dosyada. Kalan: paketin yerine geçecek tabloyu
zenginleştirip bağımlılığı `composer.json`'dan düşürmek.
**Kabul:** Analitik ekranındaki dağılımlar değişmiyor; bağımlılık
`composer.json`'dan düşüyor. Test: `UserAgentParserTest`.

##### 5.3 Test paketinin bellek bütçesi — ✅ bitti
**Neden:** `vendor/bin/phpunit` 1516 testi 44 saniyede yeşil bitiriyor ama tepe
belleği **131 MB**. Stok `memory_limit=128M` ile suite yarıda düşüyor
(`RolePermissionManagementTest` render ederken). Tek başına en ağır sınıf
83 MB'de kalıyor — yani ekran değil, suite boyunca biriken bellek. CI'da sınır
yüksek olduğu için görünmüyor; kit'i klonlayan geliştiricinin makinesinde
görünüyor.
**Kapsam:** Birikimin kaynağı (test başına tutulan uygulama örneği, fabrika
verileri) bulunup düşürülmeli; olmazsa `phpunit.xml` ya da `composer test`
gereken sınırı kendisi vermeli ve README bunu yazmalı.
**Kabul:** `composer test` stok 128 MB'lık bir PHP ile baştan sona koşuyor.
Test: mevcut suite (kendisi ölçüt).

##### 5.4 Sertleştirme kararları — 🟡 biri yapıldı
**Neden:** İki config değeri bilinçli olarak varsayılanda bırakılmıştı; karar
verilmiş ama uygulanmamış hâlde duruyorlar.
**Kapsam:** `session.serialization = json` (bakım penceresinde, oturumlar
düşeceği için) ve `cache.serializable_classes` için izin listesi.
**Yapılan:** `cache.serializable_classes` izin listesi kuruldu ve yedi
önbellekli yolun hepsi iki geçişli testle (yaz + geri oku) kapsandı.
**Kalan:** ~~`session.serialization = json`~~ — **1 Eylül 2026'da kapandı.**
Ertelenme sebebi çevirmenin açık oturumları düşürmesiydi; `migrate` modu o
bedeli kaldırdı (okuma iki biçimi de kabul ediyor, yazma JSON'a dönüyor).
Kit varsayılanı artık `json`.

---

#### Sıra ve Gerekçesi

| Faz | Neden bu sırada |
|---|---|
| 1 — Hesap ve kimlik | En çok kopyalanan parça, en eksik olan; mağaza şartı (hesap silme) buna bağlı |
| 2 — Mobil web | Kullanıcının gördüğü ikinci yüz; Faz 1'in ekranları da mobil doğsun |
| 3 — Panel ekranları | İçeride kalıyor, dışarıya söz vermiyor; ertelenebilir ama tema zaten hazır |
| 4 — API olgunluğu | Mobil uygulama başlamadan önce bitmeli, Faz 1'in uçlarıyla aynı şemayı paylaşıyor |
| 5 — Dayanıklılık | Görünmez ama en pahalı hatalar burada; bellek maddesi hedef ortamı doğrudan ilgilendiriyor |

#### Kapsam dışı (bilerek)

- **E-ticaret** (ürün, sipariş, ödeme) — `ab57deb`'de sökülmüştü, base kit
  genel kalmalı. Temada duran `orders.html` / `products.html` bu yüzden boş.
- **Sosyal giriş** (Google/Apple ile giriş) — her projede farklı sağlayıcı ve
  onay süreci; kit'e sabit gelmesi zarar veriyor.
- **Çok kiracılı yapı (multi-tenant)** — mimarinin tamamını değiştirir.


---
---

# BÖLÜM C — Base Kit Boşluk Analizi

> **Arşiv.** Bu bölüm `docs/BOSLUK-ANALIZI.md` dosyasının **tam ve değiştirilmemiş**
> içeriğidir; yalnız başlık seviyeleri bir kademe indirildi ki bu belgenin
> hiyerarşisine otursun. 31 Ağustos 2026 mimari denetimi: on beş bulgu, gerekçeleri ve hangi sırayla kapatılmaları gerektiği. Uzun süre yalnız bir Artifact olarak durdu.
>
> Kaynak dosya yerinde duruyor ve okunmaya devam edebilir.


### Base Kit Boşluk Analizi

**Mimari denetim · 31 Ağustos 2026**
**Denetlenen sürüm:** `1d2e14f`
**Dal:** `feat/laravel-13-upgrade`
**Kapsam:** `app/` · `routes/` · `config/` · `database/migrations/` ·
`resources/views/` · `public/` · `docs/`

Laravel 13 kurumsal başlangıç altyapısının production-ready olma yolunda kalan
boşlukları. Zemin sağlam — bu rapor *neyin eksik olduğunu* değil, **hangi
sırayla kapatılması gerektiğini** anlatıyor.

> **Bu dosya hakkında.** Denetim başta bir Artifact olarak yayımlanmıştı ve
> depoda karşılığı yoktu; 1 Eylül 2026'da buraya alındı. Metin orijinaline
> sadık, tek ekleme **"Bugün"** sütunu ve her bulgunun altındaki *güncel durum*
> notları — denetimden sonra kapanan maddeler işaretlensin diye.
>
> Kardeş belgeler: [`PROJE-DURUMU.md`](PROJE-DURUMU.md) (*ne var*),
> [`YOL-HARITASI.md`](YOL-HARITASI.md) (*ne eksik*),
> [`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md) (*yapıldı denilen çalışıyor mu*).

---

#### Denetim anındaki rakamlar

| Model | Servis | Policy | Migration | Test | Admin rota |
|---|---|---|---|---|---|
| 23 | 33 | 26 | 65 | 966 | 184 |

**15 bulgu** · denetim günü 7'si kapatılmış, 1'i yarısı.

---

#### Zemin: neyin üstüne inşa ediyoruz

Bu, "eksikleri sayılan" bir proje değil. Aşağıdaki alanlar çoğu kurumsal
projeden **daha olgun** ve bulgular bu zemine göre önceliklendirildi — yani
düşük seviyeli hijyen değil, üretim ve uyumluluk katmanı konuşuluyor.

- **Katman disiplini** — Controller → FormRequest → Policy → Service → Model
  zinciri istisnasız uygulanmış.
- **Kural bekçisi testler** — SoftDeletes, build-tool yasağı, enum kullanımı,
  observer cascade reflection ile korunuyor.
- **Upload sertleştirmesi** — uzantı + MIME beyaz listesi, ad temizliği ve
  `uploads/.htaccess` ile çalıştırma reddi.
- **Açık yönlendirme koruması** — `SafeRedirectTarget` altı saldırı vektörünü
  test edilmiş biçimde reddediyor.
- **DB tabanlı yetki matrisi** — izin tek kaynaktan (`PermissionKey`); rol
  yetkisi değiştirmek deploy gerektirmiyor.
- **Çok dilli SEO** — hreflang, x-default, sitemap alternates ve dile ait
  slug'lar eksiksiz.
- **Hosting gerçekçiliği** — pcntl yokluğu kabullenilip kuyruk ve cron buna
  göre kurulmuş, test ile korunuyor.
- **Sorgu bütçesi testleri** — N+1 ve tekrarlı sorgu, kod incelemesiyle değil
  testle engelleniyor.

---

#### Bulgu tablosu

| # | Bulgu | Alan | Denetim günü | Bugün |
|---|---|---|---|---|
| S-01 | Pasife alınan kullanıcı oturumdan düşmüyor | Güvenlik | ✅ `d2975a9` | ✅ |
| S-02 | Proxy güveni tanımsız — rate limit tek kovaya düşüyor | Güvenlik | ✅ `d2975a9` | ✅ |
| S-03 | İşlenmeyen istisna kimseye ulaşmıyor, log sınırsız büyüyor | Operasyon | ✅ `84df590` | ✅ |
| S-04 | Audit trail yalnızca tek modeli izliyor | Uyumluluk | ✅ `5a4bfd8` | ✅ |
| S-05 | **Content-Security-Policy yok** | Güvenlik | ⬜ Yüksek | ✅ *(1 Eyl)* |
| S-06 | `robots.txt` statik ve eski projenin alan adını taşıyor | SEO | ✅ `0a63fac` | ✅ |
| S-07 | Yedek tek diskte duruyor ve geri yükleme yok | Operasyon | 🟡 `a4c54b3` | ✅ |
| S-08 | Kuyruk görünmez: başarısız işler sessizce birikiyor | Operasyon | ✅ `fc9759b` | ✅ |
| S-09 | Çerez rızası alınmadan izleme başlıyor | Uyumluluk | ⬜ Yüksek | ✅ |
| S-10 | Parola politikası zayıf, panel için ikinci faktör yok | Güvenlik | ⬜ Orta | ✅ |
| S-11 | 966 test var, hiçbiri otomatik koşmuyor | Kalite | ✅ `41cdbf8` | ✅ |
| S-12 | **Analitik cache temizliği tüm cache'i siliyor** | Performans | ⬜ Orta | ✅ *(1 Eyl)* |
| S-13 | **Cache anahtarları otuz ayrı yerde elle temizleniyor** | Bakım | ⬜ Orta | ✅ *(1 Eyl)* |
| S-14 | **Ön yüzde çıktı cache'i yok** | Performans | ⬜ Orta | ✅ *(1 Eyl)* |
| S-15 | Site içi arama yok | Ürün | ⬜ Orta | ✅ |

---

#### S-01 — Pasife alınan kullanıcı oturumdan düşmüyor · ✅

**Güvenlik · Erişim kontrolü**

`is_active` yalnızca giriş anında, `AuthService::login()` içinde kontrol
ediliyor. `AdminMiddleware` her istekte `roles()->whereHas('permissions')`
sorgusunu attığı için *izin kaldırma* anında etkili oluyor — ama `is_active`
hiçbir middleware'de okunmuyor.

Sonuç: işten ayrılan ya da güvenlik gerekçesiyle pasife alınan bir yönetici,
mevcut oturumu ile panelde kalmaya devam ediyor. Oturum ömrü 120 dakika, "beni
hatırla" işaretliyse `remember_token` aylarca geçerli. Panelden "pasifleştir"
düğmesine basan yönetici işini bitirdiğini sanıyor.

**Yapıldı.** `EnsureUserIsActive` middleware'i `web` grubuna `SetLocale`'den
sonra eklendi; JSON isteğinde yönlendirme yerine 403 dönüyor. `SessionRevoker`
servisi oturum satırlarını ve `remember_token`'ı düşürüyor — observer'dan,
silmeden ve toplu silmeden çağrılıyor. Yol üzerinde iki kusur çıktı: force
delete sonrası `saveQuietly()` silinen kullanıcıyı geri getiriyordu, ve
`UserFactory` `is_active` üretmediği için modelde alan `null` okunuyordu.
`InactiveUserSessionTest` — 11 test.

---

#### S-02 — Proxy güveni tanımsız · ✅

**Güvenlik · Operasyon**

`bootstrap/app.php` içinde `trustProxies()` çağrısı yok. Cloudflare, nginx
reverse proxy veya yük dengeleyici arkasında `$request->ip()` ziyaretçinin
değil **proxy'nin** adresini döndürür. Üç yerde birden bozuluyor:

1. `throttle:login`, `throttle:contact`, `throttle:register` IP'ye göre kova
   açıyor — tek IP görüldüğü için tüm ziyaretçiler aynı kovayı paylaşır; bir
   kişinin üç başarısız girişi **herkesi** kilitler, kaba kuvvet saldırısı ise
   hiç yavaşlamaz.
2. `page_views.ip_address` ve audit log IP'leri anlamsızlaşır.
3. `$request->secure()` `false` döndüğü için `SecurityHeaders` HSTS başlığını
   hiç basmaz.

**Yapıldı.** `config/trustedproxy.php` eklendi — Laravel'in `TrustProxies`
sınıfı bu anahtarı kendiliğinden okuyor, liste *istek anında*
`TRUSTED_PROXIES`'ten çözülüyor ve varsayılan boş. Güvenilen başlık kümesi
çerçevenin varsayılanından dar tutuldu: `FOR | HOST | PORT | PROTO`.
`TrustedProxyTest` — 12 test, aynı proxy arkasındaki iki ziyaretçinin ayrı hız
sınırı kovasına düştüğü dahil.

---

#### S-03 — İşlenmeyen istisna kimseye ulaşmıyor · ✅

**Operasyon · Gözlemlenebilirlik**

`bootstrap/app.php` içindeki `withExceptions()` bloğu boş. Projede çalışan bir
bildirim kanalı var — `TelegramNotifier` — ama yalnızca yedekleme komutu ve
`NotificationCenter` onu çağırıyor. Canlıda 500 veren bir sayfa hiçbir yere
haber vermiyor; kullanıcı şikâyet edene kadar kimse bilmiyor.

Aynı yerde ikinci sorun: `.env.example` içinde `LOG_STACK=single`. Tek dosya,
rotasyon yok. Paylaşımlı hostingde `laravel.log` zamanla gigabaytlara çıkıp
diski doldurur — ve disk dolduğunda yedekleme de, upload da, oturum yazımı da
durur.

**Yapıldı.** `ExceptionNotifier` hatayı iki kanala birden düşürüyor: Telegram ve
panelin bildirim merkezi. Raporlama kapanışı hiçbir şey döndürmüyor — `false`
dönseydi hatanın loga yazılmasını da durdururdu, bunun testi var. Aynı hata için
10 dakikada bir bildirim (parmak izi: tür + dosya + satır), ve `notify()` baştan
sona `try/catch` içinde: bildirim yolu patlarsa asıl hatanın yerini alamaz.
`LOG_STACK=daily` + `LOG_DAILY_DAYS=14` oldu, ve Sistem Sağlık ekranına log
dizini kontrolü eklendi. `ExceptionNotificationTest` ve `LogHealthCheckTest` —
20 test.

---

#### S-04 — Audit trail yalnızca tek modeli izliyor · ✅

**Uyumluluk · Denetlenebilirlik**

Altyapı hazır ve iyi yazılmış: `audit_logs` tablosu, indeksleri, saklama süresi
temizliği, panel ekranı, hassas alan maskesi (`password`, `remember_token`,
`mail_password`…) hepsi yerinde. Ama `AuditObserver` `AppServiceProvider.php:127`'de
**tek bir modele** bağlanmış: `Setting`.

Yani "kim giriş yaptı", "kim başarısız giriş denedi", "kim hangi rolün iznini
değiştirdi", "kim kullanıcı sildi", "kim yönlendirme ekledi" sorularının
hiçbirinin cevabı yok. ISO 27001 / KVKK denetimlerinde ilk istenen bu.

**Yapıldı.** Üç ayrı yol, çünkü tek gözlemci hepsini göremiyor: `AuditObserver`
artık bir model listesine bağlı (`Setting`, `User`, `Role`, `Redirect`,
`CustomRoute`, `MailTemplate`, `Language`); bir abone giriş, çıkış ve başarısız
denemeyi yazıyor; izin matrisi, kullanıcı rolleri ve toplu silme ilgili
servislerden düşüyor — pivotun modeli yok, toplu işlem de model olayı
doğurmuyor. Şifre iki katmanla korunuyor. İçerik modelleri bilinçli olarak
dışarıda — 90 günlük saklama süresiyle izi kendi gürültüsünde boğarlardı.
`AuditTrailCoverageTest` — 17 test.

---

#### S-05 — Content-Security-Policy yok · ✅ *(1 Eylül 2026'da kapatıldı)*

**Güvenlik · XSS savunması** — denetimde *Yüksek*

`SecurityHeaders` beş başlık basıyor ve doğru olanları seçmiş, ancak **CSP yok**
— XSS'e karşı ikinci savunma hattı olan tek başlık.

Bu, sıradan bir eksiklikten fazlası: panelde `custom_head_code` ayarı ham HTML
olarak `{!! !!}` ile basılıyor ve mail şablonları TinyMCE ile düzenleniyor.
Blade'in kaçışı doğru kullanılmış, ama tek savunma o.

Yan not: `X-XSS-Protection` artık hiçbir güncel tarayıcıda desteklenmiyor; bazı
eski sürümlerde XSS'i kolaylaştırdığı için kaldırılması önerilen bir başlık.

**Öneri (denetim).** Nonce tabanlı CSP: `SecurityHeaders` istek başına bir nonce
üretip container'a koysun, layout'lardaki inline script'ler onu taşısın. Front
ve admin ayrı politika ister — admin'in TinyMCE'si `'unsafe-inline'` stil
gerektirir, front gerektirmez. İlk tur `Content-Security-Policy-Report-Only` ile
çıkılmalı; ihlaller `Log::warning`'e düşen basit bir controller'a toplanabilir.

**Yapıldı.** Nonce tabanlı politika (`ContentSecurityPolicy` + `SecurityHeaders`),
görünüm ağacındaki 39 satır içi betiğe `nonce="{{ csp_nonce() }}"`, ihlal raporu
ucu (`/csp-ihlali`, hız sınırlı ve alan beyaz listeli), panel için ayrı ve biraz
daha geniş politika (TinyMCE'nin `blob:` ihtiyacı). `X-XSS-Protection`
kaldırıldı. Ayrıntı: [`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md) → *Tur 3*.

**Sonradan bulunan ve düzeltilen kusur.** İlk sürüm satır içi *olay
işleyicilerini* (`onclick`, `onchange`, `oninput`) engelliyordu: nitelik değeri
betiğin kendisi olduğu için oraya nonce konulamıyor. Panelde bunlardan iki
yüzden fazla var — süzgeç seçicileri, karakter sayaçları, toplu işlem düğmeleri
— ve hepsi sessizce çalışmaz olmuştu. İhlal yalnız işleyici *tetiklendiğinde*
bildirildiği için sayfa açılışında konsol temiz görünüyordu; kusur, SEO
modülünün kontrol turunda ortaya çıktı.

Çözüm ayrı bir yönerge: `script-src-attr 'unsafe-inline'` yalnız nitelik
işleyicilerini kapsıyor, `<script>` bloklarına enjeksiyon nonce'a bağlı
kalıyor. Taviz dar ve bilinçli; işleyicileri JS dosyalarına taşımak yönergeyi
tamamen kaldırır (yukarıdaki modül önerileri tablosunda).

---

#### S-06 — `robots.txt` statik ve eski projenin alan adını taşıyor · ✅

**SEO · Base kit doğruluğu**

`public/robots.txt` dosyasında `Sitemap: https://orhanbabaninciftligi.com/sitemap.xml`
satırı ve sökülmüş modüllerden kalan `Disallow: /*/siparis`, `/*/sepet` kuralları
duruyor. Bu depodan türeyen **her yeni proje** arama motorlarına başka bir
sitenin sitemap adresini gösteriyor — ve kimse fark etmiyor, çünkü hata vermiyor.

İkinci boyut: dosya statik olduğu için staging kopyası da `Allow: /` diyor.

**Yapıldı.** `RobotsService` listeyi rotaların kendisinden üretiyor. `Sitemap:`
satırı `route('sitemap')`'ten geliyor, `APP_ENV` production değilse gövde
yalnızca `Disallow: /`. Listeye eski dosyada hiç olmayan iki uç eklendi: dil
değiştirici ve bülten çıkış bağlantısı. Statik dosya silindi; `RobotsTest` (14)
geri gelmediğini de bekçilik ediyor.

---

#### S-07 — Yedek tek diskte duruyor ve geri yükleme yok · ✅

**Operasyon · İş sürekliliği**

`BackupService` gecelik ZIP alıyor, `storage/app` altında tutuyor (web erişimine
kapalı — doğru tercih), saklama süresini uyguluyor ve başarısızlıkta Telegram'a
haber veriyor. İki boşluk vardı:

- **Dış kopya yok.** Yedek, yedeklediği veriyle aynı diskte. Disk arızası,
  hosting hesabının askıya alınması veya fidye yazılımı senaryosunda ikisi
  birlikte gidiyor — yani bu bir yedek değil, bir *anlık görüntü*.
- **Geri yükleme yok.** Hiç denenmemiş bir yedek, olmayan yedektir.

**Yapıldı.** Geri yükleme `a4c54b3` ile geldi: doğrula → mevcut durumun yedeğini
al → bakım moduna geç → uygula → çık. Yol üzerinde asıl kusur çıktı:
*veritabanı dökümü alınamadığında yedek yine "başarılı" sayılıyordu.* Dış kopya
sonradan `BackupOffsiteService` ile kapandı (Faz 5.1).

---

#### S-08 — Kuyruk görünmez · ✅

**Operasyon · Gözlemlenebilirlik**

`QueueRunner::drain()` bir iş patladığında `$job->fail($e)` çağırıp sıradakine
geçiyor — doğru davranış. Ama `failed_jobs` tablosu projede **tek bir yerde**
okunuyor: `HealthCheckService.php:162`, o da yalnızca son 24 saatin *sayısını*
alıyor.

Bu, proje için özellikle önemli: tüm mail gönderimi kuyruk üzerinden gidiyor.
"Doğrulama maili gelmedi" şikâyetinin cevabı `failed_jobs.exception` alanında
duruyor ve o alana panelden bakmanın yolu yok.

**Yapıldı.** **Admin → Kuyruk** ekranı: bekleyen iş, *en eski işin yaşı* (cron
çalışıyor mu sorusunun en net cevabı), son 24 saat ve toplam başarısız. Yeniden
dene / sil / listeyi temizle / kuyruğu şimdi işle — hepsi denetim izine düşüyor.
İş adı yükün serileştirilmiş gövdesinden çıkarılıyor. `QueueMonitorTest` — 20 test.

---

#### S-09 — Çerez rızası alınmadan izleme başlıyor · ✅

**Uyumluluk · KVKK / GDPR** — denetimde *Yüksek*

`layouts/app.blade.php` Google Analytics ve GTM parçacıklarını ayar doluysa
**koşulsuz** yüklüyor; buna ek olarak projenin kendi `page_views` takibi ilk
istekten itibaren IP ve oturum kimliği yazıyor. IP maskeleme var ama 90 gün
sonra devreye giriyor — yani veri toplanıyor, sonra anonimleştiriliyor.

KVKK açısından açık rıza ispat yükü veri sorumlusunda.

**Yapıldı.** `ConsentService` + kategori bazlı rıza bandı (5y). Karar verilmeden
hiçbir izleme yüklenmiyor, ispat kaydı `consents` tablosunda.

---

#### S-10 — Parola politikası zayıf, panel için ikinci faktör yok · ✅

**Güvenlik · Kimlik doğrulama**

`RegisterRequest` yalnızca `Password::min(8)` uyguluyor — karakter çeşitliliği
yok, `uncompromised()` yok. Sitenin ziyaretçi üyeleri için bu savunulabilir;
ancak **aynı kural** panele giren yöneticiler için de geçerli ve orada
savunulamaz.

Buna eşlik eden üç eksik: iki aşamalı doğrulama yok, kullanıcının aktif oturum /
cihaz listesi yok, "diğer tüm cihazlardan çık" yok.

**Yapıldı.** TOTP tabanlı 2FA (`TwoFactorService`, rol düzeyinde zorunlu
kılınabilir), cihaz/oturum yönetimi (`/hesabim/cihazlar` + API), "diğer
cihazlardan çık". Faz 1.1 ve 1.2.

---

#### S-11 — 966 test var, hiçbiri otomatik koşmuyor · ✅

**Kalite · Süreç**

Suite bu projenin en güçlü tarafı ve kuralları kendi kendine koruyacak şekilde
yazılmış. Ama `.github/workflows` dizini yok — çalışması birinin elle
`composer test` yazmasına bağlı.

İkinci boşluk statik analiz: PHPStan/Larastan yok. Üçüncüsü bir sinyal sorunu —
`pint --test` yaklaşık 180 dosyada sapma bildiriyor; araç her koşuda kırmızı
döndüğü için *gerçek* bir stil hatası fark edilmez hâle geliyor.

**Yapıldı.** İki işli GitHub Actions: testler **MySQL 8'e karşı**, ayrıca
`pint --test` ve `phpstan analyse`. **İlk koşuda SQLite'ın sakladığı altı hata
çıktı** — en ağırı, iki servisin LIKE koşulunu `ESCAPE '\'` ile yazması:
MySQL'de sözdizimi hatası, yani *arama yapan her ekran üretimde 500 veriyordu.*
`LikeSearch` ile tek yere toplandı. `pint.json` ile sapma 459 dosyadan **sıfıra**
indi. Larastan seviye 1 — temiz geçen en yüksek seviye.

---

#### S-12 — Analitik cache temizliği tüm cache'i siliyor · ✅ *(1 Eylül 2026'da kapatıldı)*

**Performans**

`AnalyticsService::flushCache()` doğrudan `Cache::flush()` çağırıyor. Yorumu
gerekçesini açıkça yazıyor — sürücü tag desteklemeyebilir — ama sonuç, analitik
ekranındaki bir yenilemenin *ayarları, çevirileri, sitemap'i, dil listesini ve
tüm ön yüz içerik cache'ini* birlikte silmesi. `CACHE_STORE=database` olduğu için
yeniden ısınma da bedava değil: ilk ziyaretçiler bütün sorguları sırtlanır.

**Öneri (denetim).** Analitik anahtarları ortak bir önekle yazılıp yalnızca o
önek silinmeli; veritabanı sürücüsünde bu tek bir `DELETE ... WHERE key LIKE`
ifadesi.

**Yapıldı.** `Cache::flush()` yerine önek bazlı temizlik (`CachePurger`):
veritabanı ve Redis'te doğrudan sorgu, dosya sürücüsünde yazarken tutulan
kayıt. Ayrıntı: [`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md) → *Tur 3*.

---

#### S-13 — Cache anahtarları otuz ayrı yerde elle temizleniyor · ✅ *(1 Eylül 2026'da kapatıldı)*

**Bakım · Ölçeklenme**

Cache kullanımı doğru — `Cache::remember` + servis içinde `Cache::forget`. Sorun
sayıda: temizlik çağrıları 30'dan fazla yere dağılmış ve anahtarlar dizge sabiti
(`'sitemap.urls'`, `'sitemap_page.groups'`, `'admin.pages.stats'`…). Bir servis
birden fazla anahtar silmek zorunda ve hangi içeriğin hangi türev cache'i
beslediği kodun içine gömülü.

Bugün çalışıyor. Ama base kit'in amacı üstüne modül eklemek: yeni bir içerik
türü eklendiğinde `sitemap.urls`'i unutmak, sitemap'in bir saat boyunca bayat
kalmasına yol açar — hata vermez, test kırmaz.

**Öneri (denetim).** Anahtarları tek bir `App\Support\CacheKeys` sınıfında
toplamak ve "içerik değişti" olayına tepki veren bir trait tanımlamak.

**Durum.** Bu turda ele alınıyor.

---

#### S-14 — Ön yüzde çıktı cache'i yok · ✅ *(1 Eylül 2026'da kapatıldı)*

**Performans**

Sorgu düzeyinde cache iyi kurulmuş (ayarlar 24 saat, sitemap 1 saat, çeviriler
süresiz) ve sorgu bütçesi testleri N+1'i engelliyor. Ama anonim bir ziyaretçinin
gördüğü her sayfa yine de tam bir render döngüsü: menü, popup, slider, dil
listesi, SEO ayarları her istekte yeniden çözülüyor.

Paylaşımlı hostingde tek büyük performans kazancı burada. İçeriğin ezici
çoğunluğu anonim ziyaretçi için birebir aynı.

**Öneri (denetim).** Oturumu olmayan `GET` istekleri için parça (fragment)
düzeyinde cache — dil ve aktif menü anahtara girmek kaydıyla. Tam sayfa cache'e
gidilecekse geçersiz kılma yüzeyi `CustomRoute` ve `Redirect` middleware'lerini
de kapsamalı.

**Yapıldı.** `@cachedInclude` direktifi ve `FragmentCache`: parça önbellekte
varsa görünüm hiç çizilmiyor. Gezinti (10 KB) önbelleğe alınıyor; alt bilgi
bilinçli olarak alınmıyor — içinde bülten formu ve dolayısıyla CSRF anahtarı
var. Ayrıntı: [`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md) → *Tur 3*.

---

#### S-15 — Site içi arama yok · ✅

**Ürün · Eksik yetenek**

`routes/front.php` içinde arama rotası yok. Blog, sayfa, galeri ve SSS modülleri
dolu ama ziyaretçinin içerikte arama yapma yolu bulunmuyor. Kurumsal bir sitede
bu, iletişim formundan sonra en çok kullanılan ikinci etkileşimdir.

**Yapıldı.** `SearchService` — blog araması ve site geneli arama (5ab). Dil
farkında, sonuç sayfası `noindex`.

---

#### Modül önerileri (denetim günü)

Sorulan modüllerin bir kısmı **zaten vardı** ve iyi durumdaydı: rol/yetkilendirme
(Spatie'ye gerek yok — `PermissionKey` tabanlı kendi matrisi daha az bağımlılıkla
aynı işi yapıyor), 301 yönlendirme yöneticisi, medya/dosya yöneticisi, mail logu,
yedekleme ve sistem sağlığı.

| Modül | Neden gerekli | Efor | Bugün |
|---|---|---|---|
| Denetim izi genişletmesi (S-04) | Kim ne zaman ne değiştirdi sorusu cevapsız | Küçük | ✅ |
| Kuyruk & iş izleyici (S-08) | Mail gönderimi kuyruğa bağlı | Küçük | ✅ |
| Oturum & cihaz yönetimi (S-01, S-10) | Pasifleştirmenin etkili olması için | Küçük | ✅ |
| İki aşamalı doğrulama (TOTP) | Panelin tek kapısı bir parola | Orta | ✅ |
| Çerez rızası yöneticisi (S-09) | KVKK ispat yükü sizde | Orta | ✅ |
| Yedek geri yükleme + dış kopya (S-07) | Denenmemiş yedek, yedek sayılmaz | Orta | ✅ |
| Site içi arama (S-15) | Ziyaretçinin arama yapma yolu yok | Orta | ✅ |
| Raporlama ekranı | `reports.html` hazır bekliyor | Orta | ✅ |
| API katmanı (Sanctum) | `routes/api.php` hiç yok | Orta | ✅ |
| İçerik sürümleme (revisions) | Denetim izi geri döndüremez | Orta | ⬜ |
| SEO denetleyici | Kaydetmeden önce başlık/alt/H1 uyarısı | Orta | ✅ *(1 Eyl)* |
| Dinamik form oluşturucu | Her projede en az bir form isteniyor | Büyük | ⬜ |
| Satır içi olay işleyicilerini JS'e taşımak | CSP'nin `script-src-attr` tavizini kaldırır (219 işleyici, 50 dosya) | Orta | ⬜ |

---

#### Önerilen sıra (denetim günü)

Sıralama bilinçliydi: önce **sessiz yanlış davranan** şeyler, sonra
**göremediğimiz** şeyler, sonra hukuki yükümlülük, en sonra yetenek.

| Tur | İçerik | Durum |
|---|---|---|
| **Tur 1** | S-01, S-02, S-06, S-03 | ✅ Tamamlandı |
| **Tur 2** | S-04, S-08, S-11, S-07 | ✅ Tamamlandı |
| **Tur 3** | S-09, S-05, S-10 | ✅ Tamamlandı |
| **Tur 4** | S-15, S-12/S-13/S-14 | ✅ Tamamlandı |

Denetim günü kapatılanlar: S-01 ve S-02 `d2975a9`, S-06 `0a63fac`,
S-03 `84df590`, S-04 `5a4bfd8`, S-08 `fc9759b`, S-11 `41cdbf8`, S-07'nin geri
yükleme yarısı `a4c54b3`.

Kalan sekiz madde sonraki turlarda kapandı: S-07'nin dış kopyası, S-09, S-10 ve
S-15 yol haritası fazlarında; **S-05, S-12, S-13 ve S-14** ise 1 Eylül 2026'da
([`PROJE-DURUMU-V2.md`](PROJE-DURUMU-V2.md) → *Tur 3*). **On beş bulgunun
tamamı kapalı.**

`docs/PROJE-DURUMU.md` içinde zaten kayıtlı olan eksikler (raporlama ekranı,
içerik listesi, `jenssegers/agent` bakımsızlığı) bu denetimde tekrarlanmadı.


---
---

# BÖLÜM D — v2 Denetimi

> **Arşiv.** Bu bölüm `docs/PROJE-DURUMU-V2.md` dosyasının **tam ve değiştirilmemiş**
> içeriğidir; yalnız başlık seviyeleri bir kademe indirildi ki bu belgenin
> hiyerarşisine otursun. 1 Eylül 2026 denetimi: "yapıldı denilen çalışıyor mu" sorusu. On altı bulgu ve boşluk analizinin kalan dört maddesinin kapanışı.
>
> Kaynak dosya yerinde duruyor ve okunmaya devam edebilir.


### Proje Durumu — v2 Denetimi

**Tarih:** 2026-09-01
**Dal:** `feat/laravel-13-upgrade`
**Başlangıç noktası:** `f1eca0c`
**Kapsam:** üç yüz birden — web, panel (CRM), mobil API

Bu belge bir *denetimin* kaydı. [`PROJE-DURUMU.md`](PROJE-DURUMU.md) *ne var*
sorusunu, [`YOL-HARITASI.md`](YOL-HARITASI.md) *ne eksik*,
[`BOSLUK-ANALIZI.md`](BOSLUK-ANALIZI.md) *hangi sırayla* sorusunu yanıtlıyor.
Buradaki soru üçüncüsü: **yapıldı denilen şeyler gerçekten çalışıyor mu, ve
kimsenin bakmadığı yerde ne birikmiş?**

---

#### Yöntem

Denetim üç aşamada koştu:

1. **Doğrulama.** Var olan 1711 test, kod stili (Pint) ve statik analiz
   (PHPStan) çalıştırıldı — üçü de temiz. Yani dokümanda "bitti" yazan
   maddelerin kod karşılığı gerçekten duruyor.
2. **Arama.** Doğrulama yeşil olduğu için asıl soru şuna dönüştü: *hangi kural
   yazılı ama bekçisiz, hangi modül kodlanmış ama sınanmamış?* Aranan şey kırık
   kod değil, **görünmeyen kod** oldu.
3. **Kapatma.** Bulunan her kusur düzeltildi ve her biri için bir bekçi kuruldu
   — düzeltmenin kendisi değil, bir daha açılmaması aranan sonuçtu.

Bulguların ortak deseni şu çıktı: **kural yazılıydı, bekçi ya yoktu ya da elle
yazılmış bir listeye bakıyordu.** Elle yazılan liste, projenin geri kalanı
büyürken yerinde kalıyor; kural teknik olarak yürürlükte ama pratikte
uygulanmıyor. On altı bulgunun on ikisi bu boşlukta birikmişti.

---

#### Özet tablo

| # | Bulgu | Ağırlık | Durum |
|---|---|---|---|
| 1 | Dışa aktarmada CSV biçimi yoktu | Yüksek | ✅ Kapatıldı |
| 2 | Üç liste ekranında dışa aktarma hiç yoktu | Yüksek | ✅ Kapatıldı |
| 3 | Dışa aktarma modülünün hiçbir testi yoktu | Yüksek | ✅ 81 test |
| 4 | Zamanlanmış raporlarda CSV seçilemiyordu | Orta | ✅ Kapatıldı |
| 5 | Editörün dosya seçicisi boş kurulumda 404 veriyordu | Yüksek | ✅ Kapatıldı |
| 6 | Kuralsız alan bekçisi panelin hiçbir formunu görmüyordu | Yüksek | ✅ Kapatıldı |
| 7 | Bekçinin tarayıcısı nitelikleri yanlış okuyordu | Orta | ✅ Kapatıldı |
| 8 | Satır içi stil yasağının bekçisi yoktu; 13 ihlal birikmişti | Orta | ✅ Kapatıldı |
| 9 | Rol matrisi 12 modül geride kalmıştı | Yüksek | ✅ Kapatıldı |
| 10 | Panel duman testi 26 rotaya bakıyordu (55 ekran var) | Orta | ✅ Kapatıldı |
| 11 | Ön yüzün duman testi hiç yoktu | Orta | ✅ Kapatıldı |
| 12 | Çeviri eşliği yalnız `site.php`'de sınanıyordu | Orta | ✅ Kapatıldı |
| 13 | `lang/tr/validation.php` dokuz kuralı taşımıyordu | Orta | ✅ Kapatıldı |
| 14 | Profil ekranında tarayıcının `alert()` kutusu | Düşük | ✅ Kapatıldı |
| 15 | Çerez rızası kutuları kuralsız ve işaretsizdi | Düşük | ✅ Kapatıldı |
| 16 | Stok config dosyalarında `strict_types` yoktu | Düşük | ✅ Kapatıldı |

**Sonuç:** 1711 → **1834 test** (1827 geçiyor, 7'si gerekçeli olarak atlanıyor),
6303 → **7408 doğrulama**. Pint sıfır sapma, PHPStan sıfır hata.

Bu tablo v2 denetiminin kendi bulgularını sayıyor. Turun sonunda ayrıca 31
Ağustos tarihli boşluk analizinin açık kalan dört bulgusu da kapatıldı —
aşağıda *Tur 3* bölümünde.

---

#### 1. Dışa aktarma — CSV yoktu

**Hata.** Modül Excel (XLSX) ve PDF yazıyordu; CSV yoktu. Bu bir biçim eksiği
gibi görünse de aslında bir *kullanım* eksiği: XLSX ve PDF insanın okuduğu
biçimler, CSV ise verinin başka bir sisteme taşındığı biçim — muhasebe
programına, e-posta aracına, bir betiğe. Kit'ten türeyen her projede er ya da
geç isteniyor ve olmadığında herkes kendi çözümünü yazıyor.

**Çözüm.** `CsvExportService` eklendi; XLSX yazıcısıyla aynı sözleşmeyi
(`ListExport`) okuyor, yani bir liste tanımı üç biçimde birden çalışıyor.

Türkçe Excel'i hedefleyen iki ayar yapılandırmadan geliyor:

- **UTF-8 BOM** — dosyanın başındaki imza olmadan Excel Türkçe harfleri sistemin
  kod sayfasıyla açıyor ve bozuyor.
- **Noktalı virgül ayracı** — ondalık ayracı virgül olan yerel ayarlarda Excel,
  virgülle ayrılmış dosyayı tek sütuna basıyor.

Başka yerel ayara kurulan bir projede üçü de `.env` üzerinden çevrilebiliyor
(`EXPORT_CSV_DELIMITER`, `EXPORT_CSV_DECIMAL_SEPARATOR`, `EXPORT_CSV_BOM`).

**Yol üzerinde bulunan iki şey:**

- Formül enjeksiyonu koruması (`=CMD(...)` gibi bir metnin hücrede formüle
  dönmesi) yalnız XLSX yazıcısında vardı. CSV'de tehlike daha büyük — orada tip
  diye bir şey yok, her hücre metin gidiyor ve açan program ne olduğuna kendi
  karar veriyor. Koruma ortak bir trait'e çıkarıldı, iki yazıcı da aynı kuralı
  uyguluyor.
- Satır tavanı kontrolü denetleyicide `format === Pdf` diye yazılıydı. Tavanı
  olan biçim gerçekten yalnız PDF (mPDF sayfaları belge kapanana kadar bellekte
  tutuyor; XLSX ve CSV akış hâlinde yazılıyor) ama bu bilgi biçimin kendisine
  ait. `ExportFormat::hasRowLimit()` olarak taşındı: yeni bir biçim
  eklendiğinde denetleyiciye dokunmak gerekmiyor.

**Değişen dosyalar**

| Dosya | Ne oldu |
|---|---|
| `app/Services/Export/CsvExportService.php` | yeni — CSV yazıcısı |
| `app/Services/Export/Concerns/GuardsSpreadsheetFormulas.php` | yeni — ortak formül koruması |
| `app/Support/Export/ExportFormat.php` | `Csv` durumu, `hasRowLimit()` |
| `app/Services/Export/ExportService.php` | CSV bağlandı, tavan biçime soruluyor |
| `app/Services/Export/ExcelExportService.php` | formül koruması trait'e devredildi |
| `app/Http/Controllers/Admin/ExportController.php` | biçime duyarlı tavan kontrolü |
| `config/export.php` | CSV ayarları |
| `resources/views/components/export-menu.blade.php` | menüye CSV satırı |

---

#### 2. Üç liste ekranında dışa aktarma hiç yoktu

**Hata.** Panelde 29 liste dışa aktarılabiliyordu, üçü aktarılamıyordu:

- **İçerikler** (`/admin/icerikler`) — blog, sayfa, galeri ve SSS'nin birleşik
  listesi. "Geçen ay ne yayınladık" sorusunun tek ekranlık cevabı, ama dosyaya
  dökülemiyordu.
- **Özel Adresler** (`/admin/custom-routes`) — hangi adresin nereye baktığı.
  Bir siteyi devralan ekibin ilk sorduğu şey ve çoğu zaman panelin dışında
  (yönlendirme planı, SEO denetimi) okunuyor.
- **Başarısız İşler** (`/admin/kuyruk`) — bu listenin kaybı en pahalısı: kayıt
  kalıcı değil, iş yeniden denendiğinde ya da tablo temizlendiğinde siliniyor.
  Dosya, tabloyu boşaltmadan önce elde kalan tek kayıt oluyor.

**Çözüm.** Üç liste tanımı yazıldı ve `config/export.php`'ye kaydedildi;
ekranlarına dışa aktarma menüsü eklendi. İki servise okuma ucu açıldı:

- `ContentListService`: sıralı sorgu `rows()` ve `count()` olarak ayrıldı,
  `paginate()` de artık onları kullanıyor — ekranda görünen ile dosyaya inen
  aynı sorgunun ürünü, zamanla ayrışamazlar.
- `QueueMonitorService`: `countFailed()` ve `eachFailedChunk()`. Satırlar
  ekrandakiyle aynı sunumdan geçiyor, yani dosyada `SendQueuedMailable` değil,
  gerçekte patlayan işin adı duruyor.

**Değişen dosyalar:** `app/Exports/ContentListExport.php`,
`app/Exports/CustomRouteExport.php`, `app/Exports/FailedJobExport.php` (yeni),
`app/Services/ContentListService.php`, `app/Services/QueueMonitorService.php`,
`config/export.php`, üç `index.blade.php`.

---

#### 3. Modülün hiçbir testi yoktu

**Hata.** 1711 testlik bir projede dışa aktarma modülü — 29 liste tanımı, iki
yazıcı, yetki kontrolü, denetim kaydı, satır tavanı — **tek testle bile
kapsanmıyordu.** (Tek istisna raporlar ekranının Excel çıktısıydı.)

Bu, modülün doğası yüzünden özellikle tehlikeli: bir liste tanımındaki hata —
yanlış sütun kapanışı, olmayan bir ilişki, sorgusuz bırakılmış bir liste — ancak
dosya üretilirken patlıyor. Ekranda hiçbir şey görünmüyor; kullanıcı indirmeye
bastığında 500 alıyor.

**Çözüm.** `ListExportTest` — 81 test (74 koşuyor, 7'si arkasında tablo olmayan
listeler için gerekçeli olarak atlanıyor). Sınav tek tek listeler üzerinden değil,
**kayıt defterinin tamamı** üzerinden koşuyor: `config/export.php`'ye yeni bir
liste eklendiği anda o liste de kapsama giriyor, ayrıca test yazmak gerekmiyor.

Kapsanan sorular:

- Kayıtlı 32 listenin her biri, üç biçimde de dosya üretiyor mu?
- Üretilen dosya iddia ettiği biçimde mi? (imza denetimi: `PK` → XLSX,
  `%PDF` → PDF, BOM → CSV)
- Yetkisiz kullanıcı indirebiliyor mu? Oturum açmamış ziyaretçi?
- Ekrandaki süzgeçler dosyaya yansıyor mu? Sayfa numarası süzgeç sanılıyor mu?
- PDF tavanı aşıldığında dosya sessizce kırpılıyor mu, kullanıcı uyarılıyor mu?
- Excel ve CSV aynı tavandan etkileniyor mu? (etkilenmemeli)
- Her indirme denetim kaydına düşüyor mu?
- Ekranların gösterdiği her anahtar kayıt defterinde var mı?
- **Dışa aktarma satır başına sorgu atıyor mu?**

Son madde ayrı bir not hak ediyor. Listelerin en sessiz tehlikesi bu: ekranda
yirmi beş satır varken ilişkiye dokunan bir sütun fark edilmiyor; aynı sütun on
bin satırlık dosyada on bin sorgu açıyor ve istek zaman aşımına düşüyor. Sınav
sayıya değil davranışa bakıyor — kayıt sayısı artınca sorgu sayısı artmamalı.
**25 listede N+1 yok**; kalan yediden altısı arkasında tablo olmayan listeler
(yedekler diskten, sistem durumu canlı ölçümden okuyor), biri de tek bir üst
kayda bağlı.

Ölçüm iki kez yanlış alarm verdi ve ikisi de öğreticiydi:

1. İlk istek ayarları ve dilleri önbelleğe alıyor; o sorgular listeye değil
   kuruluma ait. Ölçüm ısınmış durumdan başlamalı.
2. Kayıt eklemek ilgili önbellekleri düşürüyor (yönlendirme haritası gibi); o
   bir kerelik tazeleme satır sayısına bağlı değil. Kayıtlar eklendikten sonra
   ikinci bir ısıtma gerekiyor.

**Değişen dosya:** `tests/Feature/ListExportTest.php` (yeni, 81 test).

---

#### 4. Zamanlanmış raporlarda CSV seçilemiyordu

**Hata.** Rapor zamanlama formu yalnız Excel ve PDF sunuyordu; `FormRequest`
kuralı da `Rule::in(['excel', 'pdf'])` ile bunu sabitliyordu. İlginç ayrıntı:
panel temasının CSS'inde `.rpr-format-badge.csv` sınıfı **zaten duruyordu** —
tasarım CSV'yi öngörmüş, kod eklememişti.

**Çözüm.** Biçim seçeneğine CSV eklendi, sunucu kuralı genişletildi, rapor
kartlarındaki biçim rozetlerine CSV eklendi (tasarımın hazır sınıfıyla).
Gönderim yolu zaten biçimden bağımsızdı — `ReportScheduleService` dosyayı
`ExportService::store()` ile üretiyor, yani ekranda inen dosya ile postayla
gelen dosya aynı kodun ürünü.

**Değişen dosyalar:** `resources/views/admin/reports/index.blade.php`,
`app/Http/Requests/Admin/StoreReportScheduleRequest.php`.

---

#### 5. Editörün dosya seçicisi boş kurulumda 404 veriyordu

**Hata.** `FileBrowserService::resolve()` yükleme kökünü `realpath()` ile
çözüyor, çözemezse istisna fırlatıyordu; denetleyici bunu 404'e çeviriyordu.
Kök dizin ise henüz hiçbir şey yüklenmemiş taze bir kurulumda var olmayabilir —
git boş dizin taşımıyor ve yol `.env` üzerinden başka bir yere de bakabiliyor.

Sonuç: kit'i klonlayan geliştirici zengin metin editöründe "Görsel seç"e
bastığında hata alıyor, bir arıza sanıyor. Oysa ortada yalnızca boş bir kurulum
var.

Bu kusuru bulan şey, panelin genişletilmiş duman testi oldu (bkz. 10): eski
liste `/admin/dosya-secici` adresini hiç açmıyordu.

**Çözüm.** Kök dizin gerektiğinde açılıyor. Yükleme de aynı köke yazacağı için
bu, sonraki ilk yüklemenin yapacağı işi öne almaktan ibaret.

**Değişen dosya:** `app/Services/FileBrowserService.php`.

---

#### 6–7. Kuralsız alan bekçisi panelin hiçbir formunu görmüyordu

**Hata.** Proje kuralı net: *kullanıcının veri girdiği her alan ya
`data-validation-engine` taşır ya da bilerek boş bırakıldığını söyleyen
`data-fv-ignore` taşır.* Bekçisi de vardı — ama **elle yazılmış on ön yüz
görünümüne** bakıyordu. Panelin doksana yakın formu hiç taranmıyordu ve yeni bir
ekranın kapsama girmesi, o listeye elle satır yazmaya bağlıydı.

**İkinci hata, birincisinden ilginç:** bekçinin etiket tarayıcısı nitelikleri
yanlış okuyordu. İki yerde kırılıyordu:

```html
<textarea placeholder="<iframe src='...'></iframe>" data-validation-engine="...">
<input @checked($tercihler[$tur->value]) data-fv-ignore>
```

Birincisinde `placeholder` değerinin içindeki `>`, ikincisinde
`$tur->value` ifadesindeki ok işareti etiketi erken kapatıyor; ondan sonraki
nitelikler — kuralın kendisi dahil — görünmez oluyordu. Yani bekçi hem kural
taşıyan alanı kuralsız sanabiliyor, hem de kuralsız bir alanı hiç görmeyebiliyordu.

**Çözüm.** `FormFieldsCarryRulesTest` — liste yok, görünüm ağacının tamamı
taranıyor (`admin-theme`, `emails` ve `vendor` gerekçeleriyle dışarıda).
Tarayıcı düzeltildi: nitelik değerleri tırnak içinde okunuyor ve nitelik yerine
geçen Blade direktifleri maskeleniyor. Taramanın kendisi de sınanıyor — iki ayrı
test, iki kırılma biçimini tekrar üretip tarayıcının artık doğru okuduğunu
gösteriyor.

Ön yüz testindeki dar kopya kaldırıldı; yerine yeni bekçiye gönderen bir not
bırakıldı.

**Tarama sonucu:** panelin doksana yakın formunda **tek bir kuralsız alan
yoktu** — kural gerçekten uygulanmış, yalnız kanıtı eksikmiş. Ön yüzde bir
tane çıktı (bkz. 15).

**Değişen dosyalar:** `tests/Feature/FormFieldsCarryRulesTest.php` (yeni),
`tests/Feature/FrontFormInputRulesTest.php`.

---

#### 8. Satır içi stil yasağının bekçisi yoktu

**Hata.** Kural yazılıydı (*inline style yasak, her zaman class kullan*) ama
bekçisi yoktu ve görünümlerde **on üç yerde** satır içi stil birikmişti — biri
iki ayrı ekrana kopyalanmış aynı biçimdi.

Böyle biriken stiller tasarımı tek yerden değiştirilemez hâle getiriyor: aynı
kutunun rengi ekranın birinde değişiyor, ötekinde kalıyor. Bir örnek doğrudan
buydu — bildirim açılır listesinin ölçüleri hem `.nt-dropdown` sınıfında hem de
etiketin üstünde duruyordu.

**Çözüm.** On üç biçim de CSS'e taşındı, **değerler birebir korunarak**. Bootstrap'in
birebir karşılığı olan ikisi (`w-auto`, `object-fit-cover`) hazır sınıfa
bağlandı; kalanı için `styles.css`'e adı anlamlı sınıflar eklendi.

Çalışma anında hesaplanan tek değer — kampanya ilerleme çubuğunun doluluğu —
sabit bir sınıfla anlatılamaz. O, projede zaten kullanılan kalıba çevrildi:
etikete giden şey biçim değil, tek bir CSS değişkeni (`--cmp-progress`); genişliği
stil sayfası okuyor.

**Bekçi.** `InlineStylesAreForbiddenTest`. İki durum kapsam dışı ve ikisi de
kaçınılabilir değil: e-posta gövdeleri (posta istemcileri harici stili atıyor)
ve yalnız CSS değişkeni taşıyan bildirimler. Tek tek istisna da var — panelin
stil sayfasının ulaşamadığı bir `iframe` belgesine yazılan yedek metin — ve
istisna listesinin bayatlamaması ayrıca sınanıyor.

**Değişen dosyalar:** `public/assets/admin/css/styles.css`, on bir görünüm,
`tests/Feature/InlineStylesAreForbiddenTest.php` (yeni).

---

#### 9. Rol matrisi on iki modül geride kalmıştı

**Hata.** Yetki matrisi 24 rota kapsıyordu; panelde 33 modül ekranı var.
Kapsam dışı kalanlar arasında **sistem ayarlarına dokunan ekranlar** vardı:
diller, dil yazıları, kuyruk izleyici, özel adresler. Hiçbirinin rol davranışı
sınanmıyordu — biri yanlışlıkla editöre açılsa kimse görmezdi.

**Çözüm.** Önce eksik on iki modülün gerçek davranışı ölçüldü, sonra matrise
yazıldı. Ölçüm tutarlı çıktı ve mevcut yetki tasarımını doğruladı:

| Alan | Admin | Editör | Moderatör |
|---|---|---|---|
| Yardım (panel içi rehber) | ✅ | ✅ | ✅ |
| İçerik: kategoriler, birleşik liste, dosya seçici, raporlar | ✅ | ✅ | ⛔ |
| Pazarlama: aboneler, kampanyalar | ✅ | ✅ | ⛔ |
| Sistem: diller, dil yazıları, kuyruk, özel adresler | ✅ | ⛔ | ⛔ |

**Bekçi.** Matris elle yazılıyor — hangi rolün neyi göreceği bir iş kararı,
koddan çıkarılamaz. *Kapsamının tam olması* ise koddan çıkarılabilir:
`test_the_matrix_covers_every_module_in_the_panel` panele yeni bir modül
eklendiğinde kırmızı oluyor ve kararın verilmesini zorluyor.

**Değişen dosya:** `tests/Feature/AdminAuthorizationTest.php` (24 → 35 rota,
+1 kapsam bekçisi).

---

#### 10–11. Duman testleri

**Hata.** Panelin duman testi elle yazılmış **26 rotaya** bakıyordu; parametresiz
admin ekranı sayısı **55**. Aradaki fark — kuyruk, raporlar, içerikler, diller,
çeviriler, kampanyalar, yardım, özel adresler, dosya seçici — hiç açılmadan
kalıyordu. Ön yüzün duman testi ise **hiç yoktu**: bir sayfanın açıldığını
doğrulayan tek şey, o sayfaya ait modül testinin varlığıydı.

Bu boşluğun bedeli somut çıktı: 5 numaralı kusur (dosya seçicisi 404) tam olarak
buradan bulundu.

**Çözüm.**

- `AdminSmokeTest` artık rota tablosundan besleniyor: parametresiz her admin GET
  rotası geziliyor, **ayrıca hepsinin oturum açmamış ziyaretçiye kapalı olduğu**
  doğrulanıyor. Bu ikincisi ince bir riski kapatıyor: tek bir rotanın ara katman
  grubunun dışında tanımlanması yetiyor ve o ekran herkese açık kalıyor.
- `FrontSmokeTest` yeni. Herkese açık sayfalar (ana sayfa, blog, galeri,
  iletişim, arama, SSS, besleme, giriş/kayıt akışları, çevrimdışı sayfası,
  `robots.txt`, `sitemap.xml`, manifest, servis çalışanı) ve hesap alanının
  bütün ekranları — hem üyeye açık hem ziyaretçiye kapalı olduğu.

Ayrıca Laravel'in stok `ExampleTest` dosyaları kaldırıldı; biri
`assertTrue(true)` idi, yani hiçbir şey sınamıyordu.

**Değişen dosyalar:** `tests/Feature/AdminSmokeTest.php`,
`tests/Feature/FrontSmokeTest.php` (yeni), iki `ExampleTest.php` (silindi).

---

#### 12–13. Çeviri eşliği ve eksik doğrulama metinleri

**Hata.** İki dilin aynı anahtarları taşıdığını sınayan bekçi vardı ama yalnız
`site.php`'ye bakıyordu. `validation.php` ve `api.php` kapsam dışıydı — ve
`lang/tr/validation.php`, Laravel 13 ile gelen **dokuz kuralı** taşımıyordu:

`any_of`, `array_keys`, `base64`, `doesnt_contain`, `encoding`,
`in_array_keys`, `prohibited_if_accepted`, `prohibited_if_declined`, `custom`

Bu kurallardan biri kullanıldığında Türkçe sayfada mesaj yerine anahtarın
kendisi görünecekti: `validation.base64`.

**Çözüm.** Dokuz karşılık Türkçe dosyaya eklendi. Bekçi iki dilde de bulunan
bütün dosyalara genişletildi; boş değer sınavı da öyle. Yalnız bir dilde olan
dosyalar kasıtlı ve gerekçeleri dosyaların içinde yazılı: `auth.php`,
`passwords.php` ve `pagination.php` Türkçede var çünkü yedek dil Türkçe ve
dosya olmadığında anahtarın kendisi görünüyordu; İngilizcede çerçevenin kendi
dosyaları devreye giriyor.

**Değişen dosyalar:** `lang/tr/validation.php`,
`tests/Feature/InterfaceTranslationTest.php`.

---

#### 14–16. Küçük kusurlar

**Tarayıcının `alert()` kutusu.** Profil ekranında 2 MB üstü avatar seçilince
`alert()` çıkıyordu — panelin kendi kutusu (`AdminModal`) varken ve proje kuralı
bunu yasaklarken. Değiştirildi; `AdminModal` yüklenmemişse işlem yine
durduruluyor, sadece sessizce.
→ `public/assets/admin/js/profile.js`

**Çerez rızası kutuları.** Ne kural ne de "bilerek boş" işareti taşıyorlardı —
6 numaralı bekçinin ön yüzde bulduğu tek gerçek eksik. Seçim kutusu oldukları
için doğrulanacak bir uzunluk yok; gelen değerlerin tanınan kategoriler olduğunu
sunucu söylüyor (`StoreConsentRequest`). `data-fv-ignore` gerekçesiyle eklendi.
→ `resources/views/partials/cookie-consent.blade.php`

**`strict_types`.** Projenin kendi yazdığı her PHP dosyasında vardı; Laravel'in
stok config dosyalarında (10 dosya) yoktu. Eklendi.
→ `config/*.php`

---

#### Denetlenip temiz çıkanlar

Bir denetimin yarısı da bulunmayan şeylerdir. Aşağıdakiler tek tek kontrol
edildi ve kusur çıkmadı:

- **SoftDeletes** — 37 modelin hepsinde.
- **Yetkilendirme kapsamı** — 26 policy var, 11 model policy'siz. Onların
  hepsi ya bir üst modelin policy'sinden (`ContentFile` → içeriğin kendisi,
  `SubscriberList` → `Subscriber::manageLists`) ya da bir Gate'ten
  (`view-reports`, `view-queue`, `view-analytics`) geçiyor. Yetkisiz uç yok.
- **API katmanı** — Sanctum jeton yetkileri (`abilities`/`ability`), uca özel
  hız sınırları (giriş, kayıt, şifre, doğrulama), hesabı kapatılmış kullanıcı
  ve bakım modu için ara katmanlar, JSON zorlaması (hata yanıtları dahil),
  güvenlik başlıkları, CORS, `Accept-Language` ile yanıt dili. 15 API test
  sınıfı, `openapi.json` kendi kendini denetliyor.
- **N+1** — 25 dışa aktarma listesinde satır başına sorgu yok (bkz. 3).
- **Kod stili ve statik analiz** — Pint sıfır sapma, PHPStan (larastan, seviye 1)
  sıfır hata.
- **Yapı taşı yasakları** — build araçları yok (`NoBuildToolchainTest`),
  `Schedule::command()` yok (`ScheduleUsesCallablesTest`), doğrulama sınırları
  şema ile eşleşiyor (`ValidationLimitsMatchTheSchemaTest`).

---

#### Tarayıcıda doğrulanan

Test yeşil olması bir şeyin *çalıştığını* değil, *iddia edilen şeyin
sınandığını* gösterir. Aşağıdakiler ayrıca gerçek tarayıcıda, gerçek oturumla
doğrulandı:

- Üç yeni ekranın dışa aktarma menüsü açılıyor ve üç seçeneği gösteriyor.
- Üç biçim de imzası doğru dosya indiriyor: CSV `EF BB BF` (BOM), XLSX
  `50 4B 03 04` (ZIP), PDF `25 50 44 46`.
- İnen CSV'de Türkçe harfler bozulmuyor, tarihler biçimli, dil kodları büyük
  harf, durumlar çevrilmiş:
  `Tür;Başlık;Dil;Durum;Oluşturulma;Güncelleme` →
  `"Galeri Öğesi";"Çalışma alanı";TR;Yayında;"28.08.2026 21:40";…`
- Kullanıcılar, Raporlar, İçerikler ve Özel Adresler ekranlarının hepsinde üç
  biçim de bağlı.
- 2 MB üstü avatar seçildiğinde `AdminModal` açılıyor, tarayıcı kutusu değil,
  ve girdi temizleniyor.
- Satır içi stilden taşınan biçimler ölçülen değerleriyle aynı (bildirim
  listesi: 380 px / 500 px / auto).
- Çerez rızası kutuları `data-fv-ignore` taşıyor ve bant çalışıyor.

---

#### Tur 3 — Boşluk analizinin kalan dört bulgusu

Bu turun sonunda ortaya çıktı ki denetimin bir yarısı eksikti: 31 Ağustos'ta
yapılan **Base Kit Boşluk Analizi** depoda değil, bir Artifact olarak
duruyordu. Belge [`BOSLUK-ANALIZI.md`](BOSLUK-ANALIZI.md) olarak arşive alındı
ve on beş bulgusu bugünkü koda karşı tek tek kontrol edildi: on biri
kapanmıştı, **dördü hâlâ açıktı.**

Dördü de bu turda kapatıldı. Bu bulgular v2 denetiminin kendi eksenine
girmiyordu — o denetim kural/bekçi boşluklarına bakmıştı, bu dördü ise üretim
ve performans katmanına ait.

##### S-05 — Content-Security-Policy yok *(Yüksek)*

**Hata.** `SecurityHeaders` beş başlık basıyordu ve doğru olanları seçmişti,
ama CSP yoktu — XSS'e karşı ikinci savunma hattı olan tek başlık. Bu, sıradan
bir eksiklikten fazlasıydı: panelde `custom_head_code` ayarı ham HTML olarak
basılıyor ve mail şablonları zengin metin editörüyle düzenleniyor. Blade'in
kaçışı doğru kullanılmış, ama tek savunma oydu.

**Çözüm.** Nonce tabanlı politika. Her istekte bir kerelik anahtar üretiliyor;
sayfadaki satır içi betikler onu taşıyor, saldırganın enjekte ettiği betik
taşıyamıyor ve çalışmıyor.

- `ContentSecurityPolicy` politikayı kuruyor ve nonce'u istek boyunca taşıyor
  (`scoped` bağ: aynı istekte tek anahtar, uzun ömürlü süreçte her istekte
  yeni).
- Görünüm ağacındaki **39 satır içi betiğe** `nonce="{{ csp_nonce() }}"`
  eklendi; bir tanesinin unutulmadığını bekçi sınıyor.
- Panel ile ön yüz **ayrı politika** alıyor: zengin metin editörü `blob:`
  kaynaklı görsel üretiyor ve kendi iskeletini bir iframe'de açıyor; ziyaretçi
  yüzeyine o izinleri vermek kazanç olmadan yüzeyi genişletirdi.
- `style-src` tarafında `'unsafe-inline'` bilinçli olarak duruyor: Bootstrap'in
  konumlandırıcısı açılır menüleri yerleştirirken elemanın `style` niteliğini
  yazıyor. Betik tarafında ise o anahtar hiç yok — orada olsaydı politika
  anlamsız kalırdı ve bunu ayrı bir test bekliyor.
- İhlal raporları `/csp-ihlali` ucuna düşüyor: hız sınırlı, gövde tavanı olan,
  yalnız tanınan alanları loglayan dar bir uç. Rapor gönderen tarayıcı oturum
  çerezi taşımadığı için kimlik istenemiyor; koruma bu üç kapıdan geliyor.
- `X-XSS-Protection` **kaldırıldı** — güncel hiçbir tarayıcı desteklemiyor ve
  bazı eski sürümlerde filtrenin kendisi XSS'i kolaylaştırıyordu.

**Yol üzerinde bulunan.** İlk denemede tarayıcı konsolu ihlal bildirdi ve
kaynağı Laravel Debugbar çıktı — sayfaya kendi betiğini basıyor ve nonce
taşımıyordu. Debugbar `csp-nonce` adlı bir container bağı arıyor; o bağ
sağlandı, hem Debugbar hem aynı sözleşmeyi kullanan başka paketler çözüldü.
Yalnız geliştirme ortamını ilgilendiren bir çakışmaydı ama "CSP çalışmıyor"
izlenimi bırakırdı.

**Test.** `ContentSecurityPolicyTest` — 18 test.

**Değişen dosyalar:** `app/Services/ContentSecurityPolicy.php`,
`app/Http/Controllers/CspReportController.php` (yeni),
`app/Http/Middleware/SecurityHeaders.php`, `config/security.php` (yeni),
`app/Helpers/helpers.php`, `app/Providers/AppServiceProvider.php`,
`routes/web.php`, 27 görünüm.

---

##### S-12 — Analitik cache temizliği tüm cache'i siliyordu

**Hata.** `AnalyticsService::flushCache()` doğrudan `Cache::flush()`
çağırıyordu. Kendi yorumu gerekçesini yazıyordu — sürücü etiket
desteklemeyebilir — ama sonuç, analitik ekranındaki tek bir yenilemenin
ayarları, çevirileri, site haritasını, dil listesini ve bütün ön yüz içerik
önbelleğini birlikte silmesiydi. Varsayılan sürücü veritabanı olduğu için
yeniden ısınmanın bedelini de ilk ziyaretçiler ödüyordu.

İlginç ayrıntı: yorumda *"cache:clear gibi davranmak yerine bilinen prefix'leri
tek tek temizle"* yazıyordu. Niyet yazılmış, uygulanmamıştı.

**Çözüm.** `CachePurger` — önek bazlı temizlik, sürücüye göre:

| Sürücü | Yol | Neden |
|---|---|---|
| Veritabanı | tek `DELETE ... LIKE` | anahtarlar adıyla duruyor |
| Redis | `SCAN` + `DEL` | `KEYS` bütün anahtar uzayını tarar, sunucuyu kilitler |
| Dizi | bellekteki dizinin taranması | test ortamı gerçeğinden ayrılmasın |
| Dosya | yazarken tutulan kayıt | anahtar diskte hash, önek diye bir şey yok |

**Yol üzerinde bulunan.** İlk yazımda LIKE kalıbını kendi kaçış metodumla
kurmuştum ve `LikeSearchIsPortableTest` onu yakaladı — kaçış karakteri ters
bölüydü, yani MySQL'de tam olarak S-11'de bulunan sözdizimi hatasını doğuran
biçim. `LikeSearch::prefix()` + `LikeSearch::clause()` kullanıldı; bekçi kendi
işini yaptı.

---

##### S-13 — Cache anahtarları otuz ayrı yerde elle temizleniyordu

**Hata.** Temizlik çağrıları 30'dan fazla yere dağılmıştı ve anahtarlar dizge
sabitiydi (`'sitemap.urls'`, `'admin.pages.stats'`…). Hangi içeriğin hangi
türev önbelleği beslediği kodun içine gömülüydü: yeni bir içerik türü
eklendiğinde `sitemap.urls`'i unutmak, site haritasının bir saat bayat
kalmasına yol açıyordu — hata vermeden, testi kırmadan.

**Çözüm.** `App\Support\CacheKeys` — bütün anahtarlar ve önekler tek yerde.
**52 çağrı, 19 dosyada** sabite bağlandı ve dizge olarak yazılmış anahtar
kalmadığını bir bekçi sınıyor. `contentKeys()` ise "içerik değişti" sinyalini
tek yerde topluyor: yeni bir tür eklendiğinde neyin düşeceği orada
güncelleniyor, çağıran yerler değil.

İkinci bekçi daha ince bir tuzağı kapatıyor: önek taşıyan anahtarlar doğrudan
`Cache::put()` ile yazılırsa dosya sürücüsünde kayda girmiyor ve **hiçbir zaman
temizlenmiyor**. O anahtarların tek doğru yazma yolu
`CachePurger::rememberWithin()`, ve bunu bir test zorluyor.

---

##### S-14 — Ön yüzde çıktı cache'i yoktu

**Hata.** Sorgu düzeyinde önbellek iyi kurulmuştu ama anonim bir ziyaretçinin
gördüğü her sayfa yine tam bir çizim döngüsüydü: menü ağacı, alt bilgi
sütunları, dil listesi her istekte yeniden kuruluyordu. Paylaşımlı hostingde en
büyük kazanç burada.

**Çözüm.** `@cachedInclude` direktifi ve `FragmentCache`. `@include` ile aynı
yerde durur, aynı şekilde çağrılır; farkı, parça önbellekte varsa görünümün
**hiç çizilmemesi** — kazanç buradan geliyor.

Üç kapı parçanın saklanmasını engelliyor:

1. **Oturum açmış kullanıcı** — kendi adını taşıyan bir menü sonraki
   ziyaretçiye gösterilemez.
2. **GET olmayan istek** — form gönderiminden sonra çizilen sayfa o isteğe
   özgü.
3. **Kişiye özel iz taşıyan çıktı** — CSRF anahtarı ya da CSP nonce'u içeren
   bir parça saklanırsa iki hata birden doğar: başkasının anahtarını taşıyan
   form reddedilir, bayat nonce betiği çalıştırılamaz hâle getirir.

**Yol üzerinde bulunan.** İlk hedef alt bilgiydi — her sayfada aynı, menü
ağacı geziyor, iyi bir aday. Üçüncü kapı onu reddetti: alt bilgi bülten formunu
içeriyor, yani CSRF anahtarı taşıyor. Koruma çalıştı ve karar değişti: **gezinti
önbelleğe alınıyor** (10 KB, misafirde temiz), alt bilgi bilinçli olarak
alınmıyor. Gerekçe hem görünümde hem testte yazılı — biri ileride alt bilgiyi
önbelleğe alırsa bekçi kırılıyor.

Menü ya da ayar değişince çizilmiş parçalar düşüyor; ziyaretçi bir saat boyunca
eski bağlantıları görmüyor.

**Test.** `CacheHygieneTest` — 17 test (S-12, S-13 ve S-14 birlikte).

**Değişen dosyalar:** `app/Support/CacheKeys.php`, `app/Services/CachePurger.php`,
`app/Services/FragmentCache.php` (yeni), `app/Services/AnalyticsService.php`,
`app/Services/MenuService.php`, `app/Models/Setting.php`, `config/cache.php`,
`resources/views/layouts/app.blade.php`, 19 servis/observer.

---

#### Açık kalan iki madde

İkisi de bu denetimden önce de bilerek açıktı; durumları değişmedi.

- **Panelden push bildirim gönderme ekranı.** Sunucu tarafı hazır (jeton kaydı,
  sağlayıcıdan bağımsız gönderim servisi, ölü jetonun düşmesi). Admin temada bu
  ekranın tasarımı yok ve tasarımda olmayan bir ekranı uydurmak proje kuralına
  aykırı. Tasarım geldiğinde ya da onay verildiğinde yapılacak.
- **`session.serialization = json`.** Çevirmek o anda açık olan bütün oturumları
  düşürüyor; çalışan bir kurulumda bu bakım penceresi gerektiren bir karar, kod
  değil zamanlama meselesi.

---

#### Ders

Bu denetimin tek cümlelik özeti şu: **kural yazmak yetmiyor, kuralın bekçisi
olması gerekiyor — ve bekçinin kapsamı elle yazılmış bir listeye bağlıysa o
bekçi zamanla kör oluyor.**

Bulunan on altı kusurun on ikisi bu desende. Hiçbiri kodun yanlış yazılmasından
doğmadı; hepsi projenin bir yerinin büyürken bekçisinin yerinde kalmasından
doğdu. Bu turda kurulan bekçilerin ortak özelliği de bu: hiçbiri elle yazılmış
liste kullanmıyor. Rota tablosundan, görünüm ağacından, kayıt defterinden,
dil dizininden besleniyorlar. Bir sonraki ekran eklendiğinde kapsama kendiliğinden
giriyor; girmemesi gereken şey varsa gerekçesi yazılı bir istisna listesine
konuyor ve o listenin bayatlamadığı ayrıca sınanıyor.
