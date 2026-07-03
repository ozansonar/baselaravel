# Dosya Yöneticisi — Kullanıcı Rehberi

Admin panelden PDF, Word, Excel, görsel ve diğer dosyaları yükleyip,
public URL'lerini blog yazılarında, sayfalarda veya newsletter'larda
kullanmak için kütüphane.

## Hızlı başlangıç

### 1. Migration çalıştır (bir kerelik)

```bash
php artisan migrate
```

`uploaded_files` tablosu oluşur.

### 2. Sayfaya git

Admin → sol menü → **Dosya Yöneticisi**

İlk girişte boş görünür ve "Henüz dosya yüklenmemiş" uyarısı çıkar.

### 3. Dosya yükle

Üstteki **"Dosya Yükle"** alanına dosyaları sürükle (veya tıkla):
- **Görseller:** JPG, PNG, WebP, GIF (SVG kabul edilmez — güvenlik)
- **Belgeler:** PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, CSV, TXT
- **Arşiv:** ZIP
- **Video/audio:** MP4, MP3
- **Limit:** Dosya başı 50 MB, seans başı 20 dosya

**"Tümünü Yükle"** butonuna bas → paralel 4 dosya/saniye yüklenir.

### 4. URL'i kopyala ve kullan

Yüklenen dosyaya tıkla → detay sayfası açılır:

```
URL & Link Kopyala
─────────────────────────────────────────
1. Sade URL                  [📋 Kopyala]
   https://site.com/uploads/files/abc.pdf

2. HTML Link                 [📋 Kopyala]
   <a href="..." target="_blank">abc.pdf</a>

3. Markdown Görsel*          [📋 Kopyala]
   ![alt](url)

4. HTML <img> tag*           [📋 Kopyala]
   <img src="..." alt="..." loading="lazy">

* Görseller için — diğer dosyalarda görünmez
```

İstediğin formatı tek tıkla panoya kopyalar, blog/sayfa editörüne yapıştır.

## Akıllı özellikler

### Duplicate kontrolü (SHA256 hash)

Aynı dosyayı 2 kez yüklemeye çalışırsan sistem fark eder:
- Mevcut kayıt kullanılır
- Sarı uyarı: "Bu dosya daha önce yüklenmiş, mevcut kayıt kullanılıyor"
- Disk alanı boşa harcanmaz

### Otomatik kategori sınıflandırma

MIME tipinden otomatik:
- `image/*` → Görsel
- `application/pdf, .doc, .xlsx, .csv` → Belge
- `video/*` → Video
- `audio/*` → Ses
- `.zip` → Arşiv
- Diğer → Diğer

### Görsel için otomatik thumbnail

Görsel yüklediğinde UploadService otomatik:
- WebP'ye çevirir (boyut tasarrufu)
- 4 farklı boyut variant üretir (thumb, sm, md, lg)
- Grid'de küçük preview, detayda tam boyut

### Arama + filtre

- **Arama (q):** dosya adı, başlık ve alt metinde
- **Kategori filtre:** dropdown
- **Tarih aralığı:** date_from / date_to
- **Sayfa boyutu:** 12/24/48/96

## SEO için alt metin

Görsel yüklediysen detay sayfasında **"Alt Metin"** alanı doldurulur:
- Boş bırakılırsa orijinal dosya adı kullanılır
- Markdown/HTML kopyaladığında alt= attribute'ında bu metin yapışır
- Erişilebilirlik (screen reader) + SEO (Google görsel arama) için kritik

Örnek alt metin: `"Köy yapımı tereyağı bloğu — kahvaltı sofrasında"`

## Sorun giderme

### "Dosya çok büyük (50 MB üstü)"
Hosting `upload_max_filesize` ve `post_max_size` ayarlarını **64M+** yap.
Cpanel veya .htaccess'ten:
```
php_value upload_max_filesize 64M
php_value post_max_size 64M
```

### "Bu dosya türü desteklenmiyor"
Sadece beyaz listedeki uzantılar kabul edilir (yukarıda liste). SVG kabul
edilmez (XSS riski).

### "Yükleme hatası: 419"
CSRF token süresi dolmuş — sayfayı yenile (F5).

### Yükleme tamamlandı ama dosya görünmüyor
Sayfa otomatik 1.5 saniyede yenilenir. Yenilenmiyorsa F5.

### Aynı dosyayı tekrar yüklemek istiyorum
Dosya hash'i aynıysa sistem mevcut kaydı döner. Farklı hash için dosyayı
yeniden kaydet (örn. başka bir görsel düzenleme programıyla aç + kaydet).

## URL formatları nerede kullanılır?

| Format | Kullanım yeri |
|---|---|
| **Sade URL** | Browser adres çubuğu, e-posta, sosyal medya post |
| **HTML Link** | Blog yazısı içinde indirilebilir link (PDF, Excel) |
| **Markdown Görsel** | Markdown editörü olan blog yazısı içinde inline görsel |
| **HTML <img>** | Manuel HTML editörü için, `loading="lazy"` SEO için ideal |

## Güvenlik notu

- Tüm yüklenen dosyalar `public/uploads/files/` altında — web'den erişilebilir
- Dosya adları rastgele hash + slug ile (path injection imkansız)
- SVG yasak (XSS riski)
- 50 MB tavan (hosting kotası korunur)

## Gelecek özellikler (sonraki PR'lar)

- CKEditor / Trumbowyg file picker entegrasyonu
- Klasör/etiket sistemi
- Disk usage dashboard
- Toplu silme/taşıma
- Public download tracking (download_count zaten var, public endpoint eklenebilir)
