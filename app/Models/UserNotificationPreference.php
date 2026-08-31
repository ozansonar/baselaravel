<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kullanıcının bir e-posta türü için verdiği karar.
 *
 * Satır yalnız varsayılandan sapıldığında var; yokluğu "karar verilmemiş"
 * demek, "kapalı" değil.
 */
class UserNotificationPreference extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type'    => NotificationPreference::class,
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
