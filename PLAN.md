# Orhan Baba'nın Çiftliği - Proje Geliştirme Planı

> **Proje:** Doğal ve organik çiftlik ürünleri e-ticaret sitesi + Yönetim Paneli
> **Stack:** PHP 8.3 / Laravel 12 / Blade / MySQL 8 / Bootstrap 5.3.8 CDN / Vanilla JS
> **Son güncelleme:** 2026-02-24

---

## Tamamlanan Fazlar

### Faz 1 — Models ✅
- 15 model: User, Role, Category, Product, ProductImage, Order, OrderItem, Address, Campaign, Page, Slider, ContactMessage, Faq, Setting
- SoftDeletes, typed properties, ilişkiler, scope'lar, HasSlug trait

### Faz 2 — Migrations ✅
- 13 migration dosyası (users, roles, pivot, categories, products, product_images, addresses, orders, order_items, campaigns, pages, sliders, contact_messages, faqs, settings)
- Tüm tablolar, pivot tablolar, index'ler
- `down()` metotları yazıldı

### Faz 3 — Enums ✅
- OrderStatus, UserRole, ProductStatus, ContentStatus, CampaignType

### Faz 4 — Observers ✅
- UserObserver, CategoryObserver, ProductObserver, OrderObserver
- Cascade soft delete işlemleri

### Faz 5 — UploadService ✅
- Merkezi dosya yükleme servisi (WebP dönüşüm, srcset desteği)
- `app/Helpers/helpers.php` → `upload_url()`, `upload_srcset()`
- `resources/views/components/responsive-image.blade.php`

### Faz 6 — Service Layer ✅
- 12 service: Product, Category, Order, Campaign, Page, Slider, Setting, ContactMessage, Faq, Role, Address, Upload

### Faz 7 — FormRequest Validation ✅
- 20 FormRequest sınıfı (Store/Update çiftleri)
- UpdateOrderStatusRequest

### Faz 8 — Controllers + Routes ✅
- Frontend: HomeController, ProductController, PageController, ContactController, FaqController
- Admin: DashboardController, CategoryController, ProductController, OrderController, CampaignController, PageController, SliderController, FaqController, ContactMessageController, UserController, SettingController
- web.php + admin.php route dosyaları

### Faz 9 — Policies ✅
- 10 policy: Category, Product, Order, Campaign, Page, Slider, Setting, Faq, ContactMessage, User

### Faz 10 — Layout, Partial, CSS/JS ✅
- Layouts: app.blade.php (frontend), admin.blade.php, auth.blade.php
- Partials: navbar, footer, flash-messages, admin sidebar, admin topbar
- Components: responsive-image
- public/css/app.css, public/css/admin.css, public/js/app.js

### Faz 11 — Frontend View'ları ✅
- home.blade.php (hero slider, öne çıkan ürünler, kategoriler)
- products/index.blade.php (ürün listeleme)
- products/show.blade.php (ürün detay)
- contact.blade.php (iletişim formu)
- faq.blade.php (SSS)
- pages/show.blade.php (dinamik sayfalar)

### Faz 11.5 — Seeders ✅
- 8 seeder: Role, User, Category, Product, Slider, Setting, Page, Faq

### Faz 12 — Admin View'ları ✅
26 blade dosyası + admin CSS güncellemesi:

```
resources/views/admin/
├── dashboard.blade.php
├── categories/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── products/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── orders/
│   ├── index.blade.php
│   └── show.blade.php
├── campaigns/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── pages/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── sliders/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── faqs/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── contact-messages/
│   ├── index.blade.php
│   └── show.blade.php
├── users/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
└── settings/
    └── index.blade.php
```

---

### Faz 13 — Authentication ✅
- AuthController + AuthService (login, register, logout, password reset)
- LoginRequest, RegisterRequest FormRequest doğrulamaları
- Auth view'ları: login, register, forgot-password, reset-password (auth layout)
- Auth route'ları: guest middleware korumalı (/giris, /kayit, /sifremi-unuttum, /sifre-sifirla, /cikis)
- AdminMiddleware: login route düzeltmesi
- Navbar: auth durumuna göre giriş/çıkış butonları

---

## Kalan Fazlar

### Faz 14 — Kullanıcı Hesabım ✅
- AccountService + AccountController (profil, adres yönetimi, sipariş geçmişi)
- ProfileUpdateRequest, AddressRequest FormRequest doğrulamaları
- 7 view: dashboard, profile, orders/index, orders/show, addresses/index, addresses/create, addresses/edit
- Account sidebar partial (responsive: mobilde horizontal scroll)
- Route'lar: auth middleware + prefix /hesabim
- Navbar: hesabım, siparişlerim, adreslerim linkleri
- CSS: account section stilleri (account-card, account-stat-card, account-nav vb.)

### Faz 15 — Sepet Sistemi ✅
- CartService (session bazlı) + CartController (6 endpoint)
- AJAX endpoint'ler: ekle, güncelle, sil, temizle, mini cart
- Sepet sayfası view'ı (miktar kontrolleri, AJAX güncelleme, sipariş özeti)
- Mini sepet (navbar badge), ürün detayda "Sepete Ekle" butonu
- Fiyat senkronizasyonu (syncPrices), stok kontrolü

### Faz 16 — Checkout & Sipariş Akışı ✅
- CheckoutService + CheckoutController (adres seçimi, kupon, sipariş oluşturma)
- CheckoutRequest FormRequest doğrulaması
- 2 view: checkout/index (adres formu, kupon, sipariş özeti), checkout/success (onay)
- AJAX kupon uygulama, kayıtlı adres seçimi (JS ile form doldurma)
- DB::transaction ile atomik sipariş: stok azaltma + kampanya kullanım + sipariş oluşturma
- Sepet sayfasında @auth/@else checkout bağlantısı
- OrderItem'a SoftDeletes eklendi, Product::currentPrice() float dönüş tipi

### Faz 17 — Arama ✅
- SearchService (search + suggest) + SearchController (index + suggest AJAX)
- Arama sonuçları view'ı (product card grid, sepete ekleme, pagination)
- Navbar autocomplete: arama ikonu, dropdown, 300ms debounce, öneri listesi
- Route'lar: /ara (search), /ara/oneri (suggest JSON)

### Faz 18 — E-posta Bildirimleri ✅
- 4 Mailable sınıfı: OrderConfirmationMail, WelcomeMail, ContactMessageNotification, OrderStatusUpdatedMail
- Email layout (layout.blade.php) + 4 email template (welcome, order-confirmation, order-status-updated, contact-message)
- AuthService: kayıt sonrası WelcomeMail (queue)
- CheckoutService: sipariş sonrası OrderConfirmationMail (queue)
- OrderService: durum güncellemesinde OrderStatusUpdatedMail (queue)
- ContactMessageService: yeni mesajda admin'e ContactMessageNotification (queue)
- Tüm mailler DB transaction dışında, Mail::queue() ile async gönderim

### Faz 19 — Son Dokunuşlar ✅
- Error pages (404, 403, 500, 503): BEM CSS, responsive, noindex
- Rate limiting: login (5/dk), kayıt (3/dk), iletişim (3/dk) — Türkçe hata mesajları
- Cache optimizasyonu: mevcut servisler zaten Cache::remember kullanıyor (slider, kategori, ürün, sayfa, faq, ayar)
- SitemapService + SitemapController: dinamik sitemap.xml, 1 saat cache
- robots.txt: admin/hesabım/sipariş/sepet/auth sayfaları engellendi, sitemap URL eklendi
- SEO: layout'ta robots meta @yield ile dinamik, cart/checkout/search/error sayfalarına noindex

---

### Faz 20 — Misafir Sipariş (Guest Checkout) + WhatsApp Bildirim

#### Özet
Üye olmadan sipariş verme özelliği. Admin panelinden açılıp kapatılabilir toggle switch ile kontrol edilecek. Misafir siparişlerde admin'e email + WhatsApp linki bildirim gönderilecek. Müşteriye "Siparişiniz oluşturuldu, en kısa sürede sizinle iletişime geçeceğiz" mesajı gösterilecek.

#### Adım 1: Migration — Orders tablosuna guest alanları
**Yeni dosya:** `database/migrations/xxxx_add_guest_fields_to_orders_table.php`
- `guest_email` (string, nullable) → user_id'den sonra
- `guest_name` (string, nullable) → guest_email'den sonra

#### Adım 2: Order Model güncelleme
**Dosya:** `app/Models/Order.php`
- `$fillable` → `guest_email`, `guest_name` ekle
- `getCustomerEmail()` helper: `$this->user?->email ?? $this->guest_email`
- `getCustomerName()` helper: `$this->user?->name ?? $this->guest_name ?? $this->shipping_name`
- `isGuest()` helper: `$this->user_id === null`

#### Adım 3: Ayar seeder güncelleme
**Dosya:** `database/seeders/SettingSeeder.php`
- `guest_checkout_enabled` → group: `shipping`, type: `boolean`, default: `1`
- `order_whatsapp_notification` → group: `shipping`, type: `boolean`, default: `1`
- `admin_notification_email` → group: `contact`, type: `text`, default: contact_email değeri

#### Adım 4: Admin ayarlar sayfasına toggle ekleme
**Dosya:** `resources/views/admin/settings/index.blade.php`
- "E-Ticaret / Kargo" sekmesine 2 yeni switch:
  - "Misafir Sipariş İzni" (guest_checkout_enabled)
  - "WhatsApp Sipariş Bildirimi" (order_whatsapp_notification)

#### Adım 5: Route değişikliği
**Dosya:** `routes/web.php`
- Checkout route'larını `auth` middleware'den çıkar
- Middleware olmadan prefix/name group'u olarak bırak
- Controller içinde erişim kontrolü yapılacak (auth check + setting check)

#### Adım 6: CheckoutRequest güncelleme
**Dosya:** `app/Http/Requests/CheckoutRequest.php`
- Misafir ise → `guest_email` (required, email), `guest_name` (required, string, max:100)
- Auth kullanıcı ise → bu alanlar rules'a eklenmez
- `auth()->check()` ile dinamik rules

#### Adım 7: CheckoutController güncelleme
**Dosya:** `app/Http/Controllers/CheckoutController.php`

**index():**
- Guest checkout kapalıysa VE auth değilse → login'e redirect
- Auth → mevcut davranış (adresler + defaults)
- Guest → adresler boş, `$isGuest = true` flag'i view'a

**store():**
- `$user = auth()->user()` (nullable)
- Misafir → `$guestEmail` ve `$guestName` validated'den al
- `placeOrder($user, $validated, $guestEmail, $guestName)`

**success():**
- Auth → mevcut davranış (user relation)
- Guest → session'a kaydedilen `guest_order_number` ile sipariş bul

#### Adım 8: CheckoutService güncelleme
**Dosya:** `app/Services/CheckoutService.php`

**placeOrder(?User $user, array $shippingData, ?string $guestEmail, ?string $guestName):**
- `user_id` → `$user?->id`
- `guest_email` ve `guest_name` order data'ya ekle
- Email: `$user?->email ?? $guestEmail` adresine onay maili
- Sipariş sonrası → `sendAdminNotification($order)` çağır

**sendAdminNotification(Order $order):** (yeni metot)
- `order_whatsapp_notification` ayarı açıksa
- Admin email'ine `NewOrderAdminNotificationMail` gönder
- Mail içinde WhatsApp ile iletişime geç linki

#### Adım 9: OrderService güncelleme
**Dosya:** `app/Services/OrderService.php`
- `create()` → `guest_email` ve `guest_name` alanlarını order data'ya dahil et

#### Adım 10: Admin bildirim maili
**Yeni dosya:** `app/Mail/NewOrderAdminNotificationMail.php`
- Subject: "🛒 Yeni Sipariş - #OBC260315XXXX"
- İçerik:
  - Müşteri bilgileri (ad, telefon, email)
  - Sipariş özeti (ürünler, toplam)
  - Teslimat adresi
  - "WhatsApp ile İletişime Geç" butonu → `wa.me/NUMARA?text=Merhaba, SIPARIS_NO numaralı siparişiniz hakkında...`
  - "Siparişi Görüntüle" butonu → admin panel linki

**Yeni dosya:** `resources/views/emails/new-order-admin.blade.php`
- Mevcut email layout kullanılacak

#### Adım 11: Checkout view güncelleme
**Dosya:** `resources/views/checkout/index.blade.php`

Misafir kullanıcı için formun üstüne:
- "İletişim Bilgileri" kartı (guest_name, guest_email)
- "Zaten hesabınız var mı? Giriş yapın" linki
- Kayıtlı adresler bölümü gizlenir (`@auth ... @endauth`)

#### Adım 12: Success sayfası güncelleme
**Dosya:** `resources/views/checkout/success.blade.php`

Misafir için farklı mesaj:
- "Siparişiniz Oluşturuldu!"
- "En kısa sürede sizinle iletişime geçeceğiz."
- "Sipariş durumu hakkında e-posta adresinize bilgi gönderilecektir."
- "Siparişi Görüntüle" butonu gizle (hesap yok)
- "Alışverişe Devam Et" butonu kalsın

#### Adım 13: Admin sipariş detay sayfası güncelleme
**Dosya:** `resources/views/admin/orders/show.blade.php`
- Müşteri bilgileri kısmında:
  - Misafir sipariş ise "Misafir" badge'i göster
  - Guest email göster
- Telefon yanına WhatsApp ikonu (wa.me linki)
- WhatsApp linki: hazır mesaj ile açılacak

#### Adım 14: Admin sipariş listesi güncelleme
**Dosya:** `resources/views/admin/orders/index.blade.php`
- Müşteri sütununda misafir siparişler için "Misafir" etiketi

---

#### Dokunulacak Dosyalar (16 dosya)

| # | Dosya | İşlem |
|---|-------|-------|
| 1 | `database/migrations/xxxx_add_guest_fields_to_orders.php` | YENİ |
| 2 | `database/seeders/SettingSeeder.php` | Güncelle |
| 3 | `app/Models/Order.php` | Güncelle |
| 4 | `routes/web.php` | Güncelle |
| 5 | `app/Http/Requests/CheckoutRequest.php` | Güncelle |
| 6 | `app/Http/Controllers/CheckoutController.php` | Güncelle |
| 7 | `app/Services/CheckoutService.php` | Güncelle |
| 8 | `app/Services/OrderService.php` | Güncelle |
| 9 | `app/Mail/NewOrderAdminNotificationMail.php` | YENİ |
| 10 | `resources/views/emails/new-order-admin.blade.php` | YENİ |
| 11 | `resources/views/checkout/index.blade.php` | Güncelle |
| 12 | `resources/views/checkout/success.blade.php` | Güncelle |
| 13 | `resources/views/admin/settings/index.blade.php` | Güncelle |
| 14 | `resources/views/admin/orders/show.blade.php` | Güncelle |
| 15 | `resources/views/admin/orders/index.blade.php` | Güncelle |

#### Öneriler

1. **WhatsApp Bildirimi:** Otomatik mesaj göndermek için WhatsApp Business API gerekir (aylık maliyet + onay). Pratik çözüm: Admin'e email ile bildirim + içinde wa.me tıklama linki. Admin telefona tıklar, WhatsApp açılır, hazır mesajla müşteriye ulaşır.

2. **Spam Koruması:** Misafir checkout açılınca bot riskine karşı Rate Limiting uygulansın (IP başına günde max 5 sipariş). Honeypot alanı eklenebilir.

3. **Sipariş Takip (Gelecek):** Misafirler için "sipariş no + email" ile sorgulama sayfası eklenebilir. Bu faza dahil değil.

---

## Notlar

- Stack: PHP 8.3 / Laravel 12 / Blade / MySQL 8 / Bootstrap 5.3.8 CDN / Vanilla JS
- Vite, npm, Node.js, jQuery, React, Vue YASAK
- `declare(strict_types=1);` her PHP dosyasında ZORUNLU
- SoftDeletes HER MODELDE ZORUNLU
- Controller'da iş mantığı YASAK → Service katmanında
- Tüm detaylar için CLAUDE.md'ye bakılacak
- Türkçe commit: `[feat]: açıklama`
