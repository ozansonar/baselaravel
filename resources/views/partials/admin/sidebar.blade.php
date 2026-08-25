<aside class="sidebar" id="adminSidebar" aria-label="Admin menü">
    <div class="sidebar-header">
        @php $sidebarLogo = \App\Models\Setting::getValue('site_logo'); @endphp
        @if($sidebarLogo)
        <img src="{{ upload_url($sidebarLogo) }}" alt="{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}" class="sidebar-logo-img">
        @else
        <div class="sidebar-logo">OB</div>
        @endif
        <div class="sidebar-brand">
            <h5>{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}</h5>
            <span>Yönetim Paneli</span>
        </div>
    </div>

    <nav class="sidebar-nav">

        {{-- ─── ANA MENÜ ─────────────────────────────────────── --}}
        <div class="nav-section-title">Ana Menü</div>

        <a href="{{ route('admin.dashboard') }}"
           class="nav-link {{ Route::is('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        {{-- ─── İÇERİK ───────────────────────────────────────── --}}
        <div class="nav-section-title">İçerik</div>

        <a href="{{ route('admin.blog-categories.index') }}"
           class="nav-link {{ Route::is('admin.blog-categories.*') ? 'active' : '' }}">
            <i class="bi bi-bookmark-fill"></i> İçerik Kategorileri
        </a>

        <a href="{{ route('admin.blog-posts.index') }}"
           class="nav-link {{ Route::is('admin.blog-posts.*') ? 'active' : '' }}">
            <i class="bi bi-journal-richtext"></i> İçerikler
        </a>

        @php
            $pendingCommentCount = \App\Models\BlogComment::where('status', 'pending')->count();
        @endphp
        <a href="{{ route('admin.blog-comments.index') }}"
           class="nav-link {{ Route::is('admin.blog-comments.*') ? 'active' : '' }}">
            <i class="bi bi-chat-dots-fill"></i> Yorumlar
            @if($pendingCommentCount > 0)
            <span class="nav-badge">{{ $pendingCommentCount }}</span>
            @endif
        </a>

        <a href="{{ route('admin.pages.index') }}"
           class="nav-link {{ Route::is('admin.pages.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-text-fill"></i> Sayfalar
        </a>

        <a href="{{ route('admin.sliders.index') }}"
           class="nav-link {{ Route::is('admin.sliders.*') ? 'active' : '' }}">
            <i class="bi bi-images"></i> Sliderlar
        </a>

        <a href="{{ route('admin.popups.index') }}"
           class="nav-link {{ Route::is('admin.popups.*') ? 'active' : '' }}">
            <i class="bi bi-window-stack"></i> Popup / Modal
        </a>

        <a href="{{ route('admin.gallery-categories.index') }}"
           class="nav-link {{ Route::is('admin.gallery-categories.*') ? 'active' : '' }}">
            <i class="bi bi-folder-fill"></i> Galeri Kategorileri
        </a>

        <a href="{{ route('admin.gallery-items.index') }}"
           class="nav-link {{ Route::is('admin.gallery-items.*') ? 'active' : '' }}">
            <i class="bi bi-collection-fill"></i> Galeri
        </a>

        <a href="{{ route('admin.faqs.index') }}"
           class="nav-link {{ Route::is('admin.faqs.*') ? 'active' : '' }}">
            <i class="bi bi-question-circle-fill"></i> SSS
        </a>

        {{-- ─── MEDYA & DOSYA ────────────────────────────────── --}}
        <div class="nav-section-title">Medya & Dosya</div>

        <a href="{{ route('admin.files.index') }}"
           class="nav-link {{ Route::is('admin.files.*') ? 'active' : '' }}">
            <i class="bi bi-folder-fill"></i> Dosya Yöneticisi
        </a>

        {{-- ─── MÜŞTERİ & İLETİŞİM ───────────────────────────── --}}
        <div class="nav-section-title">Müşteri & İletişim</div>

        {{-- $unreadMessageCount View Composer ile paylaşılır --}}
        <a href="{{ route('admin.contact-messages.index') }}"
           class="nav-link {{ Route::is('admin.contact-messages.*') ? 'active' : '' }}">
            <i class="bi bi-envelope-fill"></i> Mesajlar
            @if($unreadMessageCount > 0)
            <span class="nav-badge">{{ $unreadMessageCount }}</span>
            @endif
        </a>

        <a href="{{ route('admin.users.index') }}"
           class="nav-link {{ Route::is('admin.users.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i> Kullanıcılar
        </a>

        {{-- ─── SEO & ENTEGRASYON ────────────────────────────── --}}
        <div class="nav-section-title">SEO & Entegrasyon</div>

        <a href="{{ route('admin.redirects.index') }}"
           class="nav-link {{ Route::is('admin.redirects.*') ? 'active' : '' }}">
            <i class="bi bi-signpost-2-fill"></i> Yönlendirmeler
        </a>

        {{-- ─── SİSTEM ───────────────────────────────────────── --}}
        <div class="nav-section-title">Sistem</div>

        <a href="{{ route('admin.settings.index') }}"
           class="nav-link {{ Route::is('admin.settings.*') ? 'active' : '' }}">
            <i class="bi bi-gear-fill"></i> Ayarlar
        </a>

        <a href="{{ route('admin.menus.index') }}"
           class="nav-link {{ Route::is('admin.menus.*') ? 'active' : '' }}">
            <i class="bi bi-list-nested"></i> Menü Yönetimi
        </a>

        <a href="{{ route('admin.analytics.index') }}"
           class="nav-link {{ Route::is('admin.analytics.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Analitik
        </a>

        <a href="{{ route('admin.mail-templates.index') }}"
           class="nav-link {{ Route::is('admin.mail-templates.*') ? 'active' : '' }}">
            <i class="bi bi-envelope-open-fill"></i> Mail Şablonları
        </a>

        <a href="{{ route('admin.mail-logs.index') }}"
           class="nav-link {{ Route::is('admin.mail-logs.*') ? 'active' : '' }}">
            <i class="bi bi-envelope-paper-fill"></i> Mail Logları
        </a>

        @if(Route::has('admin.system-health.index'))
        <a href="{{ route('admin.system-health.index') }}"
           class="nav-link {{ Route::is('admin.system-health.*') ? 'active' : '' }}">
            <i class="bi bi-heart-pulse-fill"></i> Sistem Sağlık
        </a>
        @endif

        @if(Route::has('admin.audit-logs.index'))
        <a href="{{ route('admin.audit-logs.index') }}"
           class="nav-link {{ Route::is('admin.audit-logs.*') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i> Aktivite Logları
        </a>
        @endif

        @if(Route::has('admin.notifications.index'))
        <a href="{{ route('admin.notifications.index') }}"
           class="nav-link {{ Route::is('admin.notifications.*') ? 'active' : '' }}">
            <i class="bi bi-bell-fill"></i> Bildirimler
        </a>
        @endif

        @if(Route::has('admin.backups.index'))
        <a href="{{ route('admin.backups.index') }}"
           class="nav-link {{ Route::is('admin.backups.*') ? 'active' : '' }}">
            <i class="bi bi-cloud-download-fill"></i> Yedekler
        </a>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-3 px-2">
            @csrf
            <button type="submit" class="nav-link w-100 border-0 bg-transparent text-start">
                <i class="bi bi-box-arrow-left"></i> Çıkış Yap
            </button>
        </form>
    </nav>
</aside>
