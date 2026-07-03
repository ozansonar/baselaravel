<footer class="footer" role="contentinfo">
    <div class="container">
        <div class="row g-4">
            {{-- Hakkımızda --}}
            <div class="col-lg-3 col-md-6">
                <div class="footer-brand">
                    <span class="logo-icon"><i class="fa-solid fa-leaf"></i></span>
                    {{ \App\Models\Setting::getValue('site_name', config('app.name')) }}
                </div>
                <p class="footer-tagline">
                    <i class="fa-solid fa-location-dot"></i>
                    Büyük Palabıyık Köyü, <strong>Çorum</strong> / Türkiye
                </p>
                <p class="footer-text">
                    Çorum'un bereketli topraklarından doğanın en taze haliyle çiftliğimizden sofranıza.
                    Katkısız, hormonsuz, doğal köy ürünleriyle sağlıklı yaşamın tadını çıkarın.
                </p>
                <div class="footer-social">
                    @php
                        $socialLinks = [
                            'social_facebook'  => ['icon' => 'fa-brands fa-facebook-f',  'label' => 'Facebook'],
                            'social_instagram' => ['icon' => 'fa-brands fa-instagram',    'label' => 'Instagram'],
                            'social_twitter'   => ['icon' => 'fa-brands fa-x-twitter',    'label' => 'X (Twitter)'],
                            'social_youtube'   => ['icon' => 'fa-brands fa-youtube',      'label' => 'YouTube'],
                            'social_whatsapp'  => ['icon' => 'fa-brands fa-whatsapp',     'label' => 'WhatsApp'],
                        ];
                    @endphp
                    @foreach($socialLinks as $key => $social)
                        @php $url = \App\Models\Setting::getValue($key); @endphp
                        @if($url)
                        <a href="{{ $key === 'social_whatsapp' ? whatsapp_url($url, 'Merhaba, Orhan Baba\'nın Çiftliği hakkında bilgi almak istiyorum.') : $url }}" class="social-link" target="_blank" rel="noopener noreferrer"
                           aria-label="{{ $social['label'] }}">
                            <i class="{{ $social['icon'] }}"></i>
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Hızlı Linkler --}}
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Hızlı Linkler</h5>
                <ul class="footer-links">
                    <li><a href="{{ route('home') }}"><i class="fa-solid fa-angle-right"></i> Anasayfa</a></li>
                    <li><a href="{{ route('pages.show', 'hakkimizda') }}"><i class="fa-solid fa-angle-right"></i> Hakkımızda</a></li>
                    <li><a href="{{ route('contact') }}"><i class="fa-solid fa-angle-right"></i> İletişim</a></li>
                    <li><a href="{{ route('faq') }}"><i class="fa-solid fa-angle-right"></i> SSS</a></li>
                    <li><a href="{{ route('sitemap-page') }}"><i class="fa-solid fa-angle-right"></i> Site Haritası</a></li>
                    <li><a href="{{ route('pages.show', 'teslimat-kosullari') }}"><i class="fa-solid fa-angle-right"></i> Teslimat Koşulları</a></li>
                    <li><a href="{{ route('pages.show', 'gizlilik-politikasi') }}"><i class="fa-solid fa-angle-right"></i> Gizlilik Politikası</a></li>
                </ul>
            </div>

            {{-- Kategoriler --}}
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Kategoriler</h5>
                <ul class="footer-links">
                    @php
                        $footerCategories = Cache::remember('nav_categories_footer', 3600, function () {
                            return \App\Models\Category::where('is_active', true)
                                ->whereNull('parent_id')
                                ->orderBy('sort_order')
                                ->get();
                        });
                    @endphp
                    @foreach($footerCategories as $cat)
                    <li>
                        <a href="{{ route('products.index', $cat->slug) }}"><i class="fa-solid fa-angle-right"></i> {{ $cat->name }}</a>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Hizmet Bölgelerimiz (Modül 8 — şehir landing sayfalarına SEO juice) --}}
            @if(! empty($footerCityPages ?? []) && count($footerCityPages) > 0)
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Hizmet Bölgelerimiz</h5>
                <ul class="footer-links">
                    @foreach($footerCityPages as $cityPage)
                    <li>
                        <a href="{{ url('/' . $cityPage->city_slug . '-koy-urunleri') }}">
                            <i class="fa-solid fa-angle-right"></i> {{ $cityPage->city_name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- İletişim --}}
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">İletişim</h5>
                @php
                    $footerPhone   = \App\Models\Setting::getValue('contact_phone', '0555 123 45 67');
                    $footerEmail   = \App\Models\Setting::getValue('contact_email', 'info@orhanbabaninciftligi.com');
                    $footerAddress = \App\Models\Setting::getValue('contact_address', 'Çiftlik Yolu No:1, Bolu');
                @endphp
                <address class="footer-address">
                    <div class="footer-contact-item">
                        <div class="footer-contact-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <span>{{ $footerAddress }}</span>
                    </div>
                    <div class="footer-contact-item">
                        <div class="footer-contact-icon">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <a href="tel:{{ preg_replace('/\s+/', '', $footerPhone) }}" class="text-white-50">
                            {{ format_phone($footerPhone) }}
                        </a>
                    </div>
                    <div class="footer-contact-item">
                        <div class="footer-contact-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <a href="mailto:{{ $footerEmail }}" class="text-white-50">
                            {{ $footerEmail }}
                        </a>
                    </div>
                </address>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-copyright">{{ \App\Models\Setting::getValue('footer_text', '© ' . date('Y') . ' ' . \App\Models\Setting::getValue('site_name', config('app.name')) . '. Tüm hakları saklıdır.') }}</p>
            <p class="footer-credit">
                <span class="footer-credit__label">
                    <i class="fa-solid fa-code"></i>
                    Tasarım &amp; Geliştirme
                </span>
                <a href="https://ozansonar.net/" target="_blank" rel="noopener noreferrer" class="footer-credit__link" aria-label="Site geliştiricisi Ozan SONAR">
                    <span class="footer-credit__name">Ozan SONAR</span>
                    <i class="fa-solid fa-arrow-up-right-from-square footer-credit__icon"></i>
                </a>
            </p>
        </div>
    </div>
</footer>
