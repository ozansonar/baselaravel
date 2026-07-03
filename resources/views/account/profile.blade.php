@extends('layouts.app')

@section('title', 'Profil Düzenle | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Profil bilgilerinizi güncelleyin.')
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
            <span>Profil Düzenle</span>
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
                        <h3><i class="fa-solid fa-user-pen"></i> Profil Düzenle</h3>
                    </div>

                    <form method="POST" action="{{ route('account.profile.update') }}" class="profile-form" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        {{-- Avatar --}}
                        <div class="mb-4 text-center">
                            <div class="mb-3">
                                @if($user->avatar)
                                    <img src="{{ \App\Services\UploadService::url($user->avatar, 'thumb') }}"
                                         alt="{{ $user->full_name }}"
                                         class="rounded-circle account-avatar-lg" width="120" height="120" loading="lazy" decoding="async">
                                @else
                                    <div class="account-avatar-placeholder-lg rounded-circle mx-auto">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="d-flex justify-content-center gap-2">
                                <label for="avatar" class="btn-view mb-0">
                                    <i class="fa-solid fa-camera me-1"></i> Fotoğraf Değiştir
                                </label>
                                <input type="file" class="d-none" id="avatar" name="avatar" accept="image/*">
                                @if($user->avatar)
                                <label class="btn-view mb-0" >
                                    <input type="checkbox" name="remove_avatar" value="1" class="d-none" id="removeAvatar">
                                    <i class="fa-solid fa-trash me-1"></i> Kaldır
                                </label>
                                @endif
                            </div>
                            @error('avatar')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                           id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>
                                    @error('first_name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Soyad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                           id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required>
                                    @error('last_name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0555 123 45 67">
                            @error('phone')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <h5 class="text-green-dark mb-4 mt-4">
                            <i class="fa-solid fa-lock me-2"></i>Şifre Değiştir
                            <small class="text-muted fw-normal">(opsiyonel)</small>
                        </h5>

                        <div class="form-group">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password" placeholder="En az 8 karakter">
                            @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Yeni Şifre Tekrar</label>
                            <input type="password" class="form-control"
                                   id="password_confirmation" name="password_confirmation"
                                   placeholder="Şifrenizi tekrar girin">
                        </div>

                        <button type="submit" class="btn-save">
                            <i class="fa-solid fa-check me-2"></i>Değişiklikleri Kaydet
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
