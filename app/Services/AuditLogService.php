<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Aktivite kayıtlarının okunması: süzme, sayfalama ve özetler.
 *
 * Yazma tarafı AuditLogger'da; burası yalnızca "kim ne zaman ne yaptı"
 * sorusunu ekrana taşır. Kayıtlar panelden düzenlenmez ya da silinmez —
 * denetim kaydının değeri dokunulmamış olmasından gelir.
 */
final class AuditLogService
{
    /**
     * Zamanlanmış temizliğin sakladığı gün sayısı.
     *
     * routes/console.php'deki audit-logs:prune görevi de bu değeri kullanır;
     * ekranda yazan süre ile gerçekte silinen süre böylece ayrışmaz.
     */
    public const RETENTION_DAYS = 90;

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Özet kutuları.
     *
     * @return array{total: int, today: int, week: int, deletions: int, oldest: ?Carbon}
     */
    public function stats(): array
    {
        $oldest = AuditLog::query()->min('created_at');

        return [
            'total'     => AuditLog::query()->count(),
            'today'     => AuditLog::query()->whereDate('created_at', now()->toDateString())->count(),
            'week'      => AuditLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
            // Silme, geri alınamayan tek işlem: listede öne çıkması gerekir.
            'deletions' => AuditLog::query()->where('event', AuditEvent::Deleted->value)->count(),
            'oldest'    => $oldest !== null ? Carbon::parse((string) $oldest) : null,
        ];
    }

    /**
     * Sekme rozetleri için işlem türü başına adet.
     *
     * @return array<string, int>
     */
    public function eventCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = AuditLog::query()
            ->selectRaw('event, count(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event')
            ->all();

        return $counts;
    }

    /**
     * Kayıt tutulan model türleri — süzgeçteki listeyi gerçekte var olan
     * kayıtlardan kurar, elle yazılan sınıf adı aramaktan kurtarır.
     *
     * @return array<string, array{label: string, count: int}> tam sınıf adı => bilgi
     */
    public function modelOptions(): array
    {
        return AuditLog::query()
            ->selectRaw('auditable_type, count(*) as total')
            ->whereNotNull('auditable_type')
            ->groupBy('auditable_type')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(static fn ($row): array => [
                (string) $row->auditable_type => [
                    'label' => class_basename((string) $row->auditable_type),
                    'count' => (int) $row->total,
                ],
            ])
            ->all();
    }

    /**
     * Bir kaydın zaman sırasındaki komşuları.
     *
     * Denetim kaydı çoğu zaman tek satır değil, bir oturumun peş peşe yaptığı
     * işlemler okunarak takip edilir; ileri/geri gezinme listeye dönmeden
     * bunu mümkün kılar.
     *
     * @return array{previous: ?AuditLog, next: ?AuditLog}
     */
    public function neighbours(AuditLog $log): array
    {
        return [
            // "Önceki" daha eski kayıt: liste yeniden eskiye sıralı.
            'previous' => AuditLog::query()
                ->where('created_at', '<', $log->created_at)
                ->orderByDesc('created_at')
                ->first(['id']),
            'next' => AuditLog::query()
                ->where('created_at', '>', $log->created_at)
                ->orderBy('created_at')
                ->first(['id']),
        ];
    }

    /**
     * Kayıtlarda geçen IP adresleri — "bu adresten neler yapıldı" sorusu
     * denetim kaydının en sık sorulanı, o yüzden elle yazmak yerine seçilir.
     *
     * @return array<string, int> ip => adet
     */
    public function ipOptions(int $limit = 100): array
    {
        /** @var array<string, int> $options */
        $options = AuditLog::query()
            ->selectRaw('ip_address, count(*) as total')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'ip_address')
            ->all();

        return $options;
    }

    /**
     * En çok işlem yapanlar — denetim kaydına bakan kişinin ikinci sorusu.
     *
     * @return list<array{name: string, count: int, percent: int}>
     */
    public function topActors(int $limit = 5): array
    {
        $rows = AuditLog::query()
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->all())
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->keyBy('id');

        $highest = (int) ($rows->max('total') ?? 0);

        return $rows->map(static function ($row) use ($users, $highest): array {
            $user = $row->user_id !== null ? $users->get($row->user_id) : null;
            $name = $user !== null
                ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email)
                : 'Sistem';

            return [
                'name'  => $name,
                'count' => (int) $row->total,
                // Genişlik satır içi style yerine sınıfla verildiği için beşin
                // katına yuvarlanıyor.
                'percent' => $highest > 0 ? (int) (round((int) $row->total / $highest * 100 / 5) * 5) : 0,
            ];
        })->all();
    }

    /**
     * Arama terimini LIKE kalıbına çevirir.
     *
     * Kullanıcının yazdığı % ve _ joker değil, harf sayılmalı. Kaçış karakteri
     * olarak ünlem seçildi: ters bölüyü MySQL kendiliğinden kaçış sayarken
     * SQLite saymıyor, ünlem ikisinde de düz karakter ve ESCAPE ile açıkça
     * bildiriliyor.
     */
    private function likeTerm(string $value): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
    }

    /**
     * Süzgeçlerin uygulandığı temel sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<AuditLog>
     */
    private function filtered(array $filters): Builder
    {
        $query = AuditLog::query();

        if (($filters['event'] ?? '') !== '') {
            $query->where('event', $filters['event']);
        }

        if (($filters['user_id'] ?? '') !== '') {
            // "0" seçeneği kullanıcısı olmayan kayıtlar için: zamanlanmış
            // görevlerin yazdığı satırların user_id'si boştur.
            if ((string) $filters['user_id'] === '0') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', (int) $filters['user_id']);
            }
        }

        if (($filters['model'] ?? '') !== '') {
            $query->where('auditable_type', $filters['model']);
        }

        if (($filters['ip'] ?? '') !== '') {
            $query->where('ip_address', $filters['ip']);
        }

        if (($filters['from'] ?? '') !== '') {
            $query->where('created_at', '>=', Carbon::parse((string) $filters['from'])->startOfDay());
        }

        if (($filters['to'] ?? '') !== '') {
            $query->where('created_at', '<=', Carbon::parse((string) $filters['to'])->endOfDay());
        }

        if (($filters['q'] ?? '') !== '') {
            $search = $this->likeTerm((string) $filters['q']);

            $query->where(function (Builder $sub) use ($search): void {
                $sub->whereRaw("label LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("ip_address LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("url LIKE ? ESCAPE '!'", [$search]);
            });
        }

        return $query;
    }
}
