@extends('emails.layout')

@section('content')
    <p class="em-greeting">Sipariş Onayı</p>
    <h1 class="em-heading">Siparişiniz Alındı! &#127881;</h1>

    <p class="em-text">
        Sayın {{ $order->shipping_name }}, siparişiniz başarıyla oluşturuldu.
        Sipariş detaylarınız aşağıda yer almaktadır.
    </p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Sipariş No:</span> #{{ $order->order_number }}</p>
                <p class="em-info-row"><span class="em-info-label">Tarih:</span> {{ $order->created_at->format('d.m.Y H:i') }}</p>
                <p class="em-info-row"><span class="em-info-label">Durum:</span> <span class="em-badge em-badge-warning">{{ $order->status->label() }}</span></p>
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
                <th>Birim Fiyat</th>
                <th>Toplam</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                    <td>{{ number_format((float) $item->product_price, 2, ',', '.') }} TL</td>
                    <td>{{ number_format((float) $item->total, 2, ',', '.') }} TL</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Ara Toplam</strong></td>
                <td>{{ number_format((float) $order->subtotal, 2, ',', '.') }} TL</td>
            </tr>
            @if ((float) $order->discount_amount > 0)
                <tr>
                    <td colspan="3"><strong>İndirim</strong></td>
                    <td>-{{ number_format((float) $order->discount_amount, 2, ',', '.') }} TL</td>
                </tr>
            @endif
            @if ((float) $order->shipping_cost > 0)
                <tr>
                    <td colspan="3"><strong>Kargo</strong></td>
                    <td>{{ number_format((float) $order->shipping_cost, 2, ',', '.') }} TL</td>
                </tr>
            @endif
            <tr class="em-table-total">
                <td colspan="3"><strong>Genel Toplam</strong></td>
                <td><strong>{{ number_format((float) $order->total, 2, ',', '.') }} TL</strong></td>
            </tr>
        </tfoot>
    </table>

    <hr class="em-divider">

    <p class="em-heading-sm">Teslimat Bilgileri</p>

    <table class="em-info-box" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-info-box-td">
                <p class="em-info-row"><span class="em-info-label">Ad Soyad:</span> {{ $order->shipping_name }}</p>
                <p class="em-info-row"><span class="em-info-label">Telefon:</span> {{ $order->shipping_phone }}</p>
                <p class="em-info-row"><span class="em-info-label">Adres:</span> {{ $order->shipping_address }}</p>
                <p class="em-info-row"><span class="em-info-label">İlçe / İl:</span> {{ $order->shipping_district }} / {{ $order->shipping_city }}</p>
                @if ($order->shipping_zip_code)
                    <p class="em-info-row"><span class="em-info-label">Posta Kodu:</span> {{ $order->shipping_zip_code }}</p>
                @endif
            </td>
        </tr>
    </table>

    @if ($order->notes)
        <p class="em-text"><strong>Sipariş Notu:</strong> {{ $order->notes }}</p>
    @endif

    <div class="em-btn-wrap">
        <a href="{{ $order->getTrackingUrl() }}" class="em-btn">&#128230; Siparişimi Görüntüle</a>
    </div>

    <p class="em-text">
        Siparişiniz hazırlanmaya başladığında size bilgi vereceğiz.
        Bizi tercih ettiğiniz için teşekkür ederiz!
    </p>
@endsection
