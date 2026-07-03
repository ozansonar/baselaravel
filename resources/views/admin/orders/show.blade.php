@extends('layouts.admin')

@section('title', 'Sipariş #' . $order->order_number)
@section('page_title', 'Sipariş Detayı')
@section('page_description', '#' . $order->order_number)

@push('styles')
<style>
    /* Sayfa-özel küçük yardımcılar — global ord-* class'larına ek */
    .ord-page-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
    .ord-sticky-side { position: sticky; top: 80px; }
    @media (max-width: 991.98px) { .ord-sticky-side { position: static; } }
</style>
@endpush

@section('content')
@php
    use App\Enums\OrderStatus;

    $status = $order->status;
    $isCancelled = $status === OrderStatus::Cancelled;

    // Timeline adımları — iptal edildiğinde son adım kırmızı, diğerlerinde sıralı progress
    $statusOrder = [
        OrderStatus::Pending->value    => 1,
        OrderStatus::Processing->value => 2,
        OrderStatus::Shipped->value    => 3,
        OrderStatus::Delivered->value  => 4,
    ];
    $currentStep = $isCancelled ? 0 : ($statusOrder[$status->value] ?? 1);

    $timelineSteps = [
        ['key' => 'created',    'title' => 'Sipariş Alındı',  'icon' => 'bi-check',        'step' => 1, 'time' => $order->created_at],
        ['key' => 'processing', 'title' => 'Hazırlanıyor',     'icon' => 'bi-gear-fill',    'step' => 2, 'time' => null],
        ['key' => 'shipped',    'title' => 'Kargoda',          'icon' => 'bi-truck',        'step' => 3, 'time' => null],
        ['key' => 'delivered',  'title' => 'Teslim Edildi',    'icon' => 'bi-house-check', 'step' => 4, 'time' => null],
    ];

    $waClean = preg_replace('/[^0-9]/', '', $order->shipping_phone ?? '');
    $waText  = "Merhaba {$order->shipping_name}, {$order->order_number} numaralı siparişiniz hakkında bilgi vermek istiyoruz.";
    $waLink  = "https://wa.me/{$waClean}?text=" . urlencode($waText);
@endphp

<div class="ord-page-actions">
    <a href="{{ route('admin.orders.index') }}" class="btn-glass btn-glass-sm">
        <i class="bi bi-arrow-left me-1"></i> Siparişlere Dön
    </a>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn-glass btn-glass-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Yazdır
        </button>
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn-glass btn-glass-sm text-success">
            <i class="bi bi-whatsapp me-1"></i> WhatsApp
        </a>
    </div>
</div>

{{-- ─── Header: sipariş no + status badge + tarih ─── --}}
<div class="card-dark mb-3">
    <div class="card-body-custom">
        <div class="ord-detail-header">
            <div class="ord-detail-id">
                <h4>#{{ $order->order_number }}</h4>
                <span class="ord-status-badge ord-status-{{ $status->value }}">
                    <i class="bi {{ $status->icon() }}"></i> {{ $status->label() }}
                </span>
            </div>
            <span class="ord-detail-date">
                <i class="bi bi-calendar3 me-1"></i>
                {{ $order->created_at->translatedFormat('d F Y - H:i') }}
            </span>
        </div>

        {{-- Timeline --}}
        @if($isCancelled)
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-0 mt-3">
                <i class="bi bi-x-octagon-fill"></i>
                <span><strong>Bu sipariş iptal edildi.</strong> Tekrar işleme almak için durumu güncelleyebilirsiniz.</span>
            </div>
        @else
            <div class="ord-timeline mt-3">
                @foreach($timelineSteps as $i => $step)
                    @php
                        $isCompleted = $currentStep >= $step['step'];
                        $isActive    = $currentStep === $step['step'];
                    @endphp
                    <div class="ord-timeline-step {{ $isCompleted ? 'completed' : '' }} {{ $isActive ? 'active' : '' }}">
                        <div class="ord-timeline-dot">
                            <i class="bi {{ $isCompleted ? 'bi-check' : $step['icon'] }}"></i>
                        </div>
                        <div class="ord-timeline-info">
                            <span class="ord-timeline-title">{{ $step['title'] }}</span>
                            <span class="ord-timeline-time">
                                @if($step['time'])
                                    {{ $step['time']->translatedFormat('d M, H:i') }}
                                @elseif($isCompleted)
                                    Tamamlandı
                                @else
                                    Bekleniyor
                                @endif
                            </span>
                        </div>
                    </div>
                    @if($i < count($timelineSteps) - 1)
                        <div class="ord-timeline-line {{ $currentStep > $step['step'] ? 'completed' : '' }}"></div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="row g-3">
    {{-- ─── Sol: bilgiler + items ─── --}}
    <div class="col-lg-8">
        {{-- Müşteri + Teslimat (2 kolon) --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card-dark h-100">
                    <div class="card-body-custom ord-detail-section">
                        <h6 class="ord-section-title">
                            <i class="bi bi-person me-2"></i>Müşteri Bilgileri
                            @if($order->isGuest())
                                <span class="badge bg-warning-subtle text-warning ms-2">Misafir</span>
                            @endif
                        </h6>
                        @if($order->user)
                            <div class="ord-info-row"><span>Ad Soyad</span><strong>{{ $order->user->full_name }}</strong></div>
                            <div class="ord-info-row"><span>E-posta</span><strong>{{ $order->user->email }}</strong></div>
                            @if($order->user->phone)
                                <div class="ord-info-row"><span>Telefon</span><strong>{{ $order->user->phone }}</strong></div>
                            @endif
                        @else
                            <div class="ord-info-row"><span>Ad Soyad</span><strong>{{ $order->guest_name ?? $order->shipping_name }}</strong></div>
                            @if($order->guest_email)
                                <div class="ord-info-row"><span>E-posta</span><strong>{{ $order->guest_email }}</strong></div>
                            @endif
                            <div class="ord-info-row"><span>Telefon</span><strong>{{ $order->shipping_phone }}</strong></div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-dark h-100">
                    <div class="card-body-custom ord-detail-section">
                        <h6 class="ord-section-title"><i class="bi bi-geo-alt me-2"></i>Teslimat Adresi</h6>
                        <p class="ord-address mb-2"><strong>{{ $order->shipping_name }}</strong></p>
                        <p class="ord-address mb-2">{{ $order->shipping_address }}</p>
                        <div class="ord-info-row">
                            <span>İl / İlçe</span>
                            <strong>{{ $order->shipping_district }} / {{ $order->shipping_city }}</strong>
                        </div>
                        @if($order->shipping_zip_code)
                            <div class="ord-info-row"><span>Posta Kodu</span><strong>{{ $order->shipping_zip_code }}</strong></div>
                        @endif
                        <div class="ord-info-row"><span>Telefon</span><strong>{{ $order->shipping_phone }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sipariş Kalemleri --}}
        <div class="card-dark mb-3">
            <div class="card-body-custom ord-detail-section">
                <h6 class="ord-section-title"><i class="bi bi-box-seam me-2"></i>Sipariş Kalemleri</h6>
                <div class="table-responsive">
                    <table class="table cl-table mb-0">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th>Birim Fiyat</th>
                                <th>Adet</th>
                                <th class="text-end">Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($item->product && $item->product->image)
                                                <img src="{{ upload_url($item->product->image, 'thumb') }}"
                                                     alt="{{ $item->product_name }}"
                                                     class="ord-product-thumb-lg" loading="lazy">
                                            @else
                                                <div class="ord-product-thumb-lg d-flex align-items-center justify-content-center bg-secondary-subtle">
                                                    <i class="bi bi-box text-muted"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <span class="ord-product-name">{{ $item->product_name }}</span>
                                                @if($item->product)
                                                    <span class="ord-product-variant">SKU: {{ $item->product->sku }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ number_format((float) $item->product_price, 2, ',', '.') }} ₺</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="text-end"><strong>{{ number_format((float) $item->total, 2, ',', '.') }} ₺</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Özet --}}
                <div class="ord-summary mt-3">
                    <div class="ord-summary-row">
                        <span>Ara Toplam</span>
                        <span>{{ number_format((float) $order->subtotal, 2, ',', '.') }} ₺</span>
                    </div>
                    @if($order->discount_amount > 0)
                        <div class="ord-summary-row">
                            <span>
                                İndirim
                                @if($order->campaign_code)
                                    <span class="badge bg-success-subtle text-success ms-1">{{ $order->campaign_code }}</span>
                                @endif
                            </span>
                            <span class="text-neon-green">-{{ number_format((float) $order->discount_amount, 2, ',', '.') }} ₺</span>
                        </div>
                    @endif
                    <div class="ord-summary-row">
                        <span>Kargo</span>
                        <span>{{ number_format((float) $order->shipping_cost, 2, ',', '.') }} ₺</span>
                    </div>
                    <div class="ord-summary-row ord-summary-total">
                        <span>Genel Toplam</span>
                        <span>{{ number_format((float) $order->total, 2, ',', '.') }} ₺</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sipariş Notu --}}
        @if($order->notes)
            <div class="card-dark mb-3">
                <div class="card-body-custom ord-detail-section">
                    <h6 class="ord-section-title"><i class="bi bi-chat-left-text me-2"></i>Müşteri Notu</h6>
                    <p class="mb-0">{{ $order->notes }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- ─── Sağ: sticky aksiyonlar ─── --}}
    <div class="col-lg-4">
        <div class="ord-sticky-side">
            {{-- Durum Güncelle --}}
            <div class="card-dark mb-3">
                <div class="card-body-custom ord-detail-section">
                    <h6 class="ord-section-title"><i class="bi bi-arrow-repeat me-2"></i>Durum Güncelle</h6>
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label for="status" class="form-label small text-clr-secondary">YENİ DURUM</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                @foreach($orderStatuses as $statusOption)
                                    <option value="{{ $statusOption->value }}"
                                            {{ $status === $statusOption ? 'selected' : '' }}>
                                        {{ $statusOption->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn-teal w-100">
                            <i class="bi bi-check-lg me-1"></i> Durumu Güncelle
                        </button>
                    </form>
                </div>
            </div>

            {{-- İletişim --}}
            <div class="card-dark mb-3">
                <div class="card-body-custom ord-detail-section">
                    <h6 class="ord-section-title"><i class="bi bi-whatsapp me-2"></i>İletişim</h6>
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn-teal w-100 mb-2 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-whatsapp"></i> WhatsApp ile İletişime Geç
                    </a>
                    <a href="tel:{{ $order->shipping_phone }}" class="btn-glass w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-telephone"></i> {{ $order->shipping_phone }}
                    </a>
                </div>
            </div>

            {{-- Sipariş Bilgileri --}}
            <div class="card-dark">
                <div class="card-body-custom ord-detail-section">
                    <h6 class="ord-section-title"><i class="bi bi-info-circle me-2"></i>Bilgiler</h6>
                    <div class="ord-info-row"><span>Sipariş Tarihi</span><strong>{{ $order->created_at->format('d.m.Y H:i') }}</strong></div>
                    @if($order->ip_address)
                        <div class="ord-info-row"><span>IP Adresi</span><strong>{{ $order->ip_address }}</strong></div>
                    @endif
                    <div class="ord-info-row"><span>Ürün Sayısı</span><strong>{{ $order->items->sum('quantity') }} adet</strong></div>
                    @if($order->campaign_code)
                        <div class="ord-info-row"><span>Kampanya Kodu</span><strong>{{ $order->campaign_code }}</strong></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
