<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalyticsDailyStat extends Model
{
    use SoftDeletes;

    protected $table = 'analytics_daily_stats';

    protected $fillable = [
        'date',
        'total_views',
        'unique_visitors',
        'bot_views',
        'desktop_views',
        'mobile_views',
        'tablet_views',
        'top_pages',
        'top_referrers',
        'top_browsers',
    ];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'top_pages'     => 'array',
            'top_referrers' => 'array',
            'top_browsers'  => 'array',
        ];
    }
}
