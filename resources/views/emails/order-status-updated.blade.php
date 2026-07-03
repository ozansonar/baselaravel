@extends('emails.layout')

@section('content')
    <p class="em-greeting">Sipariş Güncelleme</p>
    <h1 class="em-heading">Sipariş Durumu Güncellendi</h1>

    <p class="em-text">
        Sayın {{ $order->shipping_name }}, #{{ $order->order_number }} numaralı siparişinizin durumu güncellendi.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Sipariş No:</span> #{{ $order->order_number }}</p>
                <p class="em-info-row"><span class="em-info-label">Yeni Durum:</span> <span class="em-badge em-badge-{{ $order->status->color() }}">{{ $order->status->label() }}</span></p>
                <p class="em-info-row"><span class="em-info-label">Güncelleme Tarihi:</span> {{ $order->updated_at->format('d.m.Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    @if ($order->status === \App\Enums\OrderStatus::Processing)
        <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="em-highlight-td">
                    <p class="em-text">&#128230; Siparişiniz <strong>hazırlanmaya başlandı</strong>. En kısa sürede kargoya verilecektir.</p>
                </td>
            </tr>
        </table>
    @elseif ($order->status === \App\Enums\OrderStatus::Shipped)
        <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="em-highlight-td">
                    <p class="em-text">&#128666; Siparişiniz <strong>kargoya verildi!</strong> Teslimat sürecini takip edebilirsiniz.</p>
                </td>
            </tr>
        </table>
    @elseif ($order->status === \App\Enums\OrderStatus::Delivered)
        <table class="em-highlight" role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="em-highlight-td">
                    <p class="em-text">&#9989; Siparişiniz <strong>teslim edildi</strong>. Afiyet olsun! Ürünlerimiz hakkında görüşlerinizi bizimle paylaşmanızdan memnuniyet duyarız.</p>
                </td>
            </tr>
        </table>
    @elseif ($order->status === \App\Enums\OrderStatus::Cancelled)
        <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td class="em-info-box-td">
                    <p class="em-text">Siparişiniz <strong>iptal edilmiştir</strong>. Herhangi bir sorunuz varsa bizimle iletişime geçebilirsiniz.</p>
                </td>
            </tr>
        </table>
    @endif

    <hr class="em-divider">

    <p class="em-heading-sm">Sipariş Özeti</p>

    <table class="em-table">
        <thead>
            <tr>
                <th>Ürün</th>
                <th>Miktar</th>
                <th>Toplam</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                    <td>{{ number_format((float) $item->total, 2, ',', '.') }} TL</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="em-table-total">
                <td colspan="2"><strong>Genel Toplam</strong></td>
                <td><strong>{{ number_format((float) $order->total, 2, ',', '.') }} TL</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="em-btn-wrap">
        <a href="{{ $order->getTrackingUrl() }}" class="em-btn">Sipariş Detayları</a>
    </div>
@endsection
