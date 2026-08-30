@extends('layouts.app')

@section('title', __('site.nav.contact') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.contact.meta_desc', ['site' => \App\Models\Setting::getValue('site_name', config('app.name'))]))
@section('canonical', localized_route('contact'))

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container text-center">
            <h1 class="page-hero__title">{{ __('site.nav.contact') }}</h1>
            <p class="page-hero__lead">{{ __('site.contact.hero_lead') }}</p>
        </div>
    </section>

    {{-- ══════════ CONTACT ══════════ --}}
    <section class="section">
        <div class="container">
            <div class="row g-5">

                {{-- Contact info --}}
                <div class="col-lg-5">
                    <span class="section__eyebrow"><i class="fa-solid fa-headset"></i> {{ __('site.actions.contact_us') }}</span>
                    <h2 class="section__title">{{ __('site.contact.info') }}</h2>
                    <p class="section__lead mb-4">{{ __('site.contact.lead') }}</p>

                    @if($address)
                        <div class="contact-info">
                            <div class="contact-info__icon"><i class="fa-solid fa-location-dot"></i></div>
                            <div>
                                <div class="contact-info__label">{{ __('site.contact.address') }}</div>
                                <div class="contact-info__value">{{ $address }}</div>
                            </div>
                        </div>
                    @endif

                    @if($phone)
                        <div class="contact-info">
                            <div class="contact-info__icon"><i class="fa-solid fa-phone"></i></div>
                            <div>
                                <div class="contact-info__label">{{ __('site.contact.phone') }}</div>
                                <div class="contact-info__value">
                                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($email)
                        <div class="contact-info">
                            <div class="contact-info__icon"><i class="fa-solid fa-envelope"></i></div>
                            <div>
                                <div class="contact-info__label">{{ __('site.contact.email') }}</div>
                                <div class="contact-info__value">
                                    <a href="mailto:{{ $email }}">{{ $email }}</a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($workingHoursWeekday || $workingHoursSaturday || $workingHoursSunday)
                        <div class="contact-info">
                            <div class="contact-info__icon"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <div class="contact-info__label">{{ __('site.contact.working_hours') }}</div>
                                @if($workingHoursWeekday)
                                    <div class="contact-info__value">{{ __('site.contact.weekdays') }}: {{ $workingHoursWeekday }}</div>
                                @endif
                                @if($workingHoursSaturday)
                                    <div class="contact-info__value">{{ __('site.contact.saturday') }}: {{ $workingHoursSaturday }}</div>
                                @endif
                                @if($workingHoursSunday)
                                    <div class="contact-info__value">{{ __('site.contact.sunday') }}: {{ $workingHoursSunday }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Contact form --}}
                <div class="col-lg-7">
                    <div class="field-card">
                        <form action="{{ route('contact.store') }}" method="POST" data-validate novalidate>
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="name">{{ __('site.contact.name') }} <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('name') is-invalid @enderror"
                                           id="name" name="name" value="{{ old('name') }}"
                                           data-validation-engine="validate[required,custom[letters],minSize[2],maxSize[191]]" data-fv-mask="letters"
                                           placeholder="{{ __('site.contact.name') }}" autocomplete="name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="email">{{ __('site.contact.email') }} <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('email') is-invalid @enderror"
                                           id="email" name="email" value="{{ old('email') }}"
                                           data-validation-engine="validate[required,custom[email],maxSize[191]]"
                                           placeholder="{{ __('site.contact.email_ph') }}" autocomplete="email">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="phone">{{ __('site.contact.phone') }}</label>
                                    {{-- Sunucu bu alanda biçim dayatmıyor (nullable|string|max:20),
                                         bu yüzden custom[phone] yok: istemcinin sunucudan dar olması
                                         dahili numara yazan kullanıcıyı formdan atardı. --}}
                                    <input type="text"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           id="phone" name="phone" value="{{ old('phone') }}"
                                           data-validation-engine="validate[custom[phone],maxSize[20]]" data-fv-mask="phone"
                                           placeholder="{{ __('site.contact.phone_ph') }}" autocomplete="tel">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="subject">{{ __('site.contact.subject') }} <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('subject') is-invalid @enderror"
                                           id="subject" name="subject" value="{{ old('subject') }}"
                                           data-validation-engine="validate[required,maxSize[191]]"
                                           placeholder="{{ __('site.contact.subject_ph') }}">
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="message">{{ __('site.contact.message') }} <span class="text-brand">*</span></label>
                                    <textarea class="form-control @error('message') is-invalid @enderror"
                                              id="message" name="message" rows="5"
                                              data-validation-engine="validate[required,minSize[10],maxSize[5000]]"
                                              placeholder="{{ __('site.contact.message_ph') }}">{{ old('message') }}</textarea>
                                    @error('message')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <x-recaptcha />
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fa-solid fa-paper-plane"></i> {{ __('site.contact.send') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
