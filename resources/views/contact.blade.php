@extends('layouts.app')

@section('title', 'İletişim | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', \App\Models\Setting::getValue('site_name', config('app.name')) . ' ile iletişime geçin. Sipariş, ürün bilgisi ve diğer sorularınız için bize ulaşın.')
@section('canonical', route('contact'))
@if(\App\Models\Setting::getValue('og_image'))
@section('og_image', url(upload_url(\App\Models\Setting::getValue('og_image'))))
@endif

@push('json-ld')
@php
    $contactSiteName = \App\Models\Setting::getValue('site_name', config('app.name'));
    $contactOgImage = \App\Models\Setting::getValue('og_image');

    $localBusinessJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $contactSiteName,
        'url' => url('/'),
        'description' => \App\Models\Setting::getValue('site_description', ''),
    ];
    if ($phone) {
        $localBusinessJsonLd['telephone'] = $phone;
    }
    if ($email) {
        $localBusinessJsonLd['email'] = $email;
    }
    if ($address) {
        $localBusinessJsonLd['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressCountry' => 'TR',
        ];
    }
    if ($contactOgImage) {
        $localBusinessJsonLd['image'] = url(upload_url($contactOgImage));
    }
    $localBusinessJsonLd['openingHoursSpecification'] = [
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => Str::before($workingHoursWeekday, ' - '),
            'closes' => Str::after($workingHoursWeekday, ' - '),
        ],
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Saturday',
            'opens' => Str::before($workingHoursSaturday, ' - '),
            'closes' => Str::after($workingHoursSaturday, ' - '),
        ],
    ];
    $localBusinessJsonLd['breadcrumb'] = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'İletişim'],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($localBusinessJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="page-header-content">
            <nav class="breadcrumb-custom animate-fade-up" aria-label="Breadcrumb">
                <a href="{{ route('home') }}"><i class="fa-solid fa-home"></i> Ana Sayfa</a>
                <i class="fa-solid fa-chevron-right"></i>
                <span>İletişim</span>
            </nav>
            <h1 class="page-title animate-fade-up delay-1">Bize Ulaşın</h1>
            <p class="page-subtitle animate-fade-up delay-2">Sorularınız için her zaman yanınızdayız</p>
        </div>
    </div>
</header>

{{-- Contact Section --}}
<section class="contact-section">
    <div class="container">

        {{-- Contact Info Cards --}}
        <address class="contact-info-cards animate-fade-up">
            @if($address)
            <div class="info-card">
                <div class="info-card-icon">
                    <i class="fa-solid fa-map-marker-alt"></i>
                </div>
                <h4>Adresimiz</h4>
                <p>{{ $address }}</p>
            </div>
            @endif

            @if($phone)
            <div class="info-card">
                <div class="info-card-icon">
                    <i class="fa-solid fa-phone-alt"></i>
                </div>
                <h4>Telefon</h4>
                <p><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ format_phone($phone) }}</a></p>
            </div>
            @endif

            @if($email)
            <div class="info-card">
                <div class="info-card-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <h4>E-posta</h4>
                <p><a href="mailto:{{ $email }}">{{ $email }}</a></p>
            </div>
            @endif

            @php
                $whatsappUrl = \App\Models\Setting::getValue('social_whatsapp');
            @endphp
            @if($whatsappUrl)
            <div class="info-card">
                <div class="info-card-icon">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <h4>WhatsApp</h4>
                <p><a href="{{ whatsapp_url($whatsappUrl) }}" target="_blank" rel="noopener noreferrer">{{ format_phone($whatsappUrl) }}</a></p>
            </div>
            @endif
        </address>

        <div class="row g-4">
            {{-- Contact Form --}}
            <div class="col-lg-7">
                <div class="contact-form-container animate-fade-up delay-1">
                    <div class="form-header">
                        <h2>Mesaj Gönderin</h2>
                        <p>Sorularınızı, önerilerinizi veya siparişlerinizi bize iletin</p>
                    </div>

                    <form action="{{ route('contact.store') }}" method="POST" id="contactForm">
                        @csrf
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Adınız Soyadınız <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name"
                                       value="{{ old('name') }}"
                                       placeholder="Adınız Soyadınız" required
                                       autocomplete="name"
                                       aria-describedby="@error('name') nameError @enderror">
                                @error('name')
                                <div class="invalid-feedback" id="nameError">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">E-posta <span class="text-danger">*</span></label>
                                <input type="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       id="email" name="email"
                                       value="{{ old('email') }}"
                                       placeholder="ornek@email.com" required
                                       autocomplete="email"
                                       aria-describedby="@error('email') emailError @enderror">
                                @error('email')
                                <div class="invalid-feedback" id="emailError">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Telefon</label>
                                <input type="tel"
                                       class="form-control @error('phone') is-invalid @enderror"
                                       id="phone" name="phone"
                                       value="{{ old('phone') }}"
                                       placeholder="05XX XXX XX XX"
                                       autocomplete="tel"
                                       aria-describedby="@error('phone') phoneError @enderror">
                                @error('phone')
                                <div class="invalid-feedback" id="phoneError">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="subject">Konu <span class="text-danger">*</span></label>
                                <select class="form-select @error('subject') is-invalid @enderror"
                                        id="subject" name="subject" required>
                                    <option value="">Konu Seçin</option>
                                    <option value="Sipariş" {{ old('subject') === 'Sipariş' ? 'selected' : '' }}>Sipariş Hakkında</option>
                                    <option value="Ürün Bilgisi" {{ old('subject') === 'Ürün Bilgisi' ? 'selected' : '' }}>Ürün Bilgisi</option>
                                    <option value="Kargo" {{ old('subject') === 'Kargo' ? 'selected' : '' }}>Kargo &amp; Teslimat</option>
                                    <option value="İade" {{ old('subject') === 'İade' ? 'selected' : '' }}>İade &amp; Değişim</option>
                                    <option value="İş Birliği" {{ old('subject') === 'İş Birliği' ? 'selected' : '' }}>İş Birliği</option>
                                    <option value="Diğer" {{ old('subject') === 'Diğer' ? 'selected' : '' }}>Diğer</option>
                                </select>
                                @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Mesajınız <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('message') is-invalid @enderror"
                                          id="message" name="message"
                                          placeholder="Mesajınızı buraya yazın..." required>{{ old('message') }}</textarea>
                                @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <x-recaptcha />
                            </div>
                            <div class="col-12 text-center">
                                <button type="submit" class="btn-submit">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Mesaj Gönder
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Map & Hours --}}
            <div class="col-lg-5">
                @php
                    $mapEmbed = \App\Models\Setting::getValue('contact_map_embed');
                @endphp
                <div class="map-container animate-fade-up delay-2">
                    <div class="map-header">
                        <h3><i class="fa-solid fa-map-marked-alt me-2"></i>Konumumuz</h3>
                        <p>Doğanın kalbinde, çiftliğimizde</p>
                    </div>
                    @if($mapEmbed)
                    @php
                        $mapSrc = '';
                        if (preg_match('/src=["\']([^"\']+)["\']/', $mapEmbed, $m)) {
                            $mapSrc = $m[1];
                        }
                    @endphp
                    @if($mapSrc)
                    <iframe src="{{ $mapSrc }}"
                            width="100%" height="400"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                            allowfullscreen
                            title="Harita"></iframe>
                    @endif
                    @else
                    <div class="map-placeholder">
                        <i class="fa-solid fa-map-marked-alt"></i>
                        <p>Harita yakında eklenecek</p>
                    </div>
                    @endif
                </div>

                <div class="working-hours animate-fade-up delay-3">
                    <h4><i class="fa-solid fa-clock"></i> Çalışma Saatleri</h4>
                    <ul class="hours-list">
                        <li>
                            <span class="day">Pazartesi - Cuma</span>
                            <span class="time">{{ $workingHoursWeekday }}</span>
                        </li>
                        <li>
                            <span class="day">Cumartesi</span>
                            <span class="time">{{ $workingHoursSaturday }}</span>
                        </li>
                        <li>
                            <span class="day">Pazar</span>
                            <span class="time {{ strtolower($workingHoursSunday) === 'kapalı' ? 'closed' : '' }}">{{ $workingHoursSunday }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAQ Section --}}
<section class="contact-faq-section">
    <div class="container">
        <div class="contact-faq-header">
            <h2>Sıkça Sorulan Sorular</h2>
            <p>Merak ettiğiniz soruların cevapları burada</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="accordion" id="contactFaqAccordion">
                    <div class="accordion-item contact-faq-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button contact-faq-btn" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#contactFaq1">
                                Siparişlerim ne kadar sürede teslim edilir?
                            </button>
                        </h3>
                        <div id="contactFaq1" class="accordion-collapse collapse show"
                             data-bs-parent="#contactFaqAccordion">
                            <div class="accordion-body">
                                Siparişleriniz genellikle 1-3 iş günü içinde hazırlanır ve kargoya verilir. Teslimat süresi bulunduğunuz bölgeye göre 1-5 iş günü arasında değişmektedir. Taze ürünlerimiz soğuk zincir kargo ile gönderilmektedir.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item contact-faq-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button contact-faq-btn collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#contactFaq2">
                                Ürünleriniz nasıl muhafaza edilmeli?
                            </button>
                        </h3>
                        <div id="contactFaq2" class="accordion-collapse collapse"
                             data-bs-parent="#contactFaqAccordion">
                            <div class="accordion-body">
                                Süt ürünlerimiz (peynir, yoğurt, tereyağı) buzdolabında +4°C'de muhafaza edilmelidir. Bal ve reçellerimiz serin ve karanlık bir ortamda saklanmalıdır. Her ürünün ambalajında detaylı saklama koşulları belirtilmiştir.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item contact-faq-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button contact-faq-btn collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#contactFaq3">
                                İade ve değişim koşulları nelerdir?
                            </button>
                        </h3>
                        <div id="contactFaq3" class="accordion-collapse collapse"
                             data-bs-parent="#contactFaqAccordion">
                            <div class="accordion-body">
                                Gıda ürünlerinde hijyen kuralları gereği iade kabul edilmemektedir. Ancak hasarlı veya bozuk gelen ürünler için teslimat tarihinden itibaren 24 saat içinde fotoğraflı bildirim yapmanız halinde ürün değişimi veya iadesi yapılmaktadır.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item contact-faq-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button contact-faq-btn collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#contactFaq4">
                                Minimum sipariş tutarı var mı?
                            </button>
                        </h3>
                        <div id="contactFaq4" class="accordion-collapse collapse"
                             data-bs-parent="#contactFaqAccordion">
                            <div class="accordion-body">
                                Minimum sipariş tutarımız {{ number_format((float) \App\Models\Setting::getValue('min_order_amount', '100'), 0, ',', '.') }}₺'dir. {{ number_format((float) \App\Models\Setting::getValue('shipping_free_limit', '500'), 0, ',', '.') }}₺ ve üzeri siparişlerinizde kargo ücretsizdir. Bu tutarın altındaki siparişlerde {{ number_format((float) \App\Models\Setting::getValue('shipping_fee', '39.90'), 2, ',', '.') }}₺ kargo ücreti uygulanmaktadır.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item contact-faq-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button contact-faq-btn collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#contactFaq5">
                                Toplu sipariş verebilir miyim?
                            </button>
                        </h3>
                        <div id="contactFaq5" class="accordion-collapse collapse"
                             data-bs-parent="#contactFaqAccordion">
                            <div class="accordion-body">
                                Evet, restoranlar, oteller, marketler ve kurumsal müşterilerimiz için toplu sipariş hizmeti sunuyoruz. Toplu siparişlerde özel fiyatlandırma ve düzenli teslimat imkanı sağlıyoruz. Detaylı bilgi için bizimle iletişime geçebilirsiniz.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
    /* Contact Section */
    .contact-section {
        padding: 60px 0 100px;
    }

    /* Contact Info Cards */
    .contact-info-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 60px;
    }

    .info-card {
        background: white;
        border-radius: 25px;
        padding: 35px 25px;
        text-align: center;
        box-shadow: var(--shadow-soft);
        transition: all 0.4s ease;
    }

    .info-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-hover);
    }

    .info-card-icon {
        width: 80px;
        height: 80px;
        background: var(--green-mist);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        transition: all 0.4s ease;
    }

    .info-card:hover .info-card-icon {
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
    }

    .info-card-icon i {
        font-size: 2rem;
        color: var(--green-primary);
        transition: all 0.4s ease;
    }

    .info-card:hover .info-card-icon i {
        color: white;
    }

    .info-card h4 {
        font-size: 1.2rem;
        color: var(--green-dark);
        margin-bottom: 10px;
    }

    .info-card p {
        color: var(--brown-light);
        margin: 0;
        line-height: 1.6;
    }

    .info-card a {
        color: var(--green-primary);
        text-decoration: none;
        font-weight: 500;
    }

    .info-card a:hover {
        text-decoration: underline;
    }

    /* Contact Form */
    .contact-form-container {
        background: white;
        border-radius: 30px;
        box-shadow: var(--shadow-soft);
        padding: 50px;
    }

    .form-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .form-header h2 {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem;
        color: var(--green-dark);
        margin-bottom: 15px;
    }

    .form-header p {
        color: var(--brown-light);
        font-size: 1.1rem;
    }

    .contact-section .form-label {
        font-weight: 600;
        color: var(--green-dark);
        margin-bottom: 10px;
    }

    .contact-section .form-control {
        padding: 15px 20px;
        border: 2px solid var(--green-pale);
        border-radius: 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .contact-section .form-control:focus {
        outline: none;
        border-color: var(--green-primary);
        box-shadow: 0 0 0 4px rgba(74, 124, 67, 0.1);
    }

    .contact-section .form-control::placeholder {
        color: var(--brown-light);
    }

    .contact-section .form-select {
        padding: 15px 20px;
        border: 2px solid var(--green-pale);
        border-radius: 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .contact-section .form-select:focus {
        outline: none;
        border-color: var(--green-primary);
        box-shadow: 0 0 0 4px rgba(74, 124, 67, 0.1);
    }

    .contact-section textarea.form-control {
        min-height: 150px;
        resize: none;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--green-primary), var(--green-light));
        border: none;
        padding: 18px 50px;
        border-radius: 30px;
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.4s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 30px rgba(74, 124, 67, 0.3);
    }

    .btn-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 40px rgba(74, 124, 67, 0.4);
    }

    /* Map Section */
    .map-container {
        background: white;
        border-radius: 30px;
        box-shadow: var(--shadow-soft);
        overflow: hidden;
    }

    .map-header {
        padding: 30px;
        border-bottom: 2px solid var(--green-mist);
    }

    .map-header h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem;
        color: var(--green-dark);
        margin-bottom: 5px;
    }

    .map-header p {
        color: var(--brown-light);
        margin: 0;
    }

    .map-container iframe {
        width: 100%;
        height: 400px;
        border: none;
    }

    .map-placeholder {
        height: 400px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: var(--green-mist);
    }

    .map-placeholder i {
        font-size: 4rem;
        color: var(--green-pale);
        margin-bottom: 20px;
    }

    .map-placeholder p {
        color: var(--brown-light);
        font-size: 1.1rem;
    }

    /* Working Hours */
    .working-hours {
        margin-top: 30px;
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: var(--shadow-soft);
    }

    .working-hours h4 {
        font-family: 'Playfair Display', serif;
        font-size: 1.3rem;
        color: var(--green-dark);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .working-hours h4 i {
        color: var(--green-primary);
    }

    .hours-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .hours-list li {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--green-mist);
    }

    .hours-list li:last-child {
        border-bottom: none;
    }

    .hours-list .day {
        color: var(--green-dark);
        font-weight: 500;
    }

    .hours-list .time {
        color: var(--green-primary);
        font-weight: 600;
    }

    .hours-list .closed {
        color: var(--brown-light);
    }

    /* FAQ Section */
    .contact-faq-section {
        padding: 80px 0;
        background: var(--green-mist);
    }

    .contact-faq-header {
        text-align: center;
        margin-bottom: 50px;
    }

    .contact-faq-header h2 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        color: var(--green-dark);
        margin-bottom: 15px;
    }

    .contact-faq-header p {
        color: var(--brown-light);
        font-size: 1.1rem;
    }

    .contact-faq-item {
        background: white;
        border: none;
        border-radius: 20px !important;
        margin-bottom: 15px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
    }

    .contact-faq-btn {
        padding: 25px 30px;
        font-weight: 600;
        color: var(--green-dark);
        background: white;
        font-size: 1.1rem;
    }

    .contact-faq-btn:not(.collapsed) {
        background: var(--green-primary);
        color: white;
        box-shadow: none;
    }

    .contact-faq-btn:focus {
        box-shadow: none;
    }

    .contact-faq-btn::after {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%232d5a27'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    }

    .contact-faq-btn:not(.collapsed)::after {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    }

    .contact-faq-item .accordion-body {
        padding: 20px 30px 25px;
        color: var(--brown-light);
        line-height: 1.8;
    }

    /* Responsive */
    @media (max-width: 1199px) {
        .contact-info-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 991px) {
        .contact-form-container,
        .map-container {
            margin-bottom: 30px;
        }
    }

    @media (max-width: 767px) {
        .contact-info-cards {
            grid-template-columns: 1fr;
        }

        .contact-form-container {
            padding: 30px 20px;
        }

        .contact-faq-header h2 {
            font-size: 2rem;
        }

        .form-header h2 {
            font-size: 1.8rem;
        }

        .btn-submit {
            padding: 15px 40px;
            font-size: 1rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
        document.getElementById('contactForm').reset();
    @endif

    @if($errors->any())
        window.showResultModal('error', @json(implode('<br>', $errors->all())));
    @endif
});
</script>
@endpush
