<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class NewOrderAdminNotificationMail extends BaseMail
{
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Yeni Sipariş - #{$this->order->order_number}",
        );
    }

    protected function emailView(): string
    {
        return 'emails.new-order-admin';
    }

    protected function templateKey(): ?string
    {
        return null;
    }

    protected function templateVariables(): array
    {
        return [];
    }

    /**
     * Build WhatsApp link with pre-filled message.
     */
    public function getWhatsAppLink(): string
    {
        $whatsappNumber = Setting::getValue('social_whatsapp', '');
        $cleanNumber = preg_replace('/[^0-9]/', '', $whatsappNumber ?? '');

        $message = "Merhaba {$this->order->shipping_name}, {$this->order->order_number} numaralı siparişiniz hakkında bilgi vermek istiyoruz.";

        return "https://wa.me/{$cleanNumber}?text=" . urlencode($message);
    }
}
