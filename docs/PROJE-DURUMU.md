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
| Policy | 13 | Enum | 9 |
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

## 5. ⚠️ Yapılacak İşler

### 🔴 Güvenlik — Yetkilendirme boşluğu

**26 admin controller'ın 13'ü `authorize()` çağırmıyor.** Bu controller'lar sadece
`AdminMiddleware`'e güveniyor, o da `admin`, `editor`, `moderator` rollerinin
üçüne birden geçit veriyor. Yani bir **editör veya moderatör** şunları yapabilir:

| Controller | Ne yapabiliyor |
|---|---|
| `BackupController` | Yedek oluşturma, **indirme**, silme |
| `FileManagerController` | Dosya yükleme, silme |
| `RedirectController` | Yönlendirme ekleme/silme |
| `MenuController` / `MenuItemController` | Menü yapısını değiştirme |
| `AuditLogController` | Tüm aktivite loglarını görme |
| `AnalyticsController` | Ziyaretçi verisi |
| `MailLogController` | **Giden e-posta içeriklerini okuma** |
| `UploadController` | CKEditor upload |
| `HealthController` | Sistem bilgisi |
| `NotificationController` | Bildirimler |
| `DashboardController`, `ProfileController` | (kabul edilebilir) |

En kritikleri **BackupController** (tüm DB'yi indirebilir) ve **MailLogController**
(şifre sıfırlama e-postalarının gövdesini okuyabilir).

**Yapılacak:** Bu modeller için Policy yaz (`Redirect`, `Menu`, `MenuItem`,
`UploadedFile`, `MailLog`, `AuditLog`, `AdminNotification`) veya en azından
`AdminMiddleware`'e rol parametresi ekle (`admin` şart olan rotalar için).

### 🟡 Test kapsamı

Şu an **3 test** var:
- `tests/Unit/ExampleTest` — `assertTrue(true)`, değersiz
- `tests/Feature/ExampleTest` — anasayfa 200 mü
- `tests/Feature/AdminSmokeTest` — 25 admin GET rotası render oluyor mu

**Hiç yazma yolu test edilmiyor.** CRUD store/update/destroy, yetkilendirme,
validation, upload, mail gönderimi — hepsi testsiz. FAQ'daki gibi bir kırığın
tekrar sessizce girmesi çok kolay.

**Yapılacak:** En azından her admin modülü için store/update/destroy + policy
testi; `UploadService` için unit test.

### 🟡 CLAUDE.md kuralına aykırılıklar

**SoftDeletes 3 modelde yok** (kural: "HER MODELDE ZORUNLU"):
`AdminNotification`, `AnalyticsDailyStat`, `AuditLog`

Bunlar log/bildirim tabloları olduğu için savunulabilir, ama ya kural
gevşetilmeli ya modeller uyumlu hâle getirilmeli. Şu anki hâl belirsiz.

### 🟢 Eksik modüller (admin temada hazır tasarım var, kod yok)

`resources/views/admin-theme/` altında hazır HTML olup projede karşılığı olmayanlar:

- **`roles-permissions.html`** — Rol/yetki yönetimi ekranı. `Role` modeli ve
  `RoleService` var ama admin CRUD'u yok; roller sadece seeder'dan geliyor.
- **`reports.html`** — Raporlama ekranı
- **`content-list.html`** — Genel içerik listesi

### 🟢 Diğer

- **`README.md` tek satır** (`# baselaravel`). Base kit olarak dağıtılacaksa
  kurulum adımları, gereksinimler, seeder kullanımı yazılmalı.
- **`composer.json` adı hâlâ `laravel/laravel`**, açıklama Laravel skeleton'ınki.
- **`jenssegers/agent` 6 yıldır güncellenmiyor** (son sürüm 2020). Analytics'te
  tarayıcı/cihaz tespiti için kullanılıyor. Laravel 13 ile çalışıyor ama uzun
  vadede risk; alternatifi değerlendirilmeli.
- **Hesabım alanı zayıf** — şifre değiştirme ve e-posta doğrulama yok.

---

## 6. Laravel 13 Upgrade Notları

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

## 7. Önerilen Sıra

- [x] ~~**Hoş geldin e-postasını düzelt**~~ — tamamlandı (bkz. bölüm 4)
- [x] ~~**Ürün/sipariş kalıntılarını temizle**~~ — tamamlandı, 15 kalem

Sıradakiler:

1. **Yetkilendirme boşluğunu kapat** — özellikle `BackupController` ve
   `MailLogController` (güvenlik, kullanıcı verisi riski). Şu an en kritik madde.
2. **Test kapsamı ekle** — en azından CRUD yazma yolları. Mevcut 3 test sadece
   okuma yollarını kapsıyor.
3. **Kalan ölü kodu temizle** — `UserRole` enum, `vendor/pagination/custom.blade.php`,
   `.gitignore`'daki google kuralı
4. **README yaz** — base kit'in kurulum rehberi
5. **Eksik modüller** — rol/yetki yönetimi ekranı (`roles-permissions.html` temada hazır)
