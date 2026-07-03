<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignType;
use App\Models\Campaign;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CampaignService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Campaign::withTrashed()->latest();

        if (!empty($filters['status'])) {
            $now = now();
            match ($filters['status']) {
                'active' => $query->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now),
                'scheduled' => $query->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('start_date', '>', $now),
                'paused' => $query->whereNull('deleted_at')
                    ->where('is_active', false)
                    ->where('end_date', '>=', $now),
                'ended' => $query->whereNull('deleted_at')
                    ->where('end_date', '<', $now),
                'trashed' => $query->onlyTrashed(),
                default => $query->whereNull('deleted_at'),
            };
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['type'])) {
            $type = CampaignType::tryFrom($filters['type']);
            if ($type !== null) {
                $query->where('type', $type);
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): Campaign
    {
        return Campaign::findOrFail($id);
    }

    public function findByCode(string $code): ?Campaign
    {
        return Campaign::active()->byCode($code)->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign
    {
        return DB::transaction(fn (): Campaign => Campaign::create($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data): Campaign {
            $campaign->update($data);

            return $campaign->refresh();
        });
    }

    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
        $this->clearCache();
    }

    public function restore(int $id): Campaign
    {
        $campaign = Campaign::withTrashed()->findOrFail($id);
        $campaign->restore();
        $this->clearCache();

        return $campaign;
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.campaigns.stats', 300, function (): array {
            $now = now();

            $counts = Campaign::withTrashed()
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and is_active = 1 and start_date <= ? and end_date >= ? then 1 else 0 end) as active', [$now, $now])
                ->selectRaw('COALESCE(sum(case when deleted_at is null then used_count else 0 end), 0) as total_usage')
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'       => (int) $counts->total,
                'active'      => (int) $counts->active,
                'total_usage' => (int) $counts->total_usage,
                'trashed'     => (int) $counts->trashed,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $now = now();

        $counts = Campaign::withTrashed()
            ->selectRaw('sum(case when deleted_at is null and is_active = 1 and start_date <= ? and end_date >= ? then 1 else 0 end) as active', [$now, $now])
            ->selectRaw('sum(case when deleted_at is null and is_active = 1 and start_date > ? then 1 else 0 end) as scheduled', [$now])
            ->selectRaw('sum(case when deleted_at is null and is_active = 0 and end_date >= ? then 1 else 0 end) as paused', [$now])
            ->selectRaw('sum(case when deleted_at is null and end_date < ? then 1 else 0 end) as ended', [$now])
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
            ->first();

        return [
            'active'    => (int) $counts->active,
            'scheduled' => (int) $counts->scheduled,
            'paused'    => (int) $counts->paused,
            'ended'     => (int) $counts->ended,
            'trashed'   => (int) $counts->trashed,
        ];
    }

    public function clearCache(): void
    {
        Cache::forget('admin.campaigns.stats');
    }

    /**
     * Validate and apply a coupon code to an order total.
     *
     * @return array{valid: bool, message: string, discount: float}
     */
    public function applyCoupon(string $code, float $orderTotal): array
    {
        $campaign = $this->findByCode($code);

        if (!$campaign) {
            return ['valid' => false, 'message' => 'Geçersiz kupon kodu.', 'discount' => 0];
        }

        if (!$campaign->isValid()) {
            return ['valid' => false, 'message' => 'Bu kupon kodunun süresi dolmuş.', 'discount' => 0];
        }

        if ($orderTotal < (float) $campaign->min_order_amount) {
            return [
                'valid'    => false,
                'message'  => "Minimum sipariş tutarı: {$campaign->min_order_amount} TL",
                'discount' => 0,
            ];
        }

        $discount = $campaign->isPercentage()
            ? round($orderTotal * (float) $campaign->discount_value / 100, 2)
            : (float) $campaign->discount_value;

        return ['valid' => true, 'message' => 'Kupon uygulandı.', 'discount' => $discount];
    }

    public function incrementUsage(Campaign $campaign): void
    {
        $campaign->increment('used_count');
    }
}
