@extends('layouts.admin')

@section('title', 'Mail Kampanyaları')
@section('page_title', 'Mail Kampanyaları')
@section('page_description', 'Toplu mail gönderimlerini oluşturun, zamanlayın ve takip edin')

@section('content')
    @php
        use App\Enums\CampaignAudience;
        use App\Enums\CampaignStatus;

        // Durum sekmesi kendi göstergesi; rozetlerde tekrar edilmiyor.
        $chipFilters = collect($filters)->except(['status', 'sort']);
        $hasFilter = $chipFilters->filter(fn ($value) => (string) $value !== '')->isNotEmpty();

        $activeFilters = collect([
            'search' => ['label' => 'Arama', 'value' => $filters['search']],
            'audience' => [
                'label' => 'Kitle',
                'value' => $filters['audience'] !== ''
                    ? (CampaignAudience::tryFrom($filters['audience'])?->label() ?? '')
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
            <li class="breadcrumb-item active text-teal">Mail Kampanyaları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Mail Kampanyaları</h1>
            <p class="page-subtitle">Toplu mail gönderimlerini oluşturun, zamanlayın ve takip edin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('viewAny', App\Models\Subscriber::class)
                <a href="{{ route('admin.subscribers.index') }}" class="btn-glass">
                    <i class="bi bi-people"></i> Mail Listesi
                </a>
            @endcan
            @can('create', App\Models\Campaign::class)
                <a href="{{ route('admin.campaigns.create') }}" class="btn-teal">
                    <i class="bi bi-plus-lg"></i> Yeni Kampanya
                </a>
            @endcan
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-megaphone-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Kampanya</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-send-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Gönderimde</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['sending'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-envelope-check-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Gönderilen Mail</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['sent'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Sırada Bekleyen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['pending'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.campaigns.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $statusCounts[''] }}</span>
        </a>
        @foreach(App\Enums\CampaignStatus::cases() as $case)
            @if($statusCounts[$case->value] > 0)
                <a href="{{ route('admin.campaigns.index', array_merge(request()->except('page'), ['status' => $case->value])) }}"
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
            <form method="GET" action="{{ route('admin.campaigns.index') }}" id="filterForm" class="cl-toolbar">
                {{-- Durum sekmesi seçiliyken süzgeç değiştirmek sekmeden düşürmemeli. --}}
                @if($filters['status'] !== '')
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif

                <div class="cl-search {{ $filters['search'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Kampanya adı veya konu ile ara..." data-fv-ignore>
                    @if($filters['search'] !== '')
                        <a href="{{ route('admin.campaigns.index', request()->except(['search', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                <div class="cl-filters mt-filters">
                    <div class="mt-field">
                        <span>Kitle</span>
                        <select class="cl-filter-select" name="audience" aria-label="Alıcı kitlesi"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tüm kitleler</option>
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
                                <a href="{{ route('admin.campaigns.index', request()->except(['from', 'page'])) }}"
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
                                <a href="{{ route('admin.campaigns.index', request()->except(['to', 'page'])) }}"
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
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
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
                            <a href="{{ route('admin.campaigns.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <div class="cl-per-page">
                                <label for="perPage">Göster:</label>
                                <select name="per_page" id="perPage"
                                        onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                                    @foreach($perPageList as $pp)
                                        <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <x-export-menu export="campaigns" :total="$campaigns->total()" />
                        </div>
                    </div>
                </div>
            </form>

            @include('partials.admin.filter-chips', [
                'chips' => $activeFilters,
                'route' => 'admin.campaigns.index',
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
                            <th>Kampanya</th>
                            <th class="d-none d-lg-table-cell">Alıcı Kitlesi</th>
                            <th class="d-none d-md-table-cell">Durum</th>
                            <th>İlerleme</th>
                            <th class="d-none d-xl-table-cell">Tarih</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaigns as $campaign)
                            <tr>
                                <td data-label="Kampanya">
                                    {{-- Ad ve konu tek hücrede, önünde kitleyi anlatan
                                         bir rozetle: satır listede gözle taranırken
                                         kampanyanın ne olduğu tek bakışta anlaşılsın. --}}
                                    <div class="cmp-row">
                                        <span class="cmp-row__icon cmp-row__icon--{{ $campaign->audience->color() }}">
                                            <i class="bi {{ $campaign->audience->icon() }}"></i>
                                        </span>
                                        <span class="cmp-row__text">
                                            <a href="{{ route('admin.campaigns.show', $campaign) }}" class="cmp-row__name">
                                                {{ $campaign->name }}
                                            </a>
                                            <span class="cmp-row__subject">{{ \Illuminate\Support\Str::limit($campaign->subject, 60) }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="d-none d-lg-table-cell" data-label="Alıcı Kitlesi">
                                    <span class="sub-source sub-source--{{ $campaign->audience->color() }}">
                                        <i class="bi {{ $campaign->audience->icon() }}"></i>{{ $campaign->audience->label() }}
                                    </span>
                                </td>
                                <td class="d-none d-md-table-cell" data-label="Durum">
                                    <span class="menu-manage-tag menu-manage-tag--{{ $campaign->status->badgeClass() }}">
                                        {{ $campaign->status->label() }}
                                    </span>
                                </td>
                                <td data-label="İlerleme">
                                    @if($campaign->total_recipients > 0)
                                        <div class="cmp-bar">
                                            <div class="cmp-bar__head">
                                                <span class="cmp-bar__count">
                                                    {{ number_format($campaign->sent_count) }} / {{ number_format($campaign->total_recipients) }}
                                                </span>
                                                <span class="cmp-bar__pct">%{{ $campaign->progress() }}</span>
                                            </div>
                                            <div class="progress cmp-progress">
                                                <div class="progress-bar bg-teal cmp-progress__bar" role="progressbar"
                                                     style="--cmp-progress: {{ $campaign->progress() }}%"
                                                     aria-valuenow="{{ $campaign->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            @if($campaign->failed_count > 0)
                                                <span class="cmp-bar__fail">
                                                    <i class="bi bi-exclamation-triangle"></i>{{ number_format($campaign->failed_count) }} başarısız
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-clr-secondary">—</span>
                                    @endif
                                </td>
                                <td class="d-none d-xl-table-cell" data-label="Tarih">
                                    {{-- Hangi tarih olduğu yazmıyordu: zamanlanmış bir
                                         kampanyanın gönderim saati ile oluşturulma günü
                                         aynı sütunda ayırt edilemiyordu. --}}
                                    <div class="sub-date">
                                        @if($campaign->scheduled_at)
                                            <span><i class="bi bi-clock me-1"></i>{{ $campaign->scheduled_at->format('d.m.Y H:i') }}</span>
                                            <small>Gönderim için planlandı</small>
                                        @elseif($campaign->completed_at)
                                            <span>{{ $campaign->completed_at->format('d.m.Y H:i') }}</span>
                                            <small>Tamamlandı</small>
                                        @else
                                            <span>{{ $campaign->created_at?->format('d.m.Y H:i') }}</span>
                                            <small>Oluşturuldu</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end" data-label="İşlemler">
                                    {{-- Sarmalayıcı yoktu, düğmeler alt alta düşüyordu. --}}
                                    <div class="usr-actions justify-content-end">
                                    <a href="{{ route('admin.campaigns.show', $campaign) }}" class="usr-action-btn" title="Detay">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($campaign->isEditable())
                                        @can('update', $campaign)
                                            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="usr-action-btn" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                    @endif
                                    @can('delete', $campaign)
                                        <button type="button" class="usr-action-btn danger" title="Sil"
                                                onclick="openDeleteModal({{ $campaign->id }}, @js($campaign->name))">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-megaphone d-block mb-2 fs-2 text-muted"></i>
                                    @if($hasFilter || $filters['status'] !== '')
                                        {{-- "Kayıt yok" ile "süzgeçle eşleşen yok" farklı
                                             şeyler; ikisini aynı cümleyle söylemek
                                             kullanıcıyı listeyi boş sanmaya itiyordu. --}}
                                        <span class="text-muted">Bu süzgeçle eşleşen kampanya yok.</span>
                                        <br>
                                        <a href="{{ route('admin.campaigns.index') }}" class="text-teal">Filtreleri temizle</a>
                                    @else
                                        <span class="text-muted">Henüz kampanya oluşturulmamış.</span>
                                        @can('create', App\Models\Campaign::class)
                                            <br>
                                            <a href="{{ route('admin.campaigns.create') }}" class="text-teal">İlk kampanyayı oluştur</a>
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

    @if($campaigns->hasPages())
        <div class="cl-pagination-wrapper" data-aos="fade-up">
            <span class="text-clr-secondary">
                {{ $campaigns->firstItem() }}–{{ $campaigns->lastItem() }} / {{ $campaigns->total() }} kayıt
            </span>
            {{ $campaigns->links('pagination::bootstrap-5') }}
        </div>
    @endif

    {{-- Delete Modal --}}
    <div class="modal fade modal-custom" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-body text-center p-4">
                    <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <h5 class="mt-3">Kampanyayı sil</h5>
                    <p class="text-clr-secondary mb-4"><span id="deleteCampaignName"></span> silinecek. Gönderilmiş mailler kayıtlarda kalır.</p>
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
    <script src="{{ versioned_asset('assets/admin/js/campaigns.js') }}"></script>
@endpush
