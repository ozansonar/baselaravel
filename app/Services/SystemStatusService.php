<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Ayarlar ekranındaki sistem durumu.
 *
 * Buradaki değerler tek tek bakıldığında bir şey söylemez; ekranın işi
 * "bir sorun var mı" sorusuna bakışta cevap vermek. Bu yüzden her satır kendi
 * durumunu (iyi / uyarı / sorunlu) ve neyi beklediğini birlikte taşıyor.
 */
final class SystemStatusService
{
    /**
     * Yükleme limitleri için önerilen alt sınırlar.
     *
     * Video ve büyük görsel yüklemesi bu tavanların altında sessizce
     * başarısız oluyor: tarayıcı dosyayı gönderiyor, PHP gövdeyi atıyor.
     */
    public const RECOMMENDED_UPLOAD_BYTES = 128 * 1024 * 1024;

    public const RECOMMENDED_POST_BYTES = 128 * 1024 * 1024;

    public const RECOMMENDED_MEMORY_BYTES = 256 * 1024 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'php_sapi'        => PHP_SAPI,
            'server_software' => $this->serverSoftware(),
            'environment'     => app()->environment(),
            'debug'           => (bool) config('app.debug'),
            'timezone'        => (string) config('app.timezone'),
            'cache_driver'    => (string) config('cache.default'),
            'queue_driver'    => (string) config('queue.default'),
            'db'              => $this->database(),
            'disk'            => $this->disk(),
            'recommended_upload_human' => $this->humanBytes(self::RECOMMENDED_UPLOAD_BYTES),
            'storage_writable' => is_writable(storage_path('app')) && is_writable(storage_path('logs')),
            'limits'          => $this->limits(),
        ];
    }

    /**
     * Yükleme limitleri: değer, önerilen alt sınır ve durumu bir arada.
     *
     * @return array<int, array{key: string, label: string, value: string, bytes: int, recommended: int, recommended_human: string, state: string, note: ?string}>
     */
    public function limits(): array
    {
        $upload = $this->parseSize((string) ini_get('upload_max_filesize'));
        $post = $this->parseSize((string) ini_get('post_max_size'));
        $memory = $this->parseSize((string) ini_get('memory_limit'));
        $execution = (int) ini_get('max_execution_time');

        $rows = [
            [
                'key'         => 'upload_max_filesize',
                'label'       => 'Tek dosya sınırı',
                'value'       => (string) ini_get('upload_max_filesize'),
                'bytes'       => $upload,
                'recommended' => self::RECOMMENDED_UPLOAD_BYTES,
                'state'       => $upload >= self::RECOMMENDED_UPLOAD_BYTES ? 'ok' : 'danger',
                'note'        => 'Bir dosyanın alabileceği en büyük boyut.',
            ],
            [
                'key'         => 'post_max_size',
                'label'       => 'Form gönderim sınırı',
                'value'       => (string) ini_get('post_max_size'),
                'bytes'       => $post,
                'recommended' => self::RECOMMENDED_POST_BYTES,
                // Gövde tavanı dosya tavanının altındaysa dosya hiç ulaşmaz.
                'state'       => match (true) {
                    $post > 0 && $post < $upload => 'danger',
                    $post >= self::RECOMMENDED_POST_BYTES => 'ok',
                    default => 'danger',
                },
                'note' => $post > 0 && $post < $upload
                    ? 'Form sınırı dosya sınırından küçük: büyük dosya sunucuya hiç ulaşmaz.'
                    : 'Tüm form gövdesinin (dosyalar dahil) toplam sınırı.',
            ],
            [
                'key'         => 'memory_limit',
                'label'       => 'Bellek sınırı',
                'value'       => (string) ini_get('memory_limit'),
                'bytes'       => $memory,
                'recommended' => self::RECOMMENDED_MEMORY_BYTES,
                'state'       => ($memory === 0 || $memory >= self::RECOMMENDED_MEMORY_BYTES) ? 'ok' : 'warn',
                'note'        => 'Görsel işleme ve dışa aktarma bu sınıra takılır.',
            ],
            [
                'key'         => 'max_execution_time',
                'label'       => 'İstek süresi',
                'value'       => $execution === 0 ? 'sınırsız' : $execution . ' sn',
                'bytes'       => $execution,
                'recommended' => 30,
                'state'       => ($execution === 0 || $execution >= 30) ? 'ok' : 'warn',
                'note'        => 'Yavaş bağlantıda büyük yükleme bu süreye takılabilir.',
            ],
        ];

        // Ekran biçimlendirme yapmasın: okunur karşılık burada hazırlanıyor.
        return array_map(function (array $row): array {
            $row['recommended_human'] = $row['key'] === 'max_execution_time'
                ? $row['recommended'] . ' sn'
                : $this->humanBytes($row['recommended']);

            return $row;
        }, $rows);
    }

    /**
     * Ekranın tepesinde tek cümlelik özet: her şey yolunda mı, değilse kaç
     * başlıkta sorun var.
     *
     * @param  array<int, array<string, mixed>> $limits
     * @return array{state: string, title: string, detail: string}
     */
    public function verdict(array $limits, bool $dbConnected, bool $debugInProduction, bool $storageWritable): array
    {
        $sorunlar = [];

        if (! $dbConnected) {
            $sorunlar[] = 'veritabanı bağlantısı yok';
        }

        if ($debugInProduction) {
            $sorunlar[] = 'canlıda hata ayıklama açık';
        }

        if (! $storageWritable) {
            $sorunlar[] = 'storage klasörü yazılabilir değil';
        }

        $dusukLimit = count(array_filter($limits, fn (array $row): bool => $row['state'] === 'danger'));

        if ($dusukLimit > 0) {
            $sorunlar[] = $dusukLimit . ' yükleme limiti düşük';
        }

        $uyari = count(array_filter($limits, fn (array $row): bool => $row['state'] === 'warn'));

        if ($sorunlar === []) {
            return [
                'state'  => $uyari > 0 ? 'warn' : 'ok',
                'title'  => $uyari > 0 ? 'Çalışıyor, bakılacak bir başlık var' : 'Her şey yolunda',
                'detail' => $uyari > 0
                    ? 'Sistem çalışıyor; aşağıdaki sarı işaretli değer önerilenin altında.'
                    : 'Sunucu, veritabanı ve PHP ayarları beklenen aralıkta.',
            ];
        }

        return [
            'state'  => 'danger',
            'title'  => count($sorunlar) === 1 ? 'Bir sorun var' : count($sorunlar) . ' sorun var',
            // mb_ucfirst: ASCII ucfirst Türkçe harfle başlayan bir cümleyi bozar.
            'detail' => mb_ucfirst(implode(', ', $sorunlar)) . '.',
        ];
    }

    /**
     * PHP'nin "128M" biçimini bayta çevirir.
     */
    public function parseSize(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0') {
            return 0;
        }

        // "-1" sınırsız demek; sınırsız bir tavan, her tavanı karşılar.
        if ($value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    public function humanBytes(int $bytes): string
    {
        return match (true) {
            $bytes === 0            => 'sınırsız',
            $bytes >= 1073741824    => round($bytes / 1073741824, 1) . ' GB',
            $bytes >= 1048576       => round($bytes / 1048576) . ' MB',
            $bytes >= 1024          => round($bytes / 1024) . ' KB',
            default                 => $bytes . ' B',
        };
    }

    /**
     * @return array{connected: bool, driver: string, version: ?string, name: ?string}
     */
    private function database(): array
    {
        try {
            $pdo = DB::connection()->getPdo();

            return [
                'connected' => true,
                'driver'    => (string) DB::connection()->getDriverName(),
                'version'   => (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'name'      => DB::connection()->getDatabaseName(),
            ];
        } catch (\Throwable) {
            return [
                'connected' => false,
                'driver'    => (string) config('database.default'),
                'version'   => null,
                'name'      => null,
            ];
        }
    }

    /**
     * @return array{free: int, total: int, free_human: string, total_human: string, used_percent: int}
     */
    private function disk(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if ($free === false || $total === false || $total <= 0) {
            return ['free' => 0, 'total' => 0, 'free_human' => '—', 'total_human' => '—', 'used_percent' => 0];
        }

        return [
            'free'         => (int) $free,
            'total'        => (int) $total,
            'free_human'   => $this->humanBytes((int) $free),
            'total_human'  => $this->humanBytes((int) $total),
            'used_percent' => (int) round((($total - $free) / $total) * 100),
        ];
    }

    private function serverSoftware(): string
    {
        $software = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');

        return $software !== '' ? $software : PHP_SAPI;
    }
}
