# Base Kit Boşluk Analizi

> **Not.** Bu belge artık [`PROJE-KAYDI.md`](PROJE-KAYDI.md) içinde de
> bulunuyor — dört durum belgesinin tek dosyada toplandığı, güncel durum
> tablosunu ve kalan iş planını taşıyan kayıt. Bu dosya kaynak olarak yerinde
> duruyor ve içeriği değişmedi.

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

## Denetim anındaki rakamlar

| Model | Servis | Policy | Migration | Test | Admin rota |
|---|---|---|---|---|---|
| 23 | 33 | 26 | 65 | 966 | 184 |

**15 bulgu** · denetim günü 7'si kapatılmış, 1'i yarısı.

---

## Zemin: neyin üstüne inşa ediyoruz

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

## Bulgu tablosu

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

## S-01 — Pasife alınan kullanıcı oturumdan düşmüyor · ✅

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

## S-02 — Proxy güveni tanımsız · ✅

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

## S-03 — İşlenmeyen istisna kimseye ulaşmıyor · ✅

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

## S-04 — Audit trail yalnızca tek modeli izliyor · ✅

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

## S-05 — Content-Security-Policy yok · ✅ *(1 Eylül 2026'da kapatıldı)*

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

## S-06 — `robots.txt` statik ve eski projenin alan adını taşıyor · ✅

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

## S-07 — Yedek tek diskte duruyor ve geri yükleme yok · ✅

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

## S-08 — Kuyruk görünmez · ✅

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

## S-09 — Çerez rızası alınmadan izleme başlıyor · ✅

**Uyumluluk · KVKK / GDPR** — denetimde *Yüksek*

`layouts/app.blade.php` Google Analytics ve GTM parçacıklarını ayar doluysa
**koşulsuz** yüklüyor; buna ek olarak projenin kendi `page_views` takibi ilk
istekten itibaren IP ve oturum kimliği yazıyor. IP maskeleme var ama 90 gün
sonra devreye giriyor — yani veri toplanıyor, sonra anonimleştiriliyor.

KVKK açısından açık rıza ispat yükü veri sorumlusunda.

**Yapıldı.** `ConsentService` + kategori bazlı rıza bandı (5y). Karar verilmeden
hiçbir izleme yüklenmiyor, ispat kaydı `consents` tablosunda.

---

## S-10 — Parola politikası zayıf, panel için ikinci faktör yok · ✅

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

## S-11 — 966 test var, hiçbiri otomatik koşmuyor · ✅

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

## S-12 — Analitik cache temizliği tüm cache'i siliyor · ✅ *(1 Eylül 2026'da kapatıldı)*

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

## S-13 — Cache anahtarları otuz ayrı yerde elle temizleniyor · ✅ *(1 Eylül 2026'da kapatıldı)*

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

## S-14 — Ön yüzde çıktı cache'i yok · ✅ *(1 Eylül 2026'da kapatıldı)*

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

## S-15 — Site içi arama yok · ✅

**Ürün · Eksik yetenek**

`routes/front.php` içinde arama rotası yok. Blog, sayfa, galeri ve SSS modülleri
dolu ama ziyaretçinin içerikte arama yapma yolu bulunmuyor. Kurumsal bir sitede
bu, iletişim formundan sonra en çok kullanılan ikinci etkileşimdir.

**Yapıldı.** `SearchService` — blog araması ve site geneli arama (5ab). Dil
farkında, sonuç sayfası `noindex`.

---

## Modül önerileri (denetim günü)

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

## Önerilen sıra (denetim günü)

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
