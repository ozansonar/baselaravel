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

        {{-- ─── E-TİCARET ────────────────────────────────────── --}}
        <div class="nav-section-title">E-Ticaret</div>

        <a href="{{ route('admin.categories.index') }}"
           class="nav-link {{ Route::is('admin.categories.*') ? 'active' : '' }}">
            <i class="bi bi-folder-fill"></i> Kategoriler
        </a>

        <a href="{{ route('admin.products.index') }}"
           class="nav-link {{ Route::is('admin.products.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam-fill"></i> Ürünler
        </a>

        <a href="{{ route('admin.orders.index') }}"
           class="nav-link {{ Route::is('admin.orders.*') ? 'active' : '' }}">
            <i class="bi bi-cart-check-fill"></i> Siparişler
        </a>

        <a href="{{ route('admin.campaigns.index') }}"
           class="nav-link {{ Route::is('admin.campaigns.*') ? 'active' : '' }}">
            <i class="bi bi-megaphone-fill"></i> Kampanyalar
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

        <a href="{{ route('admin.city-pages.index') }}"
           class="nav-link {{ Route::is('admin.city-pages.*') ? 'active' : '' }}">
            <i class="bi bi-geo-alt-fill"></i> Şehir Sayfaları
        </a>

        <a href="{{ route('admin.sliders.index') }}"
           class="nav-link {{ Route::is('admin.sliders.*') ? 'active' : '' }}">
            <i class="bi bi-images"></i> Sliderlar
        </a>

        <a href="{{ route('admin.popups.index') }}"
           class="nav-link {{ Route::is('admin.popups.*') ? 'active' : '' }}">
            <i class="bi bi-window-stack"></i> Popup Yönetimi
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

        {{-- ─── SOSYAL MEDYA ─────────────────────────────────── --}}
        <div class="nav-section-title">Sosyal Medya</div>

        <a href="{{ route('admin.instagram-posts.index') }}"
           class="nav-link {{ Route::is('admin.instagram-posts.*') ? 'active' : '' }}">
            <i class="bi bi-instagram"></i> Instagram Gönderileri
        </a>

        @if(Route::has('admin.instagram-logs.index'))
        <a href="{{ route('admin.instagram-logs.index') }}"
           class="nav-link {{ Route::is('admin.instagram-logs.*') ? 'active' : '' }}">
            <i class="bi bi-journal-code"></i> Instagram API Logları
        </a>
        @endif

        <a href="{{ route('admin.youtube-videos.index') }}"
           class="nav-link {{ Route::is('admin.youtube-videos.*') ? 'active' : '' }}">
            <i class="bi bi-youtube"></i> YouTube Videoları
        </a>

        <a href="{{ route('admin.google-reviews.index') }}"
           class="nav-link {{ Route::is('admin.google-reviews.*') ? 'active' : '' }}">
            <i class="bi bi-google"></i> Google Yorumları
        </a>

        <a href="{{ route('admin.hashtag-pool.index') }}"
           class="nav-link {{ Route::is('admin.hashtag-pool.*') ? 'active' : '' }}">
            <i class="bi bi-hash"></i> Hashtag Havuzu
        </a>

        @if(Route::has('admin.hashtag-analytics.index'))
        <a href="{{ route('admin.hashtag-analytics.index') }}"
           class="nav-link {{ Route::is('admin.hashtag-analytics.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Hashtag Raporu
        </a>
        @endif

        @if(Route::has('admin.best-time-analytics.index'))
        <a href="{{ route('admin.best-time-analytics.index') }}"
           class="nav-link {{ Route::is('admin.best-time-analytics.*') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i> En İyi Saat
        </a>
        @endif

        @if(Route::has('admin.caption-ab-test.index'))
        <a href="{{ route('admin.caption-ab-test.index') }}"
           class="nav-link {{ Route::is('admin.caption-ab-test.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-data"></i> Caption A/B Test
        </a>
        @endif

        @if(Route::has('admin.special-days.index'))
        <a href="{{ route('admin.special-days.index') }}"
           class="nav-link {{ Route::is('admin.special-days.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-heart"></i> Özel Günler
        </a>
        @endif

        @if(Route::has('admin.shot-list.index'))
        <a href="{{ route('admin.shot-list.index') }}"
           class="nav-link {{ Route::is('admin.shot-list.*') ? 'active' : '' }}">
            <i class="bi bi-camera2"></i> Çekim Listesi
        </a>
        @endif

        {{-- ─── AI ARAÇLARI ──────────────────────────────────── --}}
        <div class="nav-section-title">AI Araçları</div>

        <a href="{{ route('admin.ai-generate.create') }}"
           class="nav-link {{ Route::is('admin.ai-generate.*') ? 'active' : '' }}">
            <i class="bi bi-stars"></i> AI İçerik Üret
        </a>

        <a href="{{ route('admin.ai-logs.index') }}"
           class="nav-link {{ Route::is('admin.ai-logs.*') ? 'active' : '' }}">
            <i class="bi bi-robot"></i> AI İçerik Logları
        </a>

        <a href="{{ route('admin.ai-prompt-settings.edit') }}"
           class="nav-link {{ Route::is('admin.ai-prompt-settings.*') ? 'active' : '' }}">
            <i class="bi bi-sliders2"></i> AI İçerik Ayarları
        </a>

        <a href="{{ route('admin.product-topics.index') }}"
           class="nav-link {{ Route::is('admin.product-topics.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i> Topic Cluster
        </a>

        <a href="{{ route('admin.ai-images.index') }}"
           class="nav-link {{ Route::is('admin.ai-images.*') ? 'active' : '' }}">
            <i class="bi bi-magic"></i> AI Görsel Oluştur
        </a>

        <a href="{{ route('admin.ai-image-logs.index') }}"
           class="nav-link {{ Route::is('admin.ai-image-logs.*') ? 'active' : '' }}">
            <i class="bi bi-image"></i> AI Görsel Logları
        </a>

        {{-- ─── GOOGLE VERTEX ────────────────────────────────── --}}
        <div class="nav-section-title">Google Vertex</div>

        <a href="{{ route('admin.vertex.index') }}"
           class="nav-link {{ Route::is('admin.vertex.index') ? 'active' : '' }}">
            <i class="bi bi-stars"></i> Vertex Görsel Üret
        </a>

        <a href="{{ route('admin.vertex.prompts.index') }}"
           class="nav-link {{ Route::is('admin.vertex.prompts.*') ? 'active' : '' }}">
            <i class="bi bi-collection-fill"></i> Vertex Şablonlar
        </a>

        <a href="{{ route('admin.vertex.generations.index') }}"
           class="nav-link {{ Route::is('admin.vertex.generations.*') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i> Vertex Geçmiş
        </a>

        <a href="{{ route('admin.vertex.schedules.index') }}"
           class="nav-link {{ Route::is('admin.vertex.schedules.*') ? 'active' : '' }}">
            <i class="bi bi-alarm"></i> Vertex Zamanlama
        </a>

        {{-- ─── MEDYA & DOSYA ────────────────────────────────── --}}
        <div class="nav-section-title">Medya & Dosya</div>

        @if(Route::has('admin.image-library.index'))
        <a href="{{ route('admin.image-library.index') }}"
           class="nav-link {{ Route::is('admin.image-library.*') ? 'active' : '' }}">
            <i class="bi bi-images"></i> Görsel Kütüphanesi
        </a>
        @endif

        <a href="{{ route('admin.media-library.index') }}"
           class="nav-link {{ Route::is('admin.media-library.*') ? 'active' : '' }}">
            <i class="bi bi-collection"></i> Medya Kütüphanesi
        </a>

        <a href="{{ route('admin.files.index') }}"
           class="nav-link {{ Route::is('admin.files.*') ? 'active' : '' }}">
            <i class="bi bi-folder-fill"></i> Dosya Yöneticisi
        </a>

        {{-- ─── MÜŞTERİ & İLETİŞİM ───────────────────────────── --}}
        <div class="nav-section-title">Müşteri & İletişim</div>

        {{-- $pendingReviewCount & $unreadMessageCount View Composer ile paylaşılır --}}
        <a href="{{ route('admin.reviews.index') }}"
           class="nav-link {{ Route::is('admin.reviews.*') ? 'active' : '' }}">
            <i class="bi bi-chat-square-text-fill"></i> Değerlendirmeler
            @if($pendingReviewCount > 0)
            <span class="nav-badge">{{ $pendingReviewCount }}</span>
            @endif
        </a>

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

        <a href="{{ route('admin.seo-performance.index') }}"
           class="nav-link {{ Route::is('admin.seo-performance.*') ? 'active' : '' }}">
            <i class="bi bi-google"></i> SEO Performans
        </a>

        <a href="{{ route('admin.google-integration.index') }}"
           class="nav-link {{ Route::is('admin.google-integration.*') ? 'active' : '' }}">
            <i class="bi bi-key-fill"></i> Google Entegrasyonu
        </a>

        <a href="{{ route('admin.google-merchant-feed.index') }}"
           class="nav-link {{ Route::is('admin.google-merchant-feed.*') ? 'active' : '' }}">
            <i class="bi bi-cart3"></i> Google Merchant Feed
        </a>

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

        @if(Route::has('admin.onboarding.index'))
        <a href="{{ route('admin.onboarding.index') }}"
           class="nav-link {{ Route::is('admin.onboarding.*') ? 'active' : '' }}">
            <i class="bi bi-magic"></i> Kurulum Sihirbazı
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
