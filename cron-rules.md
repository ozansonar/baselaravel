# Shared Hosting Cron & Queue Kuralları

Bu proje shared hosting'de (LiteSpeed + PHP 8.3) çalışıyor.
Aşağıdaki kısıtlamalar ve çözümler belgelenmiştir.

---

## Hosting Kısıtlamaları

| Fonksiyon | Durum | Etkisi |
|-----------|-------|--------|
| `exec()`, `shell_exec()`, `system()`, `passthru()` | YASAK | Subprocess açılamaz |
| `pcntl_signal()`, `pcntl_fork()`, `pcntl_exec()` | YASAK | Laravel Worker sınıfı çalışmaz |
| `proc_open()` | AÇIK | Ama queue:work yine pcntl istiyor |
| `mail()` | YASAK | SMTP kullanmak zorunlu |

---

## Queue Sistemi

### Kullanılmaması Gerekenler

```php
// YASAK - exec() gerektirir (subprocess açar)
Schedule::command('queue:work --stop-when-empty');

// YASAK - pcntl_signal() gerektirir (Worker sınıfı içinde)
Schedule::call(function () {
    Artisan::call('queue:work', ['--stop-when-empty' => true]);
});
```

### Doğru Kullanım: Queue::pop() + fire()

```php
// DOĞRU - Hiçbir sistem fonksiyonu gerektirmez
Schedule::call(function () {
    $maxJobs = 20;
    $maxTime = 50;
    $start = time();
    $processed = 0;

    $queue = app('queue')->connection('database');

    while ($processed < $maxJobs && (time() - $start) < $maxTime) {
        $job = $queue->pop('default');

        if (!$job) {
            break;
        }

        try {
            $job->fire();
            $job->delete();
            $processed++;
        } catch (\Throwable $e) {
            $job->fail($e);
            report($e);
        }
    }
})->name('queue-worker')->everyMinute()->withoutOverlapping(2);
```

### Neden Bu Yöntem?

1. **`Schedule::command()`** → Arka planda `exec()` ile yeni process açar → shared hosting'de `exec()` disabled → ÇALIŞMAZ
2. **`Artisan::call('queue:work')`** → Aynı process içinde çalışır ama Laravel'in `Worker` sınıfı sinyal yönetimi için `pcntl_signal()` kullanır → pcntl uzantısı yok → ÇALIŞMAZ
3. **`Queue::pop()->fire()`** → Worker sınıfını tamamen bypass eder, job'u doğrudan çalıştırır → Hiçbir özel PHP uzantısı gerektirmez → ÇALIŞIR

---

## Schedule::call() Kuralları

- **`Schedule::command()` → YASAK** (shared hosting'de `exec()` ile subprocess açar, çalışmaz)
- **`->runInBackground()` → YASAK** (ek olarak `exec()` gerektirir)
- Tüm schedule tanımları `Schedule::call()` ile yapılmalı
- Artisan komutları çalıştırmak için: `Schedule::call(fn() => Artisan::call('komut'))` kullan
- `withoutOverlapping()` kullanılacaksa `->name('...')` ZORUNLU
  - `Schedule::command()` komut adını otomatik alır, `call()` alamaz
- Closure içinde max süre kontrolü yap (cron dakikası aşılmasın)
- `->everyMinute()` ile çalıştır, hosting cron'u da dakikalık olmalı

---

## Hosting Paneli Cron Tanımı

cPanel veya benzeri panelde şu komut tanımlanmalı:

```
php /home/KULLANICI/public_html/artisan schedule:run >> /dev/null 2>&1
```

- Periyot: **Her dakika** (every minute / `* * * * *`)
- PHP yolu hosting'e göre değişebilir (`/usr/local/bin/php`, `/opt/alt/php83/usr/bin/php` vb.)

---

## Mail Gönderimi

- `mail()` fonksiyonu disabled → `.env`'de `MAIL_MAILER=smtp` kullan
- Queue driver: `QUEUE_CONNECTION=database`
- Mail notification'lar queue üzerinden gönderilmeli (`implements ShouldQueue`)

---

## Debug / Test

Sorun çıkarsa `routes/web.php` içindeki `/debug-queue-check-2026` endpoint'i ile test edilebilir.
Bu endpoint job'ları pop & fire ederek queue'nun çalışıp çalışmadığını gösterir.
**Production'da kaldırılmalı veya middleware ile korunmalı.**
