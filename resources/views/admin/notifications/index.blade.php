@extends('layouts.admin')

@section('title', 'Bildirimler')
@section('page_title', 'Bildirimler')
@section('page_description', 'Sistem bildirimleri, uyarılar ve güncellemelerinizi takip edin')

@section('content')
    @php
        /** Sekmeler: seviye filtreleri gerçekte var olan kayıtlara göre kuruluyor. */
        $levelTabs = [
            \App\Enums\NotificationLevel::Critical,
            \App\Enums\NotificationLevel::Error,
            \App\Enums\NotificationLevel::Warning,
            \App\Enums\NotificationLevel::Info,
            \App\Enums\NotificationLevel::Success,
        ];
        $activeLevel = $filters['level'];
        $isUnreadTab = $filters['unread_only'];
        $isAllTab    = ! $isUnreadTab && $activeLevel === '';
        $baseQuery   = array_filter(['q' => $filters['q']]);
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Bildirimler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Bildirimler</h1>
            <p class="page-subtitle">Sistem bildirimleri, uyarılar ve güncellemelerinizi takip edin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($unreadCount > 0)
                <button type="button" class="btn-glass" id="ntMarkAllRead"
                        data-url="{{ route('admin.notifications.mark-all-read') }}">
                    <i class="bi bi-check-all"></i> Tümünü Okundu Yap
                </button>
            @endif
            @if($stats['total'] > 0)
                <button type="button" class="btn-glass danger" id="ntClearAll">
                    <i class="bi bi-trash"></i> Tümünü Temizle
                </button>
            @endif
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="nt-stat-card accent-blue">
                <div class="nt-stat-icon"><i class="bi bi-bell-fill"></i></div>
                <div class="nt-stat-info">
                    <span class="nt-stat-label">Toplam Bildirim</span>
                    <h3 class="nt-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="nt-stat-card accent-orange">
                <div class="nt-stat-icon"><i class="bi bi-envelope-fill"></i></div>
                <div class="nt-stat-info">
                    <span class="nt-stat-label">Okunmamış</span>
                    <h3 class="nt-stat-value" data-count="{{ $stats['unread'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="nt-stat-card accent-green">
                <div class="nt-stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="nt-stat-info">
                    <span class="nt-stat-label">Bugünkü</span>
                    <h3 class="nt-stat-value" data-count="{{ $stats['today'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="nt-stat-card accent-pink">
                <div class="nt-stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="nt-stat-info">
                    <span class="nt-stat-label">Kritik / Hata</span>
                    <h3 class="nt-stat-value" data-count="{{ $stats['critical'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Bu sayfanın ne olduğu: bildirimlerin nereden geldiği ve ne kadar
         kalacağı, listeye bakan kişinin ilk merak ettiği şey. --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Bu liste sistemin size yazdığı not defteri.</strong>
            Zamanlanmış görevler, yedekleme ve sistem sağlığı kontrolleri buraya düşer;
            önemli olanlar aynı anda Telegram'a da gider. Okundu işareti yalnızca sizin
            görünümünüzü etkiler, bildirim silinmez.
        </div>
    </div>

    {{-- ==================== SECTION 2: FILTER TABS ==================== --}}
    <div class="nt-filter-bar mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="nt-tabs-wrap">
            <a href="{{ route('admin.notifications.index', $baseQuery) }}" class="nt-tab {{ $isAllTab ? 'active' : '' }}">
                <i class="bi bi-grid"></i> Tümü <span class="nt-tab-badge">{{ $stats['total'] }}</span>
            </a>
            <a href="{{ route('admin.notifications.index', $baseQuery + ['unread_only' => 1]) }}"
               class="nt-tab {{ $isUnreadTab ? 'active' : '' }}">
                <i class="bi bi-envelope"></i> Okunmamış
                @if($stats['unread'] > 0)
                    <span class="nt-tab-badge warn">{{ $stats['unread'] }}</span>
                @endif
            </a>
            @foreach($levelTabs as $level)
                @php $levelTotal = (int) ($levelCounts[$level->value] ?? 0); @endphp
                @if($levelTotal > 0)
                    <a href="{{ route('admin.notifications.index', $baseQuery + ['level' => $level->value]) }}"
                       class="nt-tab {{ $activeLevel === $level->value ? 'active' : '' }}">
                        <i class="bi {{ $level->icon() }}"></i> {{ $level->label() }}
                        <span class="nt-tab-badge">{{ $levelTotal }}</span>
                    </a>
                @endif
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.notifications.index') }}" class="nt-search">
            @if($activeLevel !== '')
                <input type="hidden" name="level" value="{{ $activeLevel }}">
            @endif
            @if($isUnreadTab)
                <input type="hidden" name="unread_only" value="1">
            @endif
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Bildirimlerde ara...">
        </form>
    </div>

    {{-- ==================== SECTION 3: NOTIFICATION LIST ==================== --}}
    <div class="nt-list" id="ntList" data-aos="fade-up" data-aos-delay="150">
        @php $currentGroup = null; @endphp

        @forelse($notifications as $notification)
            @php $group = $notification->dayGroupLabel(); @endphp

            @if($group !== $currentGroup)
                @php $currentGroup = $group; @endphp
                <div class="nt-time-header">
                    <span>{{ $group }}</span>
                    <span class="nt-time-line"></span>
                </div>
            @endif

            <div class="nt-item {{ $notification->isUnread() ? 'unread' : '' }} {{ $notification->level === \App\Enums\NotificationLevel::Critical ? 'critical' : '' }}">
                <div class="nt-item-left">
                    <input type="checkbox" class="nt-check" value="{{ $notification->id }}"
                           aria-label="{{ $notification->title }} seç">
                    <div class="nt-item-icon {{ $notification->level?->iconVariant() ?? 'system' }}">
                        <i class="bi {{ $notification->levelIcon() }}"></i>
                    </div>
                </div>
                <div class="nt-item-body">
                    <div class="nt-item-row">
                        <span class="nt-item-title">{{ $notification->title }}</span>
                        <span class="nt-item-time" title="{{ $notification->created_at?->translatedFormat('d F Y H:i') }}">
                            <i class="bi bi-clock me-1"></i>{{ $notification->created_at?->diffForHumans() }}
                        </span>
                    </div>

                    @if($notification->message)
                        <p class="nt-item-desc">{{ $notification->message }}</p>
                    @endif

                    <div class="nt-item-footer">
                        <div class="nt-item-tags">
                            @if($notification->level)
                                <span class="nt-tag {{ $notification->level->tagVariant() }}">
                                    <i class="bi {{ $notification->level->icon() }}"></i> {{ $notification->level->label() }}
                                </span>
                            @endif
                            <span class="nt-tag {{ $notification->typeTagVariant() }}">
                                <i class="bi bi-tag"></i> {{ $notification->typeLabel() }}
                            </span>
                            @if($notification->isUnread())
                                <span class="nt-tag pending"><i class="bi bi-envelope"></i> Okunmadı</span>
                            @endif
                        </div>

                        <div class="nt-item-actions">
                            @if($notification->action_url)
                                <a href="{{ $notification->action_url }}" class="nt-act-btn" title="Detaya git">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            @endif
                            <button type="button" class="nt-act-btn nt-toggle-read"
                                    data-id="{{ $notification->id }}"
                                    data-read-url="{{ route('admin.notifications.mark-read', $notification->id) }}"
                                    data-unread-url="{{ route('admin.notifications.mark-unread', $notification->id) }}"
                                    title="{{ $notification->isUnread() ? 'Okundu yap' : 'Okunmadı yap' }}">
                                <i class="bi {{ $notification->isUnread() ? 'bi-envelope-open' : 'bi-envelope' }}"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}"
                                  class="nt-delete-form" data-title="{{ $notification->title }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="nt-act-btn danger" title="Sil"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="nt-empty">
                <i class="bi bi-bell-slash"></i>
                @if($filters['q'] !== '' || $activeLevel !== '' || $isUnreadTab)
                    <h6>Bu filtreyle eşleşen bildirim yok</h6>
                    <p>Farklı bir sekme deneyin ya da <a href="{{ route('admin.notifications.index') }}" class="text-teal">filtreyi temizleyin</a>.</p>
                @else
                    <h6>Henüz bildirim yok</h6>
                    <p>Sistem bir şey bildirdiğinde burada listelenecek.</p>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Toplu işlem çubuğu — seçim yapılınca beliriyor --}}
    <div class="nt-bulk-bar d-none" id="ntBulkBar">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check2-square text-teal fs-5"></i>
            <span><strong id="ntBulkCount">0</strong> bildirim seçildi</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn-glass sm" id="ntBulkReadBtn"><i class="bi bi-envelope-open me-1"></i> Okundu Yap</button>
            <button type="button" class="btn-glass sm danger" id="ntBulkDeleteBtn"><i class="bi bi-trash me-1"></i> Sil</button>
        </div>
    </div>

    {{-- Seçim kutuları listenin içinde; formlar satırlardaki silme formlarıyla
         iç içe geçmesin diye dışarıda duruyor. --}}
    <form method="POST" action="{{ route('admin.notifications.bulk-mark-read') }}" id="ntBulkReadForm" class="d-none">
        @csrf
        <input type="hidden" name="level" value="{{ $activeLevel }}">
        <input type="hidden" name="unread_only" value="{{ $isUnreadTab ? 1 : '' }}">
        <input type="hidden" name="q" value="{{ $filters['q'] }}">
    </form>
    <form method="POST" action="{{ route('admin.notifications.bulk-destroy') }}" id="ntBulkDeleteForm" class="d-none">
        @csrf
        @method('DELETE')
        <input type="hidden" name="level" value="{{ $activeLevel }}">
        <input type="hidden" name="unread_only" value="{{ $isUnreadTab ? 1 : '' }}">
        <input type="hidden" name="q" value="{{ $filters['q'] }}">
    </form>
    <form method="POST" action="{{ route('admin.notifications.destroy-all') }}" id="ntClearAllForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <div class="mt-3">
        @include('partials.admin.pagination', ['paginator' => $notifications, 'itemLabel' => 'bildirim'])
    </div>

    {{-- ==================== SECTION 4: ÖZET ==================== --}}
    @if($typeSummary !== [])
        <div class="row g-4 mt-1 mb-4">
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="50">
                <div class="nt-card">
                    <div class="nt-card-header">
                        <div class="nt-card-icon c-purple"><i class="bi bi-bar-chart"></i></div>
                        <h6>Bildirim Özeti</h6>
                    </div>
                    <div class="nt-card-body">
                        <div class="nt-summary-list">
                            @foreach($typeSummary as $summary)
                                <div class="nt-summary-row">
                                    <div class="nt-summary-dot {{ $summary['color'] }}"></div>
                                    <span class="nt-summary-label">{{ $summary['label'] }}</span>
                                    <div class="nt-summary-bar">
                                        <div class="nt-summary-fill {{ $summary['color'] }} nt-summary-fill--{{ $summary['percent'] }}"></div>
                                    </div>
                                    <span class="nt-summary-num">{{ $summary['count'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="nt-auto-clean">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <span class="nt-auto-clean-title"><i class="bi bi-recycle me-2"></i>Saklama</span>
                                    <small>Bildirimler siz silene kadar listede kalır; otomatik temizlik görevi tanımlı değil.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="nt-card">
                    <div class="nt-card-header">
                        <div class="nt-card-icon c-teal"><i class="bi bi-lightbulb"></i></div>
                        <h6>Nasıl kullanılır?</h6>
                    </div>
                    <div class="nt-card-body">
                        <div class="nt-summary-list">
                            <div class="nt-hint-row">
                                <i class="bi bi-check2-square"></i>
                                <span>Soldaki kutulardan birden fazla bildirim seçip tek seferde okundu yapabilir ya da silebilirsiniz.</span>
                            </div>
                            <div class="nt-hint-row">
                                <i class="bi bi-envelope-open"></i>
                                <span>Zarf düğmesi okundu/okunmadı arasında geçiş yapar; yanlışlıkla okuduğunuzu geri alabilirsiniz.</span>
                            </div>
                            <div class="nt-hint-row">
                                <i class="bi bi-box-arrow-up-right"></i>
                                <span>Bildirimin ilgili olduğu sayfa varsa ok düğmesi doğrudan oraya götürür.</span>
                            </div>
                            <div class="nt-hint-row">
                                <i class="bi bi-funnel"></i>
                                <span>Sekmeler seviyeye göre süzer, arama kutusu başlık ve metin içinde arar.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/notifications.js') }}"></script>
@endpush
