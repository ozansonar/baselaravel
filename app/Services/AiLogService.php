<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiLogStatus;
use App\Models\AiLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AiLogService
{
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = AiLog::with('blogPost')->latest();

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'failed') {
                $query->failed();
            } elseif ($filters['status'] === 'success') {
                $query->successful();
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date_filter'])) {
            $query->where('created_at', '>=', match ($filters['date_filter']) {
                'today'   => now()->startOfDay(),
                'week'    => now()->startOfWeek(),
                'month'   => now()->startOfMonth(),
                'quarter' => now()->subMonths(3)->startOfDay(),
                default   => now()->subYears(10),
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getAdminStats(): array
    {
        return [
            'total'   => AiLog::count(),
            'success' => AiLog::where('status', AiLogStatus::Success)->count(),
            'failed'  => AiLog::failed()->count(),
            'today'   => AiLog::whereDate('created_at', today())->count(),
        ];
    }

    public function statusCounts(): array
    {
        return [
            'success' => AiLog::where('status', AiLogStatus::Success)->count(),
            'failed'  => AiLog::failed()->count(),
        ];
    }
}
