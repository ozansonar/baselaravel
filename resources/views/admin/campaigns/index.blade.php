@extends('layouts.admin')

@section('title', 'Mail Kampanyaları')
@section('page_title', 'Mail Kampanyaları')
@section('page_description', 'Toplu mail gönderimlerini oluşturun, zamanlayın ve takip edin')

@section('content')
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
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Kampanya adı veya konu ile ara..." data-fv-ignore>
                </div>

                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.campaigns.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            @foreach($perPageList as $pp)
                                <option value="{{ $pp }}" {{ (int) request('per_page', 15) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
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
                                <td>
                                    <a href="{{ route('admin.campaigns.show', $campaign) }}" class="fw-semibold text-teal d-block">
                                        {{ $campaign->name }}
                                    </a>
                                    <small class="text-clr-secondary">{{ \Illuminate\Support\Str::limit($campaign->subject, 60) }}</small>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <i class="bi {{ $campaign->audience->icon() }} me-1"></i>{{ $campaign->audience->label() }}
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="menu-manage-tag menu-manage-tag--{{ $campaign->status->badgeClass() }}">
                                        {{ $campaign->status->label() }}
                                    </span>
                                </td>
                                <td>
                                    @if($campaign->total_recipients > 0)
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-teal" role="progressbar"
                                                 style="width: {{ $campaign->progress() }}%"
                                                 aria-valuenow="{{ $campaign->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-clr-secondary">
                                            {{ number_format($campaign->sent_count) }} / {{ number_format($campaign->total_recipients) }}
                                            @if($campaign->failed_count > 0)
                                                <span class="text-neon-red">({{ $campaign->failed_count }} hata)</span>
                                            @endif
                                        </small>
                                    @else
                                        <small class="text-clr-secondary">—</small>
                                    @endif
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    <small class="text-clr-secondary">
                                        @if($campaign->scheduled_at)
                                            <i class="bi bi-clock me-1"></i>{{ $campaign->scheduled_at->format('d.m.Y H:i') }}
                                        @else
                                            {{ $campaign->created_at?->format('d.m.Y H:i') }}
                                        @endif
                                    </small>
                                </td>
                                <td class="text-end">
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
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-megaphone d-block mb-2" style="font-size: 2rem;"></i>
                                    Henüz kampanya oluşturulmamış.
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
