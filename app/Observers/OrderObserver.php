<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Services\NotificationCenter;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Log;

final class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        try {
            $this->notifyStatusChange($order);
        } catch (\Throwable $e) {
            Log::error('OrderObserver: bildirim hatası', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function notifyStatusChange(Order $order): void
    {
        $original = $order->getOriginal('status');
        $new      = $order->status;

        $oldLabel = $this->resolveLabel($original);
        $newLabel = $this->resolveLabel($new);
        $newEnum  = $new instanceof OrderStatus ? $new : (is_string($new) ? OrderStatus::tryFrom($new) : null);

        $orderUrl = rescue(static fn (): ?string => route('admin.orders.show', $order), null, false);
        $customerName = $order->shipping_name ?: ($order->guest_name ?? 'Misafir');
        $totalFmt = number_format((float) $order->total, 2, ',', '.');

        $isCritical = $newEnum === OrderStatus::Cancelled;
        $level = $isCritical ? AdminNotification::LEVEL_WARNING : AdminNotification::LEVEL_INFO;
        $emoji = $isCritical ? '⚠️' : '📦';

        try {
            TelegramNotifier::notifyAdminInfo(
                title: "Sipariş durumu değişti #{$order->order_number}",
                context: [
                    'müşteri'    => $customerName,
                    'eski_durum' => $oldLabel,
                    'yeni_durum' => $newLabel,
                    'tutar'      => "₺{$totalFmt}",
                ],
                url: $orderUrl,
                emoji: $emoji,
            );
        } catch (\Throwable $e) {
            Log::warning('Order status Telegram bildirimi başarısız', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        try {
            NotificationCenter::send(
                type: 'order_status_changed',
                title: "Sipariş #{$order->order_number} → {$newLabel}",
                message: "{$customerName} · {$oldLabel} → {$newLabel} · ₺{$totalFmt}",
                level: $level,
                icon: $newEnum instanceof OrderStatus ? $newEnum->icon() : 'bi-arrow-repeat',
                actionUrl: $orderUrl,
            );
        } catch (\Throwable $e) {
            Log::warning('Order status in-app bildirimi başarısız', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function resolveLabel(mixed $status): string
    {
        if ($status instanceof OrderStatus) {
            return $status->label();
        }

        if (is_string($status)) {
            return OrderStatus::tryFrom($status)?->label() ?? $status;
        }

        return '';
    }

    public function deleting(Order $order): void
    {
        if (! $order->isForceDeleting()) {
            return;
        }

        $order->items()->delete();
    }
}
