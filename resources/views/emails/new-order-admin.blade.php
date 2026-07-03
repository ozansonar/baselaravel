@extends('emails.layout')

@section('content')
    <p class="em-greeting">Yeni Sipariş Bildirimi</p>
    <h1 class="em-heading">Yeni Sipariş Geldi! &#128230;</h1>

    <p class="em-text">
        <strong>{{ $order->getCustomerName() }}</strong> tarafından yeni bir sipariş oluşturuldu.
        @if($order->isGuest())
            <br><span class="em-badge em-badge-info">Misafir Sipariş</span>
        @endif
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Sipariş No:</span> #{{ $order->order_number }}</p>
                <p class="em-info-row"><span class="em-info-label">Tarih:</span> {{ $order->created_at->format('d.m.Y H:i') }}</p>
                <p class="em-info-row"><span class="em-info-label">Toplam:</span> <strong>{{ number_format((float) $order->total, 2, ',', '.') }} TL</strong></p>
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Müşteri Bilgileri</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Ad Soyad:</span> {{ $order->shipping_name }}</p>
                <p class="em-info-row"><span class="em-info-label">Telefon:</span> {{ $order->shipping_phone }}</p>
                @if($order->getCustomerEmail())
                    <p class="em-info-row"><span class="em-info-label">E-posta:</span> {{ $order->getCustomerEmail() }}</p>
                @endif
            </td>
        </tr>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Sipariş Detayları</p>

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

    <hr class="em-divider">

    <p class="em-heading-sm">Teslimat Adresi</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row">{{ $order->shipping_address }}</p>
                <p class="em-info-row">{{ $order->shipping_district }} / {{ $order->shipping_city }}</p>
                @if ($order->shipping_zip_code)
                    <p class="em-info-row">Posta Kodu: {{ $order->shipping_zip_code }}</p>
                @endif
            </td>
        </tr>
    </table>

    @if ($order->notes)
        <p class="em-text"><strong>Sipariş Notu:</strong> {{ $order->notes }}</p>
    @endif

    <div class="em-btn-wrap">
        <a href="{{ url('/admin/orders/' . $order->id) }}" class="em-btn">&#128203; Siparişi Görüntüle</a>
    </div>

    @php
        $whatsappEnabled = \App\Models\Setting::getValue('order_whatsapp_notification', '1') === '1';
    @endphp

    @if($whatsappEnabled)
        @php
            $whatsappNumber = \App\Models\Setting::getValue('social_whatsapp', '');
            $cleanNumber = preg_replace('/[^0-9]/', '', $whatsappNumber ?? '');
            $waMessage = "Merhaba {$order->shipping_name}, {$order->order_number} numaralı siparişiniz hakkında bilgi vermek istiyoruz.";
            $waLink = "https://wa.me/{$cleanNumber}?text=" . urlencode($waMessage);
        @endphp
        <div class="em-btn-wrap">
            <a href="{{ $waLink }}" class="em-btn" style="background-color:#25D366 !important;">&#128172; WhatsApp ile İletişime Geç</a>
        </div>
    @endif
@endsection
