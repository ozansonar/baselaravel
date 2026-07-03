# TikTok Entegrasyonu — Otomatik Cross-Post Sistemi

## 1. Genel Bakış

Bu doküman, **Orhan Babanın Çiftliği** projesine **TikTok cross-post** sisteminin
entegrasyonunu adım adım anlatır. Mevcut Instagram + Facebook otomatik paylaşım
akışına TikTok'u **üçüncü hedef** olarak ekliyoruz. Hiç yeni mimari kurmuyoruz —
Facebook cross-post pattern'i birebir TikTok'a kopyalanıyor.

### Hedef Tek Cümlede

> Şu an Instagram'a paylaşılan her şey (manuel veya otomatik blog cron'u),
> aynı anda TikTok'a da otomatik paylaşılsın.

### Mevcut Akış (Bu doküman yazılmadan önce)

```
[Cron — günde 4 kez]
    ↓
BlogGenerationService::generate
    ↓
1. AI ile blog yaz
2. BlogPost oluştur
3. Kapak görseli ata
4. BlogToInstagramService::shareBlog
    ↓
    InstagramPost satırı oluştur (publish_to_facebook=true)
    ↓
[Cron — her 5dk]
    ↓
InstagramService::publish
    ├── Instagram'a yayınla ✓
    └── Facebook'a kopya  ✓ (publish_to_facebook=true ise)
```

### Hedef Akış (Bu doküman tamamlanınca)

```
... aynı 4 adım ...
    ↓
BlogToInstagramService::shareBlog
    ↓
    InstagramPost satırı oluştur
       - publish_to_facebook = true (mevcut)
       - publish_to_tiktok   = true (YENİ — Settings'teki global toggle'a göre)
    ↓
[Cron — her 5dk]
    ↓
InstagramService::publish
    ├── Instagram'a yayınla ✓
    ├── Facebook'a kopya  ✓ (mevcut)
    └── TikTok'a kopya    ✓ (YENİ — Photo Mode veya Video)
```

### Kararlar (Bölüm 0 — Specs Lock-In)

| Konu | Karar | Sebep |
|---|---|---|
| Yayın modu | **Direct Post** (audit gerekli) — geçici **Inbox** yedek | Audit beklerken yarı-otomatik çalışsın |
| Cross-post stratejisi | **Mevcut FB pattern'i** birebir kopya | Yeni mimari yok, kanıtlı kod |
| Veri modeli | Mevcut `instagram_posts` tablosu büyütülür | Polymorphic / ayrı tablo overengineering |
| TikTok'taki içerik | **Photo Mode** (slideshow) + **Video** | TikTok'ta Story yok; ikisini destekliyoruz |
| Otomatik blog → TikTok | **Tümü** otomatik (Settings global toggle) | Manuel onay UX boğar |
| Yeni paket | Yok — Laravel Http facade yeterli | Polar'da da aynı yaklaşım çalıştı |
| Token saklama | `Setting` tablosu | OAuth ile geliyor, .env'ye yazılmaz |
| Hassas alan maskeleme | Mevcut UI pattern (Setting form'unda var) | Tutarlılık |

### Kapsam Dışı (Bu sürümde YOK)

- **TikTok Story** — TikTok'ta Story yok
- **TikTok DM** — yayın değil mesajlaşma
- **TikTok Live** — sınırlı, audit gereksinimi farklı
- **AI video üretimi** (blog → TikTok video) — sonraki faz; ilk sürüm
  Photo Mode (kapak görseli slideshow olarak)
- **TikTok analytics dashboard** — kendi panelimizde TikTok istatistikleri
  ilerde, ilk sürümde sadece yayın
- **Müzik / ses ekleme** — TikTok native UI gerekir, API'den ses ekleme
  sınırlı (sadece commercial library)

### Mevcut Altyapı (Tekrar Kullanılacak)

| Bileşen | Görev | Yeniden kullanım |
|---|---|---|
| `InstagramPost` model | Cross-post bilgilerini taşır | Tablo genişletilir |
| `InstagramPostStatus` enum | Yayın durumları | Aynı (TikTok için ayrı status YOK) |
| `InstagramService::publish` | Ana yayın orchestrator | TikTok call ekleniyor |
| `FacebookPageService` | FB cross-post pattern'i | `TiktokService` örnek |
| `AuditObserver` | Setting değişikliği logu | TikTok ayarları otomatik audit'lenir |
| `AiLogObserver` | Fail bildirim merkezi | TikTok hataları otomatik Telegram + bell |
| `OrderObserver` pattern | Status değişikliği bildirimi | InstagramPost da benzer |
| Cron `publish-scheduled` | Her 5 dk scheduled post'u publish eder | TikTok cross-post içinde |
| `TelegramNotifier` | Anlık mobil bildirim | TikTok başarı/fail mesajları |
| `NotificationCenter` | In-app bell | Aynı |
| `AdminModal` | UI confirm + status | TikTok recovery butonları |

### Yol Haritası — 9 Doküman + 7 Kod Faz

#### Doküman bölümleri (parça parça commit)

1. ✅ **Genel Bakış + Yol Haritası** (bu bölüm)
2. ✅ TikTok Hesap + App + OAuth Kurulumu (sen yapacaksın, kod yok)
3. ✅ Veritabanı Şeması (instagram_posts tablo genişletme planı)
4. ✅ TiktokService + API Client Mimarisi
5. ✅ Cross-Post Entegrasyonu (InstagramService içine TikTok call)
6. ✅ Form UI + show/edit rozeti + Recovery butonları
7. ✅ Otomatik blog → TikTok akışı
8. ✅ Audit süreci + Direct Post geçişi
9. ✅ Test + Deploy + Master Kontrol Listesi

#### Kod fazları (her faz ayrı commit, doküman bölümleri bitince başlar)

| Faz | İçerik | Tahmini süre |
|---|---|---|
| **1** | Migration: `instagram_posts`'a TikTok kolonları | ~30 dk |
| **2** | Settings TikTok sekmesi + OAuth callback | ~2 saat |
| **3** | `TiktokService` + `TiktokApiClient` | ~3 saat |
| **4** | Cross-post tetikleyici (publish sonrası) | ~2 saat |
| **5** | Form UI + show/edit rozeti + recovery butonları | ~2 saat |
| **6** | Cron: `tiktok:refresh-token` | ~30 dk |
| **7** | `BlogToInstagramService` — `publish_to_tiktok` flag set | ~30 dk |

**Toplam:** ~1.5-2 gün kod + 2-6 hafta TikTok audit bekleme (paralel).

### Başarı Kriterleri

Doküman + kod tamamlandığında şu testler geçmeli:

1. `/admin/settings` → TikTok sekmesi → "Bağla" → OAuth flow → hesap bağlı
2. Manuel post (Reels veya Feed) oluştururken "TikTok'a paylaş" checkbox'ı
   görünür ve seçilebilir
3. Manuel Feed Post → Instagram'a paylaş → TikTok Photo Mode olarak da düşer
4. Manuel Reels video → Instagram'a paylaş → TikTok video olarak da düşer
5. Otomatik blog cron → 4 platform birden (blog + IG + FB + TT)
6. TikTok'a paylaşım fail olursa Instagram yayını **bozulmaz**, sadece
   `tt_error_message` doldurulur + Telegram + bell bildirim
7. `tt_post_id` ve `tt_permalink` DB'ye yazılır
8. Audit beklerken Inbox modunda: TikTok app'inde Inbox → Drafts'a düşer
9. Audit onaylanınca Setting toggle ile Direct Post moduna geçilir,
   anında TikTok'ta yayınlanır

### Riskler ve Yedek Planlar

| Risk | Yedek |
|---|---|
| TikTok audit reddi | Inbox modu sürekli çalışır — kod boşa gitmez |
| TikTok API breaking change | `TiktokApiClient` abstraction katmanı, 1 dosyada düzelt |
| Token expired | Cron `tiktok:refresh-token` günlük yenileme |
| Photo Mode beta — bazı bölgelerde aktif değil | Hata → log + bildirim, IG yayını etkilenmez |
| Video format uyumsuzluğu (boyut/aspect) | Client-side validator (Instagram'la aynı pattern) |
| Rate limit (50/gün) | Mevcut Cache::has throttle pattern |
| Hesap dondurma (TOS ihlali) | Settings'ten devre dışı bırak, IG akışı devam |

---

## 2. TikTok Developer Hesap + App + OAuth Kurulumu

Bu bölüm **manuel** adımlar içerir (kod yazılmaz, TikTok'un web arayüzünden
yapılır). Sonu bir kontrol listesi + Settings'e yazılacak değerlerle biter —
sonraki bölümlerde bu değerler kullanılacak.

### 2.1 TikTok Developer Hesabı Açma

1. **https://developers.tiktok.com** adresine git → sağ üstten **Login**.
2. TikTok hesabınla giriş yap. **Önemli:** burada giriş yapılacak hesap
   yayın yapılacak **işletme hesabı** olmalı (kişisel değil). Eğer
   `@orhanbabaninciftligi` business hesabı varsa onu kullan.
3. Profile → **Manage apps** sekmesine git.

### 2.2 Sandbox vs Production

TikTok Developer Console **iki mod** sunar:

| Mod | Kullanım | Audit |
|---|---|---|
| **Sandbox** | Test (yalnızca developer'ın hesabına yayın) | Gerek yok, hemen aktif |
| **Production** | Tüm kullanıcılara yayın | **App audit zorunlu** (2-6 hafta) |

**Akış:**
1. Önce **Sandbox** modunda app oluştur → hemen test edebilirsin
2. Sandbox'ta her şey çalışınca **Production** moduna audit başvur
3. Audit onaylanınca production credentials ile çalış

`.env` ve Setting tablosu **her ikisi için ayrı** değer tutacak
(`tiktok_mode = sandbox|production`).

### 2.3 App Oluşturma

1. **Manage apps** → **Connect an app**.
2. Bilgiler:
   - **App name:** `Orhan Babanın Çiftliği — Auto Cross-Post`
   - **Category:** `Business`
   - **Description (short):** "Otomatik blog → TikTok cross-post sistemi.
     Çiftlik ürünleri içerikleri yayınlar."
   - **App icon:** İşletme logosu (1024×1024 PNG)
3. **Save & Continue**.

### 2.4 OAuth + Scope Seçimi

App detay sayfasında **Login Kit** sekmesine git:

1. **Redirect URI** ekle:
   - Production: `https://orhanbabaninciftligi.com/admin/tiktok/oauth/callback`
   - Local (ngrok): `https://<ngrok-id>.ngrok-free.app/admin/tiktok/oauth/callback`
2. **Scope'lar** (izinler):
   - `user.info.basic` ← kullanıcı bilgisi
   - `video.publish` ← video yayınlama
   - `video.upload` ← video yükleme (alternate API)
   - `photo.publish` ← Photo Mode yayınlama (slideshow)
3. **Save**.

### 2.5 Client Key ve Client Secret

App detayında üst kısımda:
- **Client Key** (public — frontend'de kullanılır)
- **Client Secret** (private — backend'de, asla frontend'e gönderme)

Bunları kopyala, sonra `Setting` tablosuna yazılacak:
- `tiktok_client_key`
- `tiktok_client_secret`

⚠️ **Client Secret bir kere gösterilir.** Kaybedersen "Regenerate" tıklayıp
yeni üretirsin (eski hemen iptal olur).

### 2.6 Content Posting API Erişimi

TikTok'un **Content Posting API**'sini kullanmak için ayrıca **kapability**
istenmesi gerek:

1. App detay → **Add capabilities** → **Content Posting API**.
2. **Add** → onay için kısa form:
   - "How will you use this API?" → "Cross-post blog content from our farm
     website to TikTok as photo slideshow and Reels-style video."
   - "Expected daily video count" → `4` (cron 4x/gün)
   - "Target audience" → "Turkish-speaking farm product consumers"
3. Submit.
4. **Hemen otomatik approve olur (sandbox için).** Production audit ayrı süreç.

### 2.7 Audit Başvurusu (Production İçin)

Sandbox'ta her şey çalışınca:

1. App detay → **Submit for review** (sağ üst).
2. Hazırlaman gerekenler:

| Belge | Açıklama |
|---|---|
| **Privacy Policy** | `https://orhanbabaninciftligi.com/gizlilik` — TikTok verilerinin nasıl kullanıldığına dair madde içermeli |
| **Terms of Service** | `https://orhanbabaninciftligi.com/kullanim-sartlari` |
| **Demo video** | App'i çalışırken gösteren 30-60 saniyelik ekran kaydı (admin paneli + TikTok'a yayın akışı) |
| **Domain verification** | TikTok bir TXT record verir, DNS'e ekle veya `.well-known/tiktok-developers.txt` dosyası |
| **Use case açıklaması** | 300-500 kelime, neden Content Posting API gerek |

3. **Audit süresi:** 2-6 hafta (TikTok'a göre). Status sayfasından takip et.
4. **Reject olursa** belgeyi düzelt, yeniden başvur (ekstra ücret yok).

### 2.8 Domain Verification

TikTok app'in webhook'ları (callback URL'leri) güvenli olduğunu kontrol etmek
için domain doğrulaması ister.

**Yöntem 1: TXT DNS record (önerilen)**

TikTok şöyle bir kod verir: `tiktok-domain-verification=abc123xyz...`

DNS sağlayıcına ekle (Cloudflare, Cloudflare DNS, vs.):

```
Type:    TXT
Name:    @  (veya orhanbabaninciftligi.com)
Content: tiktok-domain-verification=abc123xyz...
TTL:     Auto / 3600
```

DNS propagasyon 5-30 dk. TikTok dashboard'da "Verify" tıkla.

**Yöntem 2: `.well-known` dosya**

```bash
mkdir -p public/.well-known
echo "tiktok-developers-site-verification=abc123xyz" > public/.well-known/tiktok-developers.txt
```

URL erişimi test:
```
curl https://orhanbabaninciftligi.com/.well-known/tiktok-developers.txt
```

### 2.9 Local Geliştirme (ngrok)

Polar'da olduğu gibi local'de webhook test için ngrok gerek:

```bash
ngrok http 8000
# https://xxx-yyy-zzz.ngrok-free.app
```

Bu URL'i **Redirect URI** olarak TikTok'a ekle. ngrok ücretsiz tier her
başlatmada URL değişir — Cloudflare Tunnel daha kalıcı çözüm
(`docs/payment.md` Bölüm 2.10'da detay).

### 2.10 Test Hesabı (Sandbox)

Sandbox'ta yayın yapacağın TikTok hesabı **Target Users** listesinde olmalı:

1. App detay → **Sandbox** → **Target users**.
2. Kendi TikTok kullanıcı adını ekle (örn. `@orhanbabaninciftligi`).
3. TikTok app'inde **bu hesaba giriş yaparken** uygulama "Şunu yetkilendir"
   ekranı gösterir → izinleri kabul et.

### 2.11 OAuth Akışı (Backend Tarafı — Önizleme)

Bu kısım kodla yapılacak (Faz 2), ama mantığı bilmen iyi olur:

```
1. Admin /admin/settings → TikTok sekmesi → "Bağla" butonu
2. → https://www.tiktok.com/v2/auth/authorize/?
     client_key=...&scope=video.publish,photo.publish,user.info.basic
     &response_type=code&redirect_uri=https://.../admin/tiktok/oauth/callback
     &state=<csrf-token>
3. Kullanıcı TikTok'ta izinleri onaylar
4. TikTok callback URL'imize redirect eder:
   https://.../admin/tiktok/oauth/callback?code=AUTH_CODE&state=...
5. Backend code'u access_token ile değiştirir:
   POST https://open.tiktokapis.com/v2/oauth/token/
   { client_key, client_secret, code, grant_type: 'authorization_code', redirect_uri }
6. Yanıt: { access_token, refresh_token, expires_in, open_id }
7. Setting tablosuna yaz: tiktok_access_token, tiktok_refresh_token,
   tiktok_open_id, tiktok_expires_at
8. Cron: günlük refresh (access_token 24 saat, refresh_token 365 gün)
```

### 2.12 `.env` ve `Setting` Tablosunda Saklanacak Değerler

#### `.env` (statik — admin değiştirmez)

```env
# TikTok API base URL'leri
TIKTOK_BASE_URL_SANDBOX=https://open-sandbox.tiktokapis.com
TIKTOK_BASE_URL_PRODUCTION=https://open.tiktokapis.com
```

#### `Setting` tablosu

| Anahtar | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| `tiktok_enabled` | bool (`'1'`/`'0'`) | `'0'` | TikTok cross-post aktif mi |
| `tiktok_mode` | string | `'sandbox'` | `sandbox` veya `production` |
| `tiktok_post_mode` | string | `'inbox'` | `inbox` (audit yok) veya `direct` (audit OK) |
| `tiktok_client_key` | string | `''` | App detay → Client Key |
| `tiktok_client_secret` | string (hassas) | `''` | App detay → Client Secret |
| `tiktok_access_token` | string (hassas) | `''` | OAuth callback'ten gelir |
| `tiktok_refresh_token` | string (hassas) | `''` | Token yenileme için |
| `tiktok_open_id` | string | `''` | TikTok kullanıcı kimliği (paylaşımlarda gerek) |
| `tiktok_username` | string | `''` | Görüntüleme amaçlı |
| `tiktok_expires_at` | datetime | `null` | Access token bitiş tarihi |
| `tiktok_auto_share_blog` | bool | `'0'` | Otomatik blog yazıları TT'ye gönderilsin mi |
| `tiktok_default_privacy` | string | `'PUBLIC_TO_EVERYONE'` | PUBLIC_TO_EVERYONE / MUTUAL_FOLLOW_FRIENDS / SELF_ONLY |
| `tiktok_disable_comment` | bool | `'0'` | Yorumları kapat (varsayılan tüm yayınlarda) |
| `tiktok_disable_duet` | bool | `'0'` | Duet'i kapat |
| `tiktok_disable_stitch` | bool | `'0'` | Stitch'i kapat |

#### Maskeleme + Audit log

`tiktok_client_secret`, `tiktok_access_token`, `tiktok_refresh_token`
otomatik maskelenir (mevcut `AuditLogger::SENSITIVE_FIELDS` pattern'i:
`*secret*`, `*token*`).

### 2.13 Kontrol Listesi (Bölüm 2)

Bölüm 2 tamamlandı sayılır:

- [ ] TikTok Developer hesabı açıldı
- [ ] Manage apps → app oluşturuldu, business kategorisi
- [ ] OAuth Login Kit konfigüre edildi:
  - [ ] Redirect URI eklendi (prod + local ngrok)
  - [ ] Scope'lar seçildi: `user.info.basic`, `video.publish`, `video.upload`, `photo.publish`
- [ ] Client Key + Client Secret kopyalandı, güvenli yere kaydedildi
- [ ] Content Posting API capability eklendi (sandbox için onay otomatik)
- [ ] (Production için) Audit başvurusu yapıldı:
  - [ ] Privacy policy URL hazır
  - [ ] Terms of service URL hazır
  - [ ] Demo video çekildi
  - [ ] Domain verification tamamlandı (TXT veya .well-known)
- [ ] (Opsiyonel) Sandbox target user eklendi
- [ ] Local geliştirme için ngrok HTTPS URL'i Redirect URI'ye eklendi
- [ ] `.env`'ye TIKTOK_BASE_URL_* eklendi
- [ ] (Kod yazıldıktan sonra) Setting tablosundaki 14 anahtar form'a girilebilir

Sonraki bölüm: **3. Veritabanı Şeması** — `instagram_posts` tablosuna
TikTok kolonları (Facebook cross-post pattern'i).

---

## 3. Veritabanı Şeması — `instagram_posts` Genişletme

Yeni tablo açmıyoruz. Facebook cross-post için zaten kolonlar var
(`fb_post_id`, `fb_permalink`, `fb_published_at`, `fb_error_message`).
TikTok için **aynı pattern'in birebir kopyası** ekleniyor.

### 3.1 Mevcut Cross-Post Kolonları (Facebook — Referans)

`instagram_posts` tablosunda zaten var:

```
publish_to_facebook   boolean (default false)
fb_post_id            string nullable
fb_permalink          string nullable
fb_published_at       timestamp nullable
fb_error_message      text nullable
```

`InstagramPost::fillable` ve `casts()` içinde mevcut.

### 3.2 Eklenecek TikTok Kolonları

| Kolon | Tip | Default | Açıklama |
|---|---|---|---|
| `publish_to_tiktok` | `boolean` | `false` | "Bu post TikTok'a da gitsin mi?" checkbox |
| `tt_post_id` | `string nullable` | `null` | TikTok'un yayın sonrası verdiği post ID |
| `tt_permalink` | `string nullable` | `null` | TikTok'ta görüntüleme URL'i |
| `tt_published_at` | `timestamp nullable` | `null` | TikTok'ta yayınlandığı an |
| `tt_error_message` | `text nullable` | `null` | Hata varsa son hata mesajı |
| `tt_retry_count` | `unsignedTinyInteger` | `0` | Retry sayacı (FB'da yok ama IG retry pattern'i geç) |
| `tt_inbox_id` | `string nullable` | `null` | Inbox modunda TikTok'un drafts ID'si |

### 3.3 Migration Tasarımı

```php
// database/migrations/2026_05_14_120000_add_tiktok_columns_to_instagram_posts_table.php

return new class extends Migration {
    public function up(): void {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            $table->boolean('publish_to_tiktok')->default(false)->after('fb_error_message');
            $table->string('tt_post_id', 255)->nullable()->after('publish_to_tiktok');
            $table->string('tt_permalink', 500)->nullable()->after('tt_post_id');
            $table->timestamp('tt_published_at')->nullable()->after('tt_permalink');
            $table->text('tt_error_message')->nullable()->after('tt_published_at');
            $table->unsignedTinyInteger('tt_retry_count')->default(0)->after('tt_error_message');
            $table->string('tt_inbox_id', 255)->nullable()->after('tt_retry_count');

            // Indeksler — sık sorgulanan filtre alanları
            $table->index('publish_to_tiktok');
            $table->index('tt_post_id');
        });
    }

    public function down(): void {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            $table->dropIndex(['publish_to_tiktok']);
            $table->dropIndex(['tt_post_id']);
            $table->dropColumn([
                'publish_to_tiktok',
                'tt_post_id',
                'tt_permalink',
                'tt_published_at',
                'tt_error_message',
                'tt_retry_count',
                'tt_inbox_id',
            ]);
        });
    }
};
```

### 3.4 Model Güncellemeleri

`app/Models/InstagramPost.php`:

#### `$fillable` ekleme (mevcut `'fb_error_message'` satırından sonra)

```php
protected $fillable = [
    // ... mevcut alanlar ...
    'publish_to_facebook', 'fb_post_id', 'fb_permalink', 'fb_published_at', 'fb_error_message',
    // TikTok cross-post
    'publish_to_tiktok',
    'tt_post_id',
    'tt_permalink',
    'tt_published_at',
    'tt_error_message',
    'tt_retry_count',
    'tt_inbox_id',
];
```

#### `casts()` ekleme

```php
protected function casts(): array {
    return [
        // ... mevcut cast'ler ...
        'publish_to_facebook'  => 'boolean',
        'fb_published_at'      => 'datetime',
        // TikTok
        'publish_to_tiktok'    => 'boolean',
        'tt_published_at'      => 'datetime',
        'tt_retry_count'       => 'integer',
    ];
}
```

#### Yardımcı method'lar (model'e)

```php
/**
 * TikTok cross-post başarılı mı?
 */
public function isTikTokPublished(): bool {
    return $this->tt_post_id !== null && $this->tt_published_at !== null;
}

/**
 * TikTok cross-post bekleyen mi (publish_to_tiktok=true ama henüz yayınlanmamış)?
 */
public function isTikTokPending(): bool {
    return $this->publish_to_tiktok
        && $this->status === InstagramPostStatus::Published
        && ! $this->isTikTokPublished()
        && $this->tt_retry_count < self::MAX_RETRY_COUNT;
}

/**
 * TikTok cross-post kalıcı hatada mı?
 */
public function isTikTokFailed(): bool {
    return $this->publish_to_tiktok
        && $this->tt_retry_count >= self::MAX_RETRY_COUNT
        && ! $this->isTikTokPublished();
}
```

### 3.5 Senaryolar — Her Kolon Hangi Durumda Doludur?

#### Senaryo A: Yayınlanmadan önce (draft / scheduled)
```
publish_to_tiktok    = true
tt_post_id           = null
tt_permalink         = null
tt_published_at      = null
tt_error_message     = null
tt_retry_count       = 0
tt_inbox_id          = null
```

#### Senaryo B: IG yayınlandı, TT henüz değil
```
publish_to_tiktok    = true
status               = published   (IG için)
tt_post_id           = null
tt_published_at      = null
tt_retry_count       = 0
```

#### Senaryo C: TT başarılı (Direct Post modu)
```
tt_post_id           = "7234567890123456789"
tt_permalink         = "https://www.tiktok.com/@orhanbabaninciftligi/video/7234..."
tt_published_at      = 2026-05-14 14:32:00
tt_error_message     = null
tt_retry_count       = 1
tt_inbox_id          = null
```

#### Senaryo D: TT başarısız (3 retry sonrası kalıcı)
```
tt_post_id           = null
tt_error_message     = "Caption too long" / "Token expired" / ...
tt_retry_count       = 3
tt_inbox_id          = null
```

#### Senaryo E: TT Inbox modunda (audit beklerken)
```
tt_inbox_id          = "publish_id_abc123"
tt_published_at      = null              ← kullanıcı manuel yayınlayana kadar
tt_post_id           = null              ← TT app'inden yayınlandığında dolar
tt_error_message     = null
```

Inbox modunda `tt_post_id` ve `tt_permalink` **TikTok webhook gönderirse**
doldurulur. Webhook gelmezse boş kalır ama post zaten Telegram bildirimiyle
"mobilde yayınla" notu alır.

### 3.6 Mevcut Veriler (Backwards Compatibility)

Migration'da `default(false)` kullandığımız için **mevcut tüm satırlar**
otomatik olarak `publish_to_tiktok=false` olur — eski post'lar TikTok'a
gönderilmeye çalışılmaz. Kullanıcı isterse eski post'ları manuel açar,
checkbox'ı işaretler.

### 3.7 İndeks Kararları

- `publish_to_tiktok` (boolean) — Cron yayınlama sorgusu sık filtre edecek
  (`WHERE status='published' AND publish_to_tiktok=true AND tt_post_id IS NULL`)
- `tt_post_id` (string) — Webhook callback'lerde "bu post hangi tt_post_id'ye
  ait" sorgusu (TikTok webhook'tan gelen ID ile reverse lookup)

`tt_inbox_id` için indeks YOK — nadiren sorgulanır.

### 3.8 Eski TikTok Tablosu Var Mı Olasılığı

Daha önce tiktok ile ilgili migration veya tablo yok. Boş zemine kuruyoruz.

### 3.9 Kontrol Listesi (Bölüm 3)

- [ ] Migration dosyası `2026_05_14_*_add_tiktok_columns_to_instagram_posts_table.php`
      oluşturulacak
- [ ] 7 kolon eklenecek (yukarıdaki tablo)
- [ ] 2 indeks eklenecek (`publish_to_tiktok`, `tt_post_id`)
- [ ] `InstagramPost::$fillable`'a 7 alan eklenecek
- [ ] `InstagramPost::casts()` boolean + datetime + integer cast'leri eklenecek
- [ ] 3 yardımcı method eklenecek: `isTikTokPublished`, `isTikTokPending`,
      `isTikTokFailed`
- [ ] `php artisan migrate` ile çalıştırılacak (production'da `--force`)
- [ ] Migration `down()` test edilecek (rollback çalışıyor mu)
- [ ] Mevcut post'ların `publish_to_tiktok=false` default'la geldiği kontrol
      edilecek

Sonraki bölüm: **4. TiktokService + API Client Mimarisi** — kod tarafında
hangi servis ne yapacak, sınıf diyagramı.

---

## 4. TiktokService + API Client Mimarisi

İki sınıf bölümü yapıyoruz:

- **`TiktokApiClient`** — Düşük seviye HTTP wrapper (REST endpoint çağrıları,
  signature, error handling, retry)
- **`TiktokService`** — Yüksek seviye iş mantığı (publishPhoto, publishVideo,
  refreshToken, validateToken)

Mevcut `InstagramService` ile aynı pattern. Ayrı katman olması test edilebilirliği
ve API breaking change'lerinde tek noktada düzeltme imkanı sağlar.

### 4.1 Sınıf Hiyerarşisi

```
app/Services/
├── InstagramService.php             (mevcut)
├── FacebookPageService.php          (mevcut — TT için referans)
├── TiktokService.php                (YENİ — yüksek seviye)
└── Tiktok/
    └── TiktokApiClient.php          (YENİ — düşük seviye HTTP)
```

### 4.2 `TiktokApiClient` — Sorumluluğu

```php
namespace App\Services\Tiktok;

final class TiktokApiClient
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl,   // sandbox veya production
    ) {}

    /**
     * Photo Mode yayını (slideshow).
     * POST /v2/post/publish/content/init/
     *
     * @param  list<string>  $imageUrls  Public erişilebilir görsel URL'leri
     * @return array{publish_id: string} Init başarılıysa publish_id döner
     */
    public function publishPhoto(string $caption, array $imageUrls, array $options = []): array;

    /**
     * Video yayını — iki aşamalı upload (init + chunk upload + commit).
     * POST /v2/post/publish/video/init/
     *
     * @return array{publish_id: string, upload_url?: string}
     */
    public function publishVideo(string $caption, string $videoUrl, array $options = []): array;

    /**
     * Inbox modu — Direct Post yerine drafts'a düşür.
     * POST /v2/post/publish/inbox/video/init/
     */
    public function publishToInbox(string $videoUrl, array $options = []): array;

    /**
     * Publish durumunu poll'la (TikTok asenkron işler).
     * GET /v2/post/publish/status/fetch/?publish_id=...
     */
    public function getPublishStatus(string $publishId): array;

    /**
     * Token refresh.
     * POST /v2/oauth/token/ (grant_type=refresh_token)
     */
    public function refreshToken(string $refreshToken, string $clientKey, string $clientSecret): array;

    /**
     * Kullanıcı bilgisi (token validity için kullanılır).
     * GET /v2/user/info/
     */
    public function getUserInfo(): array;
}
```

#### İç davranış

- **HTTP istemcisi:** `Illuminate\Support\Facades\Http` (Laravel built-in)
- **Timeout:** 30 saniye (video upload init için)
- **Retry:** Otomatik 2 deneme, 1sn ara (transient network hatası için)
- **Auth header:** `Authorization: Bearer <access_token>`
- **Content-Type:** `application/json` (multipart için video upload aşaması ayrı)
- **Error normalize:** TikTok hata response'larını `TiktokApiException` olarak fırlatır
- **Logging:** Her çağrı için `Log::info`/`Log::warning` (request method + endpoint + status)

#### Hata sınıfları

```php
namespace App\Services\Tiktok;

class TiktokApiException extends \RuntimeException {
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?array $rawResponse = null,
    ) {
        parent::__construct($message);
    }
}
```

### 4.3 `TiktokService` — Sorumluluğu

```php
namespace App\Services;

use App\Models\InstagramPost;
use App\Services\Tiktok\TiktokApiClient;

final class TiktokService
{
    public function __construct(
        private readonly TiktokApiClient $client,
    ) {}

    /**
     * InstagramPost'tan TikTok yayını yap.
     * Mode'a göre direct post veya inbox.
     *
     * @return array{success: bool, message: string, tt_post_id?: string,
     *               tt_permalink?: string, tt_inbox_id?: string}
     */
    public function publish(InstagramPost $post): array;

    /**
     * Bir InstagramPost için TikTok yayını başarısızsa retry.
     * Mevcut tt_retry_count kontrolü, MAX_RETRY_COUNT sınırlama.
     */
    public function retry(InstagramPost $post): array;

    /**
     * Token refresh (cron'dan günde 1 kez çağrılır).
     * Refresh başarılı → Setting tablosu güncellenir.
     */
    public function refreshAccessToken(): array;

    /**
     * Token sağlığı (Settings sayfasında "Bağlantıyı Test Et" butonu için).
     */
    public function validateConnection(): array;
}
```

#### Karar mantığı (publish içinde)

```
1. Setting kontrol: tiktok_enabled, tiktok_mode, tiktok_post_mode
2. Token expire kontrolü:
   - tiktok_expires_at < now() + 10dk → önce refreshAccessToken() çağır
3. Post tipini belirle:
   - $post->video_path varsa → video yayını
   - $post->image_path varsa → photo mode (slideshow)
4. Mode kontrolü:
   - tiktok_post_mode == 'inbox' → client->publishToInbox()
   - tiktok_post_mode == 'direct' → client->publishVideo() veya publishPhoto()
5. Publish ID dön → async polling başlat (queue job veya cron)
6. Sonuçta InstagramPost'a yaz:
   - Başarılı: tt_post_id, tt_permalink, tt_published_at
   - Inbox: tt_inbox_id (post_id null kalır)
   - Hata: tt_error_message, tt_retry_count++
```

### 4.4 Asenkron Publish — TikTok'un Özelliği

TikTok publish API **iki aşamalı**:

1. **Init request:** Backend → TikTok'a "yayınla" der → TikTok `publish_id` döner
2. **Async processing:** TikTok video'yu işler (encoding, validation) — 10sn ile
   2 dakika arası sürer
3. **Status fetch:** Backend periyodik `GET /publish/status/fetch/?publish_id=...`
   ile yayının tamamlanmasını kontrol eder
4. **Sonuç:** `status: PUBLISHED` → `tt_post_id` + `tt_permalink` ile sonuçlanır

#### İmplementasyon stratejisi

**Yöntem A: Senkron poll (basit, ilk versiyon)**
```php
$result = $this->client->publishVideo(...);
$publishId = $result['publish_id'];

// 5 sn ara ile 12 kez poll → max 60 sn bekle
for ($i = 0; $i < 12; $i++) {
    sleep(5);
    $status = $this->client->getPublishStatus($publishId);
    if ($status['status'] === 'PUBLISHED') {
        return ['success' => true, 'tt_post_id' => $status['publicaly_available_post_id'], ...];
    }
    if ($status['status'] === 'FAILED') {
        return ['success' => false, 'message' => $status['fail_reason']];
    }
}
return ['success' => false, 'message' => 'TikTok timeout (60sn)'];
```

⚠️ **Sorun:** Cron job'da senkron 60sn beklemek, sonraki cron tetiklemesini
geciktirir. Webhook handler'da 60sn HTTP timeout aşar.

**Yöntem B: Queue job ile poll (önerilen)**
```php
$publishId = ...;
PostgresPostQueueJob::dispatch($postId, $publishId)->delay(10);

// Job içinde 5sn ara ile poll, max 12 deneme
// Hala "PROCESSING" ise kendini tekrar 10sn delay ile dispatch et
// PUBLISHED veya FAILED → InstagramPost güncelle
```

Bu sayede cron bloklamaz, Laravel queue işler.

**Yöntem C: Cron polling (en basit, ama yavaş)**
```php
// Cron her 5dk → tt_inbox_id null DEĞİL + tt_post_id null olan
// post'ları al → her biri için status fetch
Schedule::command('tiktok:poll-status')->everyFiveMinutes();
```

**İlk sürüm için: Yöntem A** (senkron, 60sn timeout). Production'da
sorun çıkarsa Yöntem B'ye geçilir.

### 4.5 Caption + Hashtag Davranışı

`InstagramPost::buildFullCaption()` mevcut (2200 char limit, akıllı kırpma).
TikTok için **aynı method'u kullanıyoruz** — Instagram'la TikTok caption
limiti aynı (2200 char). Yeni bir caption builder gerek yok.

```php
// TiktokService::publish içinde
$caption = $post->buildFullCaption();  // mevcut method, 2200 garantili
```

Bu **kod tasarrufu** + **iki platformda aynı caption** demek (cross-post
amacımızla tutarlı).

### 4.6 Media URL — Public Erişim Şartı

TikTok API public URL ister (private/auth gerektiren URL kabul etmez):

- `image_path`: `public/uploads/...` zaten public erişilir ✓
- `video_path`: aynı, `upload_url($post->video_path)` public ✓

Sadece **production HTTPS** olmalı (TikTok HTTP URL'i reddeder, sandbox bile).

### 4.7 Photo Mode Detayı

TikTok Photo Mode (slideshow) için:

- **Min 1, max 35 görsel** (Instagram Feed carousel ile aynı sınır)
- **Her görsel public URL**
- **Caption + hashtag** her görseli kapsar
- **Otomatik geçiş süresi** TikTok seçer (kullanıcı app'ten değiştirebilir)
- **Müzik ekleme** kullanıcı tarafı (API'den eklenemez)

InstagramPost'tan görselleri toplama:

```php
$imageUrls = [];

// Ana görsel
if ($post->image_path) {
    $imageUrls[] = upload_url($post->image_path);
}

// Carousel (additionalImages)
foreach ($post->additionalImages as $img) {
    $imageUrls[] = upload_url($img->image_path);
}

// Max 35 sınırı
$imageUrls = array_slice($imageUrls, 0, 35);
```

### 4.8 Video Format Gereksinimleri

TikTok video yayını için:

| Özellik | Sınır |
|---|---|
| Format | MP4 / MOV / WebM |
| Max boyut | 287.6 MB |
| Max süre | 60 dakika (Business hesap), 10 dakika (Standard) |
| Min süre | 3 saniye |
| Aspect ratio | Önerilen 9:16, max 1:2.39 / min 1:1.91 |
| Resolution | Min 720×1280, max 4096×2304 |
| Codec | H.264 video, AAC ses |

Bizim mevcut Instagram Reels constraint'leri (3-90sn, 9:16) **TikTok'a uyumlu**.
Ekstra validation gerek yok — Instagram için yapılan client-side check zaten
yeterli.

### 4.9 Privacy + Engagement Ayarları

Her TikTok yayınında 4 ayar gönderiyoruz:

| Ayar | Setting'ten okunan | API'de gönderim |
|---|---|---|
| `privacy_level` | `tiktok_default_privacy` | `PUBLIC_TO_EVERYONE` / `MUTUAL_FOLLOW_FRIENDS` / `SELF_ONLY` |
| `disable_comment` | `tiktok_disable_comment` | bool |
| `disable_duet` | `tiktok_disable_duet` | bool (sadece video) |
| `disable_stitch` | `tiktok_disable_stitch` | bool (sadece video) |

Form'da post bazında override edilebilir mi? **İlk sürümde HAYIR** —
Settings'teki global değerler kullanılır. UI karmaşıklaşmasın diye.

### 4.10 Dependency Injection (Laravel Container)

`AppServiceProvider::register()`'a binding:

```php
$this->app->bind(\App\Services\Tiktok\TiktokApiClient::class, function ($app) {
    $mode = Setting::getValue('tiktok_mode', 'sandbox');
    $baseUrl = $mode === 'production'
        ? config('services.tiktok.base_url_production')
        : config('services.tiktok.base_url_sandbox');

    return new TiktokApiClient(
        accessToken: (string) Setting::getValue('tiktok_access_token', ''),
        baseUrl: $baseUrl,
    );
});
```

`config/services.php`'e ekleme:

```php
'tiktok' => [
    'base_url_sandbox'    => env('TIKTOK_BASE_URL_SANDBOX', 'https://open-sandbox.tiktokapis.com'),
    'base_url_production' => env('TIKTOK_BASE_URL_PRODUCTION', 'https://open.tiktokapis.com'),
],
```

### 4.11 Test Edilebilirlik

`TiktokApiClient` ayrı bir sınıf olduğu için `Http::fake()` ile test edilebilir:

```php
// tests/Feature/TiktokServiceTest.php
Http::fake([
    'open-sandbox.tiktokapis.com/v2/post/publish/content/init/' => Http::response([
        'data' => ['publish_id' => 'test_publish_123'],
    ]),
    'open-sandbox.tiktokapis.com/v2/post/publish/status/fetch/*' => Http::response([
        'data' => ['status' => 'PUBLISHED', 'publicly_available_post_id' => ['7234...']],
    ]),
]);

$service->publish($post);
// ... assertions
```

Test yazımı **Bölüm 9**'da. İlk sürümde manuel sandbox testi yeterli.

### 4.12 Kontrol Listesi (Bölüm 4)

- [ ] `app/Services/Tiktok/TiktokApiClient.php` oluşturulacak
- [ ] `app/Services/Tiktok/TiktokApiException.php` oluşturulacak
- [ ] `app/Services/TiktokService.php` oluşturulacak
- [ ] `config/services.php`'e `tiktok` bloğu eklenecek
- [ ] `AppServiceProvider::register()`'a binding eklenecek
- [ ] `TiktokApiClient` 6 method: publishPhoto, publishVideo, publishToInbox,
      getPublishStatus, refreshToken, getUserInfo
- [ ] `TiktokService` 4 method: publish, retry, refreshAccessToken, validateConnection
- [ ] Senkron poll (Yöntem A) implementasyonu — max 60sn timeout
- [ ] Caption: `InstagramPost::buildFullCaption()` kullanımı (kod tasarrufu)
- [ ] Privacy + engagement ayarları Setting'ten okunması
- [ ] Hata yönetimi: `TiktokApiException` + `try/catch` + log

Sonraki bölüm: **5. Cross-Post Entegrasyonu** — `InstagramService::publish`
sonunda TiktokService nasıl çağrılacak, Facebook pattern'i ile aynı yapı.

---

## 5. Cross-Post Entegrasyonu — `InstagramService::publish` Sonuna Ekleme

Mevcut `InstagramService::publish` zaten Instagram yayını yapıyor ve sonra
`publish_to_facebook` true ise Facebook'a kopya atıyor. Aynı pattern ile
TikTok ekleniyor.

### 5.1 Mevcut FB Cross-Post Akışı (Referans)

`app/Services/InstagramService.php::publish()` özet:

```
1. Token kontrolü
2. Caption build (buildFullCaption)
3. Media container oluştur (Meta Graph API)
4. Publish container
5. ig_post_id + permalink kaydet
6. publish_to_facebook=true ise → fb_publish_single() veya fb_publish_multi()
7. fb_post_id + fb_permalink kaydet (varsa)
```

FB hatası **IG yayınını ASLA bozmaz** — try/catch içinde sessizce log + bell.

### 5.2 TikTok Cross-Post Akışı (Eklenecek)

`InstagramService::publish()` sonuna yeni bir blok:

```
... (FB cross-post bittikten sonra) ...

8. publish_to_tiktok=true ise:
   - TiktokService::publish($post) çağır
   - Başarılı: tt_post_id, tt_permalink, tt_published_at güncelle
   - Hata: tt_error_message, tt_retry_count++ (try/catch içinde)
9. Sonuç dön (IG yayını her durumda başarılı sayılır)
```

### 5.3 Tasarım — TikTok Hataları İzole Edilir

TikTok cross-post'un Instagram yayınını **bozmaması** kritik. Aynı pattern FB
için kullanılıyor:

```php
// InstagramService::publish() sonuna eklenecek blok (simplified):

if ($post->publish_to_tiktok && Setting::getValue('tiktok_enabled', '0') === '1') {
    try {
        $tt = app(\App\Services\TiktokService::class);
        $result = $tt->publish($post);

        if ($result['success']) {
            $post->update([
                'tt_post_id'       => $result['tt_post_id'] ?? null,
                'tt_permalink'     => $result['tt_permalink'] ?? null,
                'tt_published_at'  => now(),
                'tt_error_message' => null,
                'tt_inbox_id'      => $result['tt_inbox_id'] ?? null,
            ]);
            Log::info('TikTok cross-post başarılı', [
                'post_id'    => $post->id,
                'tt_post_id' => $result['tt_post_id'] ?? null,
                'inbox'      => isset($result['tt_inbox_id']),
            ]);
        } else {
            $post->update([
                'tt_error_message' => $result['message'],
                'tt_retry_count'   => $post->tt_retry_count + 1,
            ]);
            Log::warning('TikTok cross-post başarısız (IG yayını etkilenmedi)', [
                'post_id' => $post->id,
                'error'   => $result['message'],
                'retry'   => $post->tt_retry_count + 1,
            ]);
        }
    } catch (\Throwable $e) {
        // TikTok exception ASLA IG yayını bozmaz — sadece log
        $post->update([
            'tt_error_message' => 'Exception: ' . $e->getMessage(),
            'tt_retry_count'   => $post->tt_retry_count + 1,
        ]);
        Log::error('TikTok cross-post exception (IG yayını etkilenmedi)', [
            'post_id' => $post->id,
            'error'   => $e->getMessage(),
        ]);
    }
}
```

### 5.4 Cron'dan Retry Akışı

Mevcut cron (`instagram:publish-scheduled`) her 5dk:

- Status `Scheduled` post'ları publish
- Status `Failed` + `retry_count < MAX` post'ları yeniden dene

TikTok için ayrı cron gerek **yok** — IG akışının içinde tetikleniyor. Ama
**bir senaryo var**: IG zaten yayınlandı (status=Published), TT fail oldu.
Bu durumda IG'yi yeniden yayınlamayız (zaten yayında). Sadece TT için retry
gerek.

#### Çözüm: Yeni cron veya mevcut cron'a TT-only branch

**Yöntem A: Mevcut cron'a TT-only branch (önerilen)**

`InstagramPost::scopeDue()` mevcut Scheduled/Failed post'ları seçiyor.
Ek olarak **TT-only retry** scope'u:

```php
// InstagramPost.php
public function scopeTiktokRetryDue(Builder $query): Builder {
    return $query
        ->where('status', InstagramPostStatus::Published->value)  // IG zaten yayında
        ->where('publish_to_tiktok', true)
        ->whereNull('tt_post_id')                                  // TT henüz yayınlanmamış
        ->whereNull('tt_inbox_id')                                 // Inbox modunda değil
        ->where('tt_retry_count', '<', self::MAX_RETRY_COUNT);
}
```

`PublishScheduledInstagramPosts` command'a ek block:

```php
// Mevcut Scheduled/Failed batch'inden sonra:
$ttRetryPosts = InstagramPost::tiktokRetryDue()->limit(5)->get();
foreach ($ttRetryPosts as $post) {
    try {
        $tt = app(TiktokService::class);
        $tt->retry($post);  // İçeride tt_retry_count++ ve sonuç yazımı
    } catch (\Throwable $e) {
        Log::warning('TT retry exception', ['post_id' => $post->id, 'err' => $e->getMessage()]);
    }
}
```

**Yöntem B: Ayrı cron `tiktok:retry-failed`** — gereksiz karmaşıklık, A yeter.

### 5.5 Tek Tek vs Toplu — TikTok'ta Carousel İçin Özel Durum

Feed Post **carousel** (10 görsel) için TikTok Photo Mode tek bir post.
Tüm görseller tek `publishPhoto` call'unda yollanır. FB için
`fb_publish_multi` ayrı method'tu çünkü FB carousel ayrı, TT'de tek.

### 5.6 Token Expire Senaryosu

Cross-post tetiklendiğinde token expire olmuş olabilir:

```php
// TiktokService::publish başında:
$expiresAt = Setting::getValue('tiktok_expires_at');
if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
    // Refresh dene
    $refreshResult = $this->refreshAccessToken();
    if (! $refreshResult['success']) {
        return [
            'success' => false,
            'message' => 'TikTok token süresi dolmuş, refresh başarısız: ' . $refreshResult['message'],
        ];
    }
    // ApiClient'ı yeni token ile yeniden bind
    $this->client = app(TiktokApiClient::class);
}
```

Bonus: günlük cron `tiktok:refresh-token` zaten varolan token'ı yeniler (Bölüm 6'da).

### 5.7 Bildirimler

Cross-post fail olunca:

1. **Log warning** (storage/logs/laravel.log)
2. **AiLogObserver YOK** — bu IG/AI değil cross-post, ayrı bildirim path'i gerek

Bunun için TiktokService::publish içinde fail olunca **manuel Telegram + bell**:

```php
TelegramNotifier::notifyAdminError(
    title: 'TikTok cross-post başarısız',
    context: [
        'post_id'  => $post->id,
        'caption'  => mb_strimwidth($post->caption ?? '', 0, 60, '…'),
        'retry'    => $post->tt_retry_count,
        'hata'     => $errorMessage,
    ],
    url: route('admin.instagram-posts.show', $post->id),
    cacheKey: 'tt_cross_post_fail:' . md5($post->id . '|' . $errorMessage),
);

NotificationCenter::send(
    type: 'tiktok_cross_post_failed',
    title: 'TikTok cross-post başarısız',
    message: "Post #{$post->id} — " . $errorMessage,
    level: AdminNotification::LEVEL_WARNING,
    icon: 'bi-tiktok',
    actionUrl: route('admin.instagram-posts.show', $post->id),
);
```

**Kalıcı hata** (`tt_retry_count >= MAX`) ayrı tetikleyici:

```php
if ($post->tt_retry_count >= InstagramPost::MAX_RETRY_COUNT) {
    NotificationCenter::sendCritical(
        title: 'TikTok cross-post kalıcı hata',
        message: "Post #{$post->id} 3 deneme sonrası TikTok'a paylaşılamadı. Manuel müdahale gerek.",
        actionUrl: route('admin.instagram-posts.edit', $post->id),
    );
}
```

### 5.8 Idempotency — Çift Yayın Koruması

`tt_post_id` zaten doluysa **yeniden publish çağrılmamalı** (idempotency):

```php
// TiktokService::publish() başında:
if ($post->tt_post_id !== null) {
    return [
        'success'      => true,
        'message'      => 'Bu post zaten TikTok\'ta yayında',
        'tt_post_id'   => $post->tt_post_id,
        'tt_permalink' => $post->tt_permalink,
    ];
}
```

Cron tarafından retry'a takılsa bile çift yayın olmaz.

### 5.9 Sınırlar ve Rate Limit

TikTok rate limit:
- **50 video / gün / kullanıcı** (Content Posting API)
- **10 request / dakika**

Bizim cron 4 blog/gün × 1 cross-post = 4 video/gün → çok altında. Manuel post'larla
toplam günlük 10'u geçmek çok zor. Yine de defensive throttle:

```php
// TiktokService::publish() başında — Cache::has guard
$key = 'tt_publish_throttle:' . now()->format('YmdHi');  // dakika başı bucket
$count = Cache::get($key, 0);
if ($count >= 8) {  // 10/min sınırından güvenli pay
    return [
        'success' => false,
        'message' => 'TikTok rate limit (10/dakika). Bir sonraki cron turunda denenecek.',
    ];
}
Cache::put($key, $count + 1, now()->addMinutes(2));
```

### 5.10 Kontrol Listesi (Bölüm 5)

- [ ] `InstagramService::publish()` sonuna TT cross-post block eklenecek
- [ ] `try/catch` ile **IG yayını ASLA bloke olmayacak**
- [ ] Başarılı: `tt_post_id`, `tt_permalink`, `tt_published_at` update
- [ ] Inbox modu: `tt_inbox_id` set
- [ ] Hata: `tt_error_message` + `tt_retry_count++`
- [ ] `InstagramPost::scopeTiktokRetryDue()` eklenecek (cron retry için)
- [ ] `PublishScheduledInstagramPosts` command'a TT-only retry block
- [ ] Token expire kontrolü `TiktokService::publish` başında
- [ ] Idempotency: `tt_post_id` doluysa skip
- [ ] Rate limit guard (10/dakika, Cache::has pattern)
- [ ] Fail → Telegram + bell bildirim (cacheKey ile throttle)
- [ ] Kalıcı hata (`retry >= MAX`) → `NotificationCenter::sendCritical`

Sonraki bölüm: **6. Form UI + show/edit rozeti + Recovery butonları** —
admin paneli tarafı.

---

## 6. Form UI + show/edit Rozeti + Recovery Butonları

Üç değişiklik:
1. **Form**: Yayın Planı kartına "TikTok'a paylaş" checkbox
2. **Show/edit**: TikTok durum rozeti (yeşil/sarı/kırmızı)
3. **Recovery**: TikTok başarısızsa "Şimdi Yayınla" + "Retry Sıfırla" butonları

Mevcut Facebook UI pattern'i ile aynı yaklaşım — tutarlılık.

### 6.1 Form Tarafı — `publish-plan.blade.php`

Şu an Facebook checkbox'ı var (`ig-target-check--fb`). Yanına TikTok eklenecek:

```html
<label class="ig-target-check ig-target-check--ig">
    <input type="checkbox" checked disabled>
    <span class="ig-target-icon"><i class="bi bi-instagram"></i></span>
    <span class="ig-target-info">
        <strong>Instagram</strong>
        <small>Her zaman paylaşılır</small>
    </span>
</label>

<label class="ig-target-check ig-target-check--fb {{ ! $fbConfigured ? 'ig-target-disabled' : '' }}">
    <input type="checkbox" name="publish_to_facebook" value="1" ...>
    <span class="ig-target-icon"><i class="bi bi-facebook"></i></span>
    <span class="ig-target-info">
        <strong>Facebook Sayfası</strong>
        ...
    </span>
</label>

{{-- YENİ: TikTok --}}
<label class="ig-target-check ig-target-check--tt {{ ! $ttConfigured ? 'ig-target-disabled' : '' }}"
       data-ig-tt-toggle>
    <input type="hidden" name="publish_to_tiktok" value="0">
    <input type="checkbox" name="publish_to_tiktok" value="1"
           {{ $publishToTt === '1' ? 'checked' : '' }}
           {{ ! $ttConfigured || $isPublished ? 'disabled' : '' }}>
    <span class="ig-target-icon"><i class="bi bi-tiktok"></i></span>
    <span class="ig-target-info">
        <strong>TikTok</strong>
        <small data-ig-tt-hint>
            @if($ttConfigured)
                @if($ttPostMode === 'inbox')
                    Inbox'a düşer, mobilde yayınla
                @else
                    Otomatik yayınlanır
                @endif
            @else
                <span class="text-warning">Bağlantı kurulu değil — <a href="{{ route('admin.settings.index') }}#stg-tiktok">Ayarlar</a></span>
            @endif
        </small>
    </span>
</label>
```

#### Form için yardımcı değişkenler (parent view'da)

`_form.blade.php` veya `instagram-posts/edit.blade.php` controller'dan
gelen değişkenler:

```php
// InstagramPostController::create() / edit() içinde:
$ttConfigured = Setting::getValue('tiktok_enabled', '0') === '1'
    && trim(Setting::getValue('tiktok_access_token', '')) !== '';
$ttPostMode = Setting::getValue('tiktok_post_mode', 'inbox');
$publishToTt = old('publish_to_tiktok', $isEdit ? ($post->publish_to_tiktok ? '1' : '0') : ($ttConfigured ? '1' : '0'));
```

#### Reels mode'unda otomatik default

JS mevcut `applyMediaContext()` içine:

```js
// Reels seçilince TT checkbox otomatik check olsun (eğer configured ise)
if (activeType === 'reels') {
    var ttCheckbox = document.querySelector('input[name="publish_to_tiktok"][value="1"]');
    if (ttCheckbox && ! ttCheckbox.disabled && ! ttCheckbox.dataset.userInteracted) {
        ttCheckbox.checked = true;
    }
}
```

Kullanıcı checkbox'a manuel dokunduysa (`userInteracted=1`) override etmeyiz.

### 6.2 Show Sayfası — TikTok Durum Rozeti

`/admin/instagram-posts/{id}/show` sayfasında Facebook bilgisi var:

```
🟢 Facebook: Paylaşıldı (12.05.2026 14:30) → FB'de Gör
```

Aşağısına TikTok satırı:

```html
@php
    $ttBadge = match (true) {
        $post->isTikTokPublished()        => ['cls' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Paylaşıldı'],
        $post->isTikTokFailed()           => ['cls' => 'danger',  'icon' => 'bi-x-octagon-fill',    'label' => 'Kalıcı Hata'],
        $post->tt_inbox_id !== null       => ['cls' => 'warning', 'icon' => 'bi-inbox-fill',       'label' => 'Inbox\'ta'],
        $post->publish_to_tiktok          => ['cls' => 'secondary','icon' => 'bi-clock-history',   'label' => 'Bekliyor'],
        default                            => null,
    };
@endphp

@if ($ttBadge)
    <div class="ig-show-meta-row">
        <span class="ig-show-meta-label">
            <i class="bi bi-tiktok me-1"></i> TikTok
        </span>
        <span class="badge bg-{{ $ttBadge['cls'] }}">
            <i class="bi {{ $ttBadge['icon'] }} me-1"></i> {{ $ttBadge['label'] }}
        </span>
        @if ($post->tt_published_at)
            <small class="text-muted ms-2">
                {{ $post->tt_published_at->format('d.m.Y H:i') }}
            </small>
        @endif
        @if ($post->tt_permalink)
            <a href="{{ $post->tt_permalink }}" target="_blank" rel="noopener"
               class="btn-glass btn-glass-sm ms-2">
                <i class="bi bi-box-arrow-up-right me-1"></i> TikTok'ta Gör
            </a>
        @endif
        @if ($post->tt_error_message)
            <div class="small text-danger mt-1">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ $post->tt_error_message }}
                @if ($post->tt_retry_count > 0)
                    (deneme: {{ $post->tt_retry_count }}/{{ \App\Models\InstagramPost::MAX_RETRY_COUNT }})
                @endif
            </div>
        @endif
    </div>
@endif
```

### 6.3 Edit Sayfası — Recovery Butonları

`publish-plan.blade.php` partial'da Facebook için "Şimdi Yayınla" varsa
benzer pattern TikTok için. Ama dikkat: TikTok cross-post **post zaten yayında**
olduktan sonra çalışıyor, yani "Şimdi Yayınla" mantığı farklı.

#### Senaryo bazlı butonlar

```html
{{-- Recovery aksiyonları (mevcut publish-plan partial içinde) --}}
@if ($post->publish_to_tiktok && $post->status === \App\Enums\InstagramPostStatus::Published)
    <div class="mt-3">
        @if ($post->isTikTokPublished())
            {{-- TT başarılı — sadece info, butona gerek yok --}}
            <small class="text-success">
                <i class="bi bi-check-circle-fill me-1"></i> TikTok'ta yayında
            </small>
        @elseif ($post->isTikTokFailed())
            {{-- Kalıcı hata — retry sıfırlama + şimdi dene butonları --}}
            <div class="d-grid gap-2">
                <form method="POST" action="{{ route('admin.instagram-posts.tt-publish-now', $post->id) }}" class="ig-tt-publish-now-form">
                    @csrf
                    <button type="submit" class="btn-teal w-100">
                        <i class="bi bi-tiktok me-1"></i> Şimdi TikTok'a Paylaş
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.instagram-posts.tt-reset-retry', $post->id) }}" class="ig-tt-reset-retry-form">
                    @csrf
                    <button type="submit" class="btn-glass w-100 text-warning">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        TikTok Retry Sayacını Sıfırla
                    </button>
                </form>
                <small class="text-muted">
                    Cron yeniden denesin. Önce caption uzunluğu / token / Settings kontrol et.
                </small>
            </div>
        @elseif ($post->tt_inbox_id !== null)
            {{-- Inbox modu — manuel yayın hatırlatması --}}
            <div class="alert alert-warning small mb-0 mt-2">
                <i class="bi bi-inbox-fill me-1"></i>
                <strong>TikTok Inbox'a düştü.</strong> Mobilde TikTok aç →
                Inbox → Drafts → "Paylaş" tıkla.
            </div>
        @else
            {{-- Bekliyor (retry < MAX) — cron otomatik denecek, manuel tetikleme opsiyonel --}}
            <div class="d-grid">
                <form method="POST" action="{{ route('admin.instagram-posts.tt-publish-now', $post->id) }}" class="ig-tt-publish-now-form">
                    @csrf
                    <button type="submit" class="btn-glass w-100">
                        <i class="bi bi-tiktok me-1"></i> Şimdi TikTok'a Dene
                    </button>
                </form>
                <small class="text-muted mt-1">
                    Cron 5dk içinde otomatik denenir. Manuel tetiklemek için yukarı.
                </small>
            </div>
        @endif
    </div>
@endif
```

### 6.4 Yeni Route'lar

```php
// routes/admin.php
Route::post('instagram-posts/{instagramPost}/tt-publish-now',
    [InstagramPostController::class, 'tiktokPublishNow'])
    ->name('instagram-posts.tt-publish-now');

Route::post('instagram-posts/{instagramPost}/tt-reset-retry',
    [InstagramPostController::class, 'tiktokResetRetry'])
    ->name('instagram-posts.tt-reset-retry');
```

### 6.5 Yeni Controller Method'ları

`InstagramPostController` içine:

```php
public function tiktokPublishNow(InstagramPost $instagramPost): RedirectResponse
{
    if ($instagramPost->status !== InstagramPostStatus::Published) {
        return back()->with('error', 'Önce Instagram\'da yayınlanmış olmalı.');
    }
    if (! $instagramPost->publish_to_tiktok) {
        return back()->with('error', 'Bu post için "TikTok\'a paylaş" işaretli değil.');
    }
    if ($instagramPost->tt_post_id !== null) {
        return back()->with('info', 'TikTok\'ta zaten yayınlanmış.');
    }

    try {
        $tt = app(TiktokService::class);
        $result = $tt->publish($instagramPost);

        if ($result['success']) {
            $instagramPost->update([
                'tt_post_id'      => $result['tt_post_id'] ?? null,
                'tt_permalink'    => $result['tt_permalink'] ?? null,
                'tt_published_at' => now(),
                'tt_error_message'=> null,
            ]);
            return back()->with('success', 'TikTok\'a paylaşıldı.');
        } else {
            $instagramPost->update([
                'tt_error_message' => $result['message'],
                'tt_retry_count'   => $instagramPost->tt_retry_count + 1,
            ]);
            return back()->with('error', 'TikTok paylaşımı başarısız: ' . $result['message']);
        }
    } catch (\Throwable $e) {
        Log::error('TT manual publish exception', ['post_id' => $instagramPost->id, 'err' => $e->getMessage()]);
        return back()->with('error', 'Beklenmedik hata: ' . $e->getMessage());
    }
}

public function tiktokResetRetry(InstagramPost $instagramPost): RedirectResponse
{
    if (! $instagramPost->publish_to_tiktok) {
        return back()->with('error', 'Bu post için TikTok aktif değil.');
    }

    $instagramPost->update([
        'tt_retry_count'   => 0,
        'tt_error_message' => null,
    ]);

    return back()->with('success', 'TikTok retry sayacı sıfırlandı. Cron 5dk içinde yeniden deneyecek.');
}
```

### 6.6 AdminModal Confirm — JS Tarafı

`instagram-posts-form.js`'e (mevcut `ig-publish-now-form` pattern'i ile aynı):

```js
document.querySelectorAll('.ig-tt-publish-now-form, .ig-tt-reset-retry-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (typeof AdminModal === 'undefined') return;
        if (form.dataset.confirmed === '1') return;
        e.preventDefault();

        var isReset = form.classList.contains('ig-tt-reset-retry-form');
        AdminModal.confirm({
            title: isReset ? 'TikTok Retry Sıfırla' : 'TikTok\'a Şimdi Paylaş',
            message: isReset
                ? 'Retry sayacı 0\'a sıfırlanacak. Cron 5dk içinde yeniden deneyecek. ' +
                  'Önce Settings\'te token + Photo Mode aktif mi kontrol et.'
                : 'Bu post hemen TikTok\'a paylaşılacak (Direct Post veya Inbox).',
            type: 'warning',
            confirmText: isReset ? 'Sıfırla' : 'Evet, Paylaş',
            confirmIcon: isReset ? 'bi bi-arrow-counterclockwise' : 'bi bi-tiktok',
        }).then(function (ok) {
            if (! ok) return;
            form.dataset.confirmed = '1';
            form.submit();
        });
    });
});
```

### 6.7 CSS — Yeni Class'lar

`public/assets/admin/css/styles.css`'e ekleme:

```css
.ig-target-check--tt {
    border-color: rgba(255, 255, 255, 0.1);
}
.ig-target-check--tt input[type="checkbox"]:checked + .ig-target-icon {
    background: linear-gradient(135deg, #25F4EE 0%, #FE2C55 100%);
    color: #fff;
}
.ig-target-check--tt .ig-target-icon {
    color: #FE2C55;  /* TikTok pink */
}
```

### 6.8 Index Sayfasında TikTok Sütunu

`/admin/instagram-posts` listesinde her satır için TikTok durumu mini badge:

```html
{{-- Mevcut "Facebook" badge'in yanına --}}
@if ($post->publish_to_tiktok)
    @if ($post->isTikTokPublished())
        <span class="badge bg-success-subtle text-success" title="TikTok'ta yayında">
            <i class="bi bi-tiktok"></i>
        </span>
    @elseif ($post->isTikTokFailed())
        <span class="badge bg-danger-subtle text-danger" title="TikTok kalıcı hata">
            <i class="bi bi-tiktok"></i>
        </span>
    @elseif ($post->tt_inbox_id)
        <span class="badge bg-warning-subtle text-warning" title="TikTok Inbox'ta">
            <i class="bi bi-tiktok"></i>
        </span>
    @else
        <span class="badge bg-secondary-subtle text-secondary" title="TikTok bekliyor">
            <i class="bi bi-tiktok"></i>
        </span>
    @endif
@endif
```

### 6.9 Kontrol Listesi (Bölüm 6)

- [ ] `publish-plan.blade.php`'e TikTok checkbox eklenecek
- [ ] Form controller'da `$ttConfigured`, `$ttPostMode`, `$publishToTt` hazırlanacak
- [ ] Reels seçilince TT checkbox otomatik check (userInteracted guard)
- [ ] Show sayfasında TikTok durum rozeti (4 senaryo: published / failed / inbox / bekliyor)
- [ ] Edit sayfasında 4 senaryo recovery UI (success info, failed buttons, inbox alert, bekliyor manual)
- [ ] 2 yeni route: `tt-publish-now`, `tt-reset-retry`
- [ ] 2 controller method: `tiktokPublishNow`, `tiktokResetRetry`
- [ ] AdminModal confirm JS (form submit interceptor)
- [ ] CSS: `.ig-target-check--tt` TikTok gradient
- [ ] Index listede TikTok mini badge (her satır)
- [ ] InstagramPost::$fillable'da `publish_to_tiktok` (zaten Bölüm 3'te eklendi)

Sonraki bölüm: **7. Otomatik blog → TikTok akışı** — BlogToInstagramService
güncellemesi.

---

## 7. Otomatik Blog → TikTok Akışı

Cron blog yazısı üretir → `BlogGenerationService::generate` → blog yayınlanır →
`BlogToInstagramService::shareBlog` çağrılır → yeni `InstagramPost` satırı
oluşur. Bu satırın `publish_to_tiktok` alanına global Setting'e göre değer
atayacağız. Sonrası mevcut akış: cron her 5dk publish, IG yayınlandıktan sonra
TT cross-post (Bölüm 5).

### 7.1 Mevcut Akış — `BlogToInstagramService::shareBlog`

Şu an:

```php
public function shareBlog(BlogPost $blogPost): ?InstagramPost
{
    // 1. AI ile caption + hashtag üret
    $aiResult = $this->fetchAiContent($blogPost);
    $caption  = $this->buildCaption($blogPost, $aiResult);
    $hashtags = $this->buildHashtags($blogPost, $aiResult);

    // 2. Görsel kopyala (blog kapak → IG post)
    // ...

    // 3. InstagramPost oluştur
    return InstagramPost::create([
        'caption'              => $caption,
        'hashtags'             => $hashtags,
        'image_path'           => $imagePath,
        'media_type'           => InstagramMediaType::Image,
        'status'               => InstagramPostStatus::Scheduled,
        'scheduled_at'         => now(),
        'blog_post_id'         => $blogPost->id,
        'publish_to_facebook'  => $this->shouldShareToFacebook(),  // global setting'ten
    ]);
}
```

### 7.2 Eklenecek Değişiklik

Tek satır: `publish_to_tiktok` aynı pattern'le set edilecek.

```php
return InstagramPost::create([
    // ... mevcut alanlar ...
    'publish_to_facebook'  => $this->shouldShareToFacebook(),
    'publish_to_tiktok'    => $this->shouldShareToTiktok(),  // YENİ
]);
```

### 7.3 `shouldShareToTiktok()` Helper

```php
/**
 * Otomatik blog yazılarında TikTok cross-post yapılsın mı?
 *
 * 3 koşul birden:
 *  - tiktok_enabled = '1'  (master switch)
 *  - tiktok_auto_share_blog = '1'  (otomatik blog için ayrıca opt-in)
 *  - tiktok_access_token dolu (OAuth bağlı)
 */
private function shouldShareToTiktok(): bool
{
    if (Setting::getValue('tiktok_enabled', '0') !== '1') {
        return false;
    }
    if (Setting::getValue('tiktok_auto_share_blog', '0') !== '1') {
        return false;
    }
    if (trim(Setting::getValue('tiktok_access_token', '')) === '') {
        return false;
    }
    return true;
}
```

İki ayrı toggle (`tiktok_enabled` + `tiktok_auto_share_blog`) sebebi:
- Genel TikTok'u kapatmadan, sadece **otomatik blog** post'larını TT'den
  hariç tutmak isteyebilirsin (örn. manuel post'lar TT'ye gitsin ama
  otomatik blog gitmesin)

### 7.4 Manuel Test Adımları

Bu davranışı test etmek için (kod yazıldıktan sonra):

1. Settings → TikTok → "TikTok'u Etkinleştir" ✓
2. Settings → TikTok → "Otomatik blog yazılarını gönder" ✓
3. Settings → TikTok → OAuth bağla (access_token dolu olmalı)
4. Admin panel'den **manuel** cron tetikle:
   ```bash
   php artisan blog:generate
   ```
5. `/admin/ai-logs` → son log → "Başarılı" → BlogPost oluşturuldu
6. `/admin/instagram-posts` → en üst satır:
   - `publish_to_facebook = true`
   - `publish_to_tiktok = true` ← YENİ
7. 5dk içinde cron `instagram:publish-scheduled` tetiklenir:
   - IG'ye yayınlanır
   - FB'ye kopya gider
   - TT'ye kopya gider (Photo Mode)
8. 1-2 dakika sonra TikTok hesabında görseli + caption + hashtag'i gör

### 7.5 Mevcut Otomatik Blog'lar İçin Geriye Dönük

Migration default `false` olduğundan **mevcut otomatik blog'lar TT'ye düşmez**.
Sadece **bu commit'ten sonra** üretilen otomatik blog'lar TT'ye dahil.

Eğer eski post'ları da TT'ye paylaşmak istersen:

```sql
-- Son 7 günde yayınlanmış, henüz TT'ye düşmemiş otomatik blog post'ları
UPDATE instagram_posts
SET publish_to_tiktok = 1
WHERE blog_post_id IS NOT NULL
  AND status = 'published'
  AND publish_to_tiktok = 0
  AND tt_post_id IS NULL
  AND created_at > NOW() - INTERVAL 7 DAY;
```

Sonra `scopeTiktokRetryDue` cron'u (Bölüm 5.4) bu satırları yakalayıp TT'ye
gönderir.

### 7.6 Bulk Import Akışı

Bulk import (Excel'den toplu post oluşturma) **şu an** her satıra
`publish_to_facebook` value'sunu Setting'ten alıyor. TikTok için aynı:

```php
// app/Services/InstagramBulkImportService.php (veya benzer) içinde:
$payload = [
    // ...
    'publish_to_facebook' => $this->shouldShareToFacebook(),
    'publish_to_tiktok'   => $this->shouldShareToTiktok(),  // YENİ (BlogToInstagram pattern)
];
```

⚠️ **Karar:** Bulk import için ayrı bir Setting toggle gerekli mi
(`tiktok_auto_share_bulk` gibi)? **HAYIR** — `tiktok_auto_share_blog`
adından "blog" çıkarıp `tiktok_auto_share_automated` yapmak daha doğru.
İlk sürümde basitlik için: bulk import da blog ile aynı toggle'a tabi
(`tiktok_auto_share_blog` — ismi biraz yanıltıcı ama kararı sonra net'le).

### 7.7 Cron Token Refresh Bağımlılığı

Otomatik blog → TT akışı çalışsın diye **token sağlığı** kritik. Eğer
`tiktok_access_token` expire olmuşsa:

- `TiktokService::publish` başında refresh denenir (Bölüm 5.6)
- Cron `tiktok:refresh-token` günde 1 kez preemptive refresh yapar (Bölüm 6)

Bu sayede sabaha gün boyu çalışan otomatik blog cron'ları token sorunu
yaşamaz.

### 7.8 Bildirim Akışı (Otomatik Akışta)

Cron başarısız bir TT cross-post yaparsa:

1. **Log warning:** `storage/logs/laravel.log`
2. **InstagramPost** `tt_error_message` + `tt_retry_count++`
3. **Telegram bildirim** (TiktokService içinden — Bölüm 5.7)
4. **Bell bildirim** (NotificationCenter)
5. **Audit log** (otomatik — InstagramPost zaten AuditObserver'a bağlı,
   `tt_post_id` update'i logger'a düşer)

Yani admin paneli açıkken sağ üstte bell sallanır, mobilde Telegram düşer.
İki yerden de log detayına gidilir.

### 7.9 Kontrol Listesi (Bölüm 7)

- [ ] `BlogToInstagramService::shareBlog` içine `publish_to_tiktok` set
- [ ] `shouldShareToTiktok()` helper method (3 koşul kontrolü)
- [ ] `InstagramBulkImportService` (veya benzeri) içine aynı pattern
- [ ] Setting'te `tiktok_auto_share_blog` toggle (Bölüm 9.x Settings UI)
- [ ] Manuel test: blog cron → IG+FB+TT 4-platform yayın
- [ ] (Opsiyonel) Geriye dönük SQL ile eski post'lar için flag set

Sonraki bölüm: **8. Audit Süreci + Direct Post Geçişi** — TikTok app
audit'i ne zaman gerekli, Inbox → Direct Post geçiş adımları.

---

## 8. Audit Süreci + Direct Post Geçişi

İlk sürüm **Inbox modunda** çalışacak — TikTok audit gerek yok, hemen
kullanılabilir. Audit onaylanınca **Direct Post moduna** geçiş tek
Setting toggle ile. Bu bölüm o geçişi netleştirir.

### 8.1 Audit Ne Zaman Gerek

| Senaryo | Audit Gerek mi |
|---|---|
| Sandbox testleri (kendi hesabıma yayın) | ❌ Hayır |
| Inbox modu (video TT app'ine düşer) | ❌ Hayır |
| **Direct Post production (anında yayın)** | ✅ Evet |
| Photo Mode production | ✅ Evet (video.publish kapsamında) |

Yani **audit olmadan** çalışan tek senaryo: Inbox modu. Bu yeterli "yarı-otomatik"
sistem — sen mobile TikTok aç → Inbox → "Paylaş" tıkla, 2 saniyede manuel
adım.

Audit onaylanınca **tam otomatik** olur.

### 8.2 Audit Başvurusu — Detaylı Adımlar

Bölüm 2.7'de özet vardı, burada detay:

#### 8.2.1 Hazırlık (kod yazılırken paralel yapılabilir)

1. **Privacy Policy** — `https://orhanbabaninciftligi.com/gizlilik`
   - TikTok'un bizim sunucudan veri aldığı/verdiği bilgisi
   - Hangi kullanıcı verisinin TikTok'a iletildiği
   - Veri saklama süresi
   - Üçüncü taraf (TikTok) işleme amacı

2. **Terms of Service** — `https://orhanbabaninciftligi.com/kullanim-sartlari`
   - Kullanıcı sorumlulukları
   - Servis sınırları

3. **Use case açıklaması** (300-500 kelime)
   - Örnek metin:
   > "Orhan Babanın Çiftliği, Çorum Büyük Palabıyık Köyü'nde aile işletmesi
   > olarak doğal süt ürünleri üretiyor. Web sitemiz üzerinden yapay zeka
   > yardımıyla otomatik olarak ürünlerimizi tanıtan blog yazıları
   > üretiyor ve bu içerikleri farklı sosyal medya platformlarına otomatik
   > paylaşıyoruz. TikTok Content Posting API'sini kullanarak, blog
   > yazılarımızın görsel + caption özetlerini Photo Mode formatında ve
   > Reels videolarımızı kendi TikTok hesabımıza otomatik yayınlamak
   > istiyoruz. Yayın hedefi sadece kendi hesabımız (bizim sahip
   > olduğumuz), kullanıcı verisi toplamıyor veya 3. tarafa iletmiyoruz.
   > Günlük ortalama 4 cron paylaşımı + manuel müdahale = max 10 post/gün."

4. **Demo video** (30-60 saniye)
   - Ekran kaydı: `/admin/instagram-posts/create` → Reels seç → video yükle
     → Settings'te TikTok bağlı → Yayınla → TikTok'ta görünme
   - Üst kısımda Türkçe veya İngilizce alt yazı: "Step 1: Create post..."

5. **Domain verification** — TXT record veya `.well-known` (Bölüm 2.8)

#### 8.2.2 Başvuru Formu

TikTok Developer Console → app → **Submit for review**.

Doldur:
- App name: `Orhan Babanın Çiftliği — Auto Cross-Post`
- Category: `Business`
- **Scope justification** (her scope için ayrı açıklama):
  - `user.info.basic`: "Display connected TikTok username in admin panel"
  - `video.publish`: "Cross-post farm content videos from website"
  - `video.upload`: "Upload video chunks for publish"
  - `photo.publish`: "Cross-post blog cover images as Photo Mode slideshow"
- Submit.

#### 8.2.3 Onay Süreci

| Aşama | Süre | Aksiyon |
|---|---|---|
| Başvuru alındı | Anında | Email confirmation |
| İlk inceleme | 2-7 gün | TikTok ekibi belgeleri okur |
| Sorular / geri bildirim | Var ise +3-5 gün | Email ile detay sorulur |
| Final karar | 2-6 hafta | Onay veya ret |

#### 8.2.4 Reject Olursa

En sık reject nedenleri:

1. **Privacy policy yetersiz** → TikTok madde 4.2'yi karşılamıyor
2. **Demo video belirsiz** → use case'i göstermiyor
3. **Use case "spam" gibi görünüyor** → "promotional content" yerine "owned account" vurgu yap
4. **Domain doğrulanmamış**

Reject olursa **belirtilen sorunu düzelt** → "Re-submit" butonu → tekrar
inceleme (genelde daha hızlı, ~1 hafta).

### 8.3 Inbox Modu vs Direct Post — Kod Farkı

`TiktokService::publish` mode'a göre dallanır:

```php
$mode = Setting::getValue('tiktok_post_mode', 'inbox');

if ($mode === 'inbox') {
    $result = $this->client->publishToInbox($videoUrl, $options);
    // Sonuç: { publish_id, share_id } — kullanıcı manuel paylaşmalı
    return [
        'success'    => true,
        'tt_inbox_id' => $result['publish_id'],
        'message'    => 'TikTok Inbox\'a düştü, mobilde yayınla',
    ];
} else {
    // direct
    $result = $this->client->publishVideo($caption, $videoUrl, $options);
    // Polling...
    return [
        'success'      => true,
        'tt_post_id'   => $publishedPostId,
        'tt_permalink' => $permalink,
    ];
}
```

Tek toggle (`tiktok_post_mode`) ile davranış değişir.

### 8.4 Geçiş Adımları (Inbox → Direct Post)

Audit onaylandıktan sonra:

1. **Settings → TikTok → Yayın Modu** dropdown'undan **"Direct Post"** seç → Kaydet
2. Bir sonraki cron veya manuel post **otomatik Direct Post** kullanır
3. Eski Inbox modunda olan post'lar (`tt_inbox_id` dolu, `tt_post_id` null)
   etkilenmez — sen onları mobilde manuel yayınlamaya devam edersin
4. (İsteğe bağlı) Geçiş sırasında bekleyen Inbox post'ları toplu temizle:
   ```sql
   UPDATE instagram_posts
   SET tt_inbox_id = NULL, publish_to_tiktok = 1, tt_retry_count = 0
   WHERE tt_inbox_id IS NOT NULL AND tt_post_id IS NULL;
   ```
   Sonra cron `scopeTiktokRetryDue` bunları Direct Post ile yeniden yayınlar.

### 8.5 Audit Beklerken Çalışma Stratejisi

Audit 2-6 hafta sürdüğünde:

| Süre | Strateji |
|---|---|
| Hafta 1-2 | Inbox modu çalışıyor. Sen mobilden günde 4 cron post'unu manuel paylaşıyorsun (her biri 5sn) |
| Hafta 3-4 | Aynı |
| Hafta 5-6 | Onay gelir → toggle "direct" |

Ya da: audit reject olursa Inbox modunu **kalıcı** kullanmaya devam et.
Mobil 5sn iş günde 4 kez = günde 20sn — yönetilebilir.

### 8.6 Audit Olmadan Çalışan Diğer Şeyler

- ✅ Token üretme (OAuth)
- ✅ User info çekme
- ✅ Publish status fetch
- ✅ Inbox upload (drafts)
- ❌ Direct video publish
- ❌ Direct photo publish

Yani API'nin **%80'i** audit'siz çalışır. Sadece "doğrudan TikTok feed'ine
düşme" kısmı audit gerekir.

### 8.7 Bonus: Domain Verification — Trust Sinyali

TikTok audit'inde "kim olduğunuzu kanıtlayın" istemi olur. Verification
**onay şansını artırır** (zorunlu değil ama tavsiye):

1. TikTok Developer → Settings → Verify Domain
2. `tiktok-developer-site-verification` TXT record DNS'e
3. Verify tıkla, 5-30 dk

Audit'te "verified domain owner" badge alır.

### 8.8 Kontrol Listesi (Bölüm 8)

#### Audit hazırlığı

- [ ] Privacy Policy URL public erişilebilir
- [ ] Terms of Service URL public erişilebilir
- [ ] Demo video 30-60 sn çekildi, YouTube unlisted veya doğrudan yüklemeye hazır
- [ ] Use case açıklaması 300-500 kelime, "owned account" vurgulu
- [ ] Domain verification yapıldı (TXT record veya .well-known)

#### Audit başvurusu

- [ ] TikTok Developer → app → Submit for review
- [ ] 4 scope için ayrı justification yazıldı
- [ ] Submit tıklandı, email confirmation geldi

#### Onay sonrası

- [ ] Settings → tiktok_post_mode = 'direct' güncellendi
- [ ] (Opsiyonel) Bekleyen Inbox post'ları SQL ile direct'e çevrildi
- [ ] Cron 5dk içinde otomatik Direct Post denemesini onayladık

#### Eğer audit reject

- [ ] Reject nedeni email'den okundu
- [ ] Belge / use case düzeltildi
- [ ] Re-submit yapıldı
- [ ] (Sonsuz alternatif) Inbox modunda kalıcı çalış

Sonraki bölüm: **9. Test + Deploy + Master Kontrol Listesi** — son kontrol
adımları, deploy stratejisi.

---

## 9. Test + Deploy + Master Kontrol Listesi

Son bölüm — kod yazıldıktan sonra hangi sırada test edilmeli, production'a
deploy adımları, master kontrol listesi.

### 9.1 Test Sırası

#### 9.1.1 Birim test (sandbox)

```
1. Settings → TikTok'u Etkinleştir (sandbox mode)
2. OAuth bağla → access_token + refresh_token DB'de
3. Test kullanıcısı sandbox target users'a eklenmiş olmalı
4. /admin/instagram-posts/create → Feed Post seç
5. Görsel yükle + caption + hashtag + "TikTok'a paylaş" işaretle
6. "Şimdi Yayınla" → IG sandbox'a publish (varsa)
7. TT cross-post tetiklenir → sandbox TikTok hesabında Photo Mode görünür
8. tt_post_id + tt_permalink DB'de doldu mu kontrol et
9. /admin/instagram-posts/{id}/show → yeşil rozet "TikTok'ta yayında"
```

#### 9.1.2 Reels video testi

```
1. Yeni post → Reels seç → 10sn'lik test MP4 yükle
2. Caption + "TikTok'a paylaş" işaretli (otomatik check olmalı)
3. Yayınla → IG'de Reels + TikTok sandbox'ta video paylaşıldı
4. tt_permalink ile TikTok'ta videoyu görüntüle
```

#### 9.1.3 Otomatik blog testi

```
1. Settings → TikTok → "Otomatik blog yazılarını gönder" ✓
2. php artisan blog:generate (manuel tetikleme)
3. /admin/ai-logs → "Başarılı" → BlogPost + InstagramPost oluştu
4. /admin/instagram-posts → en üst satır → publish_to_tiktok = 1
5. 5dk bekle veya manuel cron tetikle:
   php artisan instagram:publish-scheduled
6. Cron'dan sonra:
   - IG'de yayın ✓
   - FB'de yayın ✓
   - TikTok'ta Photo Mode yayın ✓
```

#### 9.1.4 Hata senaryoları

| Senaryo | Beklenen davranış |
|---|---|
| Token expire olmuş | refreshAccessToken otomatik çağrılır, başarılıysa devam |
| Token revoke edilmiş (kullanıcı TT'de uygulamayı kaldırdı) | Hata: "Re-authorize required", Telegram + bell |
| Caption 2200 char aşıyor | `buildFullCaption` otomatik kırpar (Instagram fix kapsamı) |
| Video > 287MB | TikTok API reject, error_message + retry |
| Video < 3sn | TikTok API reject (Reels client-side validator zaten yakalar) |
| Image URL HTTP (HTTPS değil) | TikTok reject — production'da olmaz, dev'de ngrok HTTPS |
| Aynı `publish_id` ikinci kez gelirse | Idempotency: `tt_post_id` dolu → skip |
| Rate limit aşıldı (>10/dakika) | Throttle guard: skip + bir sonraki cron |
| TikTok sunucu down | TiktokApiException → tt_error_message + retry |

### 9.2 Manuel Test Komutları

Tüm akışı tek terminal'den test:

```bash
# 1. Cache + config temizle
php artisan config:clear && php artisan cache:clear

# 2. Migration uygula
php artisan migrate

# 3. Setting tablosunu seed (opsiyonel — UI'dan da girilir)
php artisan tinker
> \App\Models\Setting::setValue('tiktok_enabled', '1', 'social');
> \App\Models\Setting::setValue('tiktok_mode', 'sandbox', 'social');
> \App\Models\Setting::setValue('tiktok_post_mode', 'inbox', 'social');

# 4. Route registration kontrolü
php artisan route:list --path=tiktok

# 5. Manual blog generate
php artisan blog:generate

# 6. Manual IG/TT publish (cron)
php artisan instagram:publish-scheduled

# 7. Manual token refresh (cron)
php artisan tiktok:refresh-token

# 8. Log takip
tail -f storage/logs/laravel.log
```

### 9.3 Production Deploy Stratejisi

#### 9.3.1 Pre-deploy

- [ ] Tüm kod fazları (1-7) commit'lendi + push'landı
- [ ] CI/test geçti (varsa)
- [ ] Sandbox modunda en az 3 başarılı test post atıldı
- [ ] `docs/tiktok.md` güncel
- [ ] `composer.lock` değişikliği yok (composer require yapmadık)

#### 9.3.2 Deploy adımları

```bash
# Production sunucuya
cd /home/orhanbabanincift/prod-project
git pull origin claude/pull-gitden-latest-IW3sn  # veya main

# Migration
php artisan migrate --force

# Config + cache + route refresh
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cron yeniden yükleme gerekmez — Laravel scheduler her dakika check eder
# Ama dilersen test:
php artisan schedule:list
```

#### 9.3.3 Post-deploy doğrulama

- [ ] Site açılıyor (homepage 200)
- [ ] `/admin` 200 (login sayfası)
- [ ] Admin login sonrası `/admin/settings` → TikTok sekmesi görünüyor
- [ ] OAuth bağla butonu çalışıyor (TikTok redirect oluyor)
- [ ] `php artisan route:list | grep tiktok` route'ları var
- [ ] `storage/logs/laravel.log` son 1 dakika hata yok
- [ ] `php artisan schedule:list | grep tiktok` cron kayıtlı

#### 9.3.4 Rollback Planı

Acil rollback gerekirse:

```bash
# Migration geri al
php artisan migrate:rollback --step=1

# Önceki commit'e dön
git checkout <previous-commit>

# Cache clear
php artisan config:clear && php artisan cache:clear
```

`down()` method'u migration'a koymuştuk (Bölüm 3.3) — kolon'lar düşer,
mevcut post'lar etkilenmez.

### 9.4 Performans Beklentileri

| İşlem | Tipik süre |
|---|---|
| OAuth callback | < 2 saniye |
| Photo Mode init request | 1-3 saniye |
| Photo Mode publish complete (poll dahil) | 15-30 saniye |
| Video upload init | 2-5 saniye |
| Video publish complete (poll dahil) | 30-90 saniye |
| Token refresh | < 1 saniye |
| Cron tek post cross-post | 30-90 sn (video) / 15-30 sn (photo) |

Cron 5dk window'unda max 8-10 post işlenir (TT polling süresi sınır). 4 cron/gün × 1 post = sorun yok.

### 9.5 Monitoring

Deploy sonrası 1 hafta günlük kontrol:

| Konu | Nereden bak |
|---|---|
| TT cross-post başarı oranı | `instagram_posts` tablosu: `tt_post_id NOT NULL` sayısı |
| Token sağlığı | `tiktok_expires_at` Setting → 7 gün+ ileride mi |
| Hata yoğunluğu | `tt_error_message NOT NULL` sayısı son 24sa |
| Telegram bildirim | Mobilde "TikTok cross-post fail" sıklığı |
| Bell bildirim | Admin paneli sağ üst, kırmızı sayı |
| TT app'inde yayınlar | TikTok hesabına bak — düşen post'lar düzenli mi |

### 9.6 Master Kontrol Listesi (Tüm Doküman)

Tüm bölümleri tek noktada birleştiren özet checklist. Production'a
geçmeden ÖNCE %100 tamamlanmalı.

#### 🔵 TikTok hesap kurulumu (Bölüm 2)

- [ ] TikTok Developer hesabı açıldı
- [ ] App oluşturuldu (business kategorisi)
- [ ] Login Kit konfigüre edildi (redirect URI prod + local)
- [ ] 4 scope seçildi: `user.info.basic`, `video.publish`, `video.upload`, `photo.publish`
- [ ] Client Key + Secret kopyalandı (güvenli saklama)
- [ ] Content Posting API capability eklendi
- [ ] (Production için) Domain verification yapıldı
- [ ] (Production için) Audit başvurusu yapıldı (privacy, ToS, demo video)
- [ ] (Local) ngrok HTTPS URL Redirect URI'ye eklendi
- [ ] (Sandbox) Target user eklendi

#### 🔵 Veritabanı (Bölüm 3)

- [ ] Migration `instagram_posts`'a 7 kolon ekledi
- [ ] 2 indeks eklendi (`publish_to_tiktok`, `tt_post_id`)
- [ ] `InstagramPost::$fillable` + `casts()` güncellendi
- [ ] 3 helper method (`isTikTokPublished`, `isTikTokPending`, `isTikTokFailed`)
- [ ] `php artisan migrate` çalıştı (prod: `--force`)

#### 🔵 Mimari (Bölüm 4)

- [ ] `TiktokApiClient` oluşturuldu (6 method)
- [ ] `TiktokApiException` oluşturuldu
- [ ] `TiktokService` oluşturuldu (4 method)
- [ ] `config/services.php` + `AppServiceProvider` binding
- [ ] `.env` TIKTOK_BASE_URL_* eklendi
- [ ] Senkron polling implementasyonu (max 60sn)
- [ ] Idempotency guard (`tt_post_id` doluysa skip)
- [ ] Rate limit guard (10/dakika throttle)

#### 🔵 Cross-Post (Bölüm 5)

- [ ] `InstagramService::publish()` sonuna TT block eklendi
- [ ] `try/catch` ile IG yayını izole
- [ ] `scopeTiktokRetryDue` scope eklendi
- [ ] `PublishScheduledInstagramPosts` command'a TT retry block
- [ ] Telegram + bell bildirim (TT-specific)
- [ ] Token expire kontrolü + refresh fallback
- [ ] Kalıcı hata → `NotificationCenter::sendCritical`

#### 🔵 Form UI (Bölüm 6)

- [ ] `publish-plan.blade.php`'e TT checkbox
- [ ] Controller'da `$ttConfigured`, `$ttPostMode`, `$publishToTt` hazır
- [ ] Reels seçince TT otomatik check (userInteracted guard)
- [ ] Show sayfasında TT durum rozeti (4 senaryo)
- [ ] Edit recovery butonları (failed: Şimdi Paylaş + Retry Sıfırla; inbox: alert)
- [ ] 2 route: `tt-publish-now`, `tt-reset-retry`
- [ ] 2 controller method: `tiktokPublishNow`, `tiktokResetRetry`
- [ ] AdminModal confirm JS
- [ ] CSS `.ig-target-check--tt` (TikTok gradient)
- [ ] Index listede TT mini badge

#### 🔵 Otomatik Blog (Bölüm 7)

- [ ] `BlogToInstagramService::shareBlog`'a `publish_to_tiktok` set
- [ ] `shouldShareToTiktok()` helper (3 koşul)
- [ ] `InstagramBulkImportService` (varsa) aynı pattern
- [ ] Settings'te `tiktok_auto_share_blog` toggle

#### 🔵 Cron (Bölüm 6 / Faz 6)

- [ ] `tiktok:refresh-token` command oluşturuldu
- [ ] `routes/console.php`'e schedule eklendi (dailyAt('04:30'))
- [ ] `php artisan schedule:list` ile doğrulandı

#### 🔵 Audit (Bölüm 8)

- [ ] Inbox modunda en az 3 başarılı test yapıldı
- [ ] (Audit istiyorsa) Submit for review yapıldı
- [ ] (Onay sonrası) `tiktok_post_mode = direct` toggle

#### 🔵 Deploy + Test (Bölüm 9)

- [ ] Migration prod'da uygulandı
- [ ] Config/route cache temizlendi
- [ ] Sandbox'ta birim test başarılı
- [ ] Otomatik blog testi başarılı (4 platform: blog + IG + FB + TT)
- [ ] 7 hata senaryosu beklenen davranışı verdi
- [ ] 1 hafta monitoring planı tetiklendi

### 9.7 Rakamlarla Hedef

Production'a geçtikten **2 hafta sonra**:

| Metrik | Hedef |
|---|---|
| TT cross-post başarı oranı | >%95 |
| TT cross-post ortalama süre | <30 sn |
| Manuel müdahale gereksinimi | <%5 (audit yoksa %100 Inbox) |
| Token refresh fail | 0 |
| Telegram fail bildirim | <2/hafta |

Hedefin altındaysa kontrol et:
- TT API status (`status.tiktok.com` benzeri)
- Token sağlığı (`tiktok_expires_at`)
- Caption uzunluk dağılımı (DB query)
- Video format compliance (FFprobe varsa duration check)

### 9.8 Final Söz

Doküman 9 bölümle bitti. Kod 7 faza bölündü:

1. Migration
2. Settings + OAuth callback
3. TiktokService + TiktokApiClient
4. Cross-post tetikleyici
5. Form UI + recovery
6. Cron
7. Auto blog flag

Her faz **ayrı commit** olarak repo'ya işlenir. Doküman bu sırayı takip eder.

Kod yazımı **sandbox modunda** test edilir, production'a sadece test geçince
deploy edilir. Audit beklenir → Inbox modunda paralel çalış. Audit onaylanır
→ Direct Post toggle. **Cross-post fail Instagram yayınını ASLA bozmaz**
(en kritik tasarım kuralı).

İyi paylaşımlar 🎬
