<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'guest_email',
        'guest_name',
        'order_number',
        'tracking_token',
        'status',
        'subtotal',
        'discount_amount',
        'shipping_cost',
        'total',
        'shipping_name',
        'shipping_phone',
        'shipping_city',
        'shipping_district',
        'shipping_zip_code',
        'shipping_address',
        'campaign_code',
        'notes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    // ── Relationships ──

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByStatus(\Illuminate\Database\Eloquent\Builder $query, OrderStatus $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRecent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderByDesc('created_at');
    }

    // ── Helpers ──

    public static function generateOrderNumber(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 10;

        do {
            $number = '';
            for ($i = 0; $i < $length; $i++) {
                $number .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    public static function generateTrackingToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function getTrackingUrl(): string
    {
        return url("/siparis/takip/{$this->order_number}/{$this->tracking_token}");
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    public function getCustomerName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? $this->shipping_name;
    }
}
