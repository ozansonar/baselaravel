<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Support\ShellExec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Otomatik veritabanı + dosya yedekleme servisi.
 *
 * Backup içeriği:
 *   - database.sql (mysqldump tüm tablolar)
 *   - uploads/ klasörü (tüm görseller + videolar)
 *   - backup-meta.json (versiyon, tarih, disk boyutları, hash)
 *
 * Storage: storage/app/backups/YYYY-MM-DD-HHMMSS.zip
 * Rotation: setting'teki backup_retention_days (default 14) günden eski silinir
 */
final class BackupService
{
    private const BACKUP_DIR = 'backups';

    public const STATUS_OK    = 'ok';
    public const STATUS_FAIL  = 'fail';

    /**
     * Tam yedek al — DB + uploads → ZIP.
     *
     * @return array{success: bool, file: ?string, size: int, size_human: string, db_size: int, files_size: int, message: string, hash: ?string}
     */
    public function create(): array
    {
        try {
            $this->ensureBackupDir();

            $timestamp = now()->format('Y-m-d-His');
            $zipName = "backup-{$timestamp}.zip";
            $zipPath = storage_path('app/' . self::BACKUP_DIR . '/' . $zipName);

            // 1. DB dump
            $dbDumpPath = $this->dumpDatabase();
            $dbSize = $dbDumpPath !== null ? (int) @filesize($dbDumpPath) : 0;

            // 2. ZIP oluştur
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                @unlink($dbDumpPath);
                return $this->failResult('ZIP oluşturulamadı: ' . $zipPath);
            }

            // 2a. DB SQL dosyasını ekle
            if ($dbDumpPath !== null && is_file($dbDumpPath)) {
                $zip->addFile($dbDumpPath, 'database.sql');
            }

            // 2b. uploads/ klasörünü ekle
            $uploadsPath = UploadService::basePath();
            $filesSize = 0;
            if (is_dir($uploadsPath)) {
                $filesSize = $this->addDirectoryToZip($zip, $uploadsPath, 'uploads');
            }

            // 2c. Meta JSON
            $meta = [
                'version'      => 1,
                'created_at'   => now()->toIso8601String(),
                'app_version'  => app()->version(),
                'php_version'  => PHP_VERSION,
                'db_size'      => $dbSize,
                'files_size'   => $filesSize,
                'total_files'  => $zip->numFiles + 1,
            ];
            $zip->addFromString('backup-meta.json', (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $zip->close();

            // 3. DB temp dosyayı sil
            if ($dbDumpPath !== null) {
                @unlink($dbDumpPath);
            }

            // 4. Hash hesapla (integrity)
            $hash = is_file($zipPath) ? hash_file('sha256', $zipPath) : null;
            $totalSize = is_file($zipPath) ? (int) filesize($zipPath) : 0;

            // 5. Setting'e son backup zamanı yaz (Health Check için)
            Setting::setValue('last_backup_at', now()->toIso8601String(), 'backup', 'datetime');

            // 6. Rotation
            $this->rotate();

            // 7. Audit log
            AuditLogger::custom('Sistem yedeklemesi alındı', [
                'file'        => $zipName,
                'size_mb'     => round($totalSize / 1_048_576, 2),
                'db_size_mb'  => round($dbSize / 1_048_576, 2),
                'files_mb'    => round($filesSize / 1_048_576, 2),
            ]);

            return [
                'success'    => true,
                'file'       => $zipName,
                'size'       => $totalSize,
                'size_human' => $this->humanBytes($totalSize),
                'db_size'    => $dbSize,
                'files_size' => $filesSize,
                'message'    => "Yedek alındı: {$zipName}",
                'hash'       => $hash,
            ];
        } catch (\Throwable $e) {
            Log::error('BackupService: hata', ['error' => $e->getMessage()]);
            return $this->failResult('Beklenmedik hata: ' . $e->getMessage());
        }
    }

    /**
     * Mevcut yedek listesini döner.
     *
     * Filtreler listeyi daraltır; rotate() gibi iç kullanımlar filtresiz çağırır
     * ve her zaman tam listeyi görür.
     *
     * @param array{q?: string|null, sort?: string|null} $filters
     * @return list<array{name: string, size: int, size_human: string, created_at: \Carbon\Carbon, age: string, path: string, expires_at: \Carbon\Carbon, expires_in_days: int, contents: ?array<string, mixed>}>
     */
    public function list(array $filters = []): array
    {
        $this->ensureBackupDir();
        $dir = storage_path('app/' . self::BACKUP_DIR);
        $files = glob($dir . '/backup-*.zip') ?: [];

        $retention = $this->retentionDays();
        $result = [];

        foreach ($files as $f) {
            $name = basename($f);
            $size = (int) @filesize($f);
            $mtime = @filemtime($f);
            $when = $mtime ? Carbon::createFromTimestamp($mtime) : now();
            $expiresAt = $when->copy()->addDays($retention);

            $result[] = [
                'name'            => $name,
                'size'            => $size,
                'size_human'      => $this->humanBytes($size),
                'created_at'      => $when,
                'age'             => $when->diffForHumans(),
                'path'            => $f,
                // Rotation silmeden önce kaç gün kaldığı; listede uyarı olarak
                // gösteriliyor, böylece indirilmesi gereken yedek fark edilir.
                'expires_at'      => $expiresAt,
                'expires_in_days' => max(0, (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)),
                'contents'        => $this->contents($name, $size, $mtime ?: 0),
            ];
        }

        $query = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($query !== '') {
            $result = array_values(array_filter(
                $result,
                static fn (array $b): bool => str_contains(mb_strtolower($b['name']), mb_strtolower($query)),
            ));
        }

        usort($result, match ($filters['sort'] ?? null) {
            'oldest'   => static fn (array $a, array $b): int => $a['created_at']->timestamp <=> $b['created_at']->timestamp,
            'largest'  => static fn (array $a, array $b): int => $b['size'] <=> $a['size'],
            'smallest' => static fn (array $a, array $b): int => $a['size'] <=> $b['size'],
            default    => static fn (array $a, array $b): int => $b['created_at']->timestamp <=> $a['created_at']->timestamp,
        });

        return $result;
    }

    /**
     * Liste başlığındaki özet kutuları.
     *
     * @return array{count: int, total_size: int, total_size_human: string, latest: ?\Carbon\Carbon, latest_age: ?string, retention_days: int, next_run: \Carbon\Carbon}
     */
    public function stats(): array
    {
        $backups = $this->list();
        $totalSize = array_sum(array_column($backups, 'size'));
        $latest = $backups[0]['created_at'] ?? null;

        return [
            'count'            => count($backups),
            'total_size'       => $totalSize,
            'total_size_human' => $totalSize > 0 ? $this->humanBytes($totalSize) : '0 B',
            'latest'           => $latest,
            'latest_age'       => $latest?->diffForHumans(),
            'retention_days'   => $this->retentionDays(),
            'next_run'         => $this->nextScheduledRun(),
        ];
    }

    /**
     * ZIP içindeki backup-meta.json — hangi verinin ne kadar yer kapladığı.
     *
     * Dosya oluşturulduktan sonra değişmediği için sonuç önbelleğe alınır;
     * liste her açılışta ondört ZIP açmak zorunda kalmaz.
     *
     * @return array<string, mixed>|null
     */
    public function contents(string $filename, int $size, int $mtime): ?array
    {
        return Cache::remember(
            "backup.contents.{$filename}.{$size}.{$mtime}",
            86400,
            function () use ($filename): ?array {
                $path = storage_path('app/' . self::BACKUP_DIR . '/' . $filename);

                if (! is_file($path)) {
                    return null;
                }

                $zip = new ZipArchive();

                if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                    return null;
                }

                $raw = $zip->getFromName('backup-meta.json');
                $zip->close();

                if (! is_string($raw)) {
                    return null;
                }

                $meta = json_decode($raw, true);

                if (! is_array($meta)) {
                    return null;
                }

                return [
                    'db_size'         => (int) ($meta['db_size'] ?? 0),
                    'db_size_human'   => $this->humanBytes((int) ($meta['db_size'] ?? 0)),
                    'files_size'      => (int) ($meta['files_size'] ?? 0),
                    'files_size_human'=> $this->humanBytes((int) ($meta['files_size'] ?? 0)),
                    'total_files'     => (int) ($meta['total_files'] ?? 0),
                    'php_version'     => (string) ($meta['php_version'] ?? ''),
                ];
            },
        );
    }

    public function retentionDays(): int
    {
        return max(1, (int) (Setting::getValue('backup_retention_days', '14') ?: 14));
    }

    /**
     * Zamanlanmış yedeğin bir sonraki çalışma anı — routes/console.php'deki
     * dailyAt('05:00') ile aynı saat.
     */
    private function nextScheduledRun(): Carbon
    {
        $next = now()->startOfDay()->setTime(5, 0);

        return $next->isPast() ? $next->addDay() : $next;
    }

    /**
     * Bir backup dosyasını sil.
     */
    public function delete(string $filename): bool
    {
        if (! $this->removeFile($filename)) {
            return false;
        }

        AuditLogger::custom('Yedek dosyası silindi', ['file' => $filename]);

        return true;
    }

    /**
     * Seçilen yedekleri tek işlemde sil.
     *
     * Bir dosyanın silinememesi kalanları durdurmaz: kullanıcı hangilerinin
     * gittiğini, hangilerinin kaldığını tek seferde görmeli. Kayda da tek satır
     * düşer, ondört ayrı satır değil.
     *
     * @param list<string> $filenames
     * @return array{deleted: list<string>, failed: list<string>}
     */
    public function deleteMany(array $filenames): array
    {
        $deleted = [];
        $failed = [];

        foreach (array_unique($filenames) as $filename) {
            if ($this->removeFile((string) $filename)) {
                $deleted[] = (string) $filename;
            } else {
                $failed[] = (string) $filename;
            }
        }

        if ($deleted !== []) {
            AuditLogger::custom('Yedek dosyaları toplu silindi', [
                'adet'     => count($deleted),
                'dosyalar' => $deleted,
            ]);
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Yedek klasöründen tek dosya siler.
     *
     * Ad doğrudan istekten geldiği için klasör dışına çıkmaya çalışan her şey
     * burada elenir.
     */
    private function removeFile(string $filename): bool
    {
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        $path = storage_path('app/' . self::BACKUP_DIR . '/' . $filename);

        if (! is_file($path)) {
            return false;
        }

        return (bool) @unlink($path);
    }

    /**
     * Verilen dosya yolunu döner — controller indirme için kullanır.
     */
    public function downloadPath(string $filename): ?string
    {
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            return null;
        }
        $path = storage_path('app/' . self::BACKUP_DIR . '/' . $filename);
        return is_file($path) ? $path : null;
    }

    /**
     * Eski yedekleri retention süresine göre temizle.
     */
    public function rotate(): int
    {
        $days = $this->retentionDays();
        $cutoff = now()->subDays($days);
        $deleted = 0;

        foreach ($this->list() as $b) {
            if ($b['created_at']->lessThan($cutoff)) {
                if (@unlink($b['path'])) $deleted++;
            }
        }

        if ($deleted > 0) {
            AuditLogger::custom('Eski yedekler temizlendi', ['silinen' => $deleted, 'retention_days' => $days]);
        }
        return $deleted;
    }

    /**
     * mysqldump ile DB dump al — temp dosyaya yaz.
     * Shared hosting'de mysqldump yoksa PHP-side dump fallback'i kullan.
     */
    private function dumpDatabase(): ?string
    {
        $cfg = config('database.connections.' . config('database.default'));
        if (! is_array($cfg) || ($cfg['driver'] ?? '') !== 'mysql') {
            // Yalnızca MySQL desteklenir
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'db_dump_');
        if ($tmpFile === false) return null;

        // mysqldump var mı?
        $mysqldump = $this->findMysqldump();

        // mysqldump binary'si var ve shell_exec gerçekten çağrılabilir mi?
        // ShellExec helper 3 katmanlı kontrol: function_exists +
        // disable_functions parse + try/catch. Disabled ise null döner,
        // sessizce phpSideDump fallback'ine düşeriz.
        if ($mysqldump !== null && ShellExec::isAvailable()) {
            $cmd = sprintf(
                '%s --user=%s --password=%s --host=%s --port=%s --single-transaction --quick --no-tablespaces %s 2>/dev/null',
                escapeshellarg($mysqldump),
                escapeshellarg((string) ($cfg['username'] ?? '')),
                escapeshellarg((string) ($cfg['password'] ?? '')),
                escapeshellarg((string) ($cfg['host'] ?? 'localhost')),
                escapeshellarg((string) ($cfg['port'] ?? '3306')),
                escapeshellarg((string) ($cfg['database'] ?? '')),
            );

            $output = ShellExec::run($cmd);
            if (is_string($output) && $output !== '') {
                file_put_contents($tmpFile, $output);
                return $tmpFile;
            }
        }

        // Fallback: PHP-side dump (shared hosting için)
        return $this->phpSideDump($tmpFile, $cfg);
    }

    private function findMysqldump(): ?string
    {
        // Yaygın yollar
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/lampp/bin/mysqldump'] as $path) {
            if (is_executable($path)) return $path;
        }

        // PATH'te ara — ShellExec helper 3 katmanlı kontrol (function_exists +
        // disable_functions + try/catch). Disabled ise null, fatal vermez.
        $which = ShellExec::run('which mysqldump 2>/dev/null');
        if (is_string($which) && trim($which) !== '') {
            return trim($which);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function phpSideDump(string $tmpFile, array $cfg): ?string
    {
        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $cfg['host'] ?? 'localhost',
                    $cfg['port'] ?? '3306',
                    $cfg['database'] ?? ''
                ),
                (string) ($cfg['username'] ?? ''),
                (string) ($cfg['password'] ?? ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            if ($tables === false) return null;

            $fp = fopen($tmpFile, 'w');
            if ($fp === false) return null;

            fwrite($fp, "-- PHP-side DB dump (mysqldump bulunamadı)\n");
            fwrite($fp, "-- " . now()->toIso8601String() . "\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($tables as $table) {
                // CREATE TABLE
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                if ($createStmt) {
                    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($fp, $createStmt[1] . ";\n\n");
                }

                // Rows
                $rows = $pdo->query("SELECT * FROM `{$table}`");
                foreach ($rows as $row) {
                    $cols = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                    fwrite($fp, "INSERT INTO `{$table}` VALUES (" . implode(',', $cols) . ");\n");
                }
                fwrite($fp, "\n");
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);
            return $tmpFile;
        } catch (\Throwable $e) {
            Log::warning('BackupService phpSideDump fail', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ZipArchive'a klasörü recursive ekler. Toplam byte döner.
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPrefix): int
    {
        $totalSize = 0;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iter as $file) {
            if (! $file->isFile()) continue;
            $absPath = $file->getPathname();
            $relPath = $zipPrefix . '/' . substr($absPath, strlen($dir) + 1);
            $relPath = str_replace('\\', '/', $relPath);
            $zip->addFile($absPath, $relPath);
            $totalSize += $file->getSize();
        }

        return $totalSize;
    }

    private function ensureBackupDir(): void
    {
        $dir = storage_path('app/' . self::BACKUP_DIR);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // .gitignore koy (versiyon kontrolüne girmesin)
        $gitignore = $dir . '/.gitignore';
        if (! is_file($gitignore)) {
            @file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }

    /** @return array<string, mixed> */
    private function failResult(string $message): array
    {
        return [
            'success'    => false,
            'file'       => null,
            'size'       => 0,
            'size_human' => '0 B',
            'db_size'    => 0,
            'files_size' => 0,
            'message'    => $message,
            'hash'       => null,
        ];
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return round($bytes / 1_048_576, 1) . ' MB';
        return round($bytes / 1_073_741_824, 2) . ' GB';
    }
}
