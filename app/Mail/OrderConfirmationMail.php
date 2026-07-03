<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class OrderConfirmationMail extends BaseMail
{
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Sipariş Onayı - #{$this->order->order_number}",
        );
    }

    protected function emailView(): string
    {
        return 'emails.order-confirmation';
    }

    protected function templateKey(): string
    {
        return 'order_confirmation';
    }

    protected function templateVariables(): array
    {
        $order = $this->order;

        return [
            'order_number'          => $order->order_number,
            'order_date'            => $order->created_at->format('d.m.Y H:i'),
            'order_status_badge'    => '<span class="em-badge em-badge-warning">' . e($order->status->label()) . '</span>',
            'shipping_name'         => $order->shipping_name,
            'shipping_phone'        => $order->shipping_phone,
            'shipping_address'      => $order->shipping_address,
            'shipping_district_city' => $order->shipping_district . ' / ' . $order->shipping_city,
            'shipping_zip_row'      => $order->shipping_zip_code
                ? '<p class="em-info-row"><span class="em-info-label">Posta Kodu:</span> ' . e($order->shipping_zip_code) . '</p>'
                : '',
            'order_notes_section'   => $order->notes
                ? '<p class="em-text"><strong>Sipariş Notu:</strong> ' . e($order->notes) . '</p>'
                : '',
            'order_items_table'     => $this->renderOrderItemsTable($order, true),
            'order_url'             => $order->getTrackingUrl(),
            'site_name'             => Setting::getValue('site_name', config('app.name')),
        ];
    }

    /**
     * Render order items as an HTML table.
     */
    private function renderOrderItemsTable(Order $order, bool $detailed = false): string
    {
        $html = '<table class="em-table"><thead><tr>';
        $html .= '<th>Ürün</th><th>Miktar</th>';
        if ($detailed) {
            $html .= '<th>Birim Fiyat</th>';
        }
        $html .= '<th>Toplam</th></tr></thead><tbody>';

        foreach ($order->items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . e($item->product_name) . '</td>';
            $html .= '<td>' . e($item->quantity) . ' ' . e($item->unit) . '</td>';
            if ($detailed) {
                $html .= '<td>' . number_format((float) $item->product_price, 2, ',', '.') . ' TL</td>';
            }
            $html .= '<td>' . number_format((float) $item->total, 2, ',', '.') . ' TL</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody><tfoot>';
        $colspan = $detailed ? 3 : 2;

        $html .= '<tr><td colspan="' . $colspan . '"><strong>Ara Toplam</strong></td>';
        $html .= '<td>' . number_format((float) $order->subtotal, 2, ',', '.') . ' TL</td></tr>';

        if ((float) $order->discount_amount > 0) {
            $html .= '<tr><td colspan="' . $colspan . '"><strong>İndirim</strong></td>';
            $html .= '<td>-' . number_format((float) $order->discount_amount, 2, ',', '.') . ' TL</td></tr>';
        }

        if ((float) $order->shipping_cost > 0) {
            $html .= '<tr><td colspan="' . $colspan . '"><strong>Kargo</strong></td>';
            $html .= '<td>' . number_format((float) $order->shipping_cost, 2, ',', '.') . ' TL</td></tr>';
        }

        $html .= '<tr class="em-table-total"><td colspan="' . $colspan . '"><strong>Genel Toplam</strong></td>';
        $html .= '<td><strong>' . number_format((float) $order->total, 2, ',', '.') . ' TL</strong></td></tr>';
        $html .= '</tfoot></table>';

        return $html;
    }
}
