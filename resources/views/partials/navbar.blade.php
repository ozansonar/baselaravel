@php
    $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));
    $siteLogo = \App\Models\Setting::getValue('site_logo');
@endphp

<header class="site-header">
    <nav class="navbar navbar-expand-lg" aria-label="Ana navigasyon">
        <div class="container">

            {{-- Brand --}}
            <a class="brand" href="{{ route('home') }}">
                @if($siteLogo)
                    <img class="brand__logo" src="{{ upload_url($siteLogo) }}" alt="{{ $siteName }}" width="140" height="38">
                @else
                    <span class="brand__mark"><i class="fa-solid fa-bolt"></i></span>
                    <span class="brand__text">{{ $siteName }}</span>
                @endif
            </a>

            {{-- Desktop nav --}}
            <ul class="navbar-nav main-nav mx-auto d-none d-lg-flex">
                @if(isset($headerMenu) && $headerMenu)
                    @foreach($headerMenu->rootItems as $item)
                        @include('partials.navbar-item', ['item' => $item])
                    @endforeach
                @endif
            </ul>

            {{-- Desktop auth actions --}}
            <div class="d-none d-lg-flex align-items-center gap-2">
                @auth
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-circle"></i>
                            <span>{{ auth()->user()->full_name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if(auth()->user()->canAccessPanel())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-gauge me-2"></i>{{ __('site.auth.admin_panel') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('account.dashboard') }}"><i class="fa-solid fa-house-user me-2"></i>{{ __('site.auth.account') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.profile') }}"><i class="fa-solid fa-user-pen me-2"></i>{{ __('site.auth.profile') }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>{{ __('site.auth.logout') }}</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-light">{{ __('site.auth.login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">{{ __('site.auth.register') }}</a>
                @endauth

                @include('partials.language-switcher')

                {{-- Koyu/açık kip --}}
                @include('partials.theme-toggle')
            </div>

            {{-- Mobile toggle --}}
            <div class="d-flex align-items-center gap-2 d-lg-none">
                @include('partials.theme-toggle')

                <button class="nav-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav"
                        aria-controls="mobileNav" aria-label="{{ __('site.misc.open_menu') }}">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>
</header>

{{-- Mobile offcanvas --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header border-bottom">
        <a class="brand" href="{{ route('home') }}" id="mobileNavLabel">
            @if($siteLogo)
                <img class="brand__logo" src="{{ upload_url($siteLogo) }}" alt="{{ $siteName }}" width="130" height="34">
            @else
                <span class="brand__mark"><i class="fa-solid fa-bolt"></i></span>
                <span class="brand__text">{{ $siteName }}</span>
            @endif
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('site.actions.close') }}"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <ul class="navbar-nav mobile-nav flex-column mb-3">
            @if(isset($headerMenu) && $headerMenu)
                @foreach($headerMenu->rootItems as $item)
                    @include('partials.navbar-item', ['item' => $item])
                @endforeach
            @endif
        </ul>

        <div class="mt-auto d-grid gap-2 pt-3">
            @auth
                <a href="{{ route('account.dashboard') }}" class="btn btn-light"><i class="fa-solid fa-house-user"></i> {{ __('site.auth.account') }}</a>
                @if(auth()->user()->canAccessPanel())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-light"><i class="fa-solid fa-gauge"></i> {{ __('site.auth.admin_panel') }}</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="d-grid">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-right-from-bracket"></i> {{ __('site.auth.logout') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-light">{{ __('site.auth.login_long') }}</a>
                <a href="{{ route('register') }}" class="btn btn-primary">{{ __('site.auth.register') }}</a>
            @endauth

            @include('partials.language-switcher')
        </div>
    </div>
</div>
