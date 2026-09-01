<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PushAudience;
use App\Enums\PushNotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Panelden gönderilen bir push bildirimi.
 *
 * Kayıt hem gönderim emri hem de sonucu: cron bu satırı okuyup gönderiyor,
 * ilerledikçe sayaçları güncelliyor. "Bu duyuru gitti mi, kaç kişiye ulaştı"
 * sorusunun cevabı burada.
 */
class PushNotification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'link',
        'audience',
        'audience_id',
        'status',
        'total_devices',
        'sent_count',
        'failed_count',
        'skipped_count',
        'cursor',
        'last_error',
        'started_at',
        'completed_at',
    ];

    /**
     * Sayaçların başlangıç değerleri.
     *
     * Sütun varsayılanları veritabanında da yazılı ama onlar yalnız satır
     * yazıldıktan sonra geçerli: yeni kurulan bir örnekte `cursor` null
     * kalıyordu ve gönderim "id > null" diye sorgu kurmaya çalışıyordu.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status'        => 'queued',
        'total_devices' => 0,
        'sent_count'    => 0,
        'failed_count'  => 0,
        'skipped_count' => 0,
        'cursor'        => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience'      => PushAudience::class,
            'status'        => PushNotificationStatus::class,
            'total_devices' => 'integer',
            'sent_count'    => 'integer',
            'failed_count'  => 'integer',
            'skipped_count' => 'integer',
            'cursor'        => 'integer',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    /** Gönderen yönetici; hesabı silinmişse null. */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cron'un ele alacağı kayıtlar — eskiden yeniye.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                PushNotificationStatus::Queued->value,
                PushNotificationStatus::Sending->value,
            ])
            ->oldest('id');
    }

    /**
     * Ulaşılan cihaz oranı — listede ilerleme çubuğu.
     */
    public function progress(): int
    {
        if ($this->total_devices === 0) {
            return $this->status === PushNotificationStatus::Sent ? 100 : 0;
        }

        $islenen = $this->sent_count + $this->failed_count + $this->skipped_count;

        return (int) min(100, round($islenen / $this->total_devices * 100));
    }

    /**
     * Hedefin okunabilir karşılığı.
     *
     * Hedef kaydı silinmiş olabilir (rol kaldırılmış, kullanıcı silinmiş);
     * o durumda kimliği göstermek, hiçbir şey göstermemekten iyi — gönderimin
     * kime gittiği sorusu hâlâ cevaplanabilir olmalı.
     */
    public function audienceLabel(): string
    {
        if ($this->audience === PushAudience::All) {
            return PushAudience::All->label();
        }

        $ad = $this->audience === PushAudience::Role
            ? Role::withTrashed()->find($this->audience_id)?->name
            : User::withTrashed()->find($this->audience_id)?->full_name;

        if (is_string($ad) && $ad !== '') {
            return $ad;
        }

        return $this->audience->label() . ' #' . $this->audience_id;
    }
}
