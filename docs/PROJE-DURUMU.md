# Proje Durumu

**Son güncelleme:** 2026-08-25
**Branch:** `feat/laravel-13-upgrade` (= `refactor/extract-base-kit`, aynı commit)
**Stack:** PHP 8.3 · Laravel 13.26.1 · Blade · MySQL 8 · Bootstrap 5.3.8 (self-hosted) · Vanilla JS

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
| Service | 33 | Migration | 38 |
| Controller | 39 (26'sı admin) | Seeder | 9 |
| FormRequest | 36 | Blade view | 109 |
| Policy | 20 | Enum | 9 |
| Observer | 9 | Artisan command | 5 |

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
- Modellerde `$fillable` (asla `$guarded = []`), `casts()` metodu, PHP 8.3 enum cast
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
| İletişim mesajları (görüntüleme) | ✅ | ✅ | ✅ |
| İletişim mesajı yanıtlama | ✅ | ✅ | — |
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

> **Yönlendirmeler neden sadece admin?** `StoreRedirectRequest`'te `old_url` için
> `starts_with:/` kuralı var ama **`new_url` için yok**. Yani hedef harici bir
> adres olabiliyor ve `HandleRedirects` ziyaretçiyi oraya yolluyor — trafik
> kaçırma vektörü. Yetkiyi daraltmak anlık çözüm; kalıcı çözüm için
> `new_url` doğrulaması da eklenmeli (bkz. bölüm 6).

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

`tests/Feature/AdminAuthorizationTest` eklendi — 6 test, 104 assertion:

- 20 rotanın üç rol için beklenen durum kodu (200/403) matrisi
- Editör yedek indiremiyor
- Editör mail log gövdesi okuyamıyor (şifre sıfırlama linki içeren gerçek kayıtla)
- Editör ve moderatör sidebar'ında yasak linkler görünmüyor
- Admin sidebar'ında her şey görünmeye devam ediyor
- Panel rolü olmayan kullanıcı 403 alıyor

---

## 6. ⚠️ Kalan Yapılacak İşler

### 🟡 Test kapsamı

Suite artık **9 test / 131 assertion**. Yetkilendirme ve okuma yolları kapsandı,
ancak **yazma yolları hâlâ testsiz**: CRUD store/update/destroy, validation,
upload, mail gönderimi. FAQ'daki gibi bir kırığın sessizce girmesi hâlâ mümkün.

### 🟡 Rol tanımı ile policy uyuşmazlığı

`RoleSeeder` moderatör rolünü "**Mesaj ve yorum** yönetimi yetkisi" diye
tanımlıyor, ama `BlogCommentPolicy` yalnızca admin + editor'e izin veriyor.
Yani moderatör tanımındaki işi yapamıyor.

Bu bir **yetki genişletme** kararı olduğu için bilinçli olarak dokunulmadı —
daraltmak güvenli, genişletmek ürün kararı. İki seçenek: ya
`BlogCommentPolicy`'ye moderatör eklenir, ya rol açıklaması düzeltilir.

### 🟡 Açık yönlendirme doğrulaması

`StoreRedirectRequest` ve `UpdateRedirectRequest` içinde `new_url` için host
kısıtı yok. Yetki artık admin'le sınırlı ama doğrulama da eklenmeli
(`starts_with:/` veya izinli host listesi).

### 🟡 CLAUDE.md kuralına aykırılıklar

**SoftDeletes 3 modelde yok** (kural: "HER MODELDE ZORUNLU"):
`AdminNotification`, `AnalyticsDailyStat`, `AuditLog`

Log/bildirim tabloları olduğu için savunulabilir, ama ya kural gevşetilmeli
ya modeller uyumlu hâle getirilmeli.

### 🟢 Eksik modüller (admin temada hazır tasarım var, kod yok)

- **`roles-permissions.html`** — Rol/yetki yönetimi ekranı. `Role` modeli ve
  `RoleService` var ama admin CRUD'u yok; roller sadece seeder'dan geliyor.
  Yeni rol matrisi göz önüne alınınca bu ekran daha da anlamlı.
- **`reports.html`** — Raporlama ekranı
- **`content-list.html`** — Genel içerik listesi

### 🟢 Diğer

- **`README.md` tek satır** (`# baselaravel`). Kurulum adımları yazılmalı.
- **`composer.json` adı hâlâ `laravel/laravel`**.
- **`jenssegers/agent` 6 yıldır güncellenmiyor** (son sürüm 2020). Laravel 13
  ile çalışıyor ama uzun vadede risk.
- **Hesabım alanı zayıf** — şifre değiştirme ve e-posta doğrulama yok.
- **Ölü kod:** `app/Enums/UserRole.php`, `vendor/pagination/custom.blade.php`,
  `.gitignore`'daki google kuralı.

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

- [x] ~~**Yetkilendirme boşluğunu kapat**~~ — tamamlandı (bkz. bölüm 5)
- [x] ~~**Hoş geldin e-postasını düzelt**~~ — tamamlandı (bkz. bölüm 4)
- [x] ~~**Ürün/sipariş kalıntılarını temizle**~~ — tamamlandı, 15 kalem

Sıradakiler:

1. **`new_url` doğrulaması ekle** — yönlendirme hedefi hâlâ harici bir adres
   olabiliyor. Yetki admin'e daraltıldı ama doğrulama da gerekli.
2. **Moderatör rolüne karar ver** — rol açıklaması yorum yönetimi diyor,
   `BlogCommentPolicy` izin vermiyor. Ya policy genişletilmeli ya açıklama
   düzeltilmeli.
3. **Test kapsamı ekle** — CRUD yazma yolları hâlâ testsiz.
4. **Kalan ölü kodu temizle** — `UserRole` enum, `vendor/pagination/custom.blade.php`,
   `.gitignore`'daki google kuralı
5. **README yaz** — base kit'in kurulum rehberi
6. **Eksik modüller** — rol/yetki yönetimi ekranı (`roles-permissions.html` temada
   hazır; yeni rol matrisi bu ekranı daha da anlamlı kılıyor)
