<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Collection;

final class TestimonialService
{
    /**
     * @return Collection<int, Testimonial>
     */
    public function allActive(int $limit = 3): Collection
    {
        return Testimonial::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
