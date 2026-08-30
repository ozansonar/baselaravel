@extends('layouts.app')

@section('title', __('site.auth.profile') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.account.profile_desc'))
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
                    <span class="section__eyebrow"><i class="fa-solid fa-user-pen"></i> {{ __('site.auth.profile') }}</span>
                    <h1 class="section__title">{{ __('site.account.edit_profile') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.account.profile_lead') }}</p>

                    <div class="field-card">
                        <form action="{{ route('account.profile.update') }}" method="POST" enctype="multipart/form-data" data-validate novalidate>
                            @csrf
                            @method('PUT')

                            {{-- Avatar --}}
                            <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                                @if($user->avatar)
                                    <img src="{{ upload_url($user->avatar) }}" alt="{{ image_alt($user->full_name) }}"
                                         class="avatar-lg" width="96" height="96" loading="lazy" decoding="async">
                                @else
                                    <div class="avatar-ph">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}</div>
                                @endif
                                <div class="flex-grow-1">
                                    <label class="form-label" for="avatar">{{ __('site.account.avatar') }}</label>
                                    <input type="file"
                                           class="form-control @error('avatar') is-invalid @enderror"
                                           id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp"
                                           data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]"
                                           data-max-size="1" data-accept="image/jpeg,image/png,image/webp">
                                    @error('avatar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($user->avatar)
                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input" id="remove_avatar" name="remove_avatar" value="1" data-fv-ignore>
                                            <label class="form-check-label" for="remove_avatar">{{ __('site.account.remove_avatar') }}</label>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="first_name">{{ __('site.account.first_name') }} <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('first_name') is-invalid @enderror"
                                           id="first_name" name="first_name"
                                           data-validation-engine="validate[required,custom[letters],maxSize[50]]"
                                           data-fv-mask="letters"
                                           value="{{ old('first_name', $user->first_name) }}">
                                    @error('first_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="last_name">{{ __('site.account.last_name') }} <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('last_name') is-invalid @enderror"
                                           id="last_name" name="last_name"
                                           data-validation-engine="validate[required,custom[letters],maxSize[50]]"
                                           data-fv-mask="letters"
                                           value="{{ old('last_name', $user->last_name) }}">
                                    @error('last_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="email">{{ __('site.account.email') }} <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('email') is-invalid @enderror"
                                           id="email" name="email"
                                           data-validation-engine="validate[required,custom[email],maxSize[191]]"
                                           value="{{ old('email', $user->email) }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="phone">{{ __('site.contact.phone') }}</label>
                                    {{-- Sunucu biçim dayatmıyor (nullable|string|max:20); custom[phone]
                                         konsaydı istemci sunucudan dar olur, dahili numara yazan
                                         kullanıcı profilini kaydedemezdi. --}}
                                    <input type="text"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           id="phone" name="phone"
                                           data-validation-engine="validate[custom[phone],maxSize[20]]" data-fv-mask="phone"
                                           value="{{ old('phone', $user->phone) }}" placeholder="{{ __('site.account.phone_ph') }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <h2 class="section__title h5 mb-1"><i class="fa-solid fa-lock text-brand me-2"></i>{{ __('site.account.change_password') }}</h2>
                            <p class="text-muted small mb-3">{{ __('site.account.password_hint') }}</p>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="current_password">{{ __('site.account.current_password') }}</label>
                                    <input type="password"
                                           class="form-control @error('current_password') is-invalid @enderror"
                                           id="current_password" name="current_password"
                                           data-validation-engine="validate[funcCallRequired[FormValidation.rules.requiredWithPassword]]"
                                           data-fv-required-with="password"
                                           placeholder="{{ __('site.account.current_password_ph') }}" autocomplete="current-password">
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password">{{ __('site.account.new_password') }}</label>
                                    <input type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           id="password" name="password"
                                           data-validation-engine="validate[minSize[8]]"
                                           placeholder="{{ __('site.account.new_password_ph') }}" autocomplete="new-password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">{{ __('site.account.repeat_password') }}</label>
                                    <input type="password"
                                           class="form-control"
                                           id="password_confirmation" name="password_confirmation"
                                           data-validation-engine="validate[equals[password]]"
                                           placeholder="{{ __('site.account.repeat_password_ph') }}" autocomplete="new-password">
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-check"></i> {{ __('site.account.save_changes') }}
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
