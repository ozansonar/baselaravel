@extends('layouts.admin')

@section('title', 'Push Duyuruları')
@section('page_title', 'Push Duyuruları')
@section('page_description', 'Mobil uygulamaya gönderilen duyuruları oluşturun ve takip edin')

@section('content')
    @php
        use App\Enums\PushAudience;
        use App\Enums\PushNotificationStatus;

        // Durum sekmesi kendi göstergesi; rozetlerde tekrar edilmiyor.
        $chipFilters = collect($filters)->except(['status', 'sort']);
        $hasFilter = $chipFilters->filter(fn ($value) => (string) $value !== '')->isNotEmpty();

        $activeFilters = collect([
            'search' => ['label' => 'Arama', 'value' => $filters['search']],
            'audience' => [
                'label' => 'Hedef',
                'value' => $filters['audience'] !== ''
                    ? (PushAudience::tryFrom($filters['audience'])?->label() ?? '')
                    : '',
            ],
            'from' => [
                'label' => 'Başlangıç',
                'value' => $filters['from'] !== '' ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d.m.Y') : '',
            ],
            'to' => [
                'label' => 'Bitiş',
                'value' => $filters['to'] !== '' ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d.m.Y') : '',
            ],
        ])->filter(fn (array $chip): bool => $chip['value'] !== '');
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Push Duyuruları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Push Duyuruları</h1>
            <p class="page-subtitle">Mobil uygulamaya gönderilen duyuruları oluşturun ve takip edin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('create', App\Models\PushNotification::class)
                <a href="{{ route('admin.push-notifications.create') }}" class="btn-teal">
                    <i class="bi bi-plus-lg"></i> Yeni Duyuru
                </a>
            @endcan
        </div>
    </div>

    @unless($configured)
        {{-- Taşıyıcı yapılandırılmadan gönderilen duyuru hiçbir cihaza
             ulaşmıyor. Bunu listede söylemek, "gönderdim ama gelmedi"
             sorusunu baştan cevaplıyor. --}}
        <div class="alert alert-warning d-flex align-items-start gap-2" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Bildirim taşıyıcısı yapılandırılmamış.</strong>
                Duyurular sıraya alınır ama hiçbir cihaza ulaşmaz. Ayar
                <code>.env</code> dosyasındaki <code>PUSH_DRIVER=fcm</code> ve
                <code>FCM_CREDENTIALS</code> (Firebase servis hesabı JSON'unun yolu)
                değerleriyle yapılır.
            </div>
        </div>
    @endunless

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-bell-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Duyuru</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Sırada Bekleyen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['pending'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-send-check-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ulaşan Cihaz</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['devices'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-phone-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Kayıtlı Cihaz</span>
                    <h3 class="usr-stat-value" data-count="{{ $devices }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.push-notifications.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts[''] }}</span>
        </a>
        @foreach(PushNotificationStatus::cases() as $case)
            @if($statusCounts[$case->value] > 0)
                <a href="{{ route('admin.push-notifications.index', array_merge(request()->except('page'), ['status' => $case->value])) }}"
                   class="cl-status-tab {{ request('status') === $case->value ? 'active' : '' }}">
                    <span>{{ $case->label() }}</span>
                    <span class="cl-tab-count">{{ $statusCounts[$case->value] }}</span>
                </a>
            @endif
        @endforeach
    </div>

    {{-- SECTION 3: FILTERS --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.push-notifications.index') }}" id="filterForm" class="cl-toolbar">
                {{-- Durum sekmesi seçiliyken süzgeç değiştirmek sekmeden düşürmemeli. --}}
                @if($filters['status'] !== '')
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif

                <div class="cl-search {{ $filters['search'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Başlık veya metin ile ara..." data-fv-ignore>
                    @if($filters['search'] !== '')
                        <a href="{{ route('admin.push-notifications.index', request()->except(['search', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                <div class="cl-filters mt-filters">
                    <div class="mt-field">
                        <span>Hedef</span>
                        <select class="cl-filter-select" name="audience" aria-label="Hedef kitle"
                                data-submit-form="filterForm" data-fv-ignore>
                            <option value="">Tüm hedefler</option>
                            @foreach($audiences as $case)
                                <option value="{{ $case->value }}" {{ $filters['audience'] === $case->value ? 'selected' : '' }}>
                                    {{ $case->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>
                            Başlangıç
                            @if($filters['from'] !== '')
                                <a href="{{ route('admin.push-notifications.index', request()->except(['from', 'page'])) }}"
                                   class="ml-field-clear" title="Başlangıç tarihini temizle" aria-label="Başlangıç tarihini temizle">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </span>
                        <input type="date" class="cl-filter-select" name="from" value="{{ $filters['from'] }}"
                               aria-label="Oluşturulma başlangıç tarihi" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>
                            Bitiş
                            @if($filters['to'] !== '')
                                <a href="{{ route('admin.push-notifications.index', request()->except(['to', 'page'])) }}"
                                   class="ml-field-clear" title="Bitiş tarihini temizle" aria-label="Bitiş tarihini temizle">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </span>
                        <input type="date" class="cl-filter-select" name="to" value="{{ $filters['to'] }}"
                               aria-label="Oluşturulma bitiş tarihi" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>Sıralama</span>
                        <select class="cl-filter-select" name="sort" aria-label="Sıralama"
                                data-submit-form="filterForm" data-fv-ignore>
                            @foreach($sortOptions as $sortValue => $sortLabel)
                                <option value="{{ $sortValue }}" {{ ($filters['sort'] ?: 'recent') === $sortValue ? 'selected' : '' }}>
                                    {{ $sortLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field mt-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            <a href="{{ route('admin.push-notifications.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <div class="cl-per-page">
                                <label for="perPage">Göster:</label>
                                <select name="per_page" id="perPage"
                                        data-submit-form="filterForm" data-fv-ignore>
                                    @foreach($perPageList as $pp)
                                        <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <x-export-menu export="push-notifications" :total="$notifications->total()" />
                        </div>
                    </div>
                </div>
            </form>

            @include('partials.admin.filter-chips', [
                'chips' => $activeFilters,
                'route' => 'admin.push-notifications.index',
            ])
        </div>
    </div>

    {{-- SECTION 4: TABLE --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Duyuru</th>
                            <th class="d-none d-lg-table-cell">Hedef</th>
                            <th class="d-none d-md-table-cell">Durum</th>
                            <th>İlerleme</th>
                            <th class="d-none d-xl-table-cell">Tarih</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notifications as $notification)
                            <tr>
                                <td data-label="Duyuru">
                                    {{-- Başlık ve metin tek hücrede: satır listede gözle
                                         taranırken duyurunun ne olduğu tek bakışta
                                         anlaşılsın. --}}
                                    <div class="cmp-row">
                                        <span class="cmp-row__icon cmp-row__icon--{{ $notification->audience->color() }}">
                                            <i class="bi {{ $notification->audience->icon() }}"></i>
                                        </span>
                                        <span class="cmp-row__text">
                                            <a href="{{ route('admin.push-notifications.show', $notification) }}" class="cmp-row__name">
                                                {{ $notification->title }}
                                            </a>
                                            <span class="cmp-row__subject">{{ \Illuminate\Support\Str::limit($notification->body, 60) }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="d-none d-lg-table-cell" data-label="Hedef">
                                    <span class="sub-source sub-source--{{ $notification->audience->color() }}">
                                        <i class="bi {{ $notification->audience->icon() }}"></i>{{ $notification->audienceLabel() }}
                                    </span>
                                </td>
                                <td class="d-none d-md-table-cell" data-label="Durum">
                                    <span class="menu-manage-tag menu-manage-tag--{{ $notification->status->badgeClass() }}">
                                        {{ $notification->status->label() }}
                                    </span>
                                </td>
                                <td data-label="İlerleme">
                                    @if($notification->total_devices > 0)
                                        <div class="cmp-bar">
                                            <div class="cmp-bar__head">
                                                <span class="cmp-bar__count">
                                                    {{ number_format($notification->sent_count) }} / {{ number_format($notification->total_devices) }}
                                                </span>
                                                <span class="cmp-bar__pct">%{{ $notification->progress() }}</span>
                                            </div>
                                            <div class="progress cmp-progress">
                                                <div class="progress-bar bg-teal cmp-progress__bar" role="progressbar"
                                                     style="--cmp-progress: {{ $notification->progress() }}%"
                                                     aria-valuenow="{{ $notification->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            @if($notification->failed_count > 0)
                                                <span class="cmp-bar__fail">
                                                    <i class="bi bi-exclamation-triangle"></i>{{ number_format($notification->failed_count) }} başarısız
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-clr-secondary">—</span>
                                    @endif
                                </td>
                                <td class="d-none d-xl-table-cell" data-label="Tarih">
                                    <div class="sub-date">
                                        @if($notification->completed_at)
                                            <span>{{ $notification->completed_at->format('d.m.Y H:i') }}</span>
                                            <small>Tamamlandı</small>
                                        @elseif($notification->started_at)
                                            <span>{{ $notification->started_at->format('d.m.Y H:i') }}</span>
                                            <small>Gönderim başladı</small>
                                        @else
                                            <span>{{ $notification->created_at?->format('d.m.Y H:i') }}</span>
                                            <small>Sıraya alındı</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end" data-label="İşlemler">
                                    <div class="usr-actions justify-content-end">
                                        <a href="{{ route('admin.push-notifications.show', $notification) }}" class="usr-action-btn" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('delete', $notification)
                                            <button type="button" class="usr-action-btn danger" title="Sil"
                                                    data-action="sil" data-id="{{ $notification->id }}" data-label="{{ $notification->title }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-bell-slash d-block mb-2 fs-2 text-muted"></i>
                                    @if($hasFilter || $filters['status'] !== '')
                                        {{-- "Kayıt yok" ile "süzgeçle eşleşen yok" farklı
                                             şeyler; ikisini aynı cümleyle söylemek
                                             kullanıcıyı listeyi boş sanmaya iter. --}}
                                        <span class="text-muted">Bu süzgeçle eşleşen duyuru yok.</span>
                                        <br>
                                        <a href="{{ route('admin.push-notifications.index') }}" class="text-teal">Filtreleri temizle</a>
                                    @else
                                        <span class="text-muted">Henüz duyuru gönderilmemiş.</span>
                                        @can('create', App\Models\PushNotification::class)
                                            <br>
                                            <a href="{{ route('admin.push-notifications.create') }}" class="text-teal">İlk duyuruyu oluştur</a>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($notifications->hasPages())
        <div class="cl-pagination-wrapper" data-aos="fade-up">
            <span class="text-clr-secondary">
                {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} / {{ $notifications->total() }} kayıt
            </span>
            {{ $notifications->links('pagination::bootstrap-5') }}
        </div>
    @endif

    {{-- Delete Modal --}}
    <div class="modal fade modal-custom" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-body text-center p-4">
                    <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <h5 class="mt-3">Duyuruyu sil</h5>
                    <p class="text-clr-secondary mb-4"><span id="deletePushName"></span> kaydı silinecek. Cihazlara ulaşmış bildirimler yerinde kalır.</p>
                    <form method="POST" id="deleteForm">
                        @csrf
                        @method('DELETE')
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-danger-solid">Sil</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/push-notifications.js') }}"></script>
@endpush
