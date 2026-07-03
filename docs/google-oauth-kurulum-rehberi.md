# Google OAuth 2.0 Kurulum Rehberi (önerilen yöntem)

Bu doküman Google Search Console + Indexing API entegrasyonunu **OAuth 2.0
user-flow** ile bağlamak için yapılacak bir kerelik kurulum adımlarını içerir.

> **Süre:** ~10 dakika
> **Maliyet:** 0 TL
> **Avantaj (Service Account JSON yöntemine göre):**
> - GSC'ye ekstra e-posta eklemek **gerekmez**
> - Workspace gerekmiyor — kişisel Gmail yeterli
> - GSC'de zaten owner olduğun hesabı kullanır → otomatik erişim
> - Bağlantıyı tek tıkla kurarsın, tek tıkla keserseniz

---

## ADIM 1 — Google Cloud Console projesi

Eğer **Service Account yöntemi için zaten bir proje oluşturduysan**, aynı
projeyi kullanabilirsin (yeni proje açma gereği yok). Yoksa:

1. https://console.cloud.google.com/ → giriş yap
2. Üstte proje seçici → **Yeni Proje**
3. Proje adı: `orhanbabaninciftligi-seo` (istediğin)
4. **Oluştur**

---

## ADIM 2 — Search Console API + Indexing API'yi etkinleştir

(Service Account yöntemi için zaten yaptıysan atla.)

1. Sol menü → **APIs & Services → Enabled APIs & services**
2. **+ ENABLE APIS AND SERVICES**
3. Ara: `Search Console API` → seç → **ENABLE**
4. Tekrar **+ ENABLE APIS AND SERVICES**
5. Ara: `Indexing API` → seç → **ENABLE**

---

## ADIM 3 — OAuth Consent Screen (kullanıcı izin ekranı)

1. **APIs & Services → OAuth consent screen**
2. User Type: **External** → **Create**
3. App information:
   - **App name:** `Orhan Babanın Çiftliği Admin`
   - **User support email:** kendi e-postan
   - **Developer contact email:** kendi e-postan
4. **Save and Continue**
5. Scopes ekranı → **Add or remove scopes**:
   - `.../auth/webmasters.readonly` (Search Console read)
   - `.../auth/indexing` (Indexing API)
   - `.../auth/userinfo.email`
   - `openid`
6. **Update → Save and Continue**
7. Test users → **+ Add Users** → kendi Gmail adresini ekle
8. **Save and Continue → Back to Dashboard**

> **Önemli:** Publishing status **"Testing"** kalsın. App'i "In production"a
> almaya çalışırsan Google verification süreci başlar (haftalar sürer, tek
> kullanıcı için anlamsız). Testing modunda **refresh token 7 gün geçerli**
> sınırı vardır — pratikte cron her gün çağırdığı için her erişim token'ı
> yeniler ve sorun çıkmaz; bağlantı 7+ gün hiç kullanılmazsa yeniden bağlanman
> gerekebilir.

---

## ADIM 4 — OAuth 2.0 Client ID oluştur

1. **APIs & Services → Credentials**
2. **+ CREATE CREDENTIALS → OAuth client ID**
3. Application type: **Web application**
4. Name: `Orhan Babanın Çiftliği — Admin OAuth`
5. **Authorized redirect URIs** → **+ ADD URI**:

   ```
   https://orhanbabaninciftligi.com/admin/google-integration/oauth/callback
   ```

   > ⚠️ Trailing slash, http/https, alt-domain — hepsi tam eşleşmeli.
   > Yanlış olursa Google `redirect_uri_mismatch` hatası verir.

6. **Create**
7. Açılan modal'da:
   - **Client ID** kopyala (örn. `123456789-abcd.apps.googleusercontent.com`)
   - **Client Secret** kopyala (örn. `GOCSPX-xxxxxxxxxxxxxxxx`)

> Bu iki değeri **bir parola yöneticisinde sakla**. Secret'ı kaybedersen
> yenisini üretip eskisini revoke etmen gerekir.

---

## ADIM 5 — Bilgileri admin panele gir

1. Admin panele giriş yap
2. Sol menü → **Google Entegrasyonu**
3. **"Google ile Bağlan"** kartının altındaki form:
   - **OAuth Client ID** → ADIM 4'teki Client ID
   - **OAuth Client Secret** → ADIM 4'teki Client Secret
4. **Kaydet**

---

## ADIM 6 — Google ile Bağlan

1. Aynı sayfada **"Google ile Bağlan"** butonu artık aktif → tıkla
2. Google consent ekranı açılır
3. ADIM 3'te eklediğin Gmail hesabıyla giriş yap
4. **"İzin Ver"** (Search Console'a oku, Indexing'e yaz isteklerini onayla)
5. Otomatik admin paneline geri dönersin
6. Üstte: **"Google başarıyla bağlandı: senin@gmail.com"** mesajı

---

## ADIM 7 — Doğrula

1. Aynı sayfada üstteki **"Bağlantıyı Test Et"** butonuna bas
2. **"✓ Google Search Console bağlantısı başarılı"** mesajı çıkmalı
3. `/admin/seo-performance` sayfasını aç → veri çekme aktif

---

## Bağlantıyı Kesmek

Admin panelde **Google Entegrasyonu → "Bağlantıyı Kes"** butonu:
- Token Google tarafında da iptal edilir (`oauth2.googleapis.com/revoke`)
- Lokal DB kaydı silinir (soft delete)
- Cron çağrıları otomatik Service Account JSON'a düşer (varsa) veya durur

Yeniden bağlanmak için ADIM 6'yı tekrarla.

---

## Sorun Giderme

### `redirect_uri_mismatch`
ADIM 4'te eklediğin Authorized redirect URI bu URL ile **bire bir** aynı
olmalı (sayfada gösterilir):
```
https://orhanbabaninciftligi.com/admin/google-integration/oauth/callback
```

### `access_denied` veya consent ekranında "uygulama doğrulanmadı"
- ADIM 3'teki publishing status'ün **Testing** olması gerek
- Test users listesinde kendi e-postanın olduğundan emin ol

### `invalid_grant` token yenilemede
- Refresh token 7 günden uzun süredir kullanılmadıysa Google iptal etmiştir
  (Testing mode kısıtı). Bağlantıyı kesip yeniden bağlan.
- Token revoke edildiyse (sen veya Google tarafından) — yine yeniden bağlan.

### Hâlâ Service Account modu görünüyor
- Admin → Google Entegrasyonu → üstteki status kartı **"Bağlandı (OAuth 2.0)"**
  yazmalı. Yazmıyorsa OAuth bağlantısı kurulmamış demektir.
- `php artisan cache:clear` ile token cache'ini temizle.

---

## Service Account Yönteminden Geçiş

OAuth bağlandığı andan itibaren tüm API çağrıları OAuth token ile yapılır.
Service Account JSON dosyası **dokunulmadan** kalır — istersen sil, istersen
fallback olarak tut. OAuth bağlantısı koptuğunda otomatik SA'ya düşer.

`/admin/google-integration` sayfasında:
- **OAuth aktifken** Service Account kartı yarı şeffaf görünür ("alternatif")
- **OAuth bağlı değilken** Service Account kartı normal görünür

---

## Cron'la Etkileşim

GSC ve Indexing cron'ları (`gsc:fetch`, indexing notifier) artık OAuth bağlıyken
sen müdahil olmadan token yenileme yapar:

```
Cron çağırır → GoogleAuthService::preferredAccessToken()
   ↓
Token süresi <5 dk mı? → Evet → refresh_token ile yeni access_token al
   ↓
API çağrısını yap
```

**Sen aktif kullanıcı olduğun sürece** (refresh token sahibi) cron senin adına
çalışır. Hesabını silersen cron başarısız olur — tekrar bağlanman gerekir.
