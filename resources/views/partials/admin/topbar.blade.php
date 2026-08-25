<header class="top-navbar" role="banner">
    <div class="d-flex align-items-center gap-3">
        <button class="sidebar-toggle-btn" type="button" id="sidebarToggle" aria-label="Menüyü aç/kapat">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <div class="d-flex align-items-center gap-3">
        {{-- $unreadMessageCount shared via View Composer --}}

        <a href="{{ route('admin.contact-messages.index') }}" class="btn-glass position-relative"
           aria-label="Okunmamış mesajlar">
            <i class="bi bi-envelope"></i>
            @if($unreadMessageCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {{ $unreadMessageCount }}
            </span>
            @endif
        </a>

        {{-- Bildirim Merkezi (in-app notifications) --}}
        @if(Route::has('admin.notifications.index'))
            @php
                $unreadNotifCount = rescue(static fn (): int =>
                    \App\Services\NotificationCenter::unreadCount(auth()->id()), 0, false);
            @endphp
            <div class="dropdown">
                <button type="button" class="btn-glass position-relative" data-bs-toggle="dropdown" aria-label="Bildirimler" id="ntBellBtn">
                    <i class="bi bi-bell-fill"></i>
                    @if($unreadNotifCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="ntBellBadge">
                            {{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}
                        </span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end nt-dropdown" style="width: 380px; max-height: 500px; overflow-y: auto;">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <strong><i class="bi bi-bell me-1"></i> Bildirimler</strong>
                        <a href="{{ route('admin.notifications.index') }}" class="small text-teal">Tümünü Gör</a>
                    </div>
                    <div id="ntDropdownList" class="nt-dropdown-list">
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-hourglass-split"></i> <small>Yükleniyor...</small>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <a href="{{ route('home') }}" class="btn-glass" target="_blank" aria-label="Siteyi görüntüle">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>

        <div class="dropdown">
            <button class="btn-glass dropdown-toggle d-flex align-items-center gap-2" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-md-inline">{{ auth()->user()?->full_name ?? 'Admin' }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="{{ route('admin.profile.edit') }}">
                        <i class="bi bi-person me-2"></i> Profil
                    </a>
                </li>
                @can('viewAny', \App\Models\Setting::class)
                <li>
                    <a class="dropdown-item" href="{{ route('admin.settings.index') }}">
                        <i class="bi bi-gear me-2"></i> Ayarlar
                    </a>
                </li>
                @endcan
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="bi bi-box-arrow-left me-2"></i> Çıkış Yap
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
