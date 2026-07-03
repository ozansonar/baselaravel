@extends('layouts.app')

@section('title', 'Şifremi Unuttum | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Şifrenizi mi unuttunuz? E-posta adresinizi girin, size şifre sıfırlama bağlantısı gönderelim.')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="login-section">
    <div class="container">
        <div class="login-wrapper">
            <div class="row g-0">

                {{-- Left: Info Panel --}}
                <div class="col-lg-6">
                    <div class="login-info-panel">
                        <div class="login-info-content">
                            <div class="login-info-icon">
                                <i class="fa-solid fa-key"></i>
                            </div>
                            <h1 class="login-info-title">Şifrenizi mi Unuttunuz?</h1>
                            <p class="login-info-subtitle">
                                Endişelenmeyin, birkaç adımda şifrenizi sıfırlayabilirsiniz.
                            </p>

                            <div class="login-info-features">
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-envelope-circle-check"></i>
                                    </div>
                                    <div>
                                        <strong>1. E-posta Girin</strong>
                                        <span>Kayıtlı e-posta adresinizi yazın</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-link"></i>
                                    </div>
                                    <div>
                                        <strong>2. Bağlantıyı Alın</strong>
                                        <span>Gelen kutunuza sıfırlama bağlantısı gönderilecek</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <div>
                                        <strong>3. Yeni Şifre Belirleyin</strong>
                                        <span>Güvenli yeni şifrenizi oluşturun</span>
                                    </div>
                                </div>
                            </div>

                            <div class="login-info-quote">
                                <i class="fa-solid fa-circle-info"></i>
                                <p>Sıfırlama bağlantısı 60 dakika geçerlidir. Gelen kutunuzda bulamazsanız spam klasörünü kontrol edin.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Form Panel --}}
                <div class="col-lg-6">
                    <div class="login-form-panel">
                        <div class="login-form-header">
                            <h2 class="login-form-title">Şifre Sıfırlama</h2>
                            <p class="login-form-subtitle">Kayıtlı e-posta adresinizi girin, size şifre sıfırlama bağlantısı gönderelim.</p>
                        </div>

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="login-field">
                                <label for="email" class="login-label">E-posta Adresi</label>
                                <div class="login-input-wrapper">
                                    <i class="fa-solid fa-envelope login-input-icon"></i>
                                    <input type="email"
                                           class="login-input @error('email') login-input--error @enderror"
                                           id="email" name="email" value="{{ old('email') }}"
                                           placeholder="ornek@mail.com" required autofocus>
                                </div>
                            </div>

                            <x-recaptcha />

                            <button type="submit" class="login-submit-btn">
                                <i class="fa-solid fa-paper-plane me-2"></i>Sıfırlama Bağlantısı Gönder
                            </button>
                        </form>

                        <div class="login-divider">
                            <span>veya</span>
                        </div>

                        <div class="forgot-password-links">
                            <p class="login-register-link">
                                Şifrenizi hatırladınız mı?
                                <a href="{{ route('login') }}">Giriş Yap</a>
                            </p>
                            @if(\App\Models\Setting::getValue('registration_enabled', '1') === '1')
                            <p class="login-register-link">
                                Hesabınız yok mu?
                                <a href="{{ route('register') }}">Ücretsiz Kayıt Ol</a>
                            </p>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    @if($errors->any())
    window.showResultModal('error', @json(implode('<br>', $errors->all())));
    @endif

    @if(session('success'))
    window.showResultModal('success', @json(session('success')));
    @endif
});
</script>
@endpush
