<?php

declare(strict_types=1);

namespace App\Services;

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
 *
 * runAll() bunlara ekranın kullandığı bağlamı ekler: ikon, kontrolün ne işe
 * yaradığı, sorun hâlinde ne yapılacağı ve ilgili panel sayfası.
 */
final class HealthCheckService
{
    public const STATUS_OK       = 'ok';
    public const STATUS_WARNING  = 'warning';
    public const STATUS_CRITICAL = 'critical';

    /** Log dizini eşikleri. */
    public const LOG_WARNING_BYTES = 250 * 1_048_576;

    public const LOG_CRITICAL_BYTES = 1_073_741_824;

    /** Dönüş kapalıyken uyarmaya başlanan boyut. */
    public const LOG_UNROTATED_WARNING_BYTES = 20 * 1_048_576;

    /**
     * Kontrolün ekrandaki yüzü: ne işe yaradığı, sorun çıkınca ne yapılacağı
     * ve varsa ilgili panel sayfası.
     *
     * Kontrolün kendi mantığı buraya karışmaz; burada yalnızca kontrolü okuyan
     * kişinin ihtiyaç duyduğu bağlam durur.
     *
     * @var array<string, array{icon: string, what: string, hint: string, route: ?string}>
     */
    private const META = [
        'db' => [
            'icon'  => 'bi-database-fill-check',
            'what'  => 'Uygulamanın veritabanına bağlanıp sürümünü okur.',
            'hint'  => 'Bağlantı bilgilerini .env dosyasından doğrulayın; sunucu MySQL servisi ayakta mı bakın.',
            'route' => null,
        ],
        'queue' => [
            'icon'  => 'bi-stack',
            'what'  => 'Kuyrukta bekleyen ve son 24 saatte başarısız olan işleri sayar.',
            'hint'  => 'Bekleyen iş birikiyorsa kuyruk tetikleyicisinin (cron) çalıştığını doğrulayın.',
            'route' => null,
        ],
        'disk' => [
            'icon'  => 'bi-hdd-fill',
            'what'  => 'Sunucu diskinin ne kadarının dolu olduğuna bakar.',
            'hint'  => 'Eski yedekleri indirip silin, kullanılmayan görselleri temizleyin.',
            'route' => 'admin.backups.index',
        ],
        'telegram' => [
            'icon'  => 'bi-send-fill',
            'what'  => 'Telegram bildirimlerinin açık ve bot bilgilerinin geçerli olduğunu doğrular.',
            'hint'  => 'Ayarlar → Telegram bölümünden bot token ve chat id değerlerini kontrol edin.',
            'route' => 'admin.settings.index',
        ],
        'storage' => [
            'icon'  => 'bi-folder-fill',
            'what'  => 'Yükleme ve önbellek dizinlerine yazılabildiğini sınar.',
            'hint'  => 'İlgili dizinlerin sahipliğini ve 775 iznini kontrol edin.',
            'route' => null,
        ],
        'php_ext' => [
            'icon'  => 'bi-plug-fill',
            'what'  => 'Uygulamanın ihtiyaç duyduğu PHP modüllerinin yüklü olduğuna bakar.',
            'hint'  => 'Eksik modülü hosting panelinden ya da php.ini üzerinden etkinleştirin.',
            'route' => null,
        ],
        'logs' => [
            'icon'  => 'bi-file-earmark-text-fill',
            'what'  => 'Log dizininin boyutuna ve günlük dönüşün açık olduğuna bakar.',
            'hint'  => '.env dosyasında LOG_STACK=daily ve LOG_DAILY_DAYS=14 kullanın; dönmeyen log dosyası diski doldurur.',
            'route' => null,
        ],
        'last_backup' => [
            'icon'  => 'bi-archive-fill',
            'what'  => 'En son yedeğin ne zaman alındığını söyler.',
            'hint'  => 'Yedekler sayfasından elle yedek alın; otomatik yedek görevinin çalıştığını doğrulayın.',
            'route' => 'admin.backups.index',
        ],
    ];

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
            $this->checkTelegram(),
            $this->checkStorageWritable(),
            $this->checkPhpExtensions(),
            $this->checkLogs(),
            $this->checkLastBackup(),
        ];

        $checks = array_map(fn (array $check): array => $this->decorate($check), $checks);

        // Sorunlu kontroller başa: ekranı açan kişi önce neyin bozuk olduğunu
        // görmeli, sağlıklı olanlar altta kalabilir.
        usort($checks, static function (array $a, array $b): int {
            $weight = ['critical' => 0, 'warning' => 1, 'ok' => 2];

            return ($weight[$a['status']] ?? 3) <=> ($weight[$b['status']] ?? 3);
        });

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
            'public/uploads'         => UploadService::basePath(),
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

    /**
     * Log dizini.
     *
     * Dönmeyen bir log dosyası sessizce büyüyor ve dolduğu gün yalnız log
     * yazımını değil yüklemeyi, yedeklemeyi ve oturumu da durduruyor. Disk
     * kontrolü bunu ancak disk tamamen dolduğunda görüyor; burada sebebe
     * bakılıyor.
     *
     * @return array<string, mixed>
     */
    private function checkLogs(): array
    {
        $dir = $this->logDirectory();

        if (! is_dir($dir)) {
            return $this->result('logs', 'Log Dizini', self::STATUS_OK,
                'Henüz log yazılmamış', null);
        }

        $files = glob($dir . '/*.log') ?: [];
        $total = 0;

        foreach ($files as $file) {
            $total += (int) @filesize($file);
        }

        $rotating = $this->logRotationEnabled();
        $human = $this->humanBytes($total);
        $detail = count($files) . ' dosya · ' . ($rotating
            ? 'günlük dönüş açık, ' . (int) config('logging.channels.daily.days', 14) . ' gün saklanıyor'
            : 'günlük dönüş KAPALI — dosya hiç silinmiyor');

        if ($total >= self::LOG_CRITICAL_BYTES) {
            return $this->result('logs', 'Log Dizini', self::STATUS_CRITICAL,
                "Log dizini {$human}", $detail);
        }

        if ($total >= self::LOG_WARNING_BYTES) {
            return $this->result('logs', 'Log Dizini', self::STATUS_WARNING,
                "Log dizini {$human}", $detail);
        }

        // Dönüş kapalıyken küçük bir dosya bile sorunun başlangıcı: büyümesi
        // an meselesi. Sıfırdan uyarmak gereksiz gürültü olurdu, bu eşik
        // uyarının fark edilmesi için bol zaman bırakıyor.
        if (! $rotating && $total >= self::LOG_UNROTATED_WARNING_BYTES) {
            return $this->result('logs', 'Log Dizini', self::STATUS_WARNING,
                "Log dönüşü kapalı — {$human} birikmiş", $detail);
        }

        return $this->result('logs', 'Log Dizini', self::STATUS_OK,
            $human, $detail);
    }

    /**
     * Logların gerçekten yazıldığı dizin.
     *
     * `storage/logs` varsayılmıyor: kanalın kendi `path` değeri okunuyor, yani
     * log başka bir yere yönlendirilmişse kontrol oraya bakıyor.
     */
    private function logDirectory(): string
    {
        foreach ($this->activeLogChannels() as $channel) {
            $path = config("logging.channels.{$channel}.path");

            if (is_string($path) && $path !== '') {
                return dirname($path);
            }
        }

        return storage_path('logs');
    }

    /**
     * Şu an yazılan kanallar.
     *
     * @return list<string>
     */
    private function activeLogChannels(): array
    {
        $default = (string) config('logging.default', 'stack');

        $channels = $default === 'stack'
            ? (array) config('logging.channels.stack.channels', [])
            : [$default];

        return array_values(array_filter($channels, 'is_string'));
    }

    /**
     * Yazılan kanallardan en az biri günlük dönüyor mu?
     */
    private function logRotationEnabled(): bool
    {
        return array_any(
            $this->activeLogChannels(),
            static fn (string $channel): bool => config("logging.channels.{$channel}.driver") === 'daily',
        );
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2) . ' GB';
        }

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
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

    /**
     * Kontrol sonucuna ekranın ihtiyaç duyduğu bağlamı ekler.
     *
     * @param array<string, mixed> $check
     * @return array<string, mixed>
     */
    private function decorate(array $check): array
    {
        $meta = self::META[$check['key']] ?? ['icon' => 'bi-activity', 'what' => '', 'hint' => '', 'route' => null];

        return $check + [
            'icon' => $meta['icon'],
            'what' => $meta['what'],
            // İpucu yalnızca sorun varken işe yarar; sağlıklı kontrolde
            // ekranı gereksiz doldurur.
            'hint' => $check['status'] === self::STATUS_OK ? null : $meta['hint'],
            'url'  => $meta['route'] !== null
                ? rescue(static fn (): ?string => route($meta['route']), null, false)
                : null,
        ];
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
