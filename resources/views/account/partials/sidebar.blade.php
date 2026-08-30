@php
    $user = $user ?? auth()->user();
@endphp

<nav class="account-nav" aria-label="{{ __('site.account.nav_aria') }}">
    {{-- User mini profile --}}
    <div class="text-center px-2 pt-3 pb-2">
        @if($user->avatar)
            <img src="{{ upload_url($user->avatar) }}" alt="{{ image_alt($user->full_name) }}"
                 class="avatar-lg mx-auto mb-3" width="96" height="96" loading="lazy" decoding="async">
        @else
            <div class="avatar-ph mx-auto mb-3">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}</div>
        @endif
        <div class="fw-bold text-truncate">{{ $user->full_name }}</div>
        <div class="text-muted small text-truncate">{{ $user->email }}</div>
    </div>

    <hr class="divider my-2">

    <a href="{{ route('account.dashboard') }}"
       class="account-nav__link @if(request()->routeIs('account.dashboard')) active @endif">
        <i class="fa-solid fa-house-user"></i> {{ __('site.auth.account') }}
    </a>

    <a href="{{ route('account.profile') }}"
       class="account-nav__link @if(request()->routeIs('account.profile')) active @endif">
        <i class="fa-solid fa-user-pen"></i> {{ __('site.auth.profile') }}
    </a>

    <form method="POST" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button type="submit" class="account-nav__link account-nav__link--danger w-100 border-0 bg-transparent text-start">
            <i class="fa-solid fa-right-from-bracket"></i> {{ __('site.auth.logout') }}
        </button>
    </form>
</nav>
