@extends('layouts.app')

@section('title', 'Kayıt Ol | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', \App\Models\Setting::getValue('site_name', config('app.name')) . ' üye olun. Doğal köy ürünlerini sipariş edin, kampanyalardan haberdar olun.')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="login-section">
    <div class="container">
        <div class="login-wrapper register-wrapper">
            <div class="row g-0">

                {{-- Left: Info Panel --}}
                <div class="col-lg-5">
                    <div class="login-info-panel">
                        <div class="login-info-content">
                            <div class="login-info-icon">
                                <i class="fa-solid fa-seedling"></i>
                            </div>
                            <h1 class="login-info-title">Ailemize Katılın</h1>
                            <p class="login-info-subtitle">
                                Ücretsiz hesap oluşturun, çiftliğimizin doğal lezzetlerini keşfedin.
                            </p>

                            <div class="login-info-features">
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-percent"></i>
                                    </div>
                                    <div>
                                        <strong>Özel İndirimler</strong>
                                        <span>Üyelere özel kampanya ve fırsatlar</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-bell"></i>
                                    </div>
                                    <div>
                                        <strong>Taze Ürün Bildirimi</strong>
                                        <span>Yeni ürünlerden ilk siz haberdar olun</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </div>
                                    <div>
                                        <strong>Sipariş Geçmişi</strong>
                                        <span>Tüm siparişlerinizi kolayca takip edin</span>
                                    </div>
                                </div>
                                <div class="login-info-feature">
                                    <div class="login-info-feature-icon">
                                        <i class="fa-solid fa-star"></i>
                                    </div>
                                    <div>
                                        <strong>Değerlendirme Yapın</strong>
                                        <span>Ürünleri puanlayın, deneyiminizi paylaşın</span>
                                    </div>
                                </div>
                            </div>

                            <div class="login-info-quote">
                                <i class="fa-solid fa-quote-left"></i>
                                <p>Binlerce mutlu müşterimize siz de katılın, doğanın tadını sofranıza taşıyın.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Register Form --}}
                <div class="col-lg-7">
                    <div class="login-form-panel">
                        <div class="login-form-header">
                            <h2 class="login-form-title">Hesap Oluştur</h2>
                            <p class="login-form-subtitle">Bilgilerinizi girerek hemen ücretsiz üye olun</p>
                        </div>

                        <form method="POST" action="{{ route('register') }}" id="registerForm" novalidate>
                            @csrf

                            <div class="register-form-grid">
                                <div class="login-field">
                                    <label for="first_name" class="login-label">Ad <span class="login-label-required">*</span></label>
                                    <div class="login-input-wrapper">
                                        <i class="fa-solid fa-user login-input-icon"></i>
                                        <input type="text"
                                               class="login-input @error('first_name') login-input--error @enderror"
                                               id="first_name" name="first_name" value="{{ old('first_name') }}"
                                               placeholder="Adınız"
                                               data-validate="required|name|min:2|max:50"
                                               data-label="Ad"
                                               autofocus
                                               autocomplete="given-name">
                                    </div>
                                    <div class="login-field-feedback" id="first_name-feedback"></div>
                                </div>

                                <div class="login-field">
                                    <label for="last_name" class="login-label">Soyad <span class="login-label-required">*</span></label>
                                    <div class="login-input-wrapper">
                                        <i class="fa-solid fa-user login-input-icon"></i>
                                        <input type="text"
                                               class="login-input @error('last_name') login-input--error @enderror"
                                               id="last_name" name="last_name" value="{{ old('last_name') }}"
                                               placeholder="Soyadınız"
                                               data-validate="required|name|min:2|max:50"
                                               data-label="Soyad"
                                               autocomplete="family-name">
                                    </div>
                                    <div class="login-field-feedback" id="last_name-feedback"></div>
                                </div>

                                <div class="login-field">
                                    <label for="email" class="login-label">E-posta Adresi <span class="login-label-required">*</span></label>
                                    <div class="login-input-wrapper">
                                        <i class="fa-solid fa-envelope login-input-icon"></i>
                                        <input type="email"
                                               class="login-input @error('email') login-input--error @enderror"
                                               id="email" name="email" value="{{ old('email') }}"
                                               placeholder="ornek@mail.com"
                                               data-validate="required|email"
                                               data-label="E-posta"
                                               autocomplete="email">
                                    </div>
                                    <div class="login-field-feedback" id="email-feedback"></div>
                                </div>

                                <div class="login-field">
                                    <label for="phone" class="login-label">Telefon <span class="login-label-optional">(Opsiyonel)</span></label>
                                    <div class="login-input-wrapper">
                                        <i class="fa-solid fa-phone login-input-icon"></i>
                                        <input type="tel"
                                               class="login-input @error('phone') login-input--error @enderror"
                                               id="phone" name="phone" value="{{ old('phone') }}"
                                               placeholder="05XX XXX XX XX"
                                               data-validate="phone"
                                               data-label="Telefon"
                                               data-mask="phone"
                                               autocomplete="tel">
                                    </div>
                                    <div class="login-field-feedback" id="phone-feedback"></div>
                                </div>

                                <div class="login-field">
                                    <label for="password" class="login-label">Şifre <span class="login-label-required">*</span></label>
                                    <div class="login-input-wrapper">
                                        <i class="fa-solid fa-lock login-input-icon"></i>
                                        <input type="password"
                                               class="login-input @error('password') login-input--error @enderror"
                                               id="password" name="password"
                                               placeholder="En az 8 karakter"
                                               data-validate="required|min:8"
                                               data-label="Şifre"
                                               autocomplete="new-password">
                                        <button type="button" class="login-toggle-password" data-target="password" aria-label="Şifreyi göster">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength">
                                        <div class="password-strength-bar">
                                            <div class="password-strength-fill" id="passwordStrengthFill"></div>
                                        </div>
                                        <span class="password-strength-text" id="passwordStrengthText"></span>
                                    </div>
                                    <div class="login-field-feedback" id="password-feedback"></div>
                                </div>

                                <div class="login-field">
                                    <label for="password_confirmation" class="login-label">Şifre Tekrar <span class="login-label-required">*</span></label>
                                    <div class="login-input-wrapper">
                                        <i class="fa-solid fa-shield-halved login-input-icon"></i>
                                        <input type="password"
                                               class="login-input"
                                               id="password_confirmation" name="password_confirmation"
                                               placeholder="Şifrenizi tekrar girin"
                                               data-validate="required|match:password"
                                               data-label="Şifre Tekrar"
                                               autocomplete="new-password">
                                        <button type="button" class="login-toggle-password" data-target="password_confirmation" aria-label="Şifreyi göster">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="login-field-feedback" id="password_confirmation-feedback">
                                    </div>
                                </div>
                            </div>

                            <x-recaptcha />

                            <button type="submit" class="login-submit-btn" id="registerSubmitBtn">
                                <i class="fa-solid fa-user-plus me-2"></i>Kayıt Ol
                            </button>
                        </form>

                        <div class="login-divider">
                            <span>veya</span>
                        </div>

                        <p class="login-register-link">
                            Zaten hesabınız var mı?
                            <a href="{{ route('login') }}">Giriş Yap</a>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ versioned_asset('js/register.js') }}"></script>
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
