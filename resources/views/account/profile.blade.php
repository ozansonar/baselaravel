@extends('layouts.app')

@section('title', 'Profil | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Profil bilgilerinizi güncelleyin.')
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="row g-4">

                {{-- Sidebar --}}
                <div class="col-lg-3">
                    @include('account.partials.sidebar', ['user' => $user])
                </div>

                {{-- Content --}}
                <div class="col-lg-9">
                    <span class="section__eyebrow"><i class="fa-solid fa-user-pen"></i> Profil</span>
                    <h1 class="section__title">Profili Düzenle</h1>
                    <p class="section__lead mb-4">Kişisel bilgilerinizi ve şifrenizi bu formdan güncelleyebilirsiniz.</p>

                    <div class="field-card">
                        <form action="{{ route('account.profile.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            {{-- Avatar --}}
                            <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                                @if($user->avatar)
                                    <img src="{{ upload_url($user->avatar) }}" alt="{{ $user->full_name }}"
                                         class="avatar-lg" width="96" height="96" loading="lazy" decoding="async">
                                @else
                                    <div class="avatar-ph">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}</div>
                                @endif
                                <div class="flex-grow-1">
                                    <label class="form-label" for="avatar">Profil Fotoğrafı</label>
                                    <input type="file"
                                           class="form-control @error('avatar') is-invalid @enderror"
                                           id="avatar" name="avatar" accept="image/*">
                                    @error('avatar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($user->avatar)
                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input" id="remove_avatar" name="remove_avatar" value="1">
                                            <label class="form-check-label" for="remove_avatar">Fotoğrafı kaldır</label>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="first_name">Ad <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('first_name') is-invalid @enderror"
                                           id="first_name" name="first_name"
                                           value="{{ old('first_name', $user->first_name) }}" required>
                                    @error('first_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="last_name">Soyad <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('last_name') is-invalid @enderror"
                                           id="last_name" name="last_name"
                                           value="{{ old('last_name', $user->last_name) }}" required>
                                    @error('last_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="email">E-posta <span class="text-brand">*</span></label>
                                    <input type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           id="email" name="email"
                                           value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="phone">Telefon</label>
                                    <input type="tel"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           id="phone" name="phone"
                                           value="{{ old('phone', $user->phone) }}" placeholder="05XX XXX XX XX">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <h2 class="section__title h5 mb-1"><i class="fa-solid fa-lock text-brand me-2"></i>Şifre Değiştir</h2>
                            <p class="text-muted small mb-3">Boş bırakırsanız değişmez.</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Yeni Şifre</label>
                                    <input type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           id="password" name="password" placeholder="En az 8 karakter" autocomplete="new-password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">Yeni Şifre (Tekrar)</label>
                                    <input type="password"
                                           class="form-control"
                                           id="password_confirmation" name="password_confirmation"
                                           placeholder="Şifrenizi tekrar girin" autocomplete="new-password">
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-check"></i> Değişiklikleri Kaydet
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
