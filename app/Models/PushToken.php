<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PushPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir cihazın bildirim adresi.
 *
 * Jeton cihaza ait, hesaba değil: aynı telefondan başka bir hesaba
 * girildiğinde kayıt o hesaba geçiyor, yoksa bildirim eski kullanıcıya
 * giderdi.
 */
class PushToken extends Model
{
    /** @use HasFactory<\Database\Factories\PushTokenFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform'     => PushPlatform::class,
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
