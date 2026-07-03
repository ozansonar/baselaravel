# Sosyal Medya Modülü — Geliştirme Yol Haritası

**Tarih:** 28 Nisan 2026
**Durum:** Plan onayı bekliyor — fazlar halinde uygulanacak
**Hedef:** Mevcut zengin Instagram/Facebook altyapısı üzerine **otomasyon, iş hızı ve etkileşim** katmanları eklenmesi

---

## ✅ Mevcut Durum — Ne Var?

### Çekirdek Yayın
- ✅ IG Feed Post (görsel + carousel max 10)
- ✅ IG Reels (video, 3-90 sn süre validation)
- ✅ IG Story (görsel veya video, 24 saat)
- ✅ FB cross-post: Feed Post + Reels (3 fazlı upload)
- ✅ Cron 19:00 günde 1 paylaşım
- ✅ Manuel "Şimdi Paylaş"

### AI Üretim
- ✅ AI Caption (Gemini, 4 ton)
- ✅ AI Hashtag (10-15 adet, HashtagPool öncelikli)
- ✅ AI Görsel (Imagen 3 + Gemini Flash Image fallback)

### Operasyon
- ✅ Auto-retry (max 3 deneme + kalıcı hata)
- ✅ Engagement metrics sync (likes, comments, reach, impressions, saved)
- ✅ Token expiry uyarı (UI banner + cron yenileme)
- ✅ Cron heartbeat dashboard widget
- ✅ Kalıcı fail → admin'e e-posta bildirimi
- ✅ API logları admin paneli (filtre + JSON modal)
- ✅ Calendar görünümü
- ✅ Instagram-style live preview (form'da)

### UI / UX
- ✅ Admin panelinde Tür kolonu (Feed/Reels/Story)
- ✅ Yayınlanmış post için detay (show) sayfası
- ✅ JS ile media type'a göre form kart show/hide
- ✅ Settings'te Instagram + AI ayarları

---

## 🎯 Phase 1 — Otomasyon (Manuel iş yükünü sıfırla)

### 1. Blog → IG/FB Otomatik Post
**Süre:** 3-4 saat | **Etki:** 🔥🔥🔥 | **Maliyet:** $0

**Akış:**
1. Cron `blog:generate` (zaten günde 4 kez çalışıyor) yeni blog yayınlar
2. Otomatik tetikleyici: `BlogPostObserver::created()` → `InstagramPost` oluşturur
3. Imagen 3 ile blog konu/başlığa göre kapak görseli üretir
4. AI caption: "Yeni yazı: [başlık]\n[özet]\nLink: ..."
5. media_type=image, scheduled_at=ertesi gün 19:00
6. Cron 19:00'da yayınlar (IG + FB)

**Bileşenler:**
- `App\Observers\BlogPostObserver` veya event/listener
- Setting: `auto_post_new_blogs` (toggle)
- Setting: `auto_post_blog_template` (caption şablonu)
- BlogPost'a `instagram_post_id` foreign key (idempotency, double-post yapmasın)

**Çıktı:** Sıfır manuel iş, blog + sosyal medya birlikte çalışır, organic backlink

---

### 2. Best-Time-to-Post Önerisi
**Süre:** 3-4 saat | **Etki:** 🔥🔥🔥 | **Maliyet:** $0

**Akış:**
1. Cron `instagram:analyze-engagement` (haftalık) → son 90 gün analiz
2. `engagement_by_hour` ve `engagement_by_weekday` Setting'e yazılır
3. Form'da scheduled_at picker'ın yanında: "💡 En iyi saat: Salı 19:00 (%35 ↑)"
4. Dashboard widget: haftalık ısı haritası

**Bileşenler:**
- `SocialMediaAnalyticsService::computeBestTimes()`
- Cron command + scheduler (haftalık)
- Settings'te cached aggregates
- Dashboard'a yeni "Optimal Saatler" widget

**Çıktı:** Optimize saat seçimi → organic reach %20-30 artış

---

## 🎯 Phase 2 — İş Hızı (Toplu işlemler + UX)

### 3. Bulk Schedule (Excel/CSV ile Toplu Plan)
**Süre:** 4-5 saat | **Etki:** 🔥🔥🔥 | **Maliyet:** $0

**Akış:**
1. `/admin/instagram-posts/bulk-import` sayfası
2. CSV şablonu indir (örnek dosya): tarih, saat, görsel_url, caption, hashtags, media_type, fb
3. Yükle → Backend her satır için `InstagramPost` oluşturur
4. Görseller URL ile alınıp `instagram/` klasörüne indirilir veya zip'ten extract
5. Validation hataları → satır satır rapor

**Bileşenler:**
- `App\Http\Controllers\Admin\InstagramPostBulkController`
- `App\Services\BulkScheduleImporter`
- CSV şablonu: `public/templates/instagram-bulk-template.csv`
- Validation: tarih çakışması, görsel boyut/oran, caption uzunluk

**Çıktı:** Aylık planlama 30 dk → 5 dk

---

### 4. Calendar Drag-Drop
**Süre:** 2 saat | **Etki:** 🔥🔥 | **Maliyet:** $0

**Akış:**
1. Calendar görünümünde planlanmış post → grab cursor
2. Sürükle-bırak ile başka tarihe taşı
3. AJAX `PATCH /admin/instagram-posts/{id}/reschedule`
4. Validation: geçmişe taşınamaz

**Bileşenler:**
- JS: HTML5 drag/drop API (extra lib gerekmez)
- Yeni route + controller method
- AJAX response: yeni tarih + success/error

**Çıktı:** Tek tıkla yeniden planlama

---

### 5. Multiple Schedule per Day
**Süre:** 2 saat | **Etki:** 🔥🔥 | **Maliyet:** $0

**Akış:**
1. Settings'te yeni alan: `instagram_daily_schedule_slots` (örn: "09:00,13:00,19:00")
2. Cron her slot için ayrı `Schedule::call(...)` ile tetiklenir
3. Her tetikleme `--limit=1` ile due olan postu yayınlar

**Bileşenler:**
- `routes/console.php` dinamik slot okuması
- Settings UI: virgülle ayrılmış saat listesi
- Heartbeat çoklu slot için güncellenir

**Çıktı:** Yoğun günlerde (kampanya, sezon) 2-3 paylaşım

---

### 6. Recycle / Tekrar Paylaş
**Süre:** 3 saat | **Etki:** 🔥🔥 | **Maliyet:** $0

**Akış:**
1. Yayınlanmış post show sayfasında "🔁 Tekrar Yayınla" butonu
2. Modal: yeni caption (AI öneri ile pre-fill), yeni tarih
3. Yeni `InstagramPost` kaydı (orijinal görseli reuse, ig_post_id boş)
4. Bonus: engagement_score > X olan postlar otomatik "tekrar yayın önerisi" işareti

**Bileşenler:**
- `recycle()` controller method
- AI caption regenerate (mevcut servis)
- `recycled_from_post_id` foreign key (history için)

**Çıktı:** İçerik kıtlığında kurtarıcı, popüler içerikten ek değer

---

## 🎯 Phase 3 — Etkileşim (Çift Yönlü İletişim)

### 7. Yorum Yönetimi (IG Comments)
**Süre:** 6-8 saat | **Etki:** 🔥🔥🔥 | **Maliyet:** $0

**Akış:**
1. Cron `instagram:sync-comments` (saatlik) → IG Graph API'den yorumlar çekilir
2. Yeni tablo: `instagram_post_comments` (id, post_id, ig_comment_id, username, text, replied_at)
3. `/admin/instagram-posts/{id}/comments` sayfası
4. Cevapla butonu → AJAX → `POST /{ig_comment_id}/replies`
5. AI Cevap Önerisi: Gemini ile "müşteriye nazik cevap" üret, admin onaylar

**Bileşenler:**
- Migration: `instagram_post_comments`
- `InstagramService::fetchComments($post)` + `replyToComment($commentId, $text)`
- Yeni Console: `SyncInstagramCommentsCommand`
- Admin panel: yorum listesi + reply form
- AI: Gemini ile reply suggestion

**Çıktı:** Etkileşim panelden yönetilir, kaçırılan yorum kalmaz, response time düşer

---

### 8. Mention Takibi
**Süre:** 4 saat | **Etki:** 🔥🔥 | **Maliyet:** $0

**Akış:**
1. Cron `instagram:sync-mentions` (günlük) → markanı etiketleyen postlar
2. IG Graph API: `/me/tags?fields=...` veya hashtag search
3. Admin panel: mention listesi → "Repost et" butonu (caption + credit)

**Bileşenler:**
- Migration: `instagram_mentions`
- Yeni Console + Service method
- Admin panel: mention feed

**Çıktı:** UGC (user-generated content) yakalama, müşteri sadakati

---

## 🟡 Phase 4 — Analytics / İçgörü

### 9. Hashtag Performans Analizi
**Süre:** 3 saat | **Etki:** 🔥🔥 | **Maliyet:** $0

**Akış:**
1. Cron `instagram:analyze-hashtags` (haftalık)
2. Her HashtagPool tag'i için: bu tag'i içeren postların ortalama engagement'i
3. `HashtagPool::avg_engagement_score` kolonu güncellenir
4. AI caption üretirken score'a göre öncelik
5. Hashtag pool admin sayfasında performans göster

**Bileşenler:**
- HashtagPool migration: `avg_engagement_score`, `last_analyzed_at`
- Console command + scheduler
- Admin UI'a kolon ekle

**Çıktı:** Smart hashtag seçimi, organic reach artışı

---

### 10. Engagement Trend Grafiği
**Süre:** 3-4 saat | **Etki:** 🔥🔥 | **Maliyet:** $0

**Akış:**
1. Dashboard'a yeni widget: 30/90 gün line chart
2. Like + comment toplamı + reach
3. Media type bazlı kıyas (Image vs Reels vs Story)
4. Chart.js veya basit SVG

**Bileşenler:**
- Dashboard widget
- Aggregate query (group by date)
- Chart.js CDN (zaten var mı kontrol edilecek)

**Çıktı:** Stratejik karar verme — hangi tip içerik tutuyor

---

### 11. Cross-Platform Karşılaştırma
**Süre:** 2 saat | **Etki:** 🔥 | **Maliyet:** $0

**Akış:**
1. Show sayfasına "FB Performansı" sekmesi (mevcut metrics ile yan yana)
2. FB Insights Graph API çağrısı (eğer varsa)
3. "FB'de 2x daha çok reach" gibi içgörüler

**Bileşenler:**
- `FacebookPageService::fetchPostMetrics($post)` (yeni method)
- Show sayfası UI

**Çıktı:** Hangi platform daha iyi çalışıyor görünür

---

## 🔵 Phase 5 — Lüks (Sonra eklenebilir)

### 12. Story Highlights Otomasyonu
IG profilindeki Story Highlights'a otomatik kategori ekleme. Belirli etiketlerle paylaşılan story'ler otomatik highlight'a girer. (~4-5 saat)

### 13. Brand Asset Library
Logo, watermark, marka renkleri kayıtlı → AI görsel üretirken otomatik uygula. (~4 saat)

### 14. Onay Akışı (Multi-User)
Admin1 taslak hazırlar → Admin2 onaylar → cron yayınlar (ekip için). User policy + workflow state. (~5-6 saat)

### 15. Stok Düşük → Acil Post
Ürün stoğu eşik altına düşünce: AI ile "son 5 adet" post taslağı oluştur. (~2 saat)

### 16. Carousel Template'leri
Önceden hazırlanmış kalıplar: "Yeni ürün serisi", "Mutfaktan", "Müşteri yorumu". (~3 saat)

### 17. Caption Template Library
Sık kullanılan caption kalıpları kayıtlı, hızlı yeniden kullan. (~2 saat)

### 18. Otomatik Altyazı (Reels/Story)
AI ile video transkripsiyonu + altyazı yazma. (~6 saat — ek API gerek)

---

## 🚫 Önermediklerim (kapsamım dışı veya değer/maliyet uyumsuz)

| Özellik | Sebep |
|---|---|
| **A/B caption testing** | Anlamlı sonuç için 1000+ post gerek, küçük volume için faydasız |
| **Webhook entegrasyonu** | Polling yeterli, kompleksitesini hak etmez |
| **Influencer yönetimi** | Kapsam çok geniş, ayrı modül olur |
| **Loyalty program** | Sosyal medya değil, e-ticaret tarafı |

---

## 📊 Karşılaştırma Tablosu

| # | Özellik | Phase | Etki | Süre | Maliyet | Bağımlılık |
|---|---|---|---|---|---|---|
| 1 | Blog → IG Otomatik | 1 | 🔥🔥🔥 | 3-4 sa | $0 | Blog AI (var) |
| 2 | Best-Time | 1 | 🔥🔥🔥 | 3-4 sa | $0 | Engagement (var) |
| 3 | Bulk Schedule | 2 | 🔥🔥🔥 | 4-5 sa | $0 | - |
| 4 | Calendar Drag-Drop | 2 | 🔥🔥 | 2 sa | $0 | - |
| 5 | Multiple Schedule | 2 | 🔥🔥 | 2 sa | $0 | - |
| 6 | Recycle | 2 | 🔥🔥 | 3 sa | $0 | - |
| 7 | Yorum Yönetimi | 3 | 🔥🔥🔥 | 6-8 sa | $0 | IG Graph API |
| 8 | Mention Takibi | 3 | 🔥🔥 | 4 sa | $0 | IG Graph API |
| 9 | Hashtag Performans | 4 | 🔥🔥 | 3 sa | $0 | Engagement (var) |
| 10 | Engagement Trend Grafik | 4 | 🔥🔥 | 3-4 sa | $0 | Engagement (var) |
| 11 | Cross-Platform Karşılaştırma | 4 | 🔥 | 2 sa | $0 | FB Insights API |
| 12-18 | Lüks (Phase 5) | 5 | 🔥 | 2-6 sa | $0 | - |

---

## 🎯 Önerilen Yol Haritası

**Phase 1** (manuel iş sıfırlanır) → ~7-8 saat:
- Blog → IG otomatik post
- Best-time-to-post

**Phase 2** (iş hızı + toplu işlemler) → ~11-12 saat:
- Bulk schedule
- Calendar drag-drop
- Multiple schedule
- Recycle

**Phase 3** (etkileşim) → ~10-12 saat:
- Yorum yönetimi
- Mention takibi

**Phase 4** (analytics) → ~8-9 saat:
- Hashtag performans
- Engagement trend grafiği
- Cross-platform karşılaştırma

**Phase 5** (lüks) → ihtiyaç ortaya çıkınca:
- Story highlights, brand asset, onay akışı, stok düşük post, vb.

---

## 📝 Notlar

- Tüm fazlar **birbirinden bağımsız** uygulanabilir, sıra sadece tavsiye
- Her faz ayrı bir Pull Request olarak yapılabilir, birleştirildiğinde stable kalır
- Her özellik için **audit + test** turu ekleyeceğim (mevcut Kritik 3'te yaşadığımız bug pattern'i tekrarlanmasın diye)
- **Production için:** Her phase commit'inden sonra `php artisan migrate && php artisan optimize:clear && php artisan optimize` yeterli
- Bu plan **kapsamlı bir backlog** — hepsi yapılmak zorunda değil, ihtiyaç önceliklerine göre seçilir

---

## 🔗 İlgili Dökümanlar

- [Instagram + Facebook Cross-Post Planlama](instagram-facebook-cross-post-planlama.md) — başlangıç planı
- [Google API Entegrasyonu](google-api-entegrasyonu.md) — paralel modül
- `CLAUDE.md` — proje kuralları
