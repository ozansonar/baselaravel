# Proje Durumu

**Son güncelleme:** 2026-08-31
**Branch:** `feat/laravel-13-upgrade` (= `refactor/extract-base-kit`, aynı commit)
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
| Model | 23 | Route | 170 |
| Service | 37 | Migration | 67 |
| Controller | 39 (26'sı admin) | Seeder | 9 |
| FormRequest | 36 | Blade view | 109 |
| Policy | 20 | Enum | 9 |
| Observer | 9 | Artisan command | 5 |
| Test | 27 | Assertion | 179 |

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

## 6. ⚠️ Kalan Yapılacak İşler

### 🟡 Test kapsamı

Suite artık **1248 test / 4518 assertion**. Yetkilendirme, açık yönlendirme,
SoftDeletes, çok dilli içerik formları, arayüz çevirisi, navigasyon ve build
tool yasağı kapsandı.

Mail ve upload yolları da kapsandı (`ImageUploadTest` 23, `MailDeliveryTest` 25);
bunlar diske ve SMTP'ye dokunduğu için kodu okuyarak doğrulanamayan tek yerdi.

Bu testler yazılırken üç gerçek kusur çıktı — bkz. 5h.

`NoBuildToolchainTest` (24) build tool yasağını bekçilik ediyor: kök dizinde
`package.json` / `vite.config.*` / `webpack.mix.js` / `tailwind.config.*` yok,
`node_modules` / `public/build` / `resources/js` / `resources/css` dizinleri
oluşmamış, hiçbir view `@vite` veya `mix()` kullanmıyor, `composer.json`
Node tabanlı araç istemiyor ve vendor kütüphaneleri commit'li dosya olarak
duruyor. Bu base kit'ten türeyen her proje için önemli — bir artisan iskele
komutu veya stok Laravel dosyası zinciri sessizce geri getirebilir.

### 🟢 Eksik modüller (admin temada hazır tasarım var, kod yok)

- **`reports.html`** — Raporlama ekranı
- **`content-list.html`** — Genel içerik listesi

~~`roles-permissions.html`~~ — yapıldı (`4b49a5a`): `admin/roles/index.blade.php`
ve tam CRUD + izin senkronizasyonu route'ları mevcut.

### 🟢 Diğer

- ~~`README.md` tek satır~~ — yazıldı: kurulum, roller, çok dilli yapı, testler.
- ~~`composer.json` adı hâlâ `laravel/laravel`~~ — `ozansonar/laravel-base` oldu.
- **`jenssegers/agent` 6 yıldır güncellenmiyor** (son sürüm 2020). Laravel 13 /
  PHP 8.4 ile çalışıyor ama uzun vadede risk. Tek kullanım yeri
  `AnalyticsService` (tarayıcı/cihaz tespiti); değiştirilmesi gerekirse etki
  alanı dar.
- ~~Ölü iskele girdileri~~ — temizlendi: `.gitignore`'daki `Homestead.*`
  satırları ve `composer.json`'daki `allow-plugins → pestphp/pest-plugin`.
- ~~Hesabım alanı zayıf~~ — şifre değiştirme (mevcut şifre doğrulamalı) ve
  e-posta doğrulama eklendi.
- ~~Ölü kod~~ — temizlendi: `vendor/pagination/custom.blade.php` ve
  `.gitignore`'daki google kuralı kaldırıldı. `UserRole` enum'u silinmedi,
  aksine bağlandı: `AdminMiddleware` ve `RoleSeeder` artık rol slug'larını
  ondan okuyor.

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

## 7. Laravel 13 Upgrade Notları

`ef5042c` commit'inde 12.52.0 → 13.26.1 yükseltmesi yapıldı. Upgrade guide'daki
kırılmaların hiçbiri projeye dokunmadı. İki config değeri **bilinçli olarak
varsayılanda bırakıldı**:

| Ayar | Durum | Sebep |
|---|---|---|
| `session.serialization` | Tanımsız → `'php'` | `json`'a çevirmek tüm aktif oturumları düşürür. Güvenlik sertleştirmesi olarak sonradan açılabilir. |
| `cache.serializable_classes` | Tanımsız → `null` | `false` yapılırsa Eloquent Collection cache'leyen 5 servis (`SliderService`, `PageService`, `FaqService`, `PopupService`, `BlogCategoryService`) kırılır. Açılacaksa allow-list ile açılmalı. |

### Kod stili uyarısı

`./vendor/bin/pint --test` ~180 dosyada sapma bildiriyor. **Bu normal** — kod
tabanı `=>` hizalamasını bilinçli kullanıyor, Pint'in varsayılan Laravel preset'i
bunu bozuyor. `pint --fix` **çalıştırılmamalı**, yoksa tüm kod tabanı yeniden
formatlanır.

---

## 8. Önerilen Sıra

- [x] ~~**SoftDeletes'i her modele yay**~~ — tamamlandı (bkz. bölüm 5d)
- [x] ~~**Yetkilendirme boşluğunu kapat**~~ — tamamlandı (bkz. bölüm 5)
- [x] ~~**Açık yönlendirmeyi kapat**~~ — tamamlandı (bkz. bölüm 5c)
- [x] ~~**Moderatör rolünü işler hâle getir**~~ — policy rol tanımına uyduruldu
- [x] ~~**Hoş geldin e-postasını düzelt**~~ — tamamlandı (bkz. bölüm 4)
- [x] ~~**Ürün/sipariş kalıntılarını temizle**~~ — tamamlandı, 15 kalem

Sıradakiler:

1. ~~**Pasif kullanıcı oturumdan düşmüyor**~~ — kapatıldı (bkz. bölüm 5n)
2. ~~**Güvenilen proxy tanımsız**~~ — kapatıldı (bkz. bölüm 5n)
3. **Ön yüzdeki sabit metinler** — arayüz metinleri (buton, başlık, form
   etiketleri) hâlâ Blade içinde Türkçe sabit. İçerik çok dilli ama arayüz
   değil; `lang/` çeviri dosyalarına taşınması gerekiyor.
4. **Blog ve galeri ön yüz sorguları** — SSS, slider ve sayfalar dil farkında;
   blog listesi/detayı ve galeri sorguları da `localeWithFallback` kullanmalı.
5. ~~**Denetim izini yay**~~ — kapatıldı (bkz. bölüm 5r)
6. ~~**Kuyruk izleyici ekranı**~~ — kapatıldı (bkz. bölüm 5s)
7. ~~**İşlenmeyen hata kimseye ulaşmıyor**~~ — kapatıldı (bkz. bölüm 5p)
8. ~~**`robots.txt` route'a taşınsın**~~ — kapatıldı (bkz. bölüm 5o)
9. ~~Rol/yetki yönetimi ekranı~~ — tamamlandı
10. ~~Kalan ölü kodu temizle~~ — tamamlandı
11. ~~Hesabım alanını genişlet~~ — mevcut şifre doğrulaması ve e-posta
    doğrulama akışı eklendi
