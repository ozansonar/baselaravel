<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Kuyruğun paneldeki yüzü.
 *
 * `failed_jobs` tablosu projede tek bir yerde okunuyordu —
 * `HealthCheckService` son 24 saatin **sayısını** alıyordu, o kadar.
 * Listeleme, hatayı görme, yeniden deneme ve silme yoktu.
 *
 * Bu, bu proje için özellikle önemli: tüm mail gönderimi
 * `MailService::queue()` üzerinden kuyruğa giriyor. "Doğrulama maili gelmedi"
 * şikâyetinin cevabı `failed_jobs.exception` alanında duruyor ve o alana
 * panelden bakmanın yolu yoktu.
 *
 * Eloquent modeli yok, sorgu kurucusu var: `jobs` ve `failed_jobs` çerçevenin
 * kendi tabloları, projenin model kurallarına (SoftDeletes, $fillable) tabi
 * değiller ve `QueueRunner` ile `HealthCheckService` de aynı biçimde okuyor.
 */
final class QueueMonitorService
{
    /** Kuyruğun tıkandığını söyleyen eşik. */
    public const STUCK_AFTER_MINUTES = 10;

    /**
     * Panonun üstündeki dört sayı.
     *
     * @return array{pending: int, oldest_minutes: ?int, failed_today: int, failed_total: int}
     */
    public function stats(): array
    {
        $oldest = DB::table('jobs')->min('available_at');

        return [
            'pending'        => (int) DB::table('jobs')->count(),
            // En eski işin yaşı, "cron çalışıyor mu" sorusunun en net cevabı:
            // kuyruk dolu ama kimse boşaltmıyorsa bu sayı büyümeye devam eder.
            'oldest_minutes' => $oldest === null
                ? null
                : (int) floor((time() - (int) $oldest) / 60),
            'failed_today'   => (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count(),
            'failed_total'   => (int) DB::table('failed_jobs')->count(),
        ];
    }

    public function isStuck(): bool
    {
        $stats = $this->stats();

        return $stats['oldest_minutes'] !== null
            && $stats['oldest_minutes'] >= self::STUCK_AFTER_MINUTES;
    }

    /**
     * Listenin tanıdığı süzgeç anahtarları.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['search', 'queue'];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->query($filters)
            ->orderByDesc('failed_at')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => $this->present($row)),
        );

        return $paginator;
    }

    /**
     * Tek bir başarısız iş — ayrıntı penceresi için.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $uuid): ?array
    {
        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        return $row === null ? null : $this->present($row, full: true);
    }

    /**
     * Kuyrukların listesi — süzgeç kutusu için.
     *
     * @return list<string>
     */
    public function queueOptions(): array
    {
        return DB::table('failed_jobs')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue')
            ->map(static fn (string $queue): string => $queue)
            ->values()
            ->all();
    }

    /**
     * Başarısız işi kuyruğa geri koy ve kaydını kaldır.
     */
    public function retry(string $uuid): bool
    {
        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        if ($row === null) {
            return false;
        }

        $this->requeue($row);

        // Çerçevenin komutu satırı kendisi siliyor; yedek yol sildirmediği için
        // burada bir kez daha çağrılıyor. İkisi de aynı sonuca varıyor.
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        AuditLogger::custom('Başarısız kuyruk işi yeniden denendi', ['uuid' => $uuid]);

        return true;
    }

    /**
     * İşi kuyruğa geri koy.
     *
     * Önce çerçevenin kendi komutu deneniyor: yükü yeniden yazmak ve
     * `retryUntil` damgasını tazelemek onun işi. Ama o adım yükün içindeki
     * nesneyi açıyor — iş sınıfı bir deploy'da kaldırılmışsa ya da yük
     * bozuksa patlıyor ve ekran 500 veriyordu. İşin geri konması bundan daha
     * önemli olduğu için o durumda deneme sayacı sıfırlanıp yük olduğu gibi
     * kuyruğa yazılıyor.
     *
     * `Artisan::call` aynı süreçte çalışıyor: alt süreç açılmıyor, paylaşımlı
     * hosting kuralı korunuyor.
     */
    private function requeue(object $row): void
    {
        try {
            Artisan::call('queue:retry', ['id' => [(string) $row->uuid]]);

            return;
        } catch (\Throwable $e) {
            report($e);
        }

        $payload = json_decode((string) $row->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $payload['attempts'] = 0;

        Queue::connection((string) $row->connection)
            ->pushRaw((string) json_encode($payload), (string) $row->queue);
    }

    public function forget(string $uuid): bool
    {
        $deleted = DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        if ($deleted === 0) {
            return false;
        }

        AuditLogger::custom('Başarısız kuyruk işi silindi', ['uuid' => $uuid]);

        return true;
    }

    /**
     * Başarısız iş listesini tümüyle temizle.
     *
     * @return int silinen kayıt sayısı
     */
    public function flush(): int
    {
        $count = (int) DB::table('failed_jobs')->count();

        if ($count === 0) {
            return 0;
        }

        DB::table('failed_jobs')->delete();

        AuditLogger::custom('Başarısız kuyruk listesi temizlendi', ['adet' => $count]);

        return $count;
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder
     */
    private function query(array $filters = []): Builder
    {
        $query = DB::table('failed_jobs');

        if (($filters['queue'] ?? '') !== '') {
            $query->where('queue', $filters['queue']);
        }

        if (($filters['search'] ?? '') !== '') {
            $term = '%' . $filters['search'] . '%';

            // Yük ve hata metni birlikte aranıyor: kullanıcı genelde ya iş
            // adını ya da hata mesajının bir parçasını hatırlıyor.
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('payload', 'like', $term)
                    ->orWhere('exception', 'like', $term)
                    ->orWhere('uuid', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * Satırı ekranın okuyabileceği hâle getirir.
     *
     * @return array<string, mixed>
     */
    private function present(object $row, bool $full = false): array
    {
        $payload = json_decode((string) $row->payload, true);
        $payload = is_array($payload) ? $payload : [];

        $exception = (string) $row->exception;

        return [
            'uuid'          => (string) $row->uuid,
            'queue'         => (string) $row->queue,
            'connection'    => (string) $row->connection,
            'job'           => $this->jobName($payload),
            'attempts'      => (int) ($payload['attempts'] ?? 0),
            'error'         => $this->firstLine($exception),
            'exception'     => $full ? $exception : null,
            'failed_at'     => Carbon::parse((string) $row->failed_at),
        ];
    }

    /**
     * İşin okunabilir adı.
     *
     * Kuyruğa giren her mail `Illuminate\Mail\SendQueuedMailable` olarak
     * görünüyor, yani `displayName` tek başına "hangi mail patladı" sorusunu
     * cevaplamıyor. Asıl sınıf yükün serileştirilmiş gövdesinde duruyor;
     * oradan okunabiliyorsa o gösteriliyor, okunamıyorsa çerçevenin adı
     * kalıyor — tahmin edilip yanlış ad basılmıyor.
     *
     * @param array<string, mixed> $payload
     */
    private function jobName(array $payload): string
    {
        $display = (string) ($payload['displayName'] ?? ($payload['job'] ?? 'Bilinmeyen iş'));

        $command = (string) ($payload['data']['command'] ?? '');

        if ($command !== '' && preg_match('/O:\d+:"(App\\\\(?:Mail|Jobs)\\\\[A-Za-z0-9_\\\\]+)"/', $command, $match) === 1) {
            return str_replace('\\\\', '\\', $match[1]);
        }

        return $display;
    }

    /**
     * Hata metninin ilk satırı: listede tam yığın izi değil ne olduğu okunur.
     */
    private function firstLine(string $exception): string
    {
        $line = strtok($exception, "\n");

        return $line === false ? '' : trim($line);
    }
}
