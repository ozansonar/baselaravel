<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiLog;
use App\Models\InstagramPost;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Sistem sağlık kontrol servisi — admin paneli için tek-bakışta sistem durumu.
 *
 * Her kontrol şu yapıyı döner:
 *  ['key' => string, 'label' => string, 'status' => 'ok|warning|critical',
 *   'message' => string, 'detail' => ?string]
 */
final class HealthCheckService
{
    public const STATUS_OK       = 'ok';
    public const STATUS_WARNING  = 'warning';
    public const STATUS_CRITICAL = 'critical';

    /**
     * Tüm kontrolleri çalıştır + özet skor + zaman damgası döner.
     *
     * @return array{
     *     summary: array{ok: int, warning: int, critical: int, total: int, overall: string},
     *     checked_at: string,
     *     checks: list<array{key: string, label: string, status: string, message: string, detail: ?string}>
     * }
     */
    public function runAll(): array
    {
        $checks = [
            $this->checkDatabase(),
            $this->checkQueueWorker(),
            $this->checkDiskSpace(),
            $this->checkInstagramToken(),
            $this->checkAiUsage(),
            $this->checkTelegram(),
            $this->checkStorageWritable(),
            $this->checkPhpExtensions(),
            $this->checkLastInstagramPublish(),
            $this->checkLastBackup(),
        ];

        $counts = ['ok' => 0, 'warning' => 0, 'critical' => 0];
        foreach ($checks as $c) {
            $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
        }

        $overall = $counts['critical'] > 0
            ? self::STATUS_CRITICAL
            : ($counts['warning'] > 0 ? self::STATUS_WARNING : self::STATUS_OK);

        return [
            'summary' => [
                'ok'       => $counts['ok'],
                'warning'  => $counts['warning'],
                'critical' => $counts['critical'],
                'total'    => count($checks),
                'overall'  => $overall,
            ],
            'checked_at' => now()->toIso8601String(),
            'checks'     => $checks,
        ];
    }

    /** @return array<string, mixed> */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::getDriverName();
            $version = match ($driver) {
                'mysql', 'mariadb' => (string) (DB::selectOne('select version() as v')->v ?? 'unknown'),
                'sqlite'           => (string) (DB::selectOne('select sqlite_version() as v')->v ?? 'unknown'),
                'pgsql'            => (string) (DB::selectOne('show server_version')->server_version ?? 'unknown'),
                default            => 'unknown',
            };

            return $this->result('db', 'Veritabanı', self::STATUS_OK,
                'Bağlantı sağlıklı', strtoupper($driver) . ' ' . $version);
        } catch (\Throwable $e) {
            return $this->result('db', 'Veritabanı', self::STATUS_CRITICAL,
                'Bağlantı kurulamadı', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function checkQueueWorker(): array
    {
        try {
            $pending = (int) DB::table('jobs')->count();
            $oldest = DB::table('jobs')->orderBy('available_at')->value('available_at');
            $failed = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            // Eski beklemede iş var mı (10dk+)
            if ($oldest && (time() - (int) $oldest) > 600) {
                return $this->result('queue', 'Queue Worker', self::STATUS_WARNING,
                    "Queue tıkanmış olabilir — bekleyen: {$pending} iş (10dk+ eski)",
                    "En eski iş: " . Carbon::createFromTimestamp((int) $oldest)->diffForHumans());
            }

            if ($failed > 5) {
                return $this->result('queue', 'Queue Worker', self::STATUS_WARNING,
                    "Son 24 saatte {$failed} job fail oldu",
                    'Bekleyen: ' . $pending . ' iş');
            }

            return $this->result('queue', 'Queue Worker', self::STATUS_OK,
                'Çalışıyor (bekleyen: ' . $pending . ' iş)',
                'Son 24sa fail: ' . $failed);
        } catch (\Throwable $e) {
            return $this->result('queue', 'Queue Worker', self::STATUS_CRITICAL,
                'Queue tablosu okunamadı', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function checkDiskSpace(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false) {
            return $this->result('disk', 'Disk', self::STATUS_WARNING,
                'Disk bilgisi alınamadı', null);
        }

        $usedPct = $total > 0 ? round((($total - $free) / $total) * 100) : 0;
        $freeGB = round($free / 1_073_741_824, 2);
        $totalGB = round($total / 1_073_741_824, 2);

        $status = $usedPct >= 90
            ? self::STATUS_CRITICAL
            : ($usedPct >= 75 ? self::STATUS_WARNING : self::STATUS_OK);

        return $this->result('disk', 'Disk', $status,
            "%{$usedPct} dolu — {$freeGB} GB boş",
            "Toplam: {$totalGB} GB");
    }

    /** @return array<string, mixed> */
    private function checkInstagramToken(): array
    {
        $expiresAt = Setting::getValue('instagram_token_expires_at', '');

        if (empty($expiresAt)) {
            return $this->result('ig_token', 'Instagram Token', self::STATUS_WARNING,
                'Token süresi tanımlı değil', 'Settings → Instagram');
        }

        try {
            $expires = Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return $this->result('ig_token', 'Instagram Token', self::STATUS_WARNING,
                'Geçersiz tarih formatı', $expiresAt);
        }

        if ($expires->isPast()) {
            return $this->result('ig_token', 'Instagram Token', self::STATUS_CRITICAL,
                'Token süresi DOLMUŞ — paylaşımlar başarısız olur',
                'Bitiş: ' . $expires->format('d.m.Y'));
        }

        // Carbon 3: diffInDays işaretli float döner. Token gelecekte olduğu için
        // pozitif; floor() ile aşağı yuvarlayıp gün sayısını tam alıyoruz.
        $daysLeft = (int) floor(now()->diffInDays($expires, absolute: false));

        if ($daysLeft <= 7) {
            return $this->result('ig_token', 'Instagram Token', self::STATUS_WARNING,
                "Token {$daysLeft} gün içinde süresi dolacak",
                'Settings → Token Yenile');
        }

        return $this->result('ig_token', 'Instagram Token', self::STATUS_OK,
            "Geçerli ({$daysLeft} gün kaldı)",
            'Bitiş: ' . $expires->format('d.m.Y'));
    }

    /** @return array<string, mixed> */
    private function checkAiUsage(): array
    {
        try {
            $last24h = AiLog::where('created_at', '>=', now()->subDay())->count();
            $cost = (float) AiLog::where('created_at', '>=', now()->subDay())->sum('cost_usd');

            return $this->result('ai_usage', 'AI Kullanımı (24sa)', self::STATUS_OK,
                "{$last24h} çağrı — \$" . number_format($cost, 4),
                'Detay: /admin/ai-logs');
        } catch (\Throwable $e) {
            return $this->result('ai_usage', 'AI Kullanımı', self::STATUS_WARNING,
                'AI logları okunamadı', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function checkTelegram(): array
    {
        $enabled = Setting::getValue('telegram_enabled', '0') === '1';
        $token = trim((string) Setting::getValue('telegram_bot_token', ''));
        $chatId = trim((string) Setting::getValue('telegram_chat_id', ''));

        if (! $enabled) {
            return $this->result('telegram', 'Telegram', self::STATUS_OK,
                'Pasif (kapalı)', 'Settings → Telegram');
        }

        if ($token === '' || $chatId === '') {
            return $this->result('telegram', 'Telegram', self::STATUS_WARNING,
                'Aktif ama token veya chat_id boş', null);
        }

        // Hafif ping — getMe çağrısı (bot token doğrulaması, mesaj atmaz)
        try {
            $response = Http::timeout(5)->get('https://api.telegram.org/bot' . $token . '/getMe');
            if ($response->successful() && ($response->json('ok') ?? false)) {
                $username = (string) $response->json('result.username');
                return $this->result('telegram', 'Telegram', self::STATUS_OK,
                    'Bağlantı sağlıklı', '@' . $username);
            }
            return $this->result('telegram', 'Telegram', self::STATUS_WARNING,
                'Bot token geçersiz', (string) $response->json('description'));
        } catch (\Throwable $e) {
            return $this->result('telegram', 'Telegram', self::STATUS_WARNING,
                'API\'ye ulaşılamadı', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function checkStorageWritable(): array
    {
        $tests = [
            'storage/logs'           => storage_path('logs'),
            'storage/framework/views'=> storage_path('framework/views'),
            'public/uploads'         => public_path('uploads'),
        ];

        $errors = [];
        foreach ($tests as $name => $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $errors[] = $name;
            }
        }

        if ($errors !== []) {
            return $this->result('storage', 'Storage Yazma', self::STATUS_CRITICAL,
                'Yazılamayan dizin: ' . implode(', ', $errors),
                'chmod 775 + chown gerekli');
        }

        return $this->result('storage', 'Storage Yazma', self::STATUS_OK,
            'Tüm dizinler yazılabilir', count($tests) . ' dizin kontrol edildi');
    }

    /** @return array<string, mixed> */
    private function checkPhpExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'intl', 'zip', 'json', 'curl'];
        $missing = [];
        foreach ($required as $ext) {
            if (! extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if ($missing !== []) {
            return $this->result('php_ext', 'PHP Modülleri', self::STATUS_CRITICAL,
                'Eksik modül: ' . implode(', ', $missing),
                'php.ini kontrol et');
        }

        return $this->result('php_ext', 'PHP Modülleri', self::STATUS_OK,
            'Tüm gerekli modüller yüklü',
            'PHP ' . PHP_VERSION . ' — ' . count($required) . ' modül OK');
    }

    /** @return array<string, mixed> */
    private function checkLastInstagramPublish(): array
    {
        try {
            $lastPublished = InstagramPost::query()
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->value('published_at');

            if (! $lastPublished) {
                return $this->result('last_publish', 'Son IG Paylaşım', self::STATUS_WARNING,
                    'Henüz hiç post yayınlanmamış', null);
            }

            $when = Carbon::parse($lastPublished);
            $diff = $when->diffForHumans();
            // Carbon 3: absolute:true → her zaman pozitif saat farkı
            $hoursAgo = (int) floor($when->diffInHours(now(), absolute: true));

            $status = $hoursAgo > 168 ? self::STATUS_WARNING : self::STATUS_OK; // 1 hafta üstü uyarı

            return $this->result('last_publish', 'Son IG Paylaşım', $status,
                $diff, $when->format('d.m.Y H:i'));
        } catch (\Throwable $e) {
            return $this->result('last_publish', 'Son IG Paylaşım', self::STATUS_WARNING,
                'Sorgu başarısız', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function checkLastBackup(): array
    {
        // Backup feature henüz aktif değil — Setting'ten son backup zamanı oku
        $lastBackup = Setting::getValue('last_backup_at', '');

        if (empty($lastBackup)) {
            return $this->result('last_backup', 'Son Yedek', self::STATUS_WARNING,
                'Henüz yedek alınmamış', '/admin/yedekler');
        }

        try {
            $when = Carbon::parse($lastBackup);
            // Carbon 3: absolute:true → her zaman pozitif saat farkı
            $hoursAgo = (int) floor($when->diffInHours(now(), absolute: true));

            $status = $hoursAgo > 48 ? self::STATUS_WARNING : self::STATUS_OK;

            return $this->result('last_backup', 'Son Yedek', $status,
                $when->diffForHumans(), $when->format('d.m.Y H:i'));
        } catch (\Throwable $e) {
            return $this->result('last_backup', 'Son Yedek', self::STATUS_WARNING,
                'Tarih parse hatası', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function result(string $key, string $label, string $status, string $message, ?string $detail): array
    {
        return [
            'key'     => $key,
            'label'   => $label,
            'status'  => $status,
            'message' => $message,
            'detail'  => $detail,
        ];
    }
}
