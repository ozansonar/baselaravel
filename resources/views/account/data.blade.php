@extends('layouts.app')

@section('title', __('site.data.title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.data.desc'))
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
                    <span class="section__eyebrow"><i class="fa-solid fa-file-shield"></i> {{ __('site.auth.account') }}</span>
                    <h1 class="section__title">{{ __('site.data.title') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.data.lead') }}</p>

                    {{-- Verilerimi indir --}}
                    <div class="field-card mb-4">
                        <h2 class="h5 fw-bold mb-1">{{ __('site.data.download') }}</h2>
                        <p class="text-muted small mb-4">{{ __('site.data.download_hint') }}</p>

                        <a href="{{ route('account.data.download') }}" class="btn btn-outline-primary">
                            <i class="fa-solid fa-download"></i> {{ __('site.data.download_btn') }}
                        </a>
                    </div>

                    {{-- Hesabı kapat --}}
                    <div class="field-card field-card--danger">
                        <h2 class="h5 fw-bold mb-1">{{ __('site.data.close') }}</h2>
                        <p class="text-muted small mb-3">{{ __('site.data.close_hint') }}</p>

                        <ul class="text-muted small mb-4">
                            <li>{{ __('site.data.close_effect_login') }}</li>
                            <li>{{ __('site.data.close_effect_sessions') }}</li>
                            <li>{{ __('site.data.close_effect_email') }}</li>
                        </ul>

                        <form action="{{ route('account.data.close') }}" method="POST"
                              class="row g-2 align-items-start" data-validate novalidate
                              data-confirm="{{ __('site.data.confirm_close') }}"
                              data-confirm-title="{{ __('site.data.close') }}"
                              data-confirm-btn="{{ __('site.data.close_btn') }}">
                            @csrf
                            @method('DELETE')
                            <div class="col-sm-6">
                                <label class="form-label" for="closePassword">{{ __('site.account.current_password') }}</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                       id="closePassword" name="password" autocomplete="current-password"
                                       data-validation-engine="validate[required,maxSize[255]]">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6 align-self-end">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fa-solid fa-user-slash"></i> {{ __('site.data.close_btn') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
