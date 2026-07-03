@php
    $user = auth()->user();
    $initials = mb_substr($user->first_name, 0, 1) . mb_substr($user->last_name, 0, 1);
@endphp

<div class="dashboard-sidebar">
    <div class="user-info">
        @if($user->avatar)
            <img src="{{ \App\Services\UploadService::url($user->avatar, 'thumb') }}"
                 alt="{{ $user->full_name }}"
                 class="user-avatar-img" width="100" height="100" loading="lazy" decoding="async">
        @else
            <div class="user-avatar">{{ mb_strtoupper($initials) }}</div>
        @endif
        <h4>{{ $user->full_name }}</h4>
        <p>{{ $user->email }}</p>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="{{ route('account.dashboard') }}"
               class="{{ Route::is('account.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>
        </li>
        <li>
            <a href="{{ route('account.orders') }}"
               class="{{ Route::is('account.orders*') ? 'active' : '' }}">
                <i class="fa-solid fa-bag-shopping"></i>
                Siparişlerim
            </a>
        </li>
        <li>
            <a href="{{ route('account.addresses') }}"
               class="{{ Route::is('account.addresses*') ? 'active' : '' }}">
                <i class="fa-solid fa-location-dot"></i>
                Adreslerim
            </a>
        </li>
        <li>
            <a href="{{ route('account.profile') }}"
               class="{{ Route::is('account.profile') ? 'active' : '' }}">
                <i class="fa-solid fa-user-pen"></i>
                Profil Düzenle
            </a>
        </li>
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Çıkış Yap
                </button>
            </form>
        </li>
    </ul>
</div>
