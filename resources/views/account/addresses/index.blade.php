@extends('layouts.app')

@section('title', 'Adreslerim | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Kayıtlı adreslerinizi yönetin.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="{{ route('account.dashboard') }}">Hesabım</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Adreslerim</span>
        </div>
        <h1 class="page-title">Hesabım</h1>
        <p class="page-subtitle">Hesap bilgilerinizi ve siparişlerinizi yönetin</p>
    </div>
</header>

{{-- Dashboard Section --}}
<section class="dashboard-section">
    <div class="container">
        <div class="row">
            {{-- Sidebar --}}
            <div class="col-lg-3">
                @include('account.partials.sidebar')
            </div>

            {{-- Content --}}
            <div class="col-lg-9">
                <div class="dashboard-content">
                    <div class="content-header">
                        <h3><i class="fa-solid fa-location-dot"></i> Adreslerim</h3>
                    </div>

                    @if($addresses->count() > 0)
                    <div class="address-cards">
                        @foreach($addresses as $address)
                        <div class="address-card {{ $address->is_default ? 'default' : '' }}">
                            @if($address->is_default)
                            <span class="default-badge">Varsayılan</span>
                            @endif
                            <h5>
                                <i class="fa-solid fa-{{ $address->is_default ? 'house' : 'location-dot' }} me-2"></i>{{ $address->title }}
                            </h5>
                            <p>
                                <strong>{{ $address->full_name }}</strong><br>
                                {{ $address->fullAddress() }}
                            </p>
                            <div class="phone">
                                <i class="fa-solid fa-phone me-2"></i>{{ $address->phone }}
                            </div>
                            <div class="address-actions">
                                @if(!$address->is_default)
                                <form method="POST" action="{{ route('account.addresses.set-default', $address) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-edit">
                                        <i class="fa-solid fa-star me-1"></i> Varsayılan
                                    </button>
                                </form>
                                @endif
                                <a href="{{ route('account.addresses.edit', $address) }}" class="btn-edit">
                                    <i class="fa-solid fa-pen me-1"></i> Düzenle
                                </a>
                                <form method="POST" action="{{ route('account.addresses.destroy', $address) }}"
                                      id="deleteAddressForm-{{ $address->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn-delete" onclick="confirmDeleteAddress({{ $address->id }})">
                                        <i class="fa-solid fa-trash me-1"></i> Sil
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach

                        <a href="{{ route('account.addresses.create') }}" class="btn-add-address">
                            <i class="fa-solid fa-plus"></i>
                            Yeni Adres Ekle
                        </a>
                    </div>
                    @else
                    <div class="empty-state">
                        <i class="fa-solid fa-map-location-dot"></i>
                        <h4>Henüz adresiniz yok</h4>
                        <p>Sipariş verebilmek için bir adres eklemeniz gerekiyor.</p>
                        <a href="{{ route('account.addresses.create') }}" class="btn-view">
                            <i class="fa-solid fa-plus me-2"></i>Adres Ekle
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
function confirmDeleteAddress(addressId) {
    window.showConfirmModal({
        title: 'Adres Silme',
        message: 'Bu adresi silmek istediğinize emin misiniz?',
        type: 'danger',
        confirmText: 'Evet, Sil',
        onConfirm: function () {
            document.getElementById('deleteAddressForm-' + addressId).submit();
        }
    });
}
</script>
@endpush
