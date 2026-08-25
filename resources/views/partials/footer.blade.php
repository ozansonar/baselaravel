@php
    $fName    = \App\Models\Setting::getValue('site_name', config('app.name'));
    $fDesc    = \App\Models\Setting::getValue('site_description', 'Modern, hızlı ve güvenilir kurumsal çözümler.');
    $fLogo    = \App\Models\Setting::getValue('site_logo');
    $fFooter  = \App\Models\Setting::getValue('footer_text', '© ' . date('Y') . ' ' . $fName . '. Tüm hakları saklıdır.');
    $fCredit  = \App\Models\Setting::getValue('footer_credit');
    $fPhone   = \App\Models\Setting::getValue('contact_phone');
    $fEmail   = \App\Models\Setting::getValue('contact_email');
    $fAddress = \App\Models\Setting::getValue('contact_address');
    $fSocials = [
        'social_facebook'  => 'fa-brands fa-facebook-f',
        'social_instagram' => 'fa-brands fa-instagram',
        'social_twitter'   => 'fa-brands fa-x-twitter',
        'social_youtube'   => 'fa-brands fa-youtube',
        'social_linkedin'  => 'fa-brands fa-linkedin-in',
    ];
@endphp

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="row g-4 g-lg-5">
            {{-- Brand --}}
            <div class="col-lg-4">
                <a class="footer-brand" href="{{ route('home') }}">
                    @if($fLogo)
                        <img src="{{ upload_url($fLogo) }}" alt="{{ $fName }}" height="34" class="brand__logo">
                    @else
                        <span class="brand__mark"><i class="fa-solid fa-bolt"></i></span>
                    @endif
                    <span>{{ $fName }}</span>
                </a>
                <p class="mb-4">{{ $fDesc }}</p>
                <div class="footer-social">
                    @foreach($fSocials as $key => $icon)
                        @php $url = \App\Models\Setting::getValue($key); @endphp
                        @if($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ str_replace('social_', '', $key) }}">
                                <i class="{{ $icon }}"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Quick links --}}
            <div class="col-6 col-lg-2">
                <h5>Menü</h5>
                <a class="footer-link" href="{{ route('home') }}">Anasayfa</a>
                <a class="footer-link" href="{{ route('blog.index') }}">İçerikler</a>
                <a class="footer-link" href="{{ route('gallery') }}">Galeri</a>
                <a class="footer-link" href="{{ route('faq') }}">SSS</a>
                <a class="footer-link" href="{{ route('contact') }}">İletişim</a>
            </div>

            {{-- Corporate pages --}}
            <div class="col-6 col-lg-2">
                <h5>Kurumsal</h5>
                <a class="footer-link" href="{{ url('/hakkimizda') }}">Hakkımızda</a>
                <a class="footer-link" href="{{ url('/gizlilik-politikasi') }}">Gizlilik Politikası</a>
                <a class="footer-link" href="{{ url('/kullanim-kosullari') }}">Kullanım Koşulları</a>
            </div>

            {{-- Contact --}}
            <div class="col-lg-4">
                <h5>İletişim</h5>
                @if($fAddress)
                    <p class="d-flex gap-2 mb-2"><i class="fa-solid fa-location-dot mt-1"></i><span>{{ $fAddress }}</span></p>
                @endif
                @if($fPhone)
                    <p class="mb-2"><a class="footer-link d-inline-flex gap-2 p-0" href="tel:{{ preg_replace('/\s+/', '', $fPhone) }}"><i class="fa-solid fa-phone"></i>{{ format_phone($fPhone) }}</a></p>
                @endif
                @if($fEmail)
                    <p class="mb-0"><a class="footer-link d-inline-flex gap-2 p-0" href="mailto:{{ $fEmail }}"><i class="fa-solid fa-envelope"></i>{{ $fEmail }}</a></p>
                @endif
            </div>
        </div>

        <div class="footer-bottom d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <span>{{ $fFooter }}</span>
            @if($fCredit)
            <span class="text-white-50">{{ $fCredit }}</span>
            @endif
        </div>
    </div>
</footer>
