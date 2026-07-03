# Ödeme Entegrasyonu — Polar.sh

## 1. Genel Bakış

Bu doküman, **Orhan Babanın Çiftliği** projesine **Polar.sh** üzerinden tek seferlik
ödeme sisteminin entegrasyonunu adım adım anlatır. Doküman parça parça yazılır
ve her bölüm bir commit olarak repoya işlenir.

### Mevcut Durum (Bu doküman yazılmadan önce)

- `app/Services/CheckoutService.php` — sepetteki ürünleri sipariş satırlarına
  çeviriyor, sipariş `status = pending` durumunda oluşturuluyor.
- `orders` tablosunda **ödeme bilgisi yok** — sadece `subtotal`, `discount`,
  `shipping_cost`, `total` ve `status` (`pending|processing|shipped|delivered|cancelled`).
- Ödeme yöntemi seçimi, ödeme alma, 3D Secure, callback, iade gibi süreçler
  **mevcut değil**.
- Sipariş tamamlanınca müşteriye e-posta, admin'e Telegram + bell bildirimi
  gidiyor (`CheckoutService::sendAdminNotification`).
- `OrderObserver::updated` status değişikliklerinde dual bildirim atıyor.

### Hedef (Bu doküman tamamlanınca)

- Müşteri sepet → adres adımından sonra **Polar.sh checkout sayfasına**
  yönlendirilecek.
- Müşteri Polar'da kartla öder, geri sitemize döner.
- Polar **webhook** ile ödeme durumunu sunucuya bildirir.
- `orders.payment_status = paid` olunca:
  - Sipariş `status` `pending` → `processing` geçer (`OrderObserver` zaten
    bildirim atıyor).
  - Müşteri ve admin "ödeme alındı" bilgisi alır.
- Ödeme başarısız/iptal olursa müşteri bilgilendirilir, sipariş `pending` kalır
  (admin elle silebilir veya 24 saat sonra otomatik iptal — opsiyonel).

### Neden Polar.sh

Detaylı analizi için git log'unda **payment provider seçim tartışması** notlarına
veya bu projenin product owner'ının kararına bakın. Özet sebep:

- **Merchant of Record (MoR):** Polar global VAT/sales tax sorumluluğunu üstlenir;
  satıcı (biz) sadece TR Kurumlar Vergisi (hizmet ihracatı beyanı) ile ilgilenir.
- **Türk satıcı kabul:** Stripe Connect Express altyapısı üzerinden TR onboarding
  destekleniyor. Stripe Atlas (Delaware LLC) zorunluluğu YOK.
- **Tek seferlik ödeme için optimum komisyon:** %4 + $0.40 (international card
  +%1.5). Stripe %2.9 + $0.30 daha ucuz görünür ama setup + vergi compliance
  küçük-orta hacimde toplam maliyeti Polar'ın üstüne çıkarır.
- **Açık kaynak:** Polar code base açık, vendor lock-in riski düşük.
- **API + DX:** REST + signed webhook + idempotency + sandbox.

### Kapsam Dışı (Bu sürümde YOK)

- **Abonelik / recurring billing** — proje tek seferlik ödeme istiyor.
- **3D Secure detayı** — Polar Stripe Connect üzerinden 3DS'i otomatik yönetir,
  manuel müdahale gerekmiyor.
- **İade arayüzü** — ilk sürümde manuel (Polar dashboard'undan); admin UI'a
  refund butonu sonraki faz.
- **Taksit** — Polar uluslararası kart işliyor, TR'ye özel taksit yok.
- **Çoklu sağlayıcı seçimi** — adapter pattern hazırlanacak (PaymentProvider
  interface) ama ilk sürümde sadece Polar implementasyonu var. İlerde
  iyzico/PayTR eklemek mimari değişiklik gerektirmeyecek.
- **Marketplace / komisyon dağıtımı** — tek satıcı modeli.

### Başarı Kriterleri

Doküman + kod tamamlandığında şu testler geçmeli:

1. Sepete ürün ekle → adres gir → "Öde" tıkla → Polar checkout açılır.
2. Polar sandbox kartıyla başarılı ödeme → webhook tetiklenir → `orders.payment_status = paid`,
   `status = processing` → admin'e Telegram + bell bildirimi.
3. Polar sandbox kartıyla başarısız ödeme → müşteri sitemize "ödeme alınamadı"
   mesajıyla döner, sipariş `pending` kalır.
4. Webhook signature yanlışsa endpoint **422** döner, log'a warning yazılır.
5. Aynı webhook event id ikinci kez gelirse (network retry) **idempotent**
   davranır — çift mark-as-paid olmaz.
6. Admin sipariş detay sayfasında **ödeme durumu rozeti** ve `payment_ref`
   (Polar order id) görülür.

### Doküman Bölümleri (parça parça)

1. ✅ **Genel Bakış + Hedefler** (bu bölüm)
2. ✅ Polar.sh hesap açma + API key + webhook secret çıkarma
3. ⏳ Veritabanı şeması — `orders` tablosuna ödeme kolonları
4. ⏳ Mimari — `PaymentService` + adapter pattern
5. ⏳ `PolarAdapter` implementasyonu
6. ⏳ Route + Controller (checkout başlat + callback)
7. ⏳ Webhook handling (signed + idempotent)
8. ⏳ Checkout akışı + müşteri arayüzü
9. ⏳ Admin settings sayfası (API key, webhook secret, mod toggle)
10. ⏳ Admin sipariş detay — ödeme rozeti
11. ⏳ Test stratejisi (Polar sandbox + senaryolar)
12. ⏳ Production deploy checklist
13. ⏳ İade ve hata senaryoları
14. ⏳ KVKK + yasal notlar

Her bölüm bağımsız okunabilir ama sıralı uygulanmalıdır — sonraki bölümler
öncekilerin çıktısını varsayar.

---

## 2. Polar.sh Hesap Açma + API Key + Webhook Secret

Bu bölüm **manuel** adımlar içerir (kod yazılmaz, web arayüzünden yapılır).
Sonu bir `.env` örneğiyle biter — sonraki bölümlerde bu değerler kullanılacak.

### 2.1 Hesap Açma

1. **https://polar.sh** adresine git → sağ üstten **Sign up**.
2. GitHub hesabıyla giriş yapmanı önerir (developer-first yaklaşım). Kişisel
   GitHub hesabı yeterli; şirket repoları gerekmez.
3. E-posta doğrulamasını tamamla.

### 2.2 Organization Oluşturma

Polar'da satışları bir **organization** üzerinden yaparsın (kişisel hesap altına
proje açmak yerine kurumsal kimlik ile).

1. Dashboard → **Create new organization**.
2. Bilgiler:
   - **Name:** "Orhan Babanın Çiftliği" (müşteri checkout sayfasında bu isim
     görünür — KVKK/IBAN/işletme adıyla uyumlu olmalı)
   - **Slug:** `orhanbabaninciftligi` (URL'de geçer:
     `polar.sh/orhanbabaninciftligi`)
   - **Avatar/Logo:** İşletmenin logosu (kare format, min 256×256)
3. **Country:** Türkiye seç → Polar Stripe Connect Express ile TR onboard'a
   başlar.

### 2.3 KYC (Stripe Connect) Doğrulama

Polar arka planda Stripe Connect Express kullanır. Para çekmek için Stripe'ın
KYC sürecini tamamlamak ZORUNLU:

1. Dashboard → **Payouts** veya **Account** → **Verify identity**.
2. Stripe'a yönlendirilir. Şunlar gerekir:
   - **Kişi:** TC kimlik, doğum tarihi, adres
   - **Vergi numarası:** Vergi kimlik no (şahıs) veya vergi no (şirket)
   - **Banka hesabı:** IBAN (TR ile başlayan) — payout buraya gelecek
   - **Kimlik belgesi:** TC kimlik kartı veya pasaport (fotoğraf)
   - **İşletme türü:** Individual / Sole proprietor / Company
3. Stripe **24-72 saat** içinde doğrulama yapar. Bu süre boyunca **test mode**
   ile geliştirmeye devam edilebilir; canlı ödeme için doğrulama beklenir.

⚠️ **Önemli:** TR Stripe Connect Express payout şu an Wise/USD wire değil,
**direkt TL hesabına TL** olarak da yapılabilir (Stripe TR 2024 sonu lansmanı).
Sales currency USD olsa bile payout aşamasında TL'ye dönüştürülür (Stripe FX
+%1-2 makas). Bu detay Polar dashboard → Payouts ayarlarından kontrol edilmeli.

### 2.4 Ürün Tanımlama (Test İçin)

Geliştirme sırasında test ödemesi için Polar'da bir ürün tanımlamak gerekir.
Ama dikkat: ileride **dinamik checkout** (Order'daki kalemleri Polar'a göndermek)
implementasyonu için ürün **runtime'da oluşturulacak** — yani burada tanımlanan
ürün sadece manuel test için.

1. Dashboard → **Products** → **New product**.
2. Bilgiler:
   - **Name:** "Test Sipariş"
   - **Type:** One-time purchase
   - **Price:** $5 (sandbox testi için sembolik)
   - **Description:** "Geliştirme amaçlı test ürünüdür."
3. **Save** → ürün `product_id` üretir, bunu not al (ileride opsiyonel).

### 2.5 API Key (Personal Access Token) Çıkarma

1. Dashboard → **Settings** → **Developer** → **Personal access tokens**.
2. **Create new token**.
3. Bilgiler:
   - **Name:** "Orhan Babanın Çiftliği — Backend"
   - **Scopes (izinler):**
     - `checkouts:read` `checkouts:write` ← checkout başlatma
     - `orders:read` ← Polar order detayını çekme
     - `customers:read` `customers:write` ← müşteri kaydı
     - `products:read` ← ürün listesi
     - `webhooks:read` ← webhook konfigürasyonunu doğrulama (opsiyonel)
   - **Expiration:** 1 yıl (sonra yenilenir)
4. **Generate token** → tek seferlik gösterilir, kopyala. Bir daha göremezsin.
   `.env`'ye yazılacak: `POLAR_API_KEY=polar_pat_...`

### 2.6 Webhook Endpoint Tanımlama

Ödeme tamamlandığında Polar bizim sunucuya HTTP POST atacak. Bunun için
Polar dashboard'unda webhook konfigüre edilir:

1. Dashboard → **Settings** → **Webhooks** → **Add endpoint**.
2. Bilgiler:
   - **URL:** `https://orhanbabaninciftligi.com/odeme/callback/polar`
     - **Geliştirme için:** ngrok veya benzeri tunnel kullan
       (`https://abc123.ngrok-free.app/odeme/callback/polar`)
   - **Format:** Raw (signed payload)
   - **Events:** Şunları seç:
     - `checkout.created`
     - `checkout.updated`
     - `order.created` ← **kritik** (ödeme başarılı)
     - `order.paid` ← **kritik**
     - `order.refunded` ← iade
     - `subscription.*` ← gerek yok (abonelik kullanmıyoruz)
3. **Save** → Polar bir **webhook secret** üretir (`whsec_...`). Bunu kopyala.
   `.env`'ye yazılacak: `POLAR_WEBHOOK_SECRET=whsec_...`

### 2.7 Sandbox / Production Ayrımı

Polar'da **iki ayrı ortam yok** — Stripe gibi `pk_test_` / `pk_live_` ayrımı
şu an Polar'da minimum. Bunun yerine:

- **Sandbox mode:** `https://sandbox.polar.sh` (ayrı bir hesap açılır)
- **Production mode:** `https://polar.sh` (gerçek hesap)

Bu **iki ayrı hesap** demek. Her ikisinde de yukarıdaki adımları (organization,
API key, webhook) tekrarlamak gerekir. `.env`'ye iki set değişken yazılır;
`Setting` tablosunda `polar_mode = sandbox|live` toggle'ı ile hangisi
kullanılacağı seçilir.

### 2.8 Composer / Laravel Paket Gereksinimleri

Polar.sh **resmi PHP SDK çıkartmamıştır**. Resmi paketler: TypeScript, Python, Go.
Bizim için bu **avantajdır** çünkü ek bağımlılık yok — Laravel'in yerleşik
araçlarıyla çalışacağız.

#### Yeni paket gerekmiyor

| İhtiyaç | Kullanılacak (zaten projede var) |
|---|---|
| HTTP istemcisi (Polar REST API çağrıları) | `Illuminate\Support\Facades\Http` (Laravel built-in) |
| JSON parse | `json_decode` / `json_encode` (PHP built-in) |
| Webhook signature doğrulama | `hash_hmac` (PHP built-in) |
| Idempotency cache | `Illuminate\Support\Facades\Cache` (zaten kullanılıyor) |
| Loglama | `Illuminate\Support\Facades\Log` (zaten kullanılıyor) |

Yani `composer require` çalıştırmana **gerek yok**. Projenin mevcut bağımlılıkları
yeterli.

#### Yine de paket eklemek istersem?

Topluluk paketleri var ama önerilmiyor (çoğu beta/abandoned):

- ❌ `polarsource/polar-php` — yok (resmi olmadığı için).
- ⚠️ `vendor/polar-laravel` benzeri toplulukta — bakım dışı, son commit 2024.
- ✅ Önerimiz: **Laravel Http facade ile direkt REST** — kontrolü elde tutar,
  Polar API değişirse 1 dosyada (`PolarAdapter`) düzeltiriz.

#### PHP versiyonu kontrolü

Polar API JSON üzerinden çalışır, herhangi bir özel PHP eklentisi
gerektirmez. Mevcut gereksinimler yeterli:

```bash
php --version           # 8.3.30 (proje min: 8.3)
php -m | grep -E "curl|json|openssl|hash"
# curl ✓  json ✓  openssl ✓  hash ✓ — webhook signature için yeterli
```

`composer.json` değişmeyecek. Migration sonrası `composer install` yapmanıza
gerek yok.

---

### 2.9 `.env` ve `Setting` Tablosunda Saklanacak Değerler

Polar konfigürasyonu **iki yerde** tutulur:

1. **`.env`:** Statik / nadiren değişen değerler.
2. **`Setting` tablosu:** Hassas + admin panelden değiştirilebilen değerler.

#### `.env` (sabit değerler)

```env
# Polar.sh — base URL'ler (mode'a göre kod tarafında seçilir)
POLAR_BASE_URL_SANDBOX=https://sandbox-api.polar.sh
POLAR_BASE_URL_LIVE=https://api.polar.sh
```

Bu kadar. `.env`'ye API key veya secret YAZILMAZ — Settings'te tutulur ki
admin panelden değişebilsin (token rotation, mode toggle).

#### `Setting` Tablosu (admin panel)

`settings` tablosuna eklenmesi gereken anahtarlar (mevcut `Setting::setValue()`
helper'ı ile yazılır — yeni migration gerekmez, Setting `key → value` modelidir):

| Anahtar | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| `polar_enabled` | boolean (`'1'`/`'0'`) | `'0'` | Ödeme akışı aktif mi (kapalıysa eski "kapıda ödeme" akışına düşer) |
| `polar_mode` | string | `'sandbox'` | `sandbox` veya `live` |
| `polar_api_key` | string (hassas) | `''` | Personal Access Token (`polar_pat_...`) |
| `polar_webhook_secret` | string (hassas) | `''` | Webhook signature secret (`whsec_...`) |
| `polar_organization_id` | string | `''` | Organization slug veya UUID (Polar API'da kullanılır) |
| `polar_success_redirect_path` | string | `/siparis-tamamlandi` | Ödeme başarılı sonrası dönüş URL'i |
| `polar_cancel_redirect_path` | string | `/sepet` | Ödeme iptal sonrası dönüş URL'i |
| `polar_currency` | string | `USD` | Polar şu an TRY native desteklemez; USD veya EUR seçilir |

#### Hassas alanlar için maskeleme

`polar_api_key` ve `polar_webhook_secret` admin panelinde **maskelenmiş**
gösterilmeli (örn: `polar_pat_••••••••8f3a`). Mevcut Setting form'unda
`type=password` input + "Düzenle" tıklayınca açılan modal pattern'i var
(zaten kullanıyoruz). Yeni alanlar için aynı UI takip edilecek.

#### `.env.example` güncellemesi

`.env.example` dosyasına şu satırlar eklenecek (gerçek değer YAZILMAZ):

```env
# Polar.sh Payment Provider
POLAR_BASE_URL_SANDBOX=https://sandbox-api.polar.sh
POLAR_BASE_URL_LIVE=https://api.polar.sh
```

#### Audit log davranışı

`Setting` modeli zaten `AuditObserver`'a bağlı (`AppServiceProvider`'daki
mevcut `\App\Models\Setting::observe(\App\Observers\AuditObserver::class)`).
Yani Polar API key veya webhook secret değiştirildiğinde:

- `audit_logs` tablosuna `event=updated`, `auditable_type=App\Models\Setting`
  satırı düşer.
- `old_values` / `new_values` **otomatik maskelenir** çünkü `AuditLogger`'da
  `SENSITIVE_FIELDS` listesinde `*token*`, `*secret*`, `*key*` pattern'leri
  zaten var.
- Kim, ne zaman, hangi mod'a (sandbox/live) geçtiği `/admin/aktivite-loglari`
  sayfasından izlenebilir.

#### Kontrol listesi (2.6 + 2.7)

- [ ] `composer.json` değişmeyecek (kontrol edildi, ek paket yok)
- [ ] PHP eklentileri yeterli (`curl`, `json`, `openssl`, `hash`)
- [ ] `.env.example` Polar base URL'leri eklendi
- [ ] Setting tablosunda 8 anahtarın varsayılan satırları seeder'a eklenecek
      (sonraki bölümlerde uygulama detayı)
- [ ] Hassas alanlar (`api_key`, `webhook_secret`) maskeleme UI desteği var

### 2.10 Local Geliştirme — ngrok + Sandbox Setup

Polar webhook'u sunucumuza HTTP POST atar. Webhook URL **public ve HTTPS**
olmak ZORUNDA. Localhost (örn. `http://localhost:8000`) Polar tarafından
erişilemez — bu yüzden local geliştirme için **tunnel** gerek.

#### Neden tunnel gerekiyor?

```
[Polar.sh sunucu] --HTTP POST--> [public URL] --tunnel--> [localhost:8000]
                                  ↑ ngrok burada devreye giriyor
```

Polar webhook tetiklendiğinde localhost'a doğrudan ulaşamaz; tunnel servisi
public bir HTTPS URL'i localhost'a yönlendirir.

#### ngrok kurulumu (önerilen)

ngrok ücretsiz tier'ı geliştirme için yeterli:

**1. Hesap aç + kurulum:**

```bash
# macOS
brew install ngrok

# Linux (Snap)
sudo snap install ngrok

# Ya da indirme: https://ngrok.com/download
```

**2. Auth token bağla:**

```bash
# ngrok.com → Dashboard → Your Authtoken
ngrok config add-authtoken <SENİN_TOKEN>
```

**3. Tunnel başlat:**

```bash
ngrok http 8000      # Laravel artisan serve port'u (default 8000)
```

Çıktı:

```
Session Status                online
Forwarding                    https://abc123-xyz.ngrok-free.app -> http://localhost:8000
```

**4. Bu HTTPS URL'i Polar dashboard'una webhook URL olarak ekle:**

```
https://abc123-xyz.ngrok-free.app/odeme/callback/polar
```

#### ngrok ücretsiz tier kısıtları (önemli)

- ⚠️ **URL her başlatmada değişir.** Bilgisayarı kapatıp açtığında yeni URL
  alırsın → Polar webhook URL'i her seferinde güncellemen gerek.
- ⚠️ **Reserved domain** ücretli ($10/ay) — sabit URL istiyorsan.
- ✅ Geliştirmenin ilk haftası için ücretsiz tier yeterli.

#### Alternatif tunnel servisleri

| Servis | Ücret | Avantaj |
|---|---|---|
| **ngrok** | Ücretsiz / $10 reserved | En yaygın, kolay setup |
| **Cloudflare Tunnel** (cloudflared) | Tamamen ücretsiz | Cloudflare hesabı varsa sabit URL |
| **Expose (Laravel)** | $7 expose.dev | Laravel ekosistemine yakın |
| **LocalTunnel** | Ücretsiz | URL stabil değil ama hızlı |
| **Cloudflare Quick Tunnel** | Ücretsiz | Cloudflare CLI ile tek komut, geçici URL |

**Önerimiz:** ilk hafta ngrok ücretsiz; düzenli geliştirmeye geçince
Cloudflare Tunnel (cloudflared) kalıcı subdomain için.

#### Local sandbox akışı

```
1. ngrok http 8000        # tunnel aç
2. Polar sandbox dashboard → webhook URL'i ngrok HTTPS URL'i ile güncelle
3. php artisan serve      # Laravel sunucusu
4. /sepet → ödeme tetikle → Polar checkout açılır
5. Polar sandbox test kartı (aşağıda)
6. Webhook ngrok üzerinden localhost'a düşer
7. storage/logs/laravel.log'dan webhook payload'ını gözle
```

#### Geliştirme sırasında webhook'u izleme

ngrok kendi inspector arayüzü sağlar:

```
http://localhost:4040       # ngrok web UI
```

Bu sayfada gelen tüm istekler (request body, headers, response) görünür.
Webhook payload'ı debug için çok değerli.

---

### 2.11 Test Kartları (Polar Sandbox)

Polar arka planda **Stripe Connect Express** kullandığı için **Stripe'ın test
kartları** Polar sandbox'unda da geçerlidir.

#### Başarılı ödeme test kartları

| Kart No | Açıklama |
|---|---|
| `4242 4242 4242 4242` | Genel başarılı kart (Visa) |
| `5555 5555 5555 4444` | Mastercard başarılı |
| `3782 822463 10005` | American Express başarılı |

- **Son kullanma:** Herhangi bir gelecekteki tarih (örn. `12/30`)
- **CVC:** Herhangi 3 hane (Amex için 4 hane)
- **Posta kodu:** Herhangi 5 hane (örn. `34000`)

#### Başarısız / hata senaryoları

| Kart No | Senaryo |
|---|---|
| `4000 0000 0000 0002` | Kart reddedildi (generic decline) |
| `4000 0000 0000 9995` | Yetersiz bakiye |
| `4000 0000 0000 9987` | Kayıp kart |
| `4000 0000 0000 9979` | Çalıntı kart |
| `4000 0000 0000 0069` | Süresi dolmuş kart |
| `4000 0000 0000 0127` | CVC hatası |
| `4100 0000 0000 0019` | Fraud koruması engelledi |

Bu kartlar ile ödeme başarısız olur → Polar webhook gönderir
(`checkout.updated` event'i, `status=failed`) → bizim akışta sipariş
`payment_status=failed` olarak işaretlenir, müşteri `/sepet` sayfasına
"ödeme alınamadı" mesajıyla döner.

#### 3D Secure test kartları

3D Secure deneyimini test etmek için (kullanıcıya banka onay sayfası gösterilir):

| Kart No | Senaryo |
|---|---|
| `4000 0027 6000 3184` | 3DS gerektirir, kullanıcı onaylar → başarılı |
| `4000 0082 6000 3178` | 3DS gerektirir, kullanıcı reddeder → başarısız |
| `4000 0000 0000 3220` | 3DS challenge mecburi |

#### Sandbox modunda dikkat edilecekler

- ⚠️ Sandbox **gerçek para çekmez**. Production'a geçmeden buradaki tüm
  senaryolar test edilmeli.
- ⚠️ Sandbox webhook'ları **production webhook URL'ine düşmez** — sandbox
  hesabının webhook konfigürasyonu farklıdır.
- ⚠️ Sandbox'taki ürün / customer kayıtları production'a taşınmaz; live'a
  geçince tüm konfigürasyon yeniden yapılır (mode toggle Setting'le, ama
  Polar dashboard ürünleri elle).
- ✅ Aynı kart numarası birden çok kez kullanılabilir; sandbox state
  tutmaz.

#### Otomatik test (PHP / Pest)

İleride feature test yazarken (Bölüm 11'de detay), Polar API çağrılarını
**HTTP fake** ile mocklamak gerek — gerçek sandbox'a istek atmadan akış
test edilebilir:

```php
Http::fake([
    'sandbox-api.polar.sh/v1/checkouts' => Http::response([
        'id' => 'checkout_test_123',
        'url' => 'https://sandbox.polar.sh/checkout/test_123',
        'status' => 'open',
    ], 200),
]);
```

Bu detay **Bölüm 11**'de işlenecek. Şimdilik manuel test için sandbox
yeterli.

#### Kontrol listesi (2.8 + 2.9)

- [ ] ngrok (veya alternatif) yüklendi, auth token bağlandı
- [ ] `ngrok http 8000` başlatıldığında HTTPS URL'i Polar sandbox webhook
      ayarlarına eklendi
- [ ] Stripe test kartları (`4242 4242...`) ile sandbox checkout açılabildi
- [ ] ngrok inspector (`http://localhost:4040`) ile webhook payload'ı
      gözlemlendi
- [ ] Başarısız senaryolar (decline, 3DS reject) en az bir kez denendi

### 2.12 Webhook URL — Public Erişim + HTTPS Zorunluluğu

Production'da webhook URL'in Polar sunucusundan erişilebilir olması KRİTİK.
Bu bölüm production tarafının ön gereksinimlerini detaylandırır.

#### Zorunlu özellikler

| Gereklilik | Neden |
|---|---|
| **HTTPS** (TLS sertifikası) | Polar HTTP'ye webhook göndermez (güvenlik) |
| **Public IP / domain** | Polar sunucusu erişebilmeli |
| **Stabil URL** | Webhook URL değişirse Polar bildirim atmaz, sessiz kayıp |
| **2xx response < 10 saniye** | Polar timeout = 10sn; 2xx dönmezsen retry yapar |
| **POST methodu kabul** | Webhook her zaman POST |
| **`X-Polar-Signature` header pass-through** | Reverse proxy bu header'ı kırpmamalı |

#### Cloudflare / CDN kullanıyorsanız

Eğer site Cloudflare arkasındaysa (proxy:on, turuncu bulut):

- ✅ Cloudflare Free Plan webhook'a karışmaz, "Bot Fight Mode" kapalı olsun.
- ⚠️ **Cloudflare WAF** bazen webhook isteklerini "POST + JSON body" diye
  spam zanneder. Çözüm:
  - WAF Custom Rule oluştur: `(http.request.uri.path eq "/odeme/callback/polar")`
    → Action: **Skip (security rules)**
- ⚠️ **Rate limit** kapalı veya yüksek olmalı (Polar retry yaparken
  blocklanmasın).
- ⚠️ **Page Rules → Cache Level: Bypass** webhook URL'i için.

#### Apache/Nginx konfigürasyon notları

**Nginx** için `nginx.conf`:

```nginx
location /odeme/callback/polar {
    proxy_pass http://localhost:9000;             # php-fpm
    proxy_set_header X-Polar-Signature $http_x_polar_signature;
    proxy_set_header X-Real-IP $remote_addr;
    client_max_body_size 100k;                    # webhook payload < 10KB
    proxy_read_timeout 15s;
}
```

**Apache** `.htaccess` veya `vhost.conf`:

```apache
<Location "/odeme/callback/polar">
    RequestHeader pass X-Polar-Signature
    # Security modules (modsecurity) bypass:
    SecRuleEngine Off
</Location>
```

#### IP whitelist (opsiyonel, defense-in-depth)

Polar webhook'ları belirli IP'lerden gelir. Resmi listeyi
**https://docs.polar.sh/api/webhooks#source-ips** sayfasından doğrula.
Whitelist eklemek **defense-in-depth** sağlar ama signature doğrulaması
zaten yeterli — IP filter "opsiyonel ekstra koruma".

Laravel tarafında middleware ile (sonraki bölümlerde detay):

```php
// app/Http/Middleware/PolarIpWhitelist.php
$allowedIps = ['x.x.x.x/24', ...];  // Polar docs'tan al
if (! in_array($request->ip(), $allowedIps)) abort(403);
```

#### Webhook isteğinin tipik akışı

```
Polar sunucu                                Sizin sunucu
─────────────                                ─────────────
POST /odeme/callback/polar ───────────►    Nginx (HTTPS termination)
Headers:                                        ↓
  X-Polar-Signature: t=...,v1=...           php-fpm (Laravel)
  Content-Type: application/json                ↓
Body: { "type":"order.paid",                PolarWebhookController
        "data":{...} }                          ↓
                                            Signature doğrula
                                                ↓
                                            Order'ı bul, payment_status update
                                                ↓
                                            OrderObserver tetiklenir
                                                ↓
                                            (Telegram + bell bildirimi)
                                                ↓
◄────────────────────────────────────────── 200 OK (boş body)
```

#### Test komutu (production sonrası)

Production canlıya alındıktan sonra Polar dashboard'undan **"Send test event"**
butonu var. Bu tıklandığında gerçek webhook gönderilir; bizim sunucu 200
dönmeli. Eğer 4xx/5xx dönerse Polar dashboard'unda hata mesajı görünür.

Komut satırından da test edilebilir (signature doğrulamayı kapatmadan):

```bash
curl -X POST https://orhanbabaninciftligi.com/odeme/callback/polar \
  -H "Content-Type: application/json" \
  -H "X-Polar-Signature: test_invalid" \
  -d '{"type":"ping"}'
# Beklenen: 401 veya 422 (signature geçersiz)
```

#### Yaygın hata senaryoları

| Belirti | Sebep | Çözüm |
|---|---|---|
| Polar dashboard "401 Unauthorized" | Webhook secret yanlış | Settings'te `polar_webhook_secret` doğru mu kontrol et |
| Polar dashboard "Timeout" | Sunucu > 10sn yanıt verdi | Webhook handler ağır iş yapmasın, queue'ya at |
| Polar dashboard "500" | Sunucuda exception | `storage/logs/laravel.log` bak |
| Webhook hiç gelmiyor | URL yanlış / Cloudflare bloke | Polar dashboard "Recent deliveries" + Cloudflare audit log |
| Aynı event 2 kez işlendi | Idempotency yok | Cache ile `event_id` track et (Bölüm 7'de detay) |

---

### 2.13 Polar Dashboard Rehberi

Polar dashboard ilk açışta karışık gelir. Buradaki haritayla hangi sekmenin
ne işe yaradığını net bilirsin.

#### Ana navigasyon

```
polar.sh/dashboard
│
├── Overview          ← Genel istatistikler (revenue, recent orders)
├── Products          ← Ürün katalogu (ileride dinamik oluşturulacak)
├── Orders            ← Tüm gelen siparişler (success + failed)
├── Customers         ← Müşteri kayıtları (email + ödeme geçmişi)
├── Payouts           ← Sana yapılan ödemeler (TR IBAN'a transfer)
├── Analytics         ← Ülke / cihaz / dönüşüm raporları
├── Settings
│   ├── General       ← Organization adı, logo
│   ├── Developer     ← API keys + Webhooks (bizim için kritik)
│   ├── Billing       ← Polar'a ödeyeceğin fee (komisyon faturası)
│   └── Account       ← KYC durumu, banka bilgisi
└── Profile (sağ üst) ← Çıkış, organization değiştirme
```

#### Geliştirme sırasında en sık kullanılacaklar

| Sekme | Ne için |
|---|---|
| **Settings → Developer → Webhooks** | Webhook URL ekle/güncelle, "Recent deliveries" log |
| **Settings → Developer → API Tokens** | Personal Access Token oluştur/sil |
| **Orders** | Test siparişlerini görüntüle, status, müşteri |
| **Settings → Account** | KYC durumu (Stripe Connect onayı bekliyor mu) |
| **Payouts → Schedule** | Payout sıklığı (default: weekly, değiştirilebilir) |

#### Webhook delivery log (debug için)

`Settings → Developer → Webhooks → Recent Deliveries`:

- Her webhook denemesi listelenir (success/failure)
- **"View payload"** ile gönderilen body görülür
- **"View response"** ile bizim sunucunun döndüğü 2xx/4xx + body görülür
- **"Retry"** butonu ile manuel tekrar gönderim

Webhook fail olursa Polar **otomatik retry**:

- 1. deneme: hemen
- 2. deneme: 1 dakika sonra
- 3. deneme: 5 dakika sonra
- 4. deneme: 30 dakika sonra
- 5. deneme: 6 saat sonra
- Sonra vazgeçer (manuel retry gerekir)

#### Payout zamanlaması (Türkiye için)

`Payouts → Schedule`:

- **Default:** Weekly (her Pazartesi)
- **Manual:** İstediğin zaman çek (minimum $20 birikmesi gerekir)
- **Monthly:** Aylık (komisyon avantajı yok, sadece muhasebe kolaylığı)

İlk payout'u almak için **KYC tamamlanmış** olmalı (Stripe Connect onayı).

#### Komisyon faturası

`Settings → Billing`:

Polar her ay senin satışlarından kestiği komisyonu **fatura** olarak gösterir.
Bu fatura **TR muhasebeci için önemli** — "gider belgesi" olarak kullanılır
(hizmet ithalatı).

PDF olarak indir, mali müşavirine ver.

#### Gerçek zamanlı analitik

`Analytics`:

- Hangi ülkeden ödeme geldi (TR yok ki burada — uluslararası satış senaryosu)
- Hangi cihaz (mobil/desktop)
- Dönüşüm oranı (checkout açılan / başarıyla ödeyen)

Bu detayları kendi admin panelinizde de görmek istiyorsanız sonraki fazda
"Ödeme Analitik" sayfası eklenebilir; ilk sürümde Polar dashboard'undan
yeterli.

#### Kontrol listesi (2.10 + 2.11)

- [ ] Production sunucusunda HTTPS aktif (Let's Encrypt veya Cloudflare SSL)
- [ ] Webhook URL DNS doğru çözülüyor (`dig orhanbabaninciftligi.com` ile
      kontrol)
- [ ] Cloudflare/WAF kuralı webhook URL'i için "Skip"
- [ ] Nginx/Apache `X-Polar-Signature` header'ını pass-through ediyor
- [ ] Polar dashboard → Webhooks → "Send test event" başarılı (200 dönüyor)
- [ ] Recent Deliveries log'undan ilk webhook'lar görülüyor

---

### 2.14 Polar Fee Yapısı — Gerçek Komisyon Hesabı

Marketing sayfasında **"%4 + $0.40"** yazıyor ama gerçekte birden fazla
katman var. Karar verirken yanılmamak için her bir kalemi netleştirelim.

#### Temel fee'ler

| Kalem | Oran / Tutar | Ne zaman tetiklenir |
|---|---|---|
| **Base transaction fee** | %4 + $0.40 | Her başarılı ödemede |
| **International card surcharge** | +%1.5 | Müşteri kartı satıcı ülkesinden farklı ise (TR satıcı için neredeyse her ödeme) |
| **Currency conversion** | +%1.5 (Stripe FX) | Müşteri kartı USD dışı bir para birimindeyse |
| **Chargeback fee** | $15 | Müşteri kartını "yetkisiz işlem" diye bankaya bildirirse (kazansak da, kaybetsek de bu $15 alınır) |
| **Refund processing fee** | $0 (iade ücretsiz) | Sen ödemeyi iade ettiğinde — ama orijinal %4 fee **geri gelmez** |
| **Payout fee (TR)** | $0 (Stripe Connect ücretsiz) | Polar → TR bank transferi |
| **Dispute fee** | $15 | Chargeback'e ek olarak dispute süreci açılırsa |

#### Gerçek senaryo — $100 satış (TR satıcı, ABD'li müşteri)

```
Müşteri ödemesi                                   $100.00
  - Base fee (%4 + $0.40)                         -$4.40
  - International card (+%1.5)                    -$1.50
  - Currency conversion (kart USD ise yok)        -$0.00
─────────────────────────────────────────────────
Net (Polar'dan payout'a giden)                    $94.10
  - Stripe Connect → TR payout                    -$0.00
─────────────────────────────────────────────────
TR banka hesabına gelen (USD)                     $94.10

TR banka USD → TL FX (BDDK kuralı, ~%1 makas)    ~₺3,200 (kur 34₺/$ varsayım)
```

**Efektif fee: ~%5.9** (reklam edilen %4'ten yüksek). Bu, **uluslararası TR
satıcı için tipik** — Stripe Atlas'la bile international card +%1.5 var.

#### Kart bazında değişen fee'ler

| Kart tipi | Ek fee |
|---|---|
| Visa / Mastercard standard | %0 (base'e dahil) |
| Visa / Mastercard premium / corporate | %0 (Polar yutuyor) |
| American Express | %0 (Polar yutuyor) |
| **International card** (satıcı ülkesinden farklı) | +%1.5 |
| **High-risk country** (sanksiyon listesi) | İşlem reddedilebilir |

#### Subscription / abonelik fee'leri (kullanmıyoruz ama not)

Eğer ileride abonelik satarsan:

- Recurring transaction fee: %4 + $0.40 (aynı)
- Failed payment retry: ücretsiz (Polar otomatik retry yapar)
- Proration: ücretsiz

Şu an abonelik kapsam dışı (`Bölüm 1.3` referansı), bu satırlar bilgi amaçlı.

#### TR satıcı için gizli maliyetler

1. **USD → TL kambiyo kaybı:** Bankan FX makası uygular (~%0.5 - %2). Polar'ın
   kontrolünde değil, bankanı seçerken makas oranı sor.
2. **Mali müşavir + hizmet ihracatı beyanı:** Yıllık ~₺2,000-5,000 (proje
   hacmine göre). Bu Polar'a değil muhasebeciye gider, ama "Polar maliyeti"
   diye düşünülmeli.
3. **180 gün getirme zorunluluğu:** USD geliri 180 gün içinde TR'ye getirilmezse
   Merkez Bankası cezası (mevzuat tebliği güncel oranlara bak).

#### Komisyon faturası (gider belgesi)

Polar Settings → Billing'den her ay **PDF komisyon faturası** indirilir.
Bu fatura TR muhasebeciye verilir, "hizmet ithalatı" olarak gider yazılır.

#### Fee karşılaştırması (özet)

| Sağlayıcı | Reklam edilen | TR efektif (international card dahil) |
|---|---|---|
| **Polar.sh** | %4 + $0.40 | ~%5.9 |
| **Lemon Squeezy** | %5 + $0.50 | ~%5.5 (international fee zaten dahil) |
| **Paddle** | %5 + $0.50 | ~%5.5 |
| **Stripe (Atlas)** | %2.9 + $0.30 | ~%4.4 + Atlas yıllık $1,000 fix |
| **Cream** | %3.9 + $0.40 | ~%6+ (abandoned cart +%5, affiliate +%2) |

**$1,000-$10,000/ay hacim için Polar en hesaplı.** Üstüne çıkınca Stripe Atlas
denklem değişir (Bölüm 1.4'te detay).

---

### 2.15 Müşteri Tarafı UX — Checkout Deneyimi

Müşteri ödeme akışında ne görür? Bu bölüm müşteri perspektifinden Polar
deneyimini netleştirir.

#### Checkout sayfası — Hosted vs Embed

Polar **iki seçenek** sunar:

**A) Hosted Checkout (önerilen — bizim implementasyon)**
- Müşteri sitemizden Polar checkout URL'ine **yönlendirilir**
- URL: `https://buy.polar.sh/<checkout_id>` (custom domain ile
  `pay.orhanbabaninciftligi.com` da yapılabilir)
- Polar'ın kendi tasarımıyla ödeme alınır
- 3DS, kart kaydı, hata ekranları hepsi Polar'da
- Başarı/iptal sonrası bizim siteye geri yönlendirilir
- ✅ **Avantaj:** Sıfır frontend kod, PCI compliance Polar'da
- ❌ **Dezavantaj:** Custom branding sınırlı, müşteri "polar.sh" markasını görür

**B) Embedded Checkout (sonraki faz)**
- Polar JavaScript SDK ile iframe içinde site'mize gömülür
- Domain hep `orhanbabaninciftligi.com` kalır
- Daha pürüzsüz UX ama frontend dev iş yükü
- İlk sürümde **hosted** yeterli

#### Müşteri akışı — Adım adım

```
1. Müşteri sepete ürün ekler
2. /checkout/adres sayfasında bilgilerini girer
3. "Ödeme Yap" butonuna basar
4. → Backend Polar checkout oluşturur (API call)
5. → Müşteri https://buy.polar.sh/... adresine yönlendirilir
6. Müşteri Polar sayfasında:
   - Sipariş özeti görür (bizim site'den gelen ürünler)
   - Email + kart bilgisi girer
   - 3DS gerekirse banka onay sayfası
   - "Pay $X.XX" tıklar
7. → Polar müşteriyi success URL'ine yönlendirir
   (https://orhanbabaninciftligi.com/siparis-tamamlandi?order_id=...)
8. Müşteri "Siparişiniz alındı" sayfasını görür
9. Arka planda Polar webhook gönderir → payment_status=paid
10. Müşteriye 2 email atılır:
    - Bizim sistem: sipariş onayı (mevcut OrderConfirmationMail)
    - Polar: ödeme fişi (otomatik, ayrı email)
```

#### Polar'ın otomatik müşteri email'leri

Polar **otomatik 3 tip email** atar (kapatılamaz):

| Email | İçerik | Atılma zamanı |
|---|---|---|
| **Receipt** | Ödeme fişi (PDF ek) | Ödeme başarılı olduğunda |
| **Refund notification** | İade onayı | Sen iade yaptığında |
| **Failed payment** | "Kart reddedildi" | Ödeme başarısız olduğunda |

**Önemli:** Bizim `OrderConfirmationMail` ile Polar'ın receipt email'i **iki ayrı email**.
Müşteri ikisini de alır. Çift email kafa karıştırıcı olabilir — Bölüm 8'de
"OrderConfirmationMail'i sadece kargo bilgisi içerecek şekilde sadeleştir"
notu eklenecek.

#### Polar checkout sayfasında nelere dikkat

Müşteri Polar'da şu alanları görür:

- ✅ Organization adı + logo (bizim "Orhan Babanın Çiftliği" yazısı)
- ✅ Ürün adı + fiyat
- ✅ Email input (Polar customer'ı bu emaille kaydeder)
- ✅ Card details + 3DS
- ⚠️ "Powered by Polar" markası (alt köşede, kaldırılamaz)
- ✅ Custom Terms of Service + Privacy Policy URL'leri (Settings'ten link verilir)

#### İade penceresi ve müşteri tarafı

- **Polar tarafı:** İade 120 güne kadar yapılabilir (Stripe Connect limiti)
- **Müşteri tarafı:** İade Polar dashboard'undan veya bizim admin panel'den
  başlatılır → Polar müşteriye otomatik mail atar → kart hesabına 5-10 iş
  günü içinde para iade edilir
- **Kısmi iade:** Destekleniyor (sipariş tutarının bir kısmı)
- **İade sonrası refund komisyonu:** Orijinal %4 fee **geri gelmez** (Polar
  cebinde kalır). Yani $100 satışta $4.40 fee → iade ettiğinde müşteri $100
  alır ama sen Polar'a $4.40 fee'yi yutmuş olursun.

#### Mobil deneyim

Polar checkout sayfası **responsive**. Apple Pay + Google Pay otomatik aktif
(Stripe Connect Express altyapısı). TR'de Apple Pay sınırlı destek, Google Pay
daha yaygın. Müşteri eligible cihazdaysa one-tap ödeme yapabilir.

#### Müşteri destek soruları (örnek)

Müşteri "ödemem geçmedi" derse:

1. Polar dashboard → Orders → status `failed` mi `pending` mi kontrol et
2. Stripe decline reason'ı görmek için: Polar order → "View in Stripe"
3. Tipik nedenler: 3DS reject, yetersiz bakiye, fraud algoritma
4. Müşteriye "kart bankası onaylamadı" cevabı + farklı kart deneme önerisi

---

### 2.16 Destek + Topluluk Kanalları

Bir sorun çıkarsa nereye yazacağını net bilmek önemli. Polar'ın destek
yapısı:

#### Resmi destek kanalları

| Kanal | URL / Email | Ne için |
|---|---|---|
| **Email destek** | `support@polar.sh` | Hesap, KYC, payout, fatura sorunları |
| **Discord** | `discord.gg/polar` | Geliştirici soruları, API, hızlı yanıt |
| **GitHub Issues** | `github.com/polarsource/polar/issues` | Bug raporu, feature request |
| **Documentation** | `docs.polar.sh` | API referans, kod örnekleri |
| **Status page** | `status.polar.sh` | Polar / Stripe outage takibi |
| **Twitter/X** | `@polar_sh` | Genel duyurular, downtime bildirimi |
| **Changelog** | `polar.sh/changelog` | API breaking change uyarıları |

#### Yanıt süreleri (gözlemler)

- **Email destek:** 1-3 iş günü (acil değilse)
- **Discord:** 2-12 saat (community + Polar ekibi)
- **GitHub:** 1-7 gün (bug priority'ye göre)
- **Status page subscribe:** Outage anında email/SMS gelir

#### En sık sorunlar + çözüm yolları

**KYC reddedildi:**
- Sebep: belge kalitesi, vergi numarası tutarsızlığı, sanctioned country
- Çözüm: `support@polar.sh` → "KYC rejected for org X" konusu → belgeyi yeniden yükle

**API rate limit:**
- Limit: dakikada 100 istek (resmi yazılı yok, gözlem)
- Çözüm: Cache + retry-after header dinle, Bölüm 5'te detay

**Webhook gelmiyor:**
- Polar dashboard → Webhooks → Recent Deliveries'e bak
- Sebep: webhook URL'i 5xx döndürüyor, Polar otomatik retry yaptı, sonra durdu
- Çözüm: "Manual retry" tıkla, sunucu log'una bak

**Payout gecikiyor:**
- Default weekly Pazartesi, ama ilk payout için 7-14 gün hold period var
  (Stripe risk algoritması)
- Çözüm: `support@polar.sh` ile iletişim, "Why is my payout on hold?"

**Hesap dondurma (rare):**
- Sebep: TOS ihlali, yüksek chargeback oranı, sanctioned customer
- Çözüm: hemen Discord'a yaz, sonra email — yedek processor (iyzico/PayTR)
  hazırla, tek MoR'a bağımlılık riskli

#### Türkçe destek var mı?

❌ Hayır, Polar destek **sadece İngilizce**. Bu yüzden:

- Resmi belgeleri TR muhasebeciyle Google Translate'le paylaşabilirsin
- API doc'u TR ekipte birinin İngilizce okuyabilmesi gerekir
- Müşteri tarafı UI Polar'da İngilizce — checkout sayfası TR seçilebiliyor
  (locale ayarı), ama dashboard sadece EN

#### Topluluk kaynakları

- **IndieHackers** — Polar tartışmaları aktif (`indiehackers.com/post/...`)
- **Reddit** — `r/SaaS`, `r/indiehackers` ara
- **YouTube** — "Polar.sh tutorial" arama (resmi kanal: `@polarsource`)
- **Twitter list** — Polar founders + erken kullanıcılar (`@birkjernstrom`,
  `@frankie_pangilinan`)

#### Acil durum aksiyon planı

Eğer Polar tamamen down olursa veya hesabın dondurulursa:

1. **Status page kontrol** (`status.polar.sh`) — outage mı?
2. **Twitter/X** Polar duyuru — global problem mü?
3. **Müşteriye duyuru** — site banner: "Ödeme sisteminde geçici sorun"
4. **Yedek plan:** İlk fazda yok, ama ileride iyzico/PayTR adapter
   (`Bölüm 4`'te adapter pattern hazırlanıyor) eklenebilir

---

### 2.17 Genişletilmiş Kontrol Listesi + Bölüm 2 Özeti

Bölüm 2 boyunca yapılan **tüm manuel adımları** tek noktada toplayan
master kontrol listesi. Kod yazımına başlamadan önce bu liste %100
tamamlanmalı.

#### 🔵 Polar Hesap + Organization

- [ ] Polar.sh hesabı açıldı (e-posta doğrulandı)
- [ ] Organization oluşturuldu (Country = Türkiye)
- [ ] Organization adı + slug + logo set edildi
- [ ] (Opsiyonel) Sandbox hesabı ayrı açıldı (sandbox.polar.sh)

#### 🔵 KYC / Banka Bilgisi (Stripe Connect Express)

- [ ] Kimlik belgesi yüklendi (TC kimlik veya pasaport)
- [ ] Vergi numarası girildi (şahıs için TC no, şirket için vergi no)
- [ ] Adres bilgisi tamamlandı
- [ ] İşletme türü seçildi (Individual / Sole proprietor / Company)
- [ ] IBAN (TR ile başlayan) eklendi
- [ ] Stripe Connect onayı bekleniyor (24-72 saat — geliştirme sırasında
      bloke değil)

#### 🔵 API Erişimi

- [ ] Personal Access Token üretildi (`polar_pat_...`)
- [ ] Token izinleri (scopes) seçildi:
  - [ ] `checkouts:read` + `checkouts:write`
  - [ ] `orders:read`
  - [ ] `customers:read` + `customers:write`
  - [ ] `products:read`
- [ ] Token süresi 1 yıl olarak ayarlandı
- [ ] Token güvenli yere kaydedildi (LastPass / 1Password / parola yöneticisi)
- [ ] (Opsiyonel) Sandbox için ayrı token üretildi

#### 🔵 Webhook Konfigürasyonu

- [ ] Webhook endpoint URL'i Polar dashboard'a eklendi:
  - Local dev: `https://<ngrok-url>.ngrok-free.app/odeme/callback/polar`
  - Production: `https://orhanbabaninciftligi.com/odeme/callback/polar`
- [ ] Webhook event'leri seçildi:
  - [ ] `checkout.created`
  - [ ] `checkout.updated`
  - [ ] `order.created`
  - [ ] `order.paid`
  - [ ] `order.refunded`
- [ ] Webhook secret üretildi (`whsec_...`) ve güvenli yere kaydedildi

#### 🔵 Local Geliştirme Ortamı

- [ ] ngrok (veya Cloudflare Tunnel) yüklendi
- [ ] Auth token bağlandı
- [ ] `ngrok http 8000` çalışıyor
- [ ] HTTPS URL Polar sandbox webhook ayarlarına eklendi
- [ ] ngrok inspector (`http://localhost:4040`) erişilebilir

#### 🔵 Test Hazırlığı

- [ ] Stripe test kartları liste olarak hazır (`4242 4242 4242 4242`,
      `4000 0000 0000 0002`, vs.)
- [ ] Sandbox modunda en az 1 başarılı ödeme manuel test edildi
- [ ] En az 1 başarısız ödeme (decline) senaryosu test edildi
- [ ] (Opsiyonel) 3D Secure akışı bir kere denendi

#### 🔵 Production Sunucu Hazırlığı

- [ ] HTTPS sertifikası aktif (Let's Encrypt veya Cloudflare SSL)
- [ ] Domain DNS doğru çözülüyor (`dig orhanbabaninciftligi.com`)
- [ ] Cloudflare arkasında ise:
  - [ ] Bot Fight Mode → webhook URL için skip
  - [ ] WAF Custom Rule: webhook URL için "Skip security rules"
  - [ ] Cache Level: Bypass
  - [ ] Rate limit yüksek veya kapalı
- [ ] Nginx/Apache config: `X-Polar-Signature` header pass-through
- [ ] PHP eklentileri kontrol: `curl`, `json`, `openssl`, `hash` (`php -m`)

#### 🔵 Settings Tablosu (Kod tarafı — sonraki bölümlerde uygulanacak)

Bu kısım kod yazıldıktan sonra admin panelinden doldurulacak. Şimdilik
hazırlık:

- [ ] Settings sayfasında "Ödeme — Polar" sekmesi yer alacak
- [ ] 8 anahtar (mode, api_key, webhook_secret, ...) form alanı tasarlanacak
- [ ] Hassas alanlar (`api_key`, `webhook_secret`) maskeleme + "Düzenle"
      modal pattern kullanılacak

#### 🔵 Operasyonel Hazırlık (Sahip / Admin Tarafı)

- [ ] **Mali müşavir bilgilendirildi:** Polar üzerinden gelen gelir
      "hizmet ihracatı" olarak beyan edilecek
- [ ] **Komisyon faturası takip:** Polar her ay Settings → Billing'den
      PDF fatura çıkarıyor — muhasebeciye düzenli iletilecek
- [ ] **Kambiyo bilinci:** USD gelirinin 180 gün içinde TR'ye getirilmesi
      kuralı (Merkez Bankası tebliği)
- [ ] **Cayma hakkı (14 gün):** TR mevzuatına uygun "İade Politikası"
      sayfası sitede hazırlanacak (Bölüm 13'te detay)
- [ ] **KVKK metni:** Polar üçüncü taraf ödeme sağlayıcı olarak
      gizlilik politikasına eklenecek (Bölüm 14'te detay)

---

#### 📋 Bölüm 2 Özeti

Bölüm 2 boyunca şunları yaptık (kod yazılmadan):

| Konu | Sonuç |
|---|---|
| **Polar hesap + organization** | Açıldı, TR onboard, KYC süreci başlatıldı |
| **API key + scopes** | Üretildi, güvenli yerde saklandı |
| **Webhook endpoint + secret** | Tanımlandı, 5 event'e abone olundu |
| **Sandbox vs production** | İki ayrı hesap, ayrı API key + secret |
| **Composer / Laravel paket** | Ek paket YOK, Laravel Http facade yeterli |
| **.env + Setting tablosu** | 2 .env satırı + 8 Setting anahtarı planı |
| **Local geliştirme** | ngrok kuruldu, HTTPS tunnel hazır |
| **Test kartları** | Stripe test kartları doküman edildi |
| **Webhook URL public erişim** | HTTPS + Cloudflare/WAF kuralları + Nginx/Apache config |
| **Polar dashboard kullanımı** | Sekmelerin haritası çıkarıldı |

#### Bu noktadan sonra

✅ **Polar hesabı tarafı tamamen hazır** — kod yazılmaya başlanabilir.

🔜 **Bölüm 3'te** veritabanı şemasını güncelleyeceğiz: `orders` tablosuna
`payment_provider`, `payment_status`, `payment_ref`, `paid_at`,
`payment_meta` kolonlarını ekleyeceğiz.

🔜 **Bölüm 4-7** mimari + servis + controller + webhook implementasyonu.

🔜 **Bölüm 8-10** UI tarafı (checkout + admin).

🔜 **Bölüm 11-14** test, deploy, iade, KVKK.

#### Geri dönüş — yaşanabilecek sorunlar

Eğer Bölüm 3'e geçmeden önce şu durumlardan biri varsa, geri dön:

- Polar KYC reddedildi → Polar destek (support@polar.sh) ile iletişim
- Webhook URL hala public erişilebilir değil → Cloudflare/Nginx audit
- API key sızdı → Polar dashboard → token revoke → yenisi
- Sandbox'ta hiç başarılı ödeme alınamadı → ngrok URL doğru mu, test kart
  numarası doğru mu, KYC `restricted` mi (sandbox bile bazen onay ister)

Bu noktadan sonra mimari + kod tarafına geçiyoruz. Bir sonraki commit:
**Bölüm 3: Veritabanı Şeması — `orders` tablosu ödeme kolonları**.
