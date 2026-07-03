<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramPostLog extends Model
{
    protected $fillable = [
        'instagram_post_id',
        'action',
        'status',
        'request_payload',
        'response_body',
        'error_message',
        'api_duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'api_duration_ms' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(InstagramPost::class, 'instagram_post_id');
    }
}
