@extends('layouts.app')

@section('title', __('site.two_factor.title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.two_factor.desc'))
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
                    <span class="section__eyebrow"><i class="fa-solid fa-shield-halved"></i> {{ __('site.auth.account') }}</span>
                    <h1 class="section__title">{{ __('site.two_factor.title') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.two_factor.lead') }}</p>

                    {{-- Kurulumdan hemen sonra bir kez gösterilen kurtarma kodları --}}
                    @if($recoveryCodes)
                        <div class="field-card mb-4">
                            <h2 class="h5 fw-bold mb-1">{{ __('site.two_factor.recovery_codes') }}</h2>
                            <p class="text-muted small mb-3">{{ __('site.two_factor.recovery_codes_hint') }}</p>

                            <ul class="recovery-codes">
                                @foreach($recoveryCodes as $code)
                                    <li class="recovery-codes__item">{{ $code }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="field-card">
                        @if($enabled)
                            <div class="d-flex align-items-start gap-3 flex-wrap mb-4">
                                <span class="device-list__icon"><i class="fa-solid fa-shield-check"></i></span>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ __('site.two_factor.status_on') }}</div>
                                    <div class="text-muted small">
                                        {{ __('site.two_factor.enabled_since', ['date' => $user->two_factor_confirmed_at?->translatedFormat('d M Y H:i')]) }}
                                    </div>
                                </div>
                                <span class="badge text-bg-success">{{ __('site.two_factor.active') }}</span>
                            </div>

                            @if($required)
                                <p class="text-muted small">{{ __('site.two_factor.required_notice') }}</p>
                            @endif

                            <hr class="divider my-4">

                            {{-- Kurtarma kodlarını yenile --}}
                            <h2 class="h6 fw-bold">{{ __('site.two_factor.regenerate') }}</h2>
                            <p class="text-muted small mb-3">{{ __('site.two_factor.regenerate_hint') }}</p>

                            <form action="{{ route('account.security.recovery-codes') }}" method="POST"
                                  class="row g-2 align-items-start mb-4" data-validate novalidate>
                                @csrf
                                <div class="col-sm-6">
                                    <label class="form-label" for="regeneratePassword">{{ __('site.account.current_password') }}</label>
                                    <input type="password" class="form-control" id="regeneratePassword" name="password"
                                           autocomplete="current-password"
                                           data-validation-engine="validate[required,maxSize[255]]">
                                </div>
                                <div class="col-sm-6 align-self-end">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fa-solid fa-rotate"></i> {{ __('site.two_factor.regenerate_btn') }}
                                    </button>
                                </div>
                            </form>

                            {{-- Kapat --}}
                            @unless($required)
                                <hr class="divider my-4">

                                <h2 class="h6 fw-bold">{{ __('site.two_factor.disable') }}</h2>
                                <p class="text-muted small mb-3">{{ __('site.two_factor.disable_hint') }}</p>

                                <form action="{{ route('account.security.two-factor.disable') }}" method="POST"
                                      class="row g-2 align-items-start" data-validate novalidate
                                      data-confirm="{{ __('site.two_factor.confirm_disable') }}"
                                      data-confirm-title="{{ __('site.two_factor.disable') }}"
                                      data-confirm-btn="{{ __('site.two_factor.disable_btn') }}">
                                    @csrf
                                    @method('DELETE')
                                    <div class="col-sm-6">
                                        <label class="form-label" for="disablePassword">{{ __('site.account.current_password') }}</label>
                                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                                               id="disablePassword" name="password" autocomplete="current-password"
                                               data-validation-engine="validate[required,maxSize[255]]">
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-sm-6 align-self-end">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="fa-solid fa-shield-slash"></i> {{ __('site.two_factor.disable_btn') }}
                                        </button>
                                    </div>
                                </form>
                            @endunless

                        @elseif($pending)
                            {{-- Kurulum yarıda: QR duruyor, ilk kod bekleniyor --}}
                            <h2 class="h5 fw-bold mb-1">{{ __('site.two_factor.setup') }}</h2>
                            <p class="text-muted small mb-4">{{ __('site.two_factor.setup_hint') }}</p>

                            <div class="row g-4 align-items-start">
                                <div class="col-md-auto">
                                    <div class="qr-frame">{!! $qrCodeSvg !!}</div>
                                </div>
                                <div class="col-md">
                                    <div class="contact-info__label">{{ __('site.two_factor.manual_key') }}</div>
                                    <p class="two-factor-secret mb-4">{{ $secret }}</p>

                                    <form action="{{ route('account.security.two-factor.confirm') }}" method="POST"
                                          class="row g-2 align-items-start" data-validate novalidate>
                                        @csrf
                                        <div class="col-sm-7">
                                            <label class="form-label" for="confirmCode">{{ __('site.two_factor.code_label') }}</label>
                                            <input type="text" class="form-control @error('code') is-invalid @enderror"
                                                   id="confirmCode" name="code" inputmode="numeric"
                                                   autocomplete="one-time-code" data-fv-mask="digits"
                                                   data-validation-engine="validate[required,custom[integer],minSize[6],maxSize[6]]">
                                            @error('code')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-sm-5 align-self-end">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fa-solid fa-check"></i> {{ __('site.two_factor.confirm_btn') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @else
                            {{-- Hiç kurulmamış --}}
                            <div class="d-flex align-items-start gap-3 flex-wrap mb-4">
                                <span class="device-list__icon"><i class="fa-solid fa-shield-halved"></i></span>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ __('site.two_factor.status_off') }}</div>
                                    <div class="text-muted small">{{ __('site.two_factor.status_off_hint') }}</div>
                                </div>
                            </div>

                            @if($required)
                                <p class="text-muted small">{{ __('site.two_factor.required_notice') }}</p>
                            @endif

                            <form action="{{ route('account.security.two-factor.enable') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-shield-halved"></i> {{ __('site.two_factor.enable_btn') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
