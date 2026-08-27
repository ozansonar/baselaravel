<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'token',
        'user_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Henüz bir kampanyaya bağlanmamış, forma iliştirilmeyi bekleyen ekler.
     *
     * Bekleyen ek oturumda tutulurken aynı anda giden yüklemeler birbirinin
     * kaydını eziyordu; her yükleme artık kendi satırını yazıyor. Sahibiyle
     * birlikte aranıyor: belirteç uydurulsa bile başkasının dosyası
     * iliştirilemez.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query, ?int $userId): Builder
    {
        $query->whereNull('campaign_id');

        return $userId === null
            ? $query->whereNull('user_id')
            : $query->where('user_id', $userId);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 1) . ' MB',
            $bytes >= 1024      => round($bytes / 1024) . ' KB',
            default             => $bytes . ' B',
        };
    }
}
