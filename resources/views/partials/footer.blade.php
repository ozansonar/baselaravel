@php
    $fName    = \App\Models\Setting::getValue('site_name', config('app.name'));
    $fDesc    = \App\Models\Setting::getValue('site_description', __('site.misc.site_description'));
    $fLogo    = \App\Models\Setting::getValue('site_logo');
    $fFooter  = \App\Models\Setting::getValue('footer_text', '© ' . date('Y') . ' ' . $fName . '. ' . __('site.misc.rights'));
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
                <a class="footer-brand" href="{{ localized_route('home') }}">
                    @if($fLogo)
                        <img src="{{ upload_url($fLogo) }}" alt="{{ image_alt($fName) }}" height="34"
                             class="brand__logo" loading="lazy" decoding="async">
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

            {{-- Bağlantı sütunları — panelden "Alt Menü" ile yönetiliyor.
                 Kök öğe sütun başlığı, çocukları o sütunun bağlantıları. --}}
            @if(isset($footerMenu) && $footerMenu)
                @php $menuItemService = app(\App\Services\MenuItemService::class); @endphp
                @foreach($footerMenu->rootItems as $column)
                    @continue(! $column->is_active || $column->activeChildren->isEmpty())
                    <div class="col-6 col-lg-2">
                        <h5>{{ $column->label }}</h5>
                        @foreach($column->activeChildren as $link)
                            <a class="footer-link" href="{{ $menuItemService->resolveUrl($link) }}"
                               @if($link->target === '_blank') target="_blank" rel="noopener" @endif>{{ $link->label }}</a>
                        @endforeach
                    </div>
                @endforeach
            @endif

            {{-- Contact --}}
            <div class="col-lg-4">
                <h5>{{ __('site.nav.contact') }}</h5>
                @if($fAddress)
                    <p class="d-flex gap-2 mb-2"><i class="fa-solid fa-location-dot mt-1"></i><span>{{ $fAddress }}</span></p>
                @endif
                @if($fPhone)
                    <p class="mb-2"><a class="footer-link d-inline-flex gap-2 p-0" href="tel:{{ preg_replace('/\s+/', '', $fPhone) }}"><i class="fa-solid fa-phone"></i>{{ format_phone($fPhone) }}</a></p>
                @endif
                @if($fEmail)
                    <p class="mb-3"><a class="footer-link d-inline-flex gap-2 p-0" href="mailto:{{ $fEmail }}"><i class="fa-solid fa-envelope"></i>{{ $fEmail }}</a></p>
                @endif

                @include('partials.newsletter-form')
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
