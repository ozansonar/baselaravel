<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class OrderStatusUpdatedMail extends BaseMail
{
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Sipariş Durumu Güncellendi - #{$this->order->order_number}",
        );
    }

    protected function emailView(): string
    {
        return 'emails.order-status-updated';
    }

    protected function templateKey(): string
    {
        return 'order_status_updated';
    }

    protected function templateVariables(): array
    {
        $order = $this->order;

        return [
            'order_number'           => $order->order_number,
            'shipping_name'          => $order->shipping_name,
            'order_status_badge'     => '<span class="em-badge em-badge-' . e($order->status->color()) . '">' . e($order->status->label()) . '</span>',
            'update_date'            => $order->updated_at->format('d.m.Y H:i'),
            'status_message_section' => $this->renderStatusMessage($order->status),
            'order_items_table'      => $this->renderOrderSummaryTable($order),
            'order_url'              => $order->getTrackingUrl(),
            'site_name'              => Setting::getValue('site_name', config('app.name')),
        ];
    }

    /**
     * Render status-specific message HTML.
     */
    private function renderStatusMessage(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Processing => '<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr><td class="em-highlight-td"><p class="em-text">&#128230; Siparişiniz <strong>hazırlanmaya başlandı</strong>. En kısa sürede kargoya verilecektir.</p></td></tr></table>',
            OrderStatus::Shipped    => '<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr><td class="em-highlight-td"><p class="em-text">&#128666; Siparişiniz <strong>kargoya verildi!</strong> Teslimat sürecini takip edebilirsiniz.</p></td></tr></table>',
            OrderStatus::Delivered  => '<table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr><td class="em-highlight-td"><p class="em-text">&#9989; Siparişiniz <strong>teslim edildi</strong>. Afiyet olsun! Ürünlerimiz hakkında görüşlerinizi bizimle paylaşmanızdan memnuniyet duyarız.</p></td></tr></table>',
            OrderStatus::Cancelled  => '<table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr><td class="em-info-box-td"><p class="em-text">Siparişiniz <strong>iptal edilmiştir</strong>. Herhangi bir sorunuz varsa bizimle iletişime geçebilirsiniz.</p></td></tr></table>',
            default                 => '',
        };
    }

    /**
     * Render order summary table (simplified - no unit price column).
     */
    private function renderOrderSummaryTable(Order $order): string
    {
        $html = '<table class="em-table"><thead><tr><th>Ürün</th><th>Miktar</th><th>Toplam</th></tr></thead><tbody>';

        foreach ($order->items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . e($item->product_name) . '</td>';
            $html .= '<td>' . e($item->quantity) . ' ' . e($item->unit) . '</td>';
            $html .= '<td>' . number_format((float) $item->total, 2, ',', '.') . ' TL</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody><tfoot>';
        $html .= '<tr class="em-table-total"><td colspan="2"><strong>Genel Toplam</strong></td>';
        $html .= '<td><strong>' . number_format((float) $order->total, 2, ',', '.') . ' TL</strong></td></tr>';
        $html .= '</tfoot></table>';

        return $html;
    }
}
