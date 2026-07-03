# Vertex Toplu Görsel Üretim — Uygulama Planı

## Özet

`/admin/vertex` sayfasında tek görsel yerine **toplu (batch) üretim** yapılabilecek.
Kullanıcı adet seçer (preset butonlar: 10, 20, 30, 40, 50, 100 veya özel sayı), "Üret" basar.
Tüm görseller kuyruğa yazılır, cron her 10 saniyede bir sıradaki görseli üretir.
Hata olduğunda Telegram'a bildirim gider.

---

## 1. Migration: `vertex_batches` tablosu

Yeni tablo — bir batch N tane generation'ı gruplar.

| Kolon | Tip | Açıklama |
|-------|-----|----------|
| id | bigIncrements | PK |
| prompt_id | FK → vertex_prompts | Hangi şablon |
| variables | JSON nullable | `{{değişken}}` değerleri snapshot |
| total_count | unsignedInteger | İstenen toplam görsel sayısı |
| completed_count | unsignedInteger default 0 | Başarılı sayısı |
| failed_count | unsignedInteger default 0 | Başarısız sayısı |
| status | enum: pending/processing/completed/partial_completed/cancelled | Batch durumu |
| started_at | timestamp nullable | İlk görsel üretimi başladığında |
| finished_at | timestamp nullable | Son görsel tamamlandığında |
| total_generation_time_ms | unsignedBigInteger default 0 | Toplam API süresi (ms) |
| created_by | FK → users nullable | Kim oluşturdu |
| timestamps + softDeletes | | |

## 2. Migration: `vertex_generations` tablosuna yeni kolonlar

| Kolon | Tip | Açıklama |
|-------|-----|----------|
| batch_id | FK → vertex_batches nullable | Hangi batch'e ait (null = eski tek üretimler) |
| queue_position | unsignedInteger nullable | Batch içindeki sıra numarası (1, 2, 3...) |
| started_at | timestamp nullable | API isteği başlangıç zamanı |
| finished_at | timestamp nullable | API isteği bitiş zamanı |
| cost_usd | decimal(8,6) nullable | Tahmini maliyet (varsa) |
| token_count | unsignedInteger nullable | Kullanılan token sayısı (API dönerse) |

## 3. Model: `VertexBatch`

- `$fillable`: tüm kolonlar
- İlişkiler: `belongsTo(VertexPrompt)`, `hasMany(VertexGeneration)`, `belongsTo(User, 'created_by')`
- Scopes: `pending()`, `processing()`, `completed()`
- Hesaplanan: `progress()` → `(completed_count + failed_count) / total_count * 100`
- Status yönetimi: `markProcessing()`, `incrementCompleted()`, `incrementFailed()`, `checkAndFinalize()`

## 4. Model güncelleme: `VertexGeneration`

- `$fillable`'a `batch_id`, `queue_position`, `started_at`, `finished_at`, `cost_usd`, `token_count` ekle
- Yeni ilişki: `belongsTo(VertexBatch)`
- Yeni scope: `queued()` → status=pending, batch_id not null, sıralı

## 5. Service: `VertexBatchService`

```php
createBatch(VertexPrompt $template, int $count, array $variables, ?int $userId): VertexBatch
```
- Batch kaydı oluştur (status=pending)
- `$count` adet `VertexGeneration` kaydı oluştur (status=pending, queue_position=1..N)
- Tüm generation'lar aynı `prompt_used` (değişkenler doldurulmuş) ile snapshot alınır
- DB::transaction ile atomik

## 6. Service güncelleme: `VertexImageService::execute()`

- `started_at` → API çağrısı öncesi kaydet
- `finished_at` → API çağrısı sonrası kaydet
- Response'dan token_count çıkarmayı dene (Vertex API `usageMetadata` döndürüyorsa)
- Batch varsa: `$generation->batch->incrementCompleted()` veya `incrementFailed()` çağır
- Hata durumunda: `TelegramNotifier::send()` ile hata bilgisi gönder

## 7. Artisan Command: `vertex:process-batch`

Cron tarafından **her dakika** çalıştırılır, `withoutOverlapping` ile tekli.

```php
Schedule::command('vertex:process-batch')
    ->everyMinute()
    ->withoutOverlapping(10); // max 10 dk lock
```

**Akış:**
1. İşlenmekte olan (`processing`) veya bekleyen (`pending`) bir batch bul (en eski)
2. Batch status=pending ise → `processing` yap, `started_at` set et
3. Bu batch'ten sıradaki pending generation'ı al (`queue_position` sırasıyla)
4. `VertexImageService::execute()` çağır
5. Sonucu güncelle (completed/failed)
6. Batch sayaçlarını güncelle
7. 10 saniye bekle
8. Sonraki pending generation'a geç (aynı batch içinde)
9. Batch'teki tüm generation'lar tamamlanınca → batch status = `completed` veya `partial_completed`
10. Zaman aşımı: command timeout 10dk, her döngüde kontrol

**Hata yönetimi:**
- Tek görsel hata alırsa → failed olarak işaretle, sonrakine geç
- Ardışık 5 hata → batch'i durdur, Telegram'a "batch durdu" bildir
- API key eksik → batch'i iptal et, Telegram bildir

## 8. Telegram Bildirimleri

Mevcut `TelegramNotifier::send()` kullanılacak.

**Bildirim gönderilecek durumlar:**
- Batch başladı: `🚀 Vertex Batch #{id} başladı — {count} görsel üretilecek ({şablon_adı})`
- Batch tamamlandı: `✅ Vertex Batch #{id} tamamlandı — {completed}/{total} başarılı, {failed} hatalı, toplam süre: {time}`
- Tek görsel hata: `⚠️ Vertex #{generation_id} hata: {error_message} (Batch #{batch_id}, sıra {position}/{total})`
- Batch durdu (ardışık hata): `🛑 Vertex Batch #{id} DURDU — ardışık {n} hata! Son hata: {msg}`
- API key eksik: `❌ Vertex API key eksik — batch iptal edildi`

## 9. Controller güncellemeleri

**`VertexImageController`:**
- `index()` → mevcut + `$activeBatches` (işlemdeki batch'ler), `$recentBatches` (son 10)
- `generateBatch(Request)` → yeni POST endpoint, count + prompt_id + variables alır
- `batchStatus(VertexBatch)` → JSON polling endpoint (progress, counts, status)
- `cancelBatch(VertexBatch)` → batch iptal et, pending generation'ları cancelled yap

**Routes (yeni):**
```
POST   /admin/vertex/generate-batch              → generateBatch (rate limit: 10/dk)
GET    /admin/vertex/batch/{batch}                → batchStatus (polling)
POST   /admin/vertex/batch/{batch}/cancel         → cancelBatch
```

## 10. UI güncellemeleri: `/admin/vertex` sayfası

### 10a. Adet seçici (şablon seçiminden sonra görünür)
- Preset butonlar: `10`, `20`, `30`, `40`, `50`, `100` (toggle grup)
- Özel sayı input'u: number field, min=1 (limit yok)
- Seçilen değer bir hidden input'a yazılır
- "1" seçiliyse mevcut tek-üretim davranışı (eski sistem)

### 10b. "Üret" butonu güncelleme
- Count > 1 ise: "🚀 {n} Görsel Üret" yazısı
- Count = 1 ise: mevcut "Görseli Üret" yazısı

### 10c. Aktif Batch Paneli (sağ tarafta veya alt kısımda)
- İşlemdeki batch varsa: progress bar + sayaçlar
- `{completed}/{total} tamamlandı · {failed} hatalı`
- Her 3 saniyede polling ile güncellenir
- "İptal Et" butonu
- Son üretilen görselin thumbnail'ı

### 10d. Batch Geçmişi (Son Üretimler bölümünün üstünde)
- Son 10 batch kartı: şablon adı, tarih, toplam/başarılı/hatalı, süre, status badge
- Progress bar (tamamlanma yüzdesi)
- Tıklayınca batch detay sayfasına veya modal'a gider

### 10e. Son Üretimler güncellemesi
- Mevcut galeri korunur, batch bilgisi eklenir (batch badge)

## 11. Dosya listesi (oluşturulacak/değiştirilecek)

### Yeni dosyalar:
1. `database/migrations/xxxx_create_vertex_batches_table.php`
2. `database/migrations/xxxx_add_batch_columns_to_vertex_generations.php`
3. `app/Models/VertexBatch.php`
4. `app/Services/VertexBatchService.php`
5. `app/Console/Commands/ProcessVertexBatch.php`

### Değiştirilecek dosyalar:
6. `app/Models/VertexGeneration.php` → batch ilişkisi + yeni kolonlar
7. `app/Services/Vertex/VertexImageService.php` → started_at/finished_at, token, batch sayaç güncelleme, Telegram hata bildirimi
8. `app/Http/Controllers/Admin/VertexImageController.php` → batch endpoint'leri
9. `resources/views/admin/vertex/index.blade.php` → adet seçici, batch panel, batch geçmişi
10. `public/assets/admin/js/vertex-generate.js` → batch form submit, polling, UI
11. `routes/admin.php` → yeni route'lar
12. `routes/console.php` → schedule kaydı

## 12. İş akışı özeti

```
Kullanıcı: Şablon seç → Değişkenleri doldur → Adet seç (50) → "Üret"
     ↓
Controller: VertexBatch oluştur + 50 VertexGeneration (pending)
     ↓
UI: Progress bar göster, 3sn polling başla
     ↓
Cron (her dakika): vertex:process-batch komutu çalışır
     ↓
Command: Pending batch bul → sıradaki generation'ı al → execute() → 10sn bekle → sonraki
     ↓
Her başarı/hata: Batch sayaçları güncelle, UI polling ile görür
     ↓
Hata olursa: TelegramNotifier ile bildir
     ↓
Tüm generation'lar bitince: Batch finalize → Telegram özet gönder
```
