# Proje Durumu

**Son güncelleme:** 2026-08-25
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
| Model | 23 | Route | 164 |
| Service | 33 | Migration | 40 |
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
| Sayfalar | ✅ CRUD + restore | ✅ `/{slug}` | Dinamik section desteği (`_sections-about`) |
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

Suite artık **273 test / 1390 assertion**. Yetkilendirme, açık yönlendirme,
SoftDeletes, çok dilli içerik formları, arayüz çevirisi ve navigasyon kapsandı.

Hâlâ testsiz kalan: **mail gönderimi ve upload yolları** (`UploadService`
varyant üretimi, `MailTemplateService` render). İçerik CRUD'u çok dilli form
testleriyle birlikte kapsandı.

`NoBuildToolchainTest` (24) build tool yasağını bekçilik ediyor: kök dizinde
`package.json` / `vite.config.*` / `webpack.mix.js` / `tailwind.config.*` yok,
`node_modules` / `public/build` / `resources/js` / `resources/css` dizinleri
oluşmamış, hiçbir view `@vite` veya `mix()` kullanmıyor, `composer.json`
Node tabanlı araç istemiyor ve vendor kütüphaneleri commit'li dosya olarak
duruyor. Bu base kit'ten türeyen her proje için önemli — bir artisan iskele
komutu veya stok Laravel dosyası zinciri sessizce geri getirebilir.

### 🟢 Eksik modüller (admin temada hazır tasarım var, kod yok)

- **`roles-permissions.html`** — Rol/yetki yönetimi ekranı. `Role` modeli ve
  `RoleService` var ama admin CRUD'u yok; roller sadece seeder'dan geliyor.
  Yeni rol matrisi göz önüne alınınca bu ekran daha da anlamlı.
- **`reports.html`** — Raporlama ekranı
- **`content-list.html`** — Genel içerik listesi

### 🟢 Diğer

- ~~`README.md` tek satır~~ — yazıldı: kurulum, roller, çok dilli yapı, testler.
- **`composer.json` adı hâlâ `laravel/laravel`**.
- **`jenssegers/agent` 6 yıldır güncellenmiyor** (son sürüm 2020). Laravel 13
  ile çalışıyor ama uzun vadede risk.
- ~~Hesabım alanı zayıf~~ — şifre değiştirme (mevcut şifre doğrulamalı) ve
  e-posta doğrulama eklendi.
- ~~Ölü kod~~ — temizlendi: `vendor/pagination/custom.blade.php` ve
  `.gitignore`'daki google kuralı kaldırıldı. `UserRole` enum'u silinmedi,
  aksine bağlandı: `AdminMiddleware` ve `RoleSeeder` artık rol slug'larını
  ondan okuyor.

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

1. **Ön yüzdeki sabit metinler** — arayüz metinleri (buton, başlık, form
   etiketleri) hâlâ Blade içinde Türkçe sabit. İçerik çok dilli ama arayüz
   değil; `lang/` çeviri dosyalarına taşınması gerekiyor.
2. **Blog ve galeri ön yüz sorguları** — SSS, slider ve sayfalar dil farkında;
   blog listesi/detayı ve galeri sorguları da `localeWithFallback` kullanmalı.
3. **Rol/yetki yönetimi ekranı** — `roles-permissions.html` temada hazır. Roller
   şu an yalnızca seeder'dan geliyor; rol matrisi netleştiği için bu ekran
   artık daha anlamlı.
4. ~~Kalan ölü kodu temizle~~ — tamamlandı
5. ~~Hesabım alanını genişlet~~ — mevcut şifre doğrulaması ve e-posta
   doğrulama akışı eklendi
