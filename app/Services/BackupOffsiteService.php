<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationLevel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Yedeğin ikinci kopyası — yedeklediği veriyle aynı diskte durmasın diye.
 *
 * Yedekleme buraya kadar tek bir varsayıma dayanıyordu: disk sağlam. Oysa
 * yedeğin var olma sebebi tam da o varsayımın çökmesi. Diski kaybeden, tek
 * kopyayı da kaybediyordu.
 *
 * İki taşıyıcı var ve ikisi de ek bağımlılık istemiyor:
 *
 *   local → başka bir yola kopyala. Paylaşımlı hosting'de bağlanan bir
 *           ağ klasörü ya da ikinci bir disk; en basit ve en sık işe yarayan.
 *   ftp   → başka bir sunucuya yükle. PHP'nin kendi ftp eklentisiyle;
 *           paylaşımlı hosting'de neredeyse her zaman açık.
 *
 * S3 gibi bir hedef için ayrı bir kütüphane gerekiyor ve bu kit hiçbir
 * bulut sağlayıcısına bağlanmıyor: yapılandırma bir kez daha karmaşıklaşır ve
 * projelerin çoğunda kullanılmaz. Yeni bir taşıyıcı eklemek burada bir metot.
 */
final class BackupOffsiteService
{
    public function isEnabled(): bool
    {
        return (string) config('backups.offsite.driver', 'none') !== 'none';
    }

    public function driver(): string
    {
        return (string) config('backups.offsite.driver', 'none');
    }

    /**
     * Yedeği dış hedefe kopyalar.
     *
     * Dönüş değeri bilerek üç durumlu: kapalıyken "başarısız" demek, hiç
     * istenmemiş bir şeyi hata saymak olurdu.
     *
     * @return array{status: 'disabled'|'ok'|'failed', message: string}
     */
    public function copy(string $localPath): array
    {
        if (! $this->isEnabled()) {
            return ['status' => 'disabled', 'message' => 'Dış kopya kapalı.'];
        }

        if (! File::exists($localPath)) {
            return ['status' => 'failed', 'message' => 'Kopyalanacak yedek bulunamadı: ' . basename($localPath)];
        }

        try {
            $ok = match ($this->driver()) {
                'local' => $this->copyToPath($localPath),
                'ftp'   => $this->copyToFtp($localPath),
                default => false,
            };

            if (! $ok) {
                return $this->fail('Dış kopya alınamadı.');
            }

            return ['status' => 'ok', 'message' => 'Dış kopya alındı.'];
        } catch (\Throwable $e) {
            Log::warning('Yedeğin dış kopyası alınamadı', [
                'driver' => $this->driver(),
                'error'  => $e->getMessage(),
            ]);

            return $this->fail('Dış kopya alınamadı: ' . $e->getMessage());
        }
    }

    /**
     * Başarısızlık sessiz kalmamalı.
     *
     * Dış kopya, kimsenin her gün baktığı bir şey değil: aylarca alınmadığı
     * fark edilmezse yedekleme yine tek kopyaya düşer ve bunu ancak diski
     * kaybeden gün öğrenilir. Bu yüzden panele bildirim düşüyor.
     *
     * @return array{status: 'failed', message: string}
     */
    private function fail(string $message): array
    {
        NotificationCenter::send(
            'backup',
            'Yedeğin dış kopyası alınamadı',
            $message,
            NotificationLevel::Warning,
            icon: 'bi-cloud-slash',
        );

        return ['status' => 'failed', 'message' => $message];
    }

    /**
     * İkinci bir yola kopyalama.
     *
     * Kopyalandıktan sonra boyut karşılaştırılıyor: yarım yazılmış bir dosya
     * "var" diye görünüp geri yükleme gününde işe yaramazdı.
     */
    private function copyToPath(string $localPath): bool
    {
        $target = rtrim((string) config('backups.offsite.path', ''), '/');

        if ($target === '') {
            throw new \RuntimeException('Dış kopya yolu tanımlı değil (BACKUP_OFFSITE_PATH).');
        }

        File::ensureDirectoryExists($target, 0755);

        $destination = $target . '/' . basename($localPath);

        if (! File::copy($localPath, $destination)) {
            return false;
        }

        clearstatcache(true, $destination);

        if ((int) @filesize($destination) !== (int) @filesize($localPath)) {
            @unlink($destination);

            throw new \RuntimeException('Kopya eksik yazıldı, silindi.');
        }

        $this->pruneLocalCopies($target);

        return true;
    }

    /**
     * FTP ile başka bir sunucuya yükleme.
     */
    private function copyToFtp(string $localPath): bool
    {
        if (! function_exists('ftp_connect')) {
            throw new \RuntimeException('PHP ftp eklentisi yüklü değil.');
        }

        $config = (array) config('backups.offsite.ftp', []);

        $connection = @ftp_connect((string) ($config['host'] ?? ''), (int) ($config['port'] ?? 21), 15);

        if ($connection === false) {
            throw new \RuntimeException('FTP sunucusuna bağlanılamadı.');
        }

        try {
            if (! @ftp_login($connection, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''))) {
                throw new \RuntimeException('FTP girişi reddedildi.');
            }

            // Pasif kip: paylaşımlı hosting'in güvenlik duvarı arkasında aktif
            // kip neredeyse hiç çalışmıyor.
            @ftp_pasv($connection, (bool) ($config['passive'] ?? true));

            $directory = trim((string) ($config['path'] ?? ''), '/');

            if ($directory !== '' && ! @ftp_chdir($connection, $directory)) {
                @ftp_mkdir($connection, $directory);
                @ftp_chdir($connection, $directory);
            }

            return @ftp_put($connection, basename($localPath), $localPath, FTP_BINARY);
        } finally {
            @ftp_close($connection);
        }
    }

    /**
     * Dış kopyanın da bir saklama süresi var.
     *
     * Olmasaydı ikinci hedef zamanla dolar ve dolduğu gün yeni kopya
     * alınamazdı — hem de kimse fark etmeden.
     */
    private function pruneLocalCopies(string $directory): void
    {
        $keepDays = (int) config('backups.offsite.retention_days', 30);

        if ($keepDays < 1) {
            return;
        }

        foreach (File::glob($directory . '/backup-*.zip') as $file) {
            if (File::lastModified($file) < now()->subDays($keepDays)->getTimestamp()) {
                File::delete($file);
            }
        }
    }
}
