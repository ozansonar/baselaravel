<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir ziyaretçinin çerez tercihi.
 *
 * Tarayıcıdaki çerez tercihi hatırlamaya yeter; bu kayıt ise ispat için.
 * Ziyaretçi tercihini değiştirdiğinde eski satır güncellenmez, yenisi yazılır —
 * rızanın geçmişi de kayıttır.
 */
final class Consent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'token',
        'categories',
        'version',
        'user_id',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'version'    => 'integer',
            'user_id'    => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bu kayıtta şu kategoriye izin verilmiş mi?
     */
    public function allows(string $category): bool
    {
        return in_array($category, (array) $this->categories, true);
    }
}
