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

## 4. ⚠️ Kaldırılacak / Düzeltilecek — Eski Proje Kalıntıları

`ab57deb` refactor'unda ürün/sipariş modülü sökülürken **bazı referanslar geride
kalmış**. Laravel 13 upgrade'i sırasında bunlardan biri (`FaqService`) sayfayı
500'e düşürdüğü için yakalandı ve düzeltildi; aşağıdakiler hâlâ duruyor.

### 🔴 Yüksek — Kullanıcıya görünen bozuk davranış

**1. Hoş geldin e-postası tamamen e-ticaret metni**

Yeni kayıt olan her kullanıcıya gıda/e-ticaret sitesi metni gidiyor:

- `resources/views/emails/welcome.blade.php:22-52` — "Taze ürünlere göz atın",
  "Kolay ve hızlı sipariş verin", "Siparişlerinizi adım adım takip edin",
  "Teslimat adreslerinizi kaydedin"
- `app/Services/MailTemplateService.php:105-112` — aynı metnin varsayılan şablonu
- `database/migrations/2026_03_12_200000_create_mail_templates_table.php:88` —
  aynı metin migration seed'inde
- `app/Mail/WelcomeMail.php:41` — `'products_url' => url('/urunler')`

**`/urunler` route'u yok.** Catch-all `/{slug}` yakalayıp 404 veriyor. Yani
e-postadaki "Ürünleri Keşfet" butonu kırık.

**Yapılacak:** Hoş geldin şablonunu base kit'e uygun genel bir metinle değiştir,
`products_url` değişkenini kaldır. Üç yerde birden (view + service + migration).

**2. Sipariş mail tipleri**

- `app/Services/MailTemplateService.php:199-203` — `Sipariş Onayı - #{order_number}`
  ve `Sipariş Durumu Güncellendi - #{order_number}` şablonları
- `app/Models/MailLog.php:80-81` — `OrderConfirmationMail` / `OrderStatusUpdatedMail`
  etiketleri

Karşılık gelen Mail sınıfları **yok** (`app/Mail/` altında 6 sınıf var, ikisi de
değil). Mail şablonları ekranında asla tetiklenmeyecek iki kayıt duruyor.

### 🟡 Orta — Yanlış/ölü UI

**3. Kullanıcı listesinde "Sipariş" istatistiği**

`resources/views/admin/users/index.blade.php:269` → `{{ $user->orders_count ?? 0 }}`

`orders` ilişkisi yok, her kullanıcı için **daima 0** gösteriyor. Ya kaldırılmalı
ya anlamlı bir metrikle değiştirilmeli (ör. yorum sayısı, son giriş).

**4. Rol açıklaması**

`database/seeders/RoleSeeder.php:16` → Editör rolü "İçerik ve **ürün** yönetimi
yetkisi" diyor. Ürün modülü yok.

**5. Ayarlar ekranında sipariş bildirimi metni**

`resources/views/admin/settings/index.blade.php:294` → "Yeni **sipariş**
bildirimleri ve **Instagram** kalıcı hata uyarıları bu adrese gönderilir."
Her ikisi de artık yok.

**6. Placeholder'larda `/urunler`**

Admin formlarında örnek URL olarak eski route geçiyor — kozmetik ama kafa karıştırıcı:
`admin/popups/create.blade.php:174`, `popups/edit.blade.php:186`,
`sliders/create.blade.php:200`, `sliders/edit.blade.php:215`,
`redirects/index.blade.php:247,275`, `menus/items.blade.php:119`
(`categorySlug=sut-urunleri`), `pages/_sections-about.blade.php:303`

### 🟢 Düşük — Ölü dosya/kod

**7. `app/Enums/UserRole.php`** — kod tabanında **0 referans**. Roller `roles`
tablosu + `Role` modeli üzerinden yönetiliyor, enum kullanılmıyor. Silinebilir.

**8. `resources/views/vendor/pagination/custom.blade.php`** — hiçbir yerden
referans verilmiyor. Sayfalama `pagination::bootstrap-5` kullanıyor.

**9. Boş upload dizinleri** — `public/uploads/products/` ve
`public/uploads/testimonials/` kodda hiç geçmiyor (`.gitkeep` dışında boş).

**10. `.gitignore`** — `/storage/app/google/*.json` kuralı duruyor ama dizin yok.

**11. `app/Helpers/helpers.php:27-29`** — docblock örnekleri hâlâ
`products/sut-a1b2c3d4e5.webp` diyor. Sadece yorum.

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

1. **Yetkilendirme boşluğunu kapat** — özellikle `BackupController` ve
   `MailLogController` (güvenlik, kullanıcı verisi riski)
2. **Hoş geldin e-postasını düzelt** — her yeni kayıtta yanlış içerik gidiyor
3. **Ölü kalıntıları temizle** — sipariş mail tipleri, `orders_count`, `UserRole`
   enum, `/urunler` placeholder'ları, rol açıklaması
4. **Test kapsamı ekle** — en azından CRUD yazma yolları
5. **README yaz** — base kit'in kurulum rehberi
6. **Eksik modüller** — rol/yetki yönetimi ekranı
