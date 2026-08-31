<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Support\ShellExec;
use App\Support\SqlStatementReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Bir yedeği geri yükler.
 *
 * Yedek alınıyordu ama geri dönüş yolu yoktu: dosya indirilebiliyor, ama
 * uygulanabilmesi için sunucuda elle SQL çalıştırmak gerekiyordu. **Hiç
 * denenmemiş bir yedek, olmayan bir yedektir** — geri yükleme yolu olmadığı
 * sürece kimse yedeğin gerçekten işe yaradığını bilmiyor.
 *
 * Sıra bilinçli:
 *
 *   1. Arşivi doğrula — bozuk bir dosyayla başlanmaz.
 *   2. **Önce mevcut durumun yedeğini al.** Geri yükleme yanlış yedekle
 *      başlatılabilir; o an geriye dönülecek bir yer olmalı. Bu adım
 *      başarısız olursa geri yükleme hiç başlamıyor.
 *   3. Bakım moduna geç — yarı geri yüklenmiş bir siteyi ziyaretçi görmesin.
 *   4. Veritabanını uygula.
 *   5. Yüklenen dosyaları aç.
 *   6. Bakım modundan çık.
 *
 * **İşlem (transaction) yok, olamaz:** MySQL'de `DROP TABLE` / `CREATE TABLE`
 * örtük commit üretir, yani şema geri yüklemesi geri alınamaz. Güvenlik
 * yedeğinin varlık sebebi tam olarak budur.
 *
 * **Yüklenen dosyalar silinmiyor, üzerine yazılıyor.** Gerçek bir aynalama
 * yedekten sonra eklenen dosyaları silerdi; kurtarma işleminin yan etkisi
 * olarak veri silmek, kurtarmanın kendisinden büyük risk.
 */
final class BackupRestoreService
{
    /** Arşivde bulunması zorunlu dosya — yedek olmayan bir zip buradan elenir. */
    private const META_FILE = 'backup-meta.json';

    private const SQL_FILE = 'database.sql';

    public function __construct(
        private readonly BackupService $backups,
        private readonly SqlStatementReader $sql,
    ) {}

    /**
     * Arşivi açmadan ne olduğuna bakar.
     *
     * @return array{ok: bool, message: string, has_database: bool, has_uploads: bool, upload_count: int, meta: ?array<string, mixed>}
     */
    public function inspect(string $filename): array
    {
        $path = $this->pathFor($filename);

        if ($path === null) {
            return $this->invalid('Yedek bulunamadı.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return $this->invalid('Dosya açılamadı — arşiv bozuk olabilir.');
        }

        try {
            $meta = $zip->getFromName(self::META_FILE);

            if ($meta === false) {
                return $this->invalid('Bu bir yedek dosyası değil: ' . self::META_FILE . ' yok.');
            }

            $decoded = json_decode($meta, true);
            $hasDatabase = $zip->locateName(self::SQL_FILE) !== false;
            $uploadCount = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                // Dizin dışına çıkmaya çalışan bir girdi varsa arşivin
                // tamamı reddediliyor: tek bir kötü girdiyi atlayıp gerisini
                // açmak, saldırganın hangi dosyayı hedeflediğini gizler.
                if ($this->escapesArchive($name)) {
                    return $this->invalid('Arşivde dizin dışına çıkan bir girdi var: ' . $name);
                }

                if (str_starts_with($name, 'uploads/') && ! str_ends_with($name, '/')) {
                    $uploadCount++;
                }
            }

            return [
                'ok'           => true,
                'message'      => 'Arşiv geçerli.',
                'has_database' => $hasDatabase,
                'has_uploads'  => $uploadCount > 0,
                'upload_count' => $uploadCount,
                'meta'         => is_array($decoded) ? $decoded : null,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Yedeği uygula.
     *
     * @return array{success: bool, message: string, safety_backup: ?string, statements: int, files: int}
     */
    public function restore(string $filename): array
    {
        $path = $this->pathFor($filename);
        $check = $this->inspect($filename);

        if ($path === null || ! $check['ok']) {
            return $this->failed($check['message'], null);
        }

        if (! $check['has_database'] && ! $check['has_uploads']) {
            return $this->failed('Arşivde geri yüklenecek bir şey yok.', null);
        }

        // Geri yükleme yanlış dosyayla da başlatılabilir. O an geriye
        // dönülecek bir yer yoksa işlem tek yönlü olur.
        $safety = $this->backups->create();

        if (! $safety['success']) {
            return $this->failed(
                'Güvenlik yedeği alınamadı, geri yükleme başlatılmadı: ' . $safety['message'],
                null,
            );
        }

        $wasInMaintenance = Setting::getValue('maintenance_mode') === '1';
        $this->setMaintenance(true);

        $statements = 0;
        $files = 0;

        try {
            if ($check['has_database']) {
                $statements = $this->restoreDatabase($path);
            }

            if ($check['has_uploads']) {
                $files = $this->restoreUploads($path);
            }
        } catch (\Throwable $e) {
            Log::error('Yedek geri yüklenemedi', ['file' => $filename, 'error' => $e->getMessage()]);

            $this->setMaintenance($wasInMaintenance);

            AuditLogger::custom('Yedek geri yükleme başarısız', [
                'dosya'           => $filename,
                'guvenlik_yedegi' => $safety['file'],
                'hata'            => $e->getMessage(),
            ]);

            return $this->failed(
                'Geri yükleme yarıda kaldı: ' . $e->getMessage(),
                $safety['file'],
            );
        }

        // Veritabanı değiştiği için ayarlar da yedekteki hâline döndü;
        // statik önbellek eski değerleri tutmasın.
        Setting::clearSettingsCache();
        $this->setMaintenance(false);

        AuditLogger::custom('Yedek geri yüklendi', [
            'dosya'           => $filename,
            'guvenlik_yedegi' => $safety['file'],
            'ifade'           => $statements,
            'dosya_sayisi'    => $files,
        ]);

        return [
            'success'       => true,
            'message'       => "Geri yükleme tamamlandı: {$statements} SQL ifadesi, {$files} dosya.",
            'safety_backup' => $safety['file'],
            'statements'    => $statements,
            'files'         => $files,
        ];
    }

    /**
     * @return int uygulanan ifade sayısı
     */
    private function restoreDatabase(string $zipPath): int
    {
        $temp = $this->extractSqlToTemp($zipPath);

        if ($temp === null) {
            throw new \RuntimeException('Veritabanı dökümü arşivden çıkarılamadı.');
        }

        try {
            $viaClient = $this->importWithClient($temp);

            if ($viaClient !== null) {
                return $viaClient;
            }

            return $this->importWithPdo($temp);
        } finally {
            @unlink($temp);
        }
    }

    /**
     * `mysql` istemcisiyle içeri al.
     *
     * Dökümü alan yol da aynı ikiliği kuruyor (mysqldump varsa o, yoksa PHP).
     * İstemci yoksa ya da kabuk kapalıysa null dönüyor ve PDO yoluna
     * düşülüyor — paylaşımlı hostingde olağan durum bu.
     *
     * @return int|null uygulanan ifade sayısı; istemci kullanılamıyorsa null
     */
    private function importWithClient(string $sqlPath): ?int
    {
        $client = $this->findMysqlClient();

        if ($client === null || ! ShellExec::isAvailable()) {
            return null;
        }

        $cfg = config('database.connections.' . config('database.default'));

        if (($cfg['driver'] ?? '') !== 'mysql') {
            return null;
        }

        // Soket tanımlıysa bağlantı onun üzerinden kurulmalı: soketle kimlik
        // doğrulayan sunucularda host/port ile bağlanmak reddedilir. Dökümü
        // alan yol da aynı seçimi yapıyor.
        $socket = (string) ($cfg['unix_socket'] ?? '');

        $command = sprintf(
            '%s --user=%s --password=%s %s %s < %s 2>&1',
            escapeshellarg($client),
            escapeshellarg((string) ($cfg['username'] ?? '')),
            escapeshellarg((string) ($cfg['password'] ?? '')),
            $socket !== ''
                ? '--socket=' . escapeshellarg($socket)
                : sprintf(
                    '--host=%s --port=%s',
                    escapeshellarg((string) ($cfg['host'] ?? 'localhost')),
                    escapeshellarg((string) ($cfg['port'] ?? '3306')),
                ),
            escapeshellarg((string) ($cfg['database'] ?? '')),
            escapeshellarg($sqlPath),
        );

        $output = ShellExec::run($command);

        if ($output === null) {
            return null;
        }

        $problem = $this->errorLinesOf($output);

        if ($problem !== '') {
            throw new \RuntimeException('mysql istemcisi hata verdi: ' . mb_strimwidth($problem, 0, 300, '…'));
        }

        // İstemci ifade saymıyor; sayının kendisi bilgilendirme amaçlı.
        return $this->countStatements($sqlPath);
    }

    /**
     * @return int uygulanan ifade sayısı
     */
    private function importWithPdo(string $sqlPath): int
    {
        $pdo = DB::connection()->getPdo();
        $applied = 0;

        foreach ($this->sql->fromFile($sqlPath) as $statement) {
            $pdo->exec($statement);
            $applied++;
        }

        return $applied;
    }

    /**
     * İstemci çıktısındaki gerçek hatalar.
     *
     * `mysql` başarıyla çalışırken de uyarı basabiliyor (şifresiz bağlantıda
     * SSL uyarısı gibi). Her çıktıyı hata saymak, sorunsuz biten bir geri
     * yüklemeyi başarısız gösterirdi.
     */
    private function errorLinesOf(string $output): string
    {
        $lines = preg_split('/\r?\n/', trim($output)) ?: [];

        $problems = array_filter($lines, static function (string $line): bool {
            $line = trim($line);

            return $line !== '' && stripos($line, 'warning') !== 0;
        });

        return implode("\n", $problems);
    }

    private function countStatements(string $sqlPath): int
    {
        $count = 0;

        foreach ($this->sql->fromFile($sqlPath) as $ignored) {
            $count++;
        }

        return $count;
    }

    private function extractSqlToTemp(string $zipPath): ?string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return null;
        }

        try {
            $stream = $zip->getStream(self::SQL_FILE);

            if ($stream === false) {
                return null;
            }

            $temp = tempnam(sys_get_temp_dir(), 'restore_sql_');

            if ($temp === false) {
                fclose($stream);

                return null;
            }

            $out = fopen($temp, 'wb');

            if ($out === false) {
                fclose($stream);

                return null;
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            return $temp;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return int açılan dosya sayısı
     */
    private function restoreUploads(string $zipPath): int
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Arşiv yüklemeler için açılamadı.');
        }

        $base = rtrim(UploadService::basePath(), '/');
        $written = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (! str_starts_with($name, 'uploads/') || str_ends_with($name, '/')) {
                    continue;
                }

                // inspect() zaten eledi; burada ikinci kez bakılıyor çünkü
                // bu kontrolün atlanması doğrudan dizin dışına yazmak demek.
                if ($this->escapesArchive($name)) {
                    throw new \RuntimeException('Arşivde dizin dışına çıkan girdi: ' . $name);
                }

                $target = $base . '/' . substr($name, strlen('uploads/'));
                $directory = dirname($target);

                if (! is_dir($directory) && ! @mkdir($directory, 0o775, true) && ! is_dir($directory)) {
                    throw new \RuntimeException('Dizin oluşturulamadı: ' . $directory);
                }

                $stream = $zip->getStream($name);

                if ($stream === false) {
                    continue;
                }

                $out = fopen($target, 'wb');

                if ($out === false) {
                    fclose($stream);

                    throw new \RuntimeException('Dosya yazılamadı: ' . $target);
                }

                stream_copy_to_stream($stream, $out);
                fclose($stream);
                fclose($out);
                $written++;
            }
        } finally {
            $zip->close();
        }

        return $written;
    }

    /**
     * Girdi adı arşivin dışına çıkıyor mu?
     *
     * Zip Slip: `uploads/../../../.env` adlı bir girdi, açılırken hedef dizinin
     * dışına yazar. Yedek dosyası panelden yüklenebildiği için arşiv her zaman
     * güvenilir değil.
     */
    private function escapesArchive(string $name): bool
    {
        $normalised = str_replace('\\', '/', $name);

        if ($normalised === '' || str_starts_with($normalised, '/')) {
            return true;
        }

        if (preg_match('#^[a-zA-Z]:#', $normalised) === 1) {
            return true;
        }

        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function findMysqlClient(): ?string
    {
        foreach (['/usr/bin/mysql', '/usr/local/bin/mysql', '/opt/homebrew/bin/mysql', '/opt/lampp/bin/mysql'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $which = ShellExec::run('which mysql 2>/dev/null');

        return is_string($which) && trim($which) !== '' ? trim($which) : null;
    }

    private function setMaintenance(bool $on): void
    {
        Setting::setValue('maintenance_mode', $on ? '1' : '0', 'maintenance', 'text');
    }

    private function pathFor(string $filename): ?string
    {
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return null;
        }

        $path = BackupService::basePath() . '/' . $filename;

        return is_file($path) ? $path : null;
    }

    /** @return array{ok: bool, message: string, has_database: bool, has_uploads: bool, upload_count: int, meta: null} */
    private function invalid(string $message): array
    {
        return [
            'ok'           => false,
            'message'      => $message,
            'has_database' => false,
            'has_uploads'  => false,
            'upload_count' => 0,
            'meta'         => null,
        ];
    }

    /** @return array{success: bool, message: string, safety_backup: ?string, statements: int, files: int} */
    private function failed(string $message, ?string $safetyBackup): array
    {
        return [
            'success'       => false,
            'message'       => $message,
            'safety_backup' => $safetyBackup,
            'statements'    => 0,
            'files'         => 0,
        ];
    }
}
