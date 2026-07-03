@extends('layouts.app')

@section('title', 'Yeni Adres | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Yeni teslimat adresi ekleyin.')
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
            <a href="{{ route('account.addresses') }}">Adreslerim</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Yeni Adres</span>
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
                        <h3><i class="fa-solid fa-location-dot"></i> Yeni Adres Ekle</h3>
                        <a href="{{ route('account.addresses') }}" class="btn-view">
                            <i class="fa-solid fa-arrow-left me-1"></i> Geri
                        </a>
                    </div>

                    <form method="POST" action="{{ route('account.addresses.store') }}" class="profile-form">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Adres Başlığı <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror"
                                           id="title" name="title" value="{{ old('title') }}"
                                           placeholder="Ev, İş, vb." required>
                                    @error('title')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('full_name') is-invalid @enderror"
                                           id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                                    @error('full_name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" value="{{ old('phone') }}"
                                   placeholder="0555 123 45 67" required>
                            @error('phone')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">İl <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('city') is-invalid @enderror"
                                           id="city" name="city" value="{{ old('city') }}" required>
                                    @error('city')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">İlçe <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('district') is-invalid @enderror"
                                           id="district" name="district" value="{{ old('district') }}" required>
                                    @error('district')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Posta Kodu</label>
                            <input type="text" class="form-control @error('zip_code') is-invalid @enderror"
                                   id="zip_code" name="zip_code" value="{{ old('zip_code') }}">
                            @error('zip_code')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Adres <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('address_line') is-invalid @enderror"
                                      id="address_line" name="address_line" rows="3"
                                      placeholder="Mahalle, sokak, bina no, daire no" required>{{ old('address_line') }}</textarea>
                            @error('address_line')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check-custom mb-4">
                            <input type="checkbox" id="is_default" name="is_default" value="1"
                                   {{ old('is_default') ? 'checked' : '' }}>
                            <label for="is_default">Bu adresi varsayılan olarak ayarla</label>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn-save">
                                <i class="fa-solid fa-check me-2"></i>Adresi Kaydet
                            </button>
                            <a href="{{ route('account.addresses') }}" class="btn-view d-flex align-items-center">
                                İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
