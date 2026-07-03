{{-- ══════════════════════════════════════
     Top Info Bar
══════════════════════════════════════ --}}
@php
    $topPhone = \App\Models\Setting::getValue('contact_phone', '0555 123 45 67');
    $topEmail = \App\Models\Setting::getValue('contact_email', 'info@orhanbabaninciftligi.com');
    $topSocials = [
        'social_facebook'  => ['icon' => 'fa-brands fa-facebook-f',  'label' => 'Facebook'],
        'social_instagram' => ['icon' => 'fa-brands fa-instagram',    'label' => 'Instagram'],
        'social_twitter'   => ['icon' => 'fa-brands fa-x-twitter',    'label' => 'X (Twitter)'],
        'social_youtube'   => ['icon' => 'fa-brands fa-youtube',      'label' => 'YouTube'],
        'social_whatsapp'  => ['icon' => 'fa-brands fa-whatsapp',     'label' => 'WhatsApp'],
    ];
@endphp
<div class="top-bar" aria-label="İletişim bilgileri">
    <div class="container">
        <div class="top-bar-inner">
            <div class="top-bar-left">
                <a href="tel:{{ preg_replace('/\s+/', '', $topPhone) }}" class="top-bar-item">
                    <i class="fa-solid fa-phone"></i>
                    <span>{{ format_phone($topPhone) }}</span>
                </a>
                <span class="top-bar-divider"></span>
                <a href="mailto:{{ $topEmail }}" class="top-bar-item">
                    <i class="fa-solid fa-envelope"></i>
                    <span>{{ $topEmail }}</span>
                </a>
            </div>
            <div class="top-bar-right">
                @foreach($topSocials as $key => $social)
                    @php $url = \App\Models\Setting::getValue($key); @endphp
                    @if($url)
                    <a href="{{ $key === 'social_whatsapp' ? whatsapp_url($url) : $url }}" class="top-bar-social" target="_blank" rel="noopener noreferrer"
                       aria-label="{{ $social['label'] }}">
                        <i class="{{ $social['icon'] }}"></i>
                    </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════
     Main Navbar
══════════════════════════════════════ --}}
<nav class="navbar navbar-expand-lg navbar-custom fixed-top" aria-label="Ana navigasyon">
    <div class="container">
        @php
            $siteLogo = \App\Models\Setting::getValue('site_logo');
        @endphp
        {{-- Mobile: Single row - Hamburger left, Logo center, Search+Cart right --}}
        <div class="navbar-mobile-row d-lg-none">
            <div class="navbar-mobile-left">
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                        data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                        aria-label="Menüyü aç/kapat">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="navbar-mobile-center">
                <a class="navbar-brand" href="{{ route('home') }}">
                    <img src="{{ $siteLogo ? upload_url($siteLogo) : 'https://www.orhanbabaninciftligi.com/uploads/images/5f6cd32c2da54-1600967468.png' }}"
                         alt="{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}"
                         class="navbar-logo-img"
                         loading="eager"
                         decoding="sync"
                         fetchpriority="high"
                         width="75"
                         height="75">
                </a>
            </div>
            <div class="navbar-mobile-right">
                <button class="btn nav-action-btn" type="button"
                        data-bs-toggle="modal" data-bs-target="#searchModal"
                        aria-label="Ürün ara">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                @php
                    $mobileCartCount = app(\App\Services\CartService::class)->count();
                @endphp
                <a href="{{ route('cart.index') }}" class="btn-custom position-relative" id="miniCartBtnMobile">
                    <i class="fa-solid fa-basket-shopping"></i>
                    <span class="mini-cart-badge {{ $mobileCartCount > 0 ? '' : 'd-none' }}" id="cartBadgeMobile">{{ $mobileCartCount }}</span>
                </a>
            </div>
        </div>

        {{-- Desktop: Logo (normal flow) --}}
        <a class="navbar-brand d-none d-lg-flex" href="{{ route('home') }}">
            <img src="{{ $siteLogo ? upload_url($siteLogo) : 'https://www.orhanbabaninciftligi.com/uploads/images/5f6cd32c2da54-1600967468.png' }}"
                 alt="{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}"
                 class="navbar-logo-img"
                 loading="eager"
                 decoding="sync"
                 fetchpriority="high"
                 width="75"
                 height="75">
        </a>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                @if(isset($headerMenu) && $headerMenu)
                    @foreach($headerMenu->rootItems as $item)
                        @include('partials.navbar-item', ['item' => $item])
                    @endforeach
                @endif
            </ul>

            <div class="d-flex align-items-center gap-2">
                {{-- Search Modal Trigger --}}
                <button class="btn nav-action-btn" type="button"
                        data-bs-toggle="modal" data-bs-target="#searchModal"
                        aria-label="Ürün ara">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>

                @auth
                <div class="dropdown">
                    <button class="btn nav-action-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user me-1"></i> {{ auth()->user()->full_name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end nav-user-dropdown">
                        @if(auth()->user()->hasAnyRole(['admin', 'editor', 'moderator']))
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                <i class="fa-solid fa-gauge me-2"></i> Yönetim Paneli
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @endif
                        <li>
                            <a class="dropdown-item" href="{{ route('account.dashboard') }}">
                                <i class="fa-solid fa-user me-2"></i> Hesabım
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('account.orders') }}">
                                <i class="fa-solid fa-box me-2"></i> Siparişlerim
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('account.addresses') }}">
                                <i class="fa-solid fa-location-dot me-2"></i> Adreslerim
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i> Çıkış Yap
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                {{-- Guest: Login button hidden - admin login via /admin --}}
                @endauth

                @include('partials.mini-cart')
            </div>
        </div>
    </div>
</nav>

{{-- ══════════════════════════════════════
     Arama Modalı
══════════════════════════════════════ --}}
<div class="modal fade" id="searchModal" tabindex="-1"
     aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg search-modal-dialog">
        <div class="modal-content search-modal-content">

            {{-- Header --}}
            <div class="search-modal-header">
                <div class="search-modal-brand">
                    <span class="search-modal-leaf"><i class="fa-solid fa-leaf"></i></span>
                    <span class="search-modal-title" id="searchModalLabel">Ürün Ara</span>
                </div>
                <button type="button" class="search-modal-close" data-bs-dismiss="modal" aria-label="Kapat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            {{-- Search Input --}}
            <div class="search-modal-body">
                <form method="GET" action="{{ route('search') }}" id="searchModalForm">
                    <div class="search-modal-input-wrap">
                        <i class="fa-solid fa-magnifying-glass search-modal-icon"></i>
                        <input type="text"
                               class="search-modal-input"
                               name="q"
                               id="modalSearchInput"
                               placeholder="Organik ürün, meyve, sebze ara..."
                               autocomplete="off">
                        <button type="submit" class="search-modal-submit">
                            Ara
                        </button>
                    </div>
                </form>

                {{-- Sonuçlar --}}
                <div class="search-modal-results" id="modalSearchResults">
                    <div class="search-modal-hint" id="modalSearchHint">
                        <i class="fa-solid fa-seedling search-modal-hint-icon"></i>
                        <p>Aramaya başlamak için yazmaya başlayın</p>
                        <span>Meyve, sebze, peynir ve daha fazlası...</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
