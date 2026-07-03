@extends('layouts.app')

@section('title', 'İletişim | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', \App\Models\Setting::getValue('site_name', config('app.name')) . ' ile iletişime geçin. Sorularınız, önerileriniz ve talepleriniz için bize ulaşın.')
@section('canonical', route('contact'))

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container text-center">
            <h1 class="page-hero__title">İletişim</h1>
            <p class="page-hero__lead">Sorularınız, önerileriniz veya talepleriniz için bize ulaşın. Ekibimiz en kısa sürede size dönüş yapacaktır.</p>
        </div>
    </section>

    {{-- ══════════ CONTACT ══════════ --}}
    <section class="section">
        <div class="container">
            <div class="row g-5">

                {{-- Contact info --}}
                <div class="col-lg-5">
                    <span class="section__eyebrow"><i class="fa-solid fa-headset"></i> Bize Ulaşın</span>
                    <h2 class="section__title">İletişim Bilgileri</h2>
                    <p class="section__lead mb-4">Aşağıdaki kanallardan bize doğrudan erişebilir veya formu doldurarak mesaj gönderebilirsiniz.</p>

                    @if($address)
                        <div class="contact-info">
                            <div class="contact-info__icon"><i class="fa-solid fa-location-dot"></i></div>
                            <div>
                                <div class="contact-info__label">Adres</div>
                                <div class="contact-info__value">{{ $address }}</div>
                            </div>
                        </div>
                    @endif

                    @if($phone)
                        <div class="contact-info">
                            <div class="contact-info__icon"><i class="fa-solid fa-phone"></i></div>
                            <div>
                                <div class="contact-info__label">Telefon</div>
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
                                <div class="contact-info__label">E-posta</div>
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
                                <div class="contact-info__label">Çalışma Saatleri</div>
                                @if($workingHoursWeekday)
                                    <div class="contact-info__value">Pazartesi - Cuma: {{ $workingHoursWeekday }}</div>
                                @endif
                                @if($workingHoursSaturday)
                                    <div class="contact-info__value">Cumartesi: {{ $workingHoursSaturday }}</div>
                                @endif
                                @if($workingHoursSunday)
                                    <div class="contact-info__value">Pazar: {{ $workingHoursSunday }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Contact form --}}
                <div class="col-lg-7">
                    <div class="field-card">
                        <form action="{{ route('contact.store') }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="name">Adınız Soyadınız <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('name') is-invalid @enderror"
                                           id="name" name="name" value="{{ old('name') }}"
                                           placeholder="Adınız Soyadınız" required autocomplete="name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="email">E-posta <span class="text-brand">*</span></label>
                                    <input type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           id="email" name="email" value="{{ old('email') }}"
                                           placeholder="ornek@email.com" required autocomplete="email">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="phone">Telefon</label>
                                    <input type="tel"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           id="phone" name="phone" value="{{ old('phone') }}"
                                           placeholder="05XX XXX XX XX" autocomplete="tel">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="subject">Konu <span class="text-brand">*</span></label>
                                    <input type="text"
                                           class="form-control @error('subject') is-invalid @enderror"
                                           id="subject" name="subject" value="{{ old('subject') }}"
                                           placeholder="Mesajınızın konusu" required>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="message">Mesajınız <span class="text-brand">*</span></label>
                                    <textarea class="form-control @error('message') is-invalid @enderror"
                                              id="message" name="message" rows="5"
                                              placeholder="Mesajınızı buraya yazın..." required minlength="10">{{ old('message') }}</textarea>
                                    @error('message')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <x-recaptcha />
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fa-solid fa-paper-plane"></i> Mesaj Gönder
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
