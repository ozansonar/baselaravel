# Proje Durumu

**Son güncelleme:** 2026-08-31 (yol haritasının beş fazı tamamlandıktan sonra)
**Branch:** `feat/laravel-13-upgrade` — `main`'e göre 36 commit önde
**Kalan iş listesi:** [`YOL-HARITASI.md`](YOL-HARITASI.md)
**Stack:** PHP 8.4 · Laravel 13.26.1 · Blade · MySQL 8 · Bootstrap 5.3.8 (self-hosted) · Vanilla JS

---

## 1. Proje Nedir?

Yeniden kullanılabilir bir **kurumsal site + admin panel base kit**'i. Projeye özgü
modüller (ürün, sipariş, e-ticaret) `ab57deb` commit'inde sökülüp genel altyapı
bırakıldı. Amaç: yeni bir kurumsal proje başlarken bu iskeleti klonlayıp üstüne
inşa etmek.

Build tool yok — Vite/npm/Node kullanılmıyor, tüm vendor kütüphaneleri
`public/assets/vendor/` altında hazır dosya olarak duruyor.

### Rakamlar

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

## 2. Mimari

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

### Öne çıkan tasarım kararları

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

## 3. Mevcut Modüller

### İçerik yönetimi
| Modül | Admin | Front | Not |
|---|---|---|---|
| Sayfalar | ✅ CRUD + restore | ✅ `/{slug}` | Başlık/içerik/görsel/SEO — dil sekmeli |
| Blog | ✅ Yazı + kategori + yorum moderasyonu | ✅ liste/kategori/detay | RSS feed dahil |
| Galeri | ✅ Öğe + kategori | ✅ `/galeri` | Görsel + video türü |
| SSS | ✅ CRUD | ✅ `/sikca-sorulan-sorular` | |
| Slider | ✅ CRUD | ✅ anasayfa | |
| Popup/Modal | ✅ CRUD | ✅ sayfa bazlı | Tarih aralığı + sayfa hedefleme |
| Menü | ✅ Drag-drop sıralama | ✅ navbar | Sortable.js, konum bazlı (header) |

### Sistem
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

### Front kullanıcı alanı (`/hesabim`)
Sadece **dashboard + profil düzenleme** var. Şifre değiştirme, e-posta doğrulama,
adres/tercih yönetimi yok.

### Zamanlanmış görevler (`routes/console.php`)
- Her dakika: queue worker (manuel pop/fire)
- 02:00: analytics günlük agregasyon
- 03:00: IP anonimleştirme (KVKK, 90 gün) + gecelik backup
- Haftalık: eski page_views temizliği (365 gün), audit log temizliği (90 gün)

---

## 4. Eski Proje Kalıntıları — ✅ Temizlendi

`ab57deb` refactor'unda ürün/sipariş modülü sökülürken geride kalan referanslar
temizlendi. Aşağıdaki tablo ne yapıldığını kayıt altına alır.

### Hoş geldin e-postası

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

### Diğer temizlenenler

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

### ⬜ Hâlâ duran ölü kod (sipariş/ürünle ilgisiz)

Bunlar ayrı bir temizlik turu ister:

- **`app/Enums/UserRole.php`** — kod tabanında **0 referans**. Roller `roles`
  tablosu + `Role` modeli üzerinden yönetiliyor.
- **`resources/views/vendor/pagination/custom.blade.php`** — hiçbir yerden
  referans verilmiyor; sayfalama `pagination::bootstrap-5` kullanıyor.
- **`.gitignore`** — `/storage/app/google/*.json` kuralı duruyor ama dizin yok.

---

## 5. Yetkilendirme — ✅ Kapatıldı

Daha önce 26 admin controller'ın 13'ü `authorize()` çağırmıyordu; tek koruma
`AdminMiddleware`'di ve o da `admin`, `editor`, `moderator` rollerinin üçünü
birden içeri alıyordu. Yani bir **editör** veritabanı yedeğini indirebiliyor,
şifre sıfırlama e-postalarının gövdesini okuyabiliyordu.

### Rol matrisi (artık uygulanıyor)

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

### Yapılanlar

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

### Doğrulama

`tests/Feature/AdminAuthorizationTest` — 7 test:

- 21 rotanın üç rol için beklenen durum kodu (200/403) matrisi
- Editör yedek indiremiyor
- Editör mail log gövdesi okuyamıyor (şifre sıfırlama linki içeren gerçek kayıtla)
- Editör ve moderatör sidebar'ında yasak linkler görünmüyor
- Admin sidebar'ında her şey görünmeye devam ediyor
- Panel rolü olmayan kullanıcı 403 alıyor
- Moderatör yorum onaylayabiliyor ama silemiyor

---

## 5c. Açık Yönlendirme — ✅ Kapatıldı

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

## 5d. SoftDeletes — ✅ Her Modelde

CLAUDE.md "SoftDeletes → HER MODELDE ZORUNLU" diyordu ama 3 model dışarıdaydı:
`AdminNotification`, `AnalyticsDailyStat`, `AuditLog`. Üçüne de eklendi;
**23 modelin tamamı** artık SoftDeletes kullanıyor.

`2026_08_25_130000_add_soft_deletes_to_log_tables` migration'ı `deleted_at`
kolonlarını ekliyor. `audit_logs` ve `admin_notifications` her panel isteğinde
sorgulandığı için trait'in eklediği `deleted_at is null` koşuluna ayrı index
verildi. `up()` ve `down()` ayrı ayrı test edildi.

### Bu değişikliğin bozacağı 3 yer önceden düzeltildi

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

### Doğrulama

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

## 5e. Çok Dilli Yapı — ✅ Kuruldu

Site birden fazla dilde yayınlanabiliyor. Diller panelden yönetiliyor, içerik
dil başına ayrı satır olarak tutuluyor ve aynı içeriğin farklı dillerdeki
sürümleri ortak bir `lang_group_id` ile birbirine bağlı.

### Diller

`languages` tablosu panelden yönetiliyor; yeni dil eklemek deploy gerektirmiyor.
Kurulumla Türkçe (varsayılan) ve İngilizce aktif, Almanca/Fransızca/İtalyanca
pasif hazır geliyor.

`LanguageService` **tam olarak bir varsayılan dil** kuralının sahibi:

- Varsayılanı taşımak öncekini tek işlemde temizliyor, ikinci varsayılan oluşamıyor
- Varsayılan dil pasife alınamıyor ve silinemiyor
- Pasif bir dil varsayılan yapılırsa otomatik aktifleşiyor
- Sistemdeki ilk dil zorunlu olarak varsayılan oluyor

### Ziyaretçi hangi dili görüyor

1. Seçiciden seçtiği dil (oturumda)
2. `Accept-Language` başlığından en iyi eşleşme — q değerleri dikkate alınıyor,
   bölgesel varyant taban dile eşleniyor (`de-AT` → `de`)
3. Varsayılan dil

Yalnızca aktif diller sayılıyor. Seçici sağ üstte, aktif dil bayrak ve kodla
gösteriliyor; tek dil aktifse hiç render edilmiyor. `<html lang>`, `hreflang`
etiketleri ve `og:locale` aktif dilden geliyor.

### İçerik nasıl saklanıyor

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

### Admin formları

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

### Cache

Ön yüz sorguları dile göre cache'leniyor. Anahtarlar dil içermeseydi ilk
ziyaretçinin dili, süre dolana kadar herkese servis edilirdi. `LocalizedCache`
trait'i anahtarları dile göre üretiyor ve içerik değişince tüm dillerin
anahtarını temizliyor.

### Yol üzerinde bulunan hata

`resources/views/pages/show.blade.php` içinde
`@section('meta_description', $page->meta_description ?? $page->excerpt)` vardı.
İkisi de boş olduğunda Blade `@section('x', null)` çağrısını blok formu sanıp
`ob_start()` açıyor ve kapanış hiç gelmiyor. Yani meta açıklaması ve özeti
olmayan **her sayfa görüntülemesi bir çıktı tamponu sızdırıyordu**. PHPUnit'in
"risky" işareti sayesinde yakalandı, bölüm artık yalnızca içerik varsa
tanımlanıyor.

### Testler

`LanguageManagementTest` (12), `ContentTranslationTest` (13),
`LocaleResolutionTest` (13), `TranslatedPageFormTest` (13),
`TranslatedContentFormsTest` (19), `FrontLocaleContentTest` (17).

---

## 5f. Arayüz Çevirisi — ✅ Tamamlandı

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

## 5g. Çok Dilli Navigasyon — ✅ Tamamlandı

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

## 6. Kalan Yapılacak İşler

[`YOL-HARITASI.md`](YOL-HARITASI.md)'nin beş fazı da tamamlandı. Geriye
bilerek ertelenmiş iki madde ve bir gözlem kaldı.

### Üç yüzün karşılaştırması

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

### ⬜ Bilerek ertelenenler

- **Panelden push bildirim gönderme ekranı.** Sunucu tarafı hazır (jeton
  kaydı, sağlayıcıdan bağımsız gönderim, ölü jetonun düşmesi). Admin temada bu
  ekranın tasarımı yok — `notifications.html` yalnız tercih anahtarları
  içeriyor — ve tasarımda olmayan bir ekranı uydurmak proje kuralına aykırı.
  Tasarım geldiğinde ya da onay verildiğinde yapılacak.
- **`session.serialization = json`.** Çevirmek o anda açık olan bütün
  oturumları düşürüyor; çalışan bir kurulumda bu bakım penceresi gerektiren
  bir karar, kod değil zamanlama meselesi. `cache.serializable_classes` ise
  yapıldı (bkz. 5z).

### 🔍 MySQL doğrulaması

Bu turun dokuz migration'ı ve bütün suite MySQL 8'e karşı da koşuldu (yerel
`lb_migtest`). Dört senaryo geçildi: sıfırdan kurulum, mevcut veriyle göç,
`down()` gidiş-dönüşü ve tohumlama.

Yol üzerinde **SQLite'ın sakladığı bir kusur** çıktı: `LanguageService`
varsayılan dilin yokluğunu istek boyunca hatırlıyordu; bir kez null çözülünce
varsayılan dil `config('app.locale')` değerine düşüyor ve dile duyarlı bütün
sorgular yanlış dile bakıyordu. API'de yayında olan bir yazının yorum ucu 404
dönüyordu. Düzeltildi — yalnız gerçekten bulunan dil hatırlanıyor.

### 👀 İzlemede

- Bir koşuda `ModelFactoriesTest`'te tek seferlik bir hata görüldü; ardından
  dört ayrı koşuda tekrarlanmadı. Sebebi bulunamadı, kayda geçirildi.

### ✅ Bu turda kapananlar

Faz 1 (hesap ve kimlik), Faz 2 (mobil web), Faz 3 (panel ekranları),
Faz 4 (API olgunluğu), Faz 5 (dayanıklılık) — ayrıntılar bölüm 5t–5z ve
yol haritasında.

---

## 5h. Mail ve Upload Yolları — ✅ Test Edildi ve Üç Kusur Kapatıldı

Diske ve SMTP'ye dokunan yollar kodu okuyarak doğrulanamıyordu. Test yazılırken
üçü de sessizce çalışan üç kusur çıktı.

### 1. Upload kökü iki farklı yerden okunuyordu

Yazma `config('uploads.path')` kullanıyordu ama **okuma altı yerde
`public_path('uploads')` sabitliyordu** — `UploadService::url()`,
`srcset()`, `getOriginalWidth()`, `BaseMail` (mail logosu), `BackupService`,
`HealthCheckService` ve `FileManagerService`.

Üretimde ikisi aynı klasöre denk geldiği için görünmüyordu, ama upload yolu
yapılandırıldığı anda: her varyant araması ışınlanıp boşa düşüyor ve **her görsel
sessizce tam boy orijinaline geri dönüyordu**; yedekleme boş klasörü yedekliyor,
sağlık kontrolü yanlış klasörü raporluyordu.

`UploadService::basePath()` tek kaynak oldu, yedi çağrı yeri ona bağlandı.

### 2. `contact_reply` şablonu hiç seed edilmemişti

`ContactMessageReplyMail::templateKey()` her zaman `'contact_reply'` döndürüyordu
ve `MailTemplateService` bunun varsayılanını biliyordu, ama **veritabanına hiç
satır eklenmemişti**. Sonuç: Mail Şablonları ekranı bu şablonu hiç listelemiyordu,
iletişim mesajına panelden verilen yanıt sessizce Blade view'ına düşüyordu —
yani yöneticinin en çok kendi cümleleriyle yazmak isteyeceği mail, düzenleyemediği
tek mail'di. `2026_08_25_210000` migration'ı ile seed edildi.

### 3. Şablon drift'i (yanlış alarm, doğrulandı)

`resetToDefault()` ile migration seed'i arasında dört şablonda fark vardı, ama
normalize edilince farkın **tamamen biçimsel** olduğu görüldü (girinti ve etiket
içi boşluk). İçerik regresyonu yok. Test bu yüzden boşluğa duyarsız karşılaştırma
yapıyor — biçim değil, kullanıcının okuduğu kelimeler korunuyor mu diye bakıyor.

### Bekçiler

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

## 5i. Toplu Mail (Kampanyalar) — ✅ Kuruldu

Üyelere, bülten listesine, Excel'den yüklenen veya elle girilen adreslere toplu
mail gönderimi. Cron ile arka planda, saatlik limite göre yayarak.

### Gönderim motoru

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

### Akış

Form asla doğrudan göndermiyor: taslak kaydediliyor, **onay ekranı** gerçek
alıcı sayısını, alıcılardan örneği, cron'un ne zaman çalışacağını ve tahmini
bitişi gösteriyor, gönderim ancak açık onaydan sonra başlıyor.

### Görseller

**CID olarak gömülüyor**, bağlantı olarak değil. Mail programlarının çoğu uzak
görselleri varsayılan engelliyor; bağlantılı görsel mail iletildiğinde veya
çevrimdışı okunduğunda tamamen kayboluyor. Site dışındaki görseller olduğu gibi
bırakılıyor — gönderim döngüsünden üçüncü parti URL'e istek atılmıyor.

### Excel

`openspout/openspout` kullanılıyor (akış tabanlı; tüm sayfayı belleğe almıyor,
paylaşımlı hostingte on binlerce satır güvenli). Başlık satırı isimle
eşleştiriliyor, başlık yoksa adres sütunu bulunuyor. Türkçe Excel'in noktalı
virgüllü CSV'si ve BOM'u destekleniyor. Panelde örnek şablon indirme var.

### Yol üzerinde bulunan üç kusur

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

### Abonelikten çıkma

Her mail alıcıya özel çıkış bağlantısı taşıyor (gövde + `List-Unsubscribe`
başlığı). Elle girilen ve Excel'den yüklenen alıcılar da kendi anahtarını
alıyor — ilk kurulumda yalnızca abonelerde vardı, yani listede olmayan kişinin
çıkış yolu yoktu. Çıkan adres `subscribers` tablosuna engelleme kaydı olarak
yazılıyor ve sonraki kampanyalara dahil edilmiyor.

### Testler

`CampaignDispatchTest` (28), `CampaignPanelTest` (25),
`CampaignMailContentTest` (17).

---

## 5j. Shared Hosting Uyumu — ✅ Kritik Hata Düzeltildi

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

### Yapılan

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

### Doküman

`cron-rules.md` kök dizinden `docs/SHARED-HOSTING.md`'ye taşındı ve genişletildi:
mevcut görev takvimi, cron'un çalıştığını doğrulama, deploy sonrası kontrol
listesi, mail ve upload kısıtlamaları. CLAUDE.md'ye kırmızı çizgi olarak,
README'ye cron bölümüne bağlandı.

### Bekçi

`ScheduleUsesCallablesTest` (11): hiçbir görev `Schedule::command()` ile
tanımlanmamış, `runInBackground()` kullanılmamış, her görevin adı var, beklenen
yedi görev kayıtlı, mail gönderim aralığı `CampaignDispatcher::RUN_INTERVAL_MINUTES`
ile uyumlu. Yasak çağrı kontrolü kaynak kodun token'larından yapılıyor —
dosya bu çağrıları neden yasak olduklarını anlatmak için zaten adıyla anıyor.

---

## 5k. Diller Ekranı — ✅ Eksik Tamamlandı

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

### Yol üzerinde bulunan hata

`$request->boolean('is_active', true)` işaretlenmemiş kutuyu da `true`
yapıyordu — işaretsiz checkbox istekte hiç yer almadığı için varsayılan devreye
giriyor ve **hiçbir dil formdan pasife alınamıyordu.** Varsayılan `false` oldu.

### Testler

`LanguagePanelTest` (21): ekran listeleme, çeviri dosyası eksikliği uyarısı,
ekleme (kod doğrulama, büyük harf normalizasyonu, tekrar reddi), güncelleme,
yayına alma/kaldırma, varsayılanın kapatılamaması, dört ardışık varsayılan
değişikliğinden sonra hâlâ tek varsayılan olması, silme kısıtları, yetki
ayrımı, değişikliğin ön yüz dil seçicisine ve `hreflang` etiketlerine yansıması.

---

## 5l. Dil Yazıları Ekranı — ✅ Kuruldu

Arayüz metinleri yalnızca `lang/` dosyalarındaydı; değiştirmek için kod
düzenlemek gerekiyordu. **Admin → Dil Yazıları** ekranı eklendi: 231 metin,
dile göre sekmeler, bölümlere ayrılmış form, anlık arama.

### Neden dosyaya değil veritabanına yazıyor

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

### Nasıl çalışıyor

`DatabaseOverrideLoader`, Laravel'in dosya yükleyicisini sarıyor. Her `__()`
çağrısı ve her Blade `@lang` olduğu gibi kalıyor — tek satır view değişmedi.

Ekrandaki anahtar listesi **varsayılan dilin dosyasından** okunuyor, yani kodda
yeni bir metin eklendiğinde panelde kendiliğinden beliriyor.

Varsayılana eşit değer override olarak saklanmıyor, siliniyor: aksi hâlde metin
donar ve ileride dosyadaki varsayılan değişse bile siteye ulaşmazdı.

### Yol üzerinde bulunan üç sorun

1. **`TranslationService` singleton değildi.** Yükleyici kendi örneğini tutuyor
   ve istek içi hafızası vardı; ikinci bir örnek kaydederken kendi hafızasını
   temizlerken yükleyici bayat değeri sunmaya devam ediyordu.
2. **Kaydetme mesajı yanıltıcıydı:** "228 metin varsayılana döndü" diyordu, oysa
   hiçbir şey geri alınmamış, sadece dokunulmamış alanlar varsayılana eşitti.
   Artık yalnızca gerçekten geri alınanlar sayılıyor.
3. **`Schema::hasTable()` her soğuk yüklemede fazladan sorgu atıyordu** — yalnızca
   ilk migration öncesi var olan bir durumu korumak için, sonsuza kadar. Zaten
   var olan try/catch bunu bedelsiz kapsıyor.

### Testler

`TranslationOverrideTest` (21): çözümleme, dil kapsamı, yer tutucuların
korunması, varsayılana eşit değerin saklanmaması, boş değerin varsayılana
dönmesi, tanımsız anahtarın yazılamaması, sayaçların yalnızca gerçek
değişikliği bildirmesi, ısınmış sayfanın sıfır sorgu atması, panel akışı ve
yetki ayrımı.

---

## 5m. Bölgesel Ayarlar Temizliği — ✅ İki Kusur Kapatıldı

Ayarlar → Bölgesel ekranı incelenirken iki sorun çıktı.

### 1. Dil alanı hiçbir işe yaramıyordu

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

### 2. Saat dilimi cron'da uygulanmıyordu

Saat dilimi `SetLocale` **middleware**'inde uygulanıyordu — yani yalnızca web
isteklerinde. Scheduler konsolda çalışır ve middleware oraya hiç uğramaz.

Sonuç: yönetici saat dilimini değiştirdiğinde web istekleri yeni saat dilimini,
cron'un yazdığı her şey (yedek dosya adları, kampanya `sent_at` değerleri,
analitik toplama) `config` varsayılanını kullanıyordu. **Aynı kolonlara iki
farklı saat diliminde zaman damgası yazılıyordu.**

Uygulama `AppServiceProvider::boot()`'a taşındı; hem web hem konsol için
çalışıyor. Geçersiz bir değer ve ayar tablosu henüz yokken (taze klon, migration
ortası) sessizce config varsayılanına düşüyor.

### Tek nokta kuralı

Artık her kavramın tek bir yeri var:

| Ne | Nerede |
|---|---|
| Diller, varsayılan dil | Diller ekranı |
| Arayüz metinleri | Dil Yazıları ekranı |
| Saat dilimi | Ayarlar → Saat Dilimi |

### Testler

`TimezoneSettingTest` (9): ayarın uygulanması, konsolda da geçerli olması, web
ve konsolun aynı değeri kullanması, ayar yokken config varsayılanının
korunması, geçersiz değerin yok sayılması, ekranda dil dropdown'ının
bulunmaması ve yönlendirmenin yetkiye göre link/düz metin olması.

---

## 5n. Pasif Kullanıcı Oturumu ve Güvenilen Proxy — ✅ İki Sessiz Açık Kapatıldı

Base kit'in production'a hazırlık denetiminde çıkan iki bulgu. İkisinin de ortak
özelliği **hata vermeden yanlış davranmaları**: kod çalışıyor, test yeşil, ekran
doğru sonucu gösteriyor — ama iş yapılmıyor.

### 1. Pasife alınan kullanıcı oturumundan düşmüyordu

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

### Yol üzerinde bulunan iki kusur

**Force delete kullanıcıyı geri getiriyordu.** `revoke()` içindeki
`saveQuietly()` çağrısı, `forceDelete()` sonrası `exists = false` olan bir model
üzerinde çalıştığında UPDATE değil **INSERT** üretiyor ve silinen kullanıcıyı
geri yazıyordu. `exists` kontrolü eklendi; testi var.

**`UserFactory` `is_active` üretmiyordu.** Kolon varsayılanına bırakılmıştı, yani
satır aktif oluyordu ama `create()`'in döndürdüğü **modelde alan hiç yoktu** ve
`null` okunuyordu. Middleware modele sorduğu için suite'te 294 test birden
düştü. Fabrikaya `'is_active' => true` ve bir `inactive()` state'i eklendi.

### 2. Güvenilen proxy tanımsızdı

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

### Testler

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

### Bilinen ortam kaynaklı hata

Suite'te 5 test ağ erişimi olmayan ortamda düşüyor:
`CampaignPanelTest` (3), `FrontFormInputRulesTest` (1), `SubscriberListTest` (1).
Sebep `email:rfc,dns` kuralının DNS sorgusu; bu değişikliklerden önce de aynı
şekilde düşüyorlardı, kodla ilgisi yok.


---

## 5o. robots.txt — ✅ Dosyadan Rotaya Taşındı

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

### Şimdi nasıl çalışıyor

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

### Fazla satır basılmıyor

Robots kuralları önek eşleştirdiği için `/tr/hesabim` yazıldıktan sonra
`/tr/hesabim/profil` fazladan satır. Üretilen liste tekrarları atıyor ve kısa
öneki olan uzun yolları düşürüyor.

### Bakım modu

`/robots.txt` `web` grubunda, yani bakım modunda `CheckMaintenanceMode` 503
dönüyor. Bu bilinçli: arama motorları robots.txt'e gelen 5xx'i "şimdilik hiçbir
şeyi tarama" diye okur, bakım penceresinde istenen davranış tam olarak budur.

### Yol üzerinde bulunan şey

Giriş/kayıt/şifre sayfalarına `@section('robots', 'noindex, nofollow')` eklemeye
kalkıldı — gereksizdi: `layouts/auth.blade.php` `noindex` etiketini zaten sabit
basıyor ve o layout böyle bir section yield etmiyor. Eklenen satırlar ölü kod
olacaktı, geri alındı. (`auth/verify-email.blade.php:5` içinde aynı sebeple ölü
duran bir section var; zararsız olduğu için dokunulmadı.)

### Testler

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

## 5p. Hata Bildirimi ve Log Rotasyonu — ✅ Kapatıldı

Canlıda 500 veren bir sayfa **kimseye haber vermiyordu**. `bootstrap/app.php`
içindeki `withExceptions()` bloğu boştu; hata yalnızca `storage/logs` altına
düşüyordu ve oraya kimse bakmıyordu. Bir kullanıcı şikâyet edene kadar sitenin
bir bölümü günlerce kırık kalabilirdi.

Acı olan tarafı: projede çalışan bir bildirim kanalı **zaten vardı**.
`TelegramNotifier` ve `NotificationCenter` yazılmış, throttle'ı kurulmuş, panel
zilinde gösterimi hazırdı — yalnızca yedekleme komutu ve birkaç servis onu
çağırıyordu.

### 1. İşlenmeyen hata artık yöneticiye ulaşıyor

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

### 2. Log dosyası artık dönüyor

`.env.example` `LOG_STACK=single` diyordu: tek dosya, rotasyon yok. Paylaşımlı
hostingde `laravel.log` zamanla gigabaytlara çıkar ve **disk dolduğunda yalnız
log yazımı değil yükleme, yedekleme ve oturum yazımı da durur.**

`LOG_STACK=daily` + `LOG_DAILY_DAYS=14` oldu (`config/logging.php` bu değişkeni
zaten okuyordu). `LOG_LEVEL` için de canlıda `error` önerisi yorum olarak
eklendi.

### 3. Sistem Sağlık ekranına log kontrolü

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

### Testler

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

### Yol üzerinde görülen

`telegram_notify_level` ayarı panelde kaydediliyor ama **kodda hiçbir yerde
okunmuyor** — temizlenen `app_locale` ile aynı durumda. Bu turda dokunulmadı;
anlamı (iş yeniden deneme verbosity'si) hata bildirimiyle ilgisiz.


---

## 5r. Denetim İzi — ✅ Tek Modelden Kritik Kümeye

Altyapı baştan iyi yazılmıştı: `audit_logs` tablosu, indeksleri, saklama süresi
temizliği, panel ekranı, süzgeçleri ve **hassas alan maskesi** hepsi yerindeydi.
Eksik olan tek şey neyin izlendiğiydi — `AuditObserver`
`AppServiceProvider.php:127`'de **tek bir modele** bağlıydı: `Setting`.

Yani "kim giriş yaptı", "kim başarısız giriş denedi", "kim hangi rolün iznini
değiştirdi", "kim kullanıcı sildi", "kim yönlendirme ekledi" sorularının
hiçbirinin cevabı yoktu. Kurumsal bir denetimin ilk sorduğu şeyler de bunlar.

### Üç ayrı yol, çünkü tek gözlemci hepsini göremiyor

| Yol | Neyi yakalıyor | Neden ayrı |
|---|---|---|
| `AuditObserver` (model listesi) | `Setting`, `User`, `Role`, `Redirect`, `CustomRoute`, `MailTemplate`, `Language` | Satır değişikliklerini görür |
| `AuditAuthenticationEvents` (abone) | Giriş, çıkış, başarısız deneme | Bu olaylar hiçbir satırı değiştirmiyor |
| Servislerin kendi `AuditLogger::custom()` çağrıları | İzin matrisi, kullanıcı rolleri, toplu silme/geri yükleme, şifre sıfırlama | Pivot tablosunun modeli yok; toplu işlemler sorgu kurucusundan gidiyor ve model olayı doğurmuyor |

Model listesi dizi üzerinden geçiyor — yeni bir kritik model eklendiğinde tek
satır yetiyor.

### Kapsam neden içerik modellerini almıyor

Sayfa, blog ve galeri her kaydetmede satır üretir. 90 günlük saklama süresiyle
denetim izi kendi gürültüsünde boğulur ve asıl aranan kayıt — bir yetkinin ne
zaman verildiği — bulunamaz hâle gelir. Buradaki liste erişimi, yetkiyi,
gönderilen mailleri ve ziyaretçinin nereye gideceğini belirleyenler. İçeriğin
geçmişi denetim izinin değil **sürümlemenin** işi (bkz. modül önerileri).

Testi var: `Page` oluşturmak denetim izine düşmüyor.

### Ayrıntılar

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

### Yol üzerinde bulunan

`AuditLogPageTest` ve `AuditLogDetailTest` kendi kurdukları kayıtların sayısına
bakıyordu; `User` izlenmeye başlayınca sayfayı açan yöneticinin kendi izi
sonuçları kaydırdı ve 5 test düştü. Fikstür kurulumundan sonra tablo
sıfırlanıyor — testlerin niyeti "şu N kayıt verildiğinde özet şunu der",
kurulum gürültüsü değil.

### Testler

`AuditTrailCoverageTest` (17): izlenen modellerin her biri için gerçekten kayıt
doğması, içerik modellerinin dışarıda kalması, şifrenin ize hiç girmemesi,
etiketin kullanıcıyı adıyla anması, giriş/çıkış/başarısız denemenin kaydı,
denenen şifrenin yazılmaması, sıfırlama bağlantısı isteği, izin matrisi
değişikliği, **değişmeyen kaydın yazılmaması**, kullanıcı rolleri, toplu silme
ve geri yükleme, geri alma ve kalıcı silme.

Bağlantı kaldırılıp doğrulandı: gözlemci listesi ve abone çıkarılınca 9 test
düşüyor.


---

## 5s. Kuyruk İzleyici — ✅ Kuruldu

`failed_jobs` tablosu projede **tek bir yerde** okunuyordu:
`HealthCheckService.php:162`, o da yalnızca son 24 saatin *sayısını* alıyordu.
Listeleme, hatayı görme, yeniden deneme ve silme yoktu.

Bu proje için özellikle önemli: tüm mail gönderimi `MailService::queue()`
üzerinden kuyruğa giriyor. "Doğrulama maili gelmedi" şikâyetinin cevabı
`failed_jobs.exception` alanında duruyor ve o alana panelden bakmanın yolu
yoktu — kayıt tabloda sessizce birikiyordu.

### Ekran

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

### Tasarım kararları

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

### Yeni izinler mevcut kurulumlara nasıl gidiyor

İzinlerin tek kaynağı `PermissionKey` enum'u ama `PermissionSeeder` yalnızca
kurulumda çalışıyor. Deploy `git pull` + `migrate` ile yapıldığı için yeni bir
enum case'i satır karşılığı bulamaz ve **yönetici bile ekranı göremezdi**.
`2026_08_31_100000_seed_queue_permissions` migration'ı satırları ekliyor ve
yönetici rolüne veriyor.

### Testler

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

## 5t. Ölü Telegram Ayarı ve Kaydedilmeyen Başarısız İşler — ✅ İkisi de Kapatıldı

`telegram_notify_level` ayarı panelde kaydediliyordu ama **kodda hiçbir yerde
okunmuyordu** — 5m'de temizlenen `app_locale` ile birebir aynı durum. Kararı
verirken çok daha büyük bir şey çıktı.

### Ayar neden kaldırıldı, bağlanmadı

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

### Yol üzerinde bulunan asıl kusur: başarısız işler hiç kaydedilmiyordu

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

### Ayrıca

Telegram bölümünün alt başlığı hâlâ **"Instagram paylaşımları başarısız
olduğunda..."** diyordu — sökülmüş modülden kalan ve artık düpedüz yanlış olan
bir metin. Tam da neyin bildirim ürettiğini anlatan kutunun üstünde durduğu
için düzeltildi.

### Testler

`FailedJobRecordingTest` (5): patlayan işin `failed_jobs`'a düşmesi, kaydın
hata metnini ve yükü taşıması, işin yeniden denenmemesi, yöneticinin zaten
haberdar edilmesi ve başarılı işin geride kayıt bırakmaması.

`QueueMonitorTest`'e uçtan uca bir test eklendi: gerçek bir başarısızlık
Kuyruk ekranında görünüyor. Diğer testler satırı doğrudan yazıyordu, yani
zincirin kopuk halkasını göremezlerdi.

Dinleyici kaldırılıp doğrulandı: 3 test düşüyor.


---

## 5u. Yedek Geri Yükleme — ✅ Kuruldu, Yol Üzerinde Sessiz Bir Kusur Çıktı

Yedek alınıyordu ama **geri dönüş yolu yoktu**: dosya indirilebiliyor, ama
uygulanabilmesi için sunucuda elle SQL çalıştırmak gerekiyordu. Hiç denenmemiş
bir yedek, olmayan bir yedektir.

### Yol üzerinde bulunan asıl kusur: gövdesiz yedekler

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

### Geri yükleme

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

### SQL dökümünü ifadelere ayırma

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

### Dışarıdan yedek yükleme

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

### Yedek dizini artık yapılandırmadan geliyor

`config/backups.php` eklendi ve sınama takımı burayı geçici bir dizine
çeviriyor (`phpunit.xml`, yükleme dizini için zaten yapılan şey). Öncesinde
testler geliştiricinin **gerçek yedek dizinine** yazıyordu ve `create()` →
`rotate()` zinciri oradaki eski yedekleri silebilirdi.

### Testler

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

### Gerçek MySQL'de doğrulama

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

### Kalan yarı

Yedeğin **dış kopyası** hâlâ yok: arşiv yedeklediği veriyle aynı diskte
duruyor. Geri yükleme artık mümkün olduğu için dosyanın başka bir yerde
durması da anlamlı hâle geldi — sonraki tur.


---

## 5v. CI ve Statik Analiz — ✅ Kuruldu, Altı Gizli Hata Çıkardı

1282 test vardı ve **hiçbiri otomatik koşmuyordu**. Kırılmadığını doğrulamak
birinin elle `composer test` yazmasına bağlıydı; bu base kit'ten türeyen
projelerde ilk terk edilen alışkanlık.

### Üç kontrol, iki iş

`.github/workflows/ci.yml`: push ve pull request'te koşuyor.

| İş | Ne yapıyor |
|---|---|
| **Testler** | MySQL 8 servisine karşı migration + tüm suite |
| **Kalite** | `pint --test` ve `phpstan analyse` |

`composer check` (lint + analyse + test) aynı üçünü yerelde koşuyor.

### Testler neden MySQL'e karşı

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

### Kod stili: gürültüden sinyale

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

### Statik analiz: seviye 1, sıfır tolerans

Larastan eklendi. Seviye 5'te 552 hata çıkıyor ama ezici çoğunluğu gerçek kusur
değil, Eloquent çıkarım sınırı (`selectRaw('count(*) as count')` sütunları,
jenerik `Model` üzerinden görünmeyen SoftDeletes). Seviye 2'de bile 279.

**Seviye 1 seçildi: temiz geçen en yüksek seviye.** Yükseltip taban dosyasıyla
susturmak borcu kapatmak değil gizlemek olurdu; sıfır toleranslı düşük bir
seviye, gürültülü yüksek bir seviyeden çok iş görüyor. Yukarı çıkmanın yolu
modellere `@property` blokları eklemek — ayrı ve büyük bir iş, `phpstan.neon`
içinde not düşüldü.

### Seviye 0'ın çıkardığı üç şey

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

### Testler

`LikeSearchIsPortableTest` (4): ters bölü kaçışının hiçbir serviste geri
gelmemesi, yardımcıyı kullanan her servisin koşulu da ondan alması, joker
karakterin harf sayılması ve kaçış karakterinin kendisinin de kaçırılması.
Son ikisi sorguyu gerçek veritabanına atıyor.

Bekçinin gerçekten yakaladığı mutasyonla doğrulandı — ilk yazımı kaynak metin
yerine çalışma zamanı değerini aradığı için yakalamıyordu, düzeltildi.


---

## 5y. Çerez Rızası — ✅ Kuruldu

Hiçbir rıza mekanizması yoktu. Google Analytics ve Tag Manager ayar doluysa
**koşulsuz** yükleniyor, projenin kendi ziyaret kaydı da ilk istekten itibaren
IP ve oturum kimliği yazıyordu. IP maskeleme vardı ama 90 gün *sonra* devreye
giriyor — yani veri önce toplanıp sonra anonimleştiriliyordu. KVKK'da açık rıza
ispat yükü veri sorumlusunda; GDPR kapsamındaki bir ziyaretçi için de analitik
çerezler rızadan önce çalışamaz.

### Üç kategori

`ConsentCategory` enum'u: **zorunlu** (oturum, güvenlik jetonu, dil ve tema —
kapatılamaz), **analitik** (kendi ziyaret kaydımız + Google Analytics),
**pazarlama** (Google Tag Manager).

Tag Manager'ın pazarlama sayılması bilinçli: bir kap içine ne konduğu koddan
görünmez, her etiketi yükleyebilir. Belirsiz olanı en dar kategoriye koymak
doğru varsayılan.

### Karar verilmeden hiçbir şey yüklenmiyor

Betikler sayfaya konup "çalışmasın" denmiyor — **hiç basılmıyor**. Bir etiket
yüklendiği anda istek atıyor ve çerezini kuruyor; sonradan susturmak geç kalır.

Dört yol da kapalı: başlıktaki GA betiği, GTM betiği, `<noscript>` GTM
çerçevesi ve izleme betiği. Üstüne izleme uç noktası da rızayı **kendisi**
denetliyor: betik rıza olmadan yüklenmiyor ama uç nokta herkese açık, doğrudan
istek atan biri kaydı yine de oluşturabilirdi.

### Betiksiz de çalışıyor

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

### İspat kaydı

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

### Metin sürümü

`ConsentService::VERSION`. Kategoriler ya da açıklamaları değişirse artırılıyor;
eski rıza yeni metne verilmiş sayılmıyor ve ziyaretçiye bir kez daha soruluyor.

### Yol üzerinde bulunan sızıntı

Başlıktaki iki betik kapatıldıktan sonra test hâlâ GTM kimliğini buluyordu:
gövdedeki **`<noscript>` GTM çerçevesi** gözden kaçmıştı. Betiği kapatıp
çerçeveyi açık bırakmak, JavaScript'i kapalı ziyaretçiyi — tam da korunması
gereken kişiyi — rızasız izlemek olurdu.

### Testler

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

## 5z. API Katmanı (v1) — ✅ Kuruldu

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

### Kapsam

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

### Jeton yetkileri ve önbellek başlıkları (`26fa4fd`)

Yetkiler enum'da: `profile:read`, `profile:write`, `devices:manage`. Çıkış
bilerek yetkisiz — bir jeton her zaman kendini iptal edebilmeli, yoksa dar
yetkili bir jeton ele geçtiğinde sahibi onu kapatamaz.

Seyrek değişen uçlar `ETag` ile dönüyor; istemci `If-None-Match` gönderince
içerik değişmemişse 304 alıyor ve gövde hiç inmiyor. En büyük kazanç çeviri
sözlüğünde (yüz kilobayta yaklaşabiliyor). İçerik listeleri bilerek dışarıda:
orada tazelik önbellekten değerli ve sayfalama ETag'i sürekli değiştiriyor.

### Cihaz yönetimi (`471ea76`)

Kullanıcı kendi oturumlarını görüp kapatabiliyor. Doğrulanmış e-posta şartı
bilerek yok: hesabına şüpheli erişim olduğunu düşünen kişi, doğrulama adımını
tamamlayamamış olsa bile oturumları kapatabilmeli.

### E-posta değişimi (`7873d89`, `fbabbaf`)

Adres değişince doğrulama sıfırlanıyor ve **eski adrese** güvenlik uyarısı
gidiyor — hesabı ele geçiren biri adresi değiştirse bile sahibi haberdar olur.

### Makine okunur sözleşme (`782cea2`)

`docs/openapi.json` — OpenAPI 3.1. Kendi kendini denetliyor:
`Api/OpenApiSpecTest` şemayı rotalarla karşılaştırıyor, yeni bir uç şemaya
yazılmadan eklenirse test düşüyor. İkinci bir Postman koleksiyonu bilerek
tutulmuyor: ikinci dosya ikinci bayatlama kaynağı.

### Testler

`tests/Feature/Api/` altında 11 sınıf: kimlik, şifre sıfırlama, hesap, cihaz,
jeton yetkileri, önbellek başlıkları, içerik uçları, herkese açık uçlar, blog
araması, site araması ve sözleşme denetimi.

---

## 5ab. Arama — ✅ Kuruldu (blog + site geneli)

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

## 6a. Hesap ve Kimlik — ✅ Faz 1

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

## 6b. Mobil Web — ✅ Faz 2

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

## 6c. Panelin Eksik Ekranları — ✅ Faz 3

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

## 6d. API Olgunluğu — ✅ Faz 4

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

## 6e. Dayanıklılık — ✅ Faz 5

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

## 7. Laravel 13 Upgrade Notları

`ef5042c` commit'inde 12.52.0 → 13.26.1 yükseltmesi yapıldı. Upgrade guide'daki
kırılmaların hiçbiri projeye dokunmadı. İki config değeri **bilinçli olarak
varsayılanda bırakıldı**:

| Ayar | Durum | Sebep |
|---|---|---|
| `session.serialization` | Tanımsız → `'php'` | `json`'a çevirmek tüm aktif oturumları düşürür. Güvenlik sertleştirmesi olarak sonradan açılabilir. |
| `cache.serializable_classes` | Tanımsız → `null` | `false` yapılırsa Eloquent Collection cache'leyen 5 servis (`SliderService`, `PageService`, `FaqService`, `PopupService`, `BlogCategoryService`) kırılır. Açılacaksa allow-list ile açılmalı. |

### Kod stili uyarısı

~~`pint --test` ~180 dosyada sapma bildiriyor~~ — kapatıldı (bkz. bölüm 5v).
`pint.json` projenin kendi biçimini tanımlıyor, sapma sıfır ve fix modu artık
güvenle çalıştırılabiliyor.

---

## 8. Tamamlananlar

### Kapanan turlar

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

### Açık kalan iki madde

Bölüm 6'da: panelden push gönderme ekranı (tasarım bekliyor) ve
`session.serialization = json` (bakım penceresi bekliyor).
