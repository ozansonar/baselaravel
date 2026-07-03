@extends('layouts.app')

@section('title', 'Giriş Yap | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', \App\Models\Setting::getValue('site_name', config('app.name')) . ' üye girişi. Hesabınıza giriş yaparak siparişlerinizi takip edin.')
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
                                <i class="fa-solid fa-leaf"></i>
                            </div>
                            <h1 class="login-info-title">Hoş Geldiniz</h1>
                            <p class="login-info-subtitle">
                                Çiftliğimizin doğal lezzetlerine ulaşmak için giriş yapın.
                            </p>

                            <div class="login-info-features">
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-basket-shopping"></i>
                                    </div>
                                    <div>
                                        <strong>Kolay Sipariş</strong>
                                        <span>Ürünlerimizi hızlıca sepetinize ekleyin</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-truck-fast"></i>
                                    </div>
                                    <div>
                                        <strong>Sipariş Takibi</strong>
                                        <span>Siparişlerinizi anlık olarak takip edin</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-heart"></i>
                                    </div>
                                    <div>
                                        <strong>Favori Listeniz</strong>
                                        <span>Beğendiğiniz ürünleri kaydedin</span>
                                    </div>
                                </div>
                            </div>

                            <div class="login-info-quote">
                                <i class="fa-solid fa-quote-left"></i>
                                <p>Doğanın sunduğu en taze ve doğal ürünler, çiftliğimizden sofranıza.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Login Form --}}
                <div class="col-lg-6">
                    <div class="login-form-panel">
                        <div class="login-form-header">
                            <h2 class="login-form-title">Giriş Yap</h2>
                            <p class="login-form-subtitle">Hesabınıza giriş yaparak alışverişe başlayın</p>
                        </div>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="login-field">
                                <label for="email" class="login-label">E-posta Adresi</label>
                                <div class="login-input-wrapper">
                                    <i class="fa-solid fa-envelope login-input-icon"></i>
                                    <input type="email"
                                           class="login-input @error('email') login-input--error @enderror"
                                           id="email" name="email" value="{{ old('email') }}"
                                           placeholder="ornek@mail.com" required autofocus
                                           autocomplete="email">
                                </div>
                            </div>

                            <div class="login-field">
                                <label for="password" class="login-label">Şifre</label>
                                <div class="login-input-wrapper">
                                    <i class="fa-solid fa-lock login-input-icon"></i>
                                    <input type="password"
                                           class="login-input @error('password') login-input--error @enderror"
                                           id="password" name="password"
                                           placeholder="••••••••" required
                                           autocomplete="current-password">
                                    <button type="button" class="login-toggle-password" aria-label="Şifreyi göster">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="login-options">
                                <div class="login-remember">
                                    <input type="checkbox" class="login-checkbox" id="remember"
                                           name="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label for="remember">Beni hatırla</label>
                                </div>
                                <a href="{{ route('password.request') }}" class="login-forgot">Şifremi unuttum</a>
                            </div>

                            <x-recaptcha />

                            <button type="submit" class="login-submit-btn">
                                <i class="fa-solid fa-right-to-bracket me-2"></i>Giriş Yap
                            </button>
                        </form>

                        @if(\App\Models\Setting::getValue('registration_enabled', '1') === '1')
                        <div class="login-divider">
                            <span>veya</span>
                        </div>

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
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Toggle password visibility
    var toggleBtn = document.querySelector('.login-toggle-password');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            var input = document.getElementById('password');
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    }

    // Show validation errors via global modal
    @if($errors->any())
    window.showResultModal('error', @json(implode('<br>', $errors->all())));
    @endif

    // Show success messages via global modal
    @if(session('success'))
    window.showResultModal('success', @json(session('success')));
    @endif
});
</script>
@endpush
