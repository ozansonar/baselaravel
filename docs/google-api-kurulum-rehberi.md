# Google Search Console & Indexing API — Kurulum Rehberi

Bu doküman site sahibi/admin tarafından **bir kez** yapılacak setup adımlarını
içerir. Geliştirme tamamlandı; sadece Service Account JSON dosyası eklenince
sistem otomatik çalışmaya başlar.

> **Süre:** ~30 dakika
> **Maliyet:** 0 TL (Google ücretsiz limitler içinde)
> **Risk:** Sıfır — JSON anahtarı yanlış olursa sadece veri çekilmez,
> site çalışmaya devam eder.

---

## ADIM 1: Google Cloud Console Projesi Oluştur

1. https://console.cloud.google.com/ adresine git (Gmail hesabınla giriş)
2. Üstteki proje seçici → **"Yeni Proje"**
3. Proje adı: `orhanbabaninciftligi-seo` (istediğin adı verebilirsin)
4. Organize: yok (kişisel)
5. **Oluştur** → 1-2 saniye bekle, proje aktif olur

---

## ADIM 2: API'leri Etkinleştir

Aynı projedeyken **APIs & Services → Enabled APIs & services**:

1. **+ ENABLE APIS AND SERVICES** (üstte)
2. Arama: `Search Console API` → seç → **ENABLE**
3. Tekrar **+ ENABLE APIS AND SERVICES**
4. Arama: `Indexing API` → seç → **ENABLE**

> İki API'nin de aktif olduğunu **APIs & Services → Enabled APIs** altında
> doğrula.

---

## ADIM 3: Service Account Oluştur

1. **APIs & Services → Credentials** sayfasına git
2. **+ CREATE CREDENTIALS** → **Service account**
3. Service account adı: `gsc-integration`
4. Service account ID: otomatik dolar (örn: `gsc-integration@orhanbabaninciftligi-seo.iam.gserviceaccount.com`)
5. Description: `Search Console + Indexing API entegrasyonu`
6. **CREATE AND CONTINUE**
7. **Grant this service account access** → boş bırak (atla, **CONTINUE**)
8. **DONE**

---

## ADIM 4: JSON Anahtarını İndir

1. Az önce oluşturduğun service account'a tıkla (listede)
2. **KEYS** sekmesi → **ADD KEY** → **Create new key**
3. Key type: **JSON** → **CREATE**
4. JSON dosyası **otomatik indirildi** → güvenli bir yerde sakla
5. **Önemli:** Bu anahtar parolayla denk — kaybedersen yenisini üretip eskisini revoke etmen gerek

---

## ADIM 5: Search Console'da Yetki Ver

Service Account'un sitenin verisine erişebilmesi için:

1. https://search.google.com/search-console adresine git
2. **orhanbabaninciftligi.com** property'sini seç
3. Sol menü → **Settings** (ayarlar dişli ikonu)
4. **Users and permissions**
5. **+ ADD USER**
6. Email: ADIM 3'teki service account email'i (örn: `gsc-integration@orhanbabaninciftligi-seo.iam.gserviceaccount.com`)
7. Permission: **Owner** seç → **ADD**

> ⚠️ "Restricted user" yetersiz — Indexing API için "Owner" gerekli.

---

## ADIM 6: JSON'u Sunucuya Yükle

İndirdiğin JSON dosyasını sunucuya `storage/app/google/service-account.json`
yoluna **SCP** veya **cPanel File Manager** ile yükle:

### SCP ile (önerilen):
```bash
scp ~/Downloads/orhanbabaninciftligi-seo-xxxxx.json \
    user@orhanbabaninciftligi.com:/path/to/site/storage/app/google/service-account.json
```

### cPanel File Manager ile:
1. cPanel → File Manager
2. `storage/app/google/` klasörüne git
3. **Upload** → JSON dosyasını seç
4. **Rename** → `service-account.json` olarak adlandır

### Dosya izni kontrol:
```bash
chmod 600 storage/app/google/service-account.json
chown www-data:www-data storage/app/google/service-account.json
```

> ⚠️ **GÜVENLIK:** Bu dosya `.gitignore`'da → asla commit edilmez.
> Kimseyle paylaşma. Yedek olarak kişisel bir yerde sakla (1Password vb).

---

## ADIM 7: Doğrula

1. Admin panele giriş yap
2. Sol menüden **SEO Performans** → tıkla
3. Sayfanın üstünde "Service Account bağlandı" mesajını gör
4. **Bağlantıyı Test Et** butonuna tıkla
5. "✓ Google Search Console bağlantısı başarılı" mesajı çıkmalı

Eğer hata alırsan:
- "Service Account JSON bulunamadı" → ADIM 6'da yol yanlış
- "401 Unauthorized" → ADIM 5'te yetki Owner değil
- "404 Property not found" → `.env` dosyasında `GSC_PROPERTY_URL` yanlış

---

## ADIM 8 (Opsiyonel): Indexing API'yi Aç

Yeni eklenen ürün/blog/şehir sayfasının Google'a anında haber gönderilmesi için:

Admin panel → Settings (ayarlar) → Google Entegrasyonları sekmesi (yakında):
- `google_gsc_enabled` → 1 (default açık)
- `google_indexing_enabled` → 1 (manuel açman gerek)

veya MySQL'de:
```sql
INSERT INTO settings (key, value, group, type, created_at, updated_at)
VALUES ('google_indexing_enabled', '1', 'integrations', 'boolean', NOW(), NOW())
ON DUPLICATE KEY UPDATE value='1';
```

---

## Cron'un Çalıştığını Görmek

İlk veri ~6 saat içinde otomatik çekilir. Beklemek istemezsen:

```bash
php artisan gsc:fetch
```

Sonra `/admin/seo-performance` sayfasını yenile — veriler görünmeli.

---

## Veri Gecikmesi

Google Search Console verilerini **2-3 gün gecikmeyle** sunar:
- Bugün 27 Nisan ise GSC'den max 24-25 Nisan verisi alabilirsin
- "Son 28 gün" filtresi aslında "27 Nisan'dan 28 gün önce - 24 Nisan" arası
- Bugünün verisi YOK (Google'ın kararı, biz bunu değiştiremeyiz)

Bu yüzden site az gün önce yayına alındıysa **birkaç gün veri yok** olabilir.

---

## Sorun Giderme

### "Service Account JSON bulunamadı"
- `storage/app/google/service-account.json` yolu doğru mu?
- Dosya izinleri: `chmod 600`, sahibi web kullanıcısı (`www-data` veya `apache`)?
- `.env` dosyasında `GOOGLE_SERVICE_ACCOUNT_PATH` kontrol et

### "401 Unauthorized"
- Search Console'da service account email'i Owner olarak eklendi mi?
- Eklendiyse 5-10 dakika bekle, Google'ın yetkiyi yayması zaman alır

### "Quota exceeded"
- Site yeni — günde 50 isteğin altındayız, kota aşma teorik
- Kullanım Google Cloud Console → APIs → Search Console API → Quotas

### Cron çalışmıyor
```bash
php artisan schedule:list   # gsc-fetch-metrics görünmeli
php artisan schedule:run    # manuel tetikle (eğer planlanmış zamandaysak)
php artisan gsc:fetch       # cron'u atlayıp direkt çağır
```

### Verileri silmek istiyorum (yeniden başla)
```sql
TRUNCATE TABLE gsc_query_metrics;
TRUNCATE TABLE gsc_page_metrics;
TRUNCATE TABLE indexing_requests;
TRUNCATE TABLE google_api_logs;
```
Sonra `php artisan gsc:fetch` ile yeniden çek.

---

## Ne Görüyorum, Ne Anlıyorum?

### Top Queries
- En çok kelimeler ki **siteniz aratıldığında çıkıyor**
- Tıklama: Google sonuçlarında çıktığında kaç kişi tıkladı
- Gösterim: Kaç defa Google sonuçlarında çıktınız
- Pozisyon: Ortalama hangi sırada (1 = en üst, 100 = en alt)
- CTR: Tıklanma oranı (5%+ iyi, 2-5% orta, <2% kötü)

### Per-Page Performance
- Her ürün/blog/şehir sayfasının kendi performansı
- "Pozisyon ≤10 ama CTR <%2" → Meta description'ı yenile (uyarı kutusunda görür)

### Indexing Requests
- Yeni eklediğiniz içerik Google'a haber edildi mi
- Failed olanları "Yeniden Dene" butonuyla tekrar gönderebilirsiniz

### API Logs
- Cron'lar başarıyla çalışıyor mu görmek için
- Hata varsa sebep nedir
