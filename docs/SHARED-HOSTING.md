# Shared Hosting — Cron, Queue ve Kısıtlamalar

Bu proje shared hosting'de (LiteSpeed + PHP 8.4) çalışacak şekilde yazıldı.
Buradaki kurallar tercih değil **zorunluluk**: ihlal edildiğinde kod hata
vermez, sessizce hiç çalışmaz. En tehlikeli tarafı bu — yedek alınmadığını
yedeğe ihtiyaç duyduğunda öğrenirsin.

> Yeni bir zamanlanmış görev veya kuyruk işi eklemeden önce bu dosyayı oku.
> `ScheduleUsesCallablesTest` kuralları test olarak da koruyor.

---

## 1. Hosting kısıtlamaları

| Fonksiyon | Durum | Etkisi |
|-----------|-------|--------|
| `exec()`, `shell_exec()`, `system()`, `passthru()` | **Kapalı** | Alt süreç açılamaz |
| `pcntl_signal()`, `pcntl_fork()`, `pcntl_exec()` | **Kapalı** | Laravel `Worker` sınıfı çalışmaz |
| `proc_open()` | Açık | Ama `queue:work` yine `pcntl` istiyor |
| `mail()` | **Kapalı** | SMTP zorunlu |

---

## 2. Zamanlanmış görevler

### ❌ Yasak

```php
// Komutu ayrı bir süreçte çalıştırmaya çalışır → alt süreç açılamaz → HİÇ ÇALIŞMAZ
Schedule::command('backup:run')->daily();

// Ek olarak alt süreç gerektirir
Schedule::call(...)->runInBackground();

// Worker sınıfı pcntl_signal() kullanır
Schedule::call(fn () => Artisan::call('queue:work'));
```

`Schedule::command()` hata fırlatmaz. Görev listede görünür, `schedule:list`
çıktısında durur, ama tetiklendiğinde hiçbir şey olmaz.

### ✅ Doğru

```php
Schedule::call(fn () => Artisan::call('backup:run'))
    ->name('backup-daily')      // withoutOverlapping için ZORUNLU
    ->dailyAt('05:00')
    ->withoutOverlapping(60);
```

`Artisan::call()` komutu **aynı PHP sürecinde** çalıştırır; alt süreç, sinyal
yönetimi, özel uzantı gerekmez.

### Bundan doğan üç kural

**a) İsim zorunlu.** `withoutOverlapping()` kilidi görev adına bakar.
`Schedule::command()` adı komuttan alır, `Schedule::call()` alamaz —
`->name()` yazılmazsa çalışma anında patlar.

**b) Hata izole edilmeli.** Tüm görevler tek PHP sürecini paylaşır; birinde
çıkan istisna geri kalanını da düşürür. `routes/console.php` içindeki `$run`
yardımcısı her komutu `try/catch` ile sarar ve hatayı loglar.

**c) Saatler çakışmamalı.** Aynı dakikaya denk gelen görevler arka arkaya, tek
süreçte çalışır. `withoutOverlapping()` yalnızca **aynı** görevin üst üste
binmesini engeller, farklı görevlerin aynı anda sıraya girmesini değil. Bu
yüzden yavaş işlere ayrı saat verilir.

### Mevcut takvim

| Saat | Görev | Sıklık |
|------|-------|--------|
| her dakika | `queue-worker` | Kuyruk işlerini işler |
| her 5 dakika | `campaigns-dispatch` | Toplu mail gönderimi |
| 02:00 | `analytics-aggregate-daily` | Günlük istatistik toplama |
| 03:00 | `analytics-anonymize-ips` | 90 günden eski IP'leri maskeler (KVKK) |
| 03:30 Pazar | `audit-logs-prune` | 90 günden eski denetim kayıtlarını siler |
| 04:00 Pazar | `analytics-prune-old` | 365 günden eski ziyaretleri siler |
| 05:00 | `backup-daily` | Veritabanı + uploads → ZIP |

`campaigns-dispatch` aralığı `CampaignDispatcher::RUN_INTERVAL_MINUTES` ile
**aynı olmak zorunda** — saatlik gönderim limiti bu sayıdan hesaplanır. Test
ikisinin birbirini tuttuğunu doğruluyor.

---

## 3. Kuyruk (queue)

`queue:work` bu hostingte çalışmaz: Laravel'in `Worker` sınıfı sinyal yönetimi
için `pcntl_signal()` kullanır. Onun yerine işler doğrudan pop edilip
çalıştırılır — `routes/console.php` içindeki `queue-worker` görevi bunu yapar:

```php
$job = $queue->pop('default');
$job->fire();
$job->delete();
```

`Worker` sınıfını tamamen atlar, hiçbir özel uzantı gerektirmez.

Sınırlar bilinçli: turda en fazla **20 iş**, en fazla **50 saniye**. Cron
dakikası aşılmasın diye pay bırakılmıştır.

`.env`:

```env
QUEUE_CONNECTION=database
```

---

## 4. Hosting panelinde cron tanımı

cPanel → Cron Jobs → **her dakika** (`* * * * *`):

```
php /home/KULLANICI/public_html/artisan schedule:run >> /dev/null 2>&1
```

PHP yolu hostinge göre değişir. Doğrusunu bulmak için:

```bash
which php
# veya
ls /opt/alt/php84/usr/bin/php /usr/local/bin/php 2>/dev/null
```

Sık karşılaşılanlar: `/usr/local/bin/php`, `/opt/alt/php84/usr/bin/php`,
`/opt/cpanel/ea-php84/root/usr/bin/php`

> **Tek bir cron yeter.** Laravel scheduler'ı her dakika çağrılır ve hangi
> görevin zamanı geldiğine kendisi karar verir. Her görev için ayrı cron
> tanımlamaya gerek yok.

### Cron gerçekten çalışıyor mu?

```bash
php artisan schedule:list
```

Görevleri ve sıradaki çalışma zamanlarını gösterir. Tek seferlik denemek için:

```bash
php artisan schedule:run
```

Belirli bir görevi elle çalıştırmak için:

```bash
php artisan campaigns:dispatch
php artisan backup:run
```

---

## 5. Mail

- `mail()` kapalı → `.env`'de `MAIL_MAILER=smtp` **zorunlu**
- SMTP ayarları panelden de yönetilebilir (**Ayarlar → Mail**); veritabanındaki
  değerler `.env`'i ezer (`MailConfigServiceProvider`)
- Toplu gönderim saatlik limite göre yayılır → [README, Toplu mail](../README.md#toplu-mail-kampanyalar)

---

## 6. Dosya yükleme

- Yüklemeler `public/uploads/` altına yapılır, `storage/` **kullanılmaz** —
  symlink gerektirmez, shared hostingte symlink çoğu zaman sorun çıkarır
- Yol `config/uploads.php` üzerinden yapılandırılır (`UPLOADS_PATH`)
- Yazma izni: `public/uploads/` **755**, sahibi web kullanıcısı olmalı

---

## 7. Deploy sonrası kontrol listesi

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan schedule:list          # görevler görünüyor mu
```

- [ ] Panelde cron tanımlı ve **her dakika** çalışıyor
- [ ] `.env` içinde `MAIL_MAILER=smtp`, `QUEUE_CONNECTION=database`
- [ ] `public/uploads/` yazılabilir
- [ ] `storage/` ve `bootstrap/cache/` yazılabilir
- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Demo hesapların şifresi değiştirildi veya hesaplar silindi

---

## 8. Bu kuralları koruyan testler

`tests/Feature/ScheduleUsesCallablesTest.php`:

- Hiçbir görev `Schedule::command()` ile tanımlanmamış
- `runInBackground()` hiçbir yerde kullanılmamış
- Her görevin adı var (`withoutOverlapping` için gerekli)
- Beklenen yedi görevin hepsi kayıtlı
- Mail gönderim aralığı `CampaignDispatcher::RUN_INTERVAL_MINUTES` ile uyumlu
