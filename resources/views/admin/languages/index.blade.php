@extends('layouts.admin')

@section('title', 'Diller')
@section('page_title', 'Diller')
@section('page_description', 'Sitenin yayınlandığı dilleri yönetin')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item active text-teal">Diller</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Diller</h1>
            <p class="page-subtitle">Sitenin yayınlandığı dilleri yönetin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('viewAny', App\Models\Translation::class)
                <a href="{{ route('admin.translations.index') }}" class="btn-glass">
                    <i class="bi bi-fonts"></i> Dil Yazıları
                </a>
            @endcan
            @can('create', App\Models\Language::class)
                <a href="{{ route('admin.languages.create') }}" class="btn-teal">
                    <i class="bi bi-plus-lg"></i> Dil Ekle
                </a>
            @endcan
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-translate"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Tanımlı Dil</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Yayında</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-pause-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Pasif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['inactive'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-star-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Varsayılan</span>
                    <h3 class="usr-stat-value">{{ strtoupper($stats['default'] ?? '—') }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mb-4" data-aos="fade-up" data-aos-delay="60">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Varsayılan dil</strong> her zaman bir tanedir; pasife alınamaz ve silinemez.
        Tarayıcı diline uyan bir dil yoksa ziyaretçi bu dili görür, çevrilmemiş içerik de
        bu dilden gösterilir.
    </div>

    {{-- SECTION 2: STATUS TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="80">
        <a href="{{ route('admin.languages.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $stats['total'] }}</span>
        </a>
        <a href="{{ route('admin.languages.index', array_merge(request()->except('page'), ['status' => 'active'])) }}"
           class="cl-status-tab {{ request('status') === 'active' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Yayında</span>
            <span class="cl-tab-count">{{ $stats['active'] }}</span>
        </a>
        <a href="{{ route('admin.languages.index', array_merge(request()->except('page'), ['status' => 'inactive'])) }}"
           class="cl-status-tab {{ request('status') === 'inactive' ? 'active' : '' }}">
            <i class="bi bi-pause-circle text-neon-orange"></i>
            <span>Pasif</span>
            <span class="cl-tab-count">{{ $stats['inactive'] }}</span>
        </a>
    </div>

    {{-- SECTION 3: FILTERS --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="120">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.languages.index') }}" id="filterForm" class="cl-toolbar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif

                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="search" value="{{ request('search') }}"
                           placeholder="Dil adı veya kodu ile ara…" autocomplete="off" data-fv-ignore>
                </div>

                <div class="cl-filters">
                    <select class="cl-filter-select" name="files" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                        <option value="">Arayüz çevirisi: tümü</option>
                        <option value="yes" {{ request('files') === 'yes' ? 'selected' : '' }}>Çevirisi olanlar</option>
                        <option value="no" {{ request('files') === 'no' ? 'selected' : '' }}>Çevirisi olmayanlar</option>
                    </select>
                </div>

                <div class="cl-toolbar-actions">
                    <button type="submit" class="btn-glass"><i class="bi bi-funnel"></i> Filtrele</button>
                    @if($filtered)
                        <a href="{{ route('admin.languages.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 4: TABLE --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="160">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Dil</th>
                            <th>Kod</th>
                            <th class="d-none d-md-table-cell">Durum</th>
                            <th class="d-none d-lg-table-cell">Arayüz Çevirisi</th>
                            <th class="d-none d-lg-table-cell">İçerik</th>
                            <th class="d-none d-xl-table-cell">Sıra</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($languages as $language)
                            @php
                                $hasFiles = in_array($language->code, $translated, true);
                                $contentCount = $contentStats[$language->code] ?? 0;
                            @endphp
                            <tr>
                                <td data-label="Dil">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-5">{{ $language->flag }}</span>
                                        <div>
                                            <span class="fw-semibold d-block">{{ $language->native_name ?: $language->name }}</span>
                                            @if($language->native_name && $language->native_name !== $language->name)
                                                <small class="text-clr-secondary">{{ $language->name }}</small>
                                            @endif
                                        </div>
                                        @if($language->is_default)
                                            <span class="menu-manage-tag menu-manage-tag--info ms-1">
                                                <i class="bi bi-star-fill"></i> Varsayılan
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Kod"><code>{{ $language->code }}</code></td>
                                <td data-label="Durum" class="d-none d-md-table-cell">
                                    @if($language->is_active)
                                        <span class="menu-manage-tag menu-manage-tag--success">Yayında</span>
                                    @else
                                        <span class="menu-manage-tag menu-manage-tag--muted">Pasif</span>
                                    @endif
                                </td>
                                <td data-label="Arayüz Çevirisi" class="d-none d-lg-table-cell">
                                    @if($hasFiles)
                                        <span class="text-neon-green"><i class="bi bi-check-circle"></i> var</span>
                                    @else
                                        <span class="text-neon-orange" title="lang/{{ $language->code }}/ klasörü yok">
                                            <i class="bi bi-exclamation-triangle"></i> yok
                                        </span>
                                    @endif
                                </td>
                                <td data-label="İçerik" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ number_format($contentCount) }} kayıt</span>
                                </td>
                                <td data-label="Sıra" class="d-none d-xl-table-cell">
                                    <span class="usr-meta">{{ $language->sort_order }}</span>
                                </td>
                                <td data-label="İşlem">
                                    {{-- usr-actions is the flex wrapper. Without it each form is
                                         a block element and the buttons stack vertically. --}}
                                    <div class="usr-actions justify-content-end">
                                        @can('update', $language)
                                            @unless($language->is_default)
                                                <form method="POST" action="{{ route('admin.languages.default', $language) }}">
                                                    @csrf
                                                    <button type="submit" class="usr-action-btn" title="Varsayılan yap">
                                                        <i class="bi bi-star"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                            <a href="{{ route('admin.languages.edit', $language) }}"
                                               class="usr-action-btn" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('delete', $language)
                                            @unless($language->is_default)
                                                <button type="button" class="usr-action-btn danger" title="Sil"
                                                        onclick="openLanguageDelete({{ $language->id }}, @js($language->native_name ?: $language->name), {{ $contentCount }})">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @endunless
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-translate d-block mb-2" style="font-size: 2rem;"></i>
                                    <p class="mb-0 text-clr-secondary">
                                        {{ $filtered ? 'Bu filtreyle eşleşen dil yok.' : 'Henüz dil tanımlanmamış.' }}
                                    </p>
                                    @if($filtered)
                                        <a href="{{ route('admin.languages.index') }}" class="btn-glass btn-sm mt-3">
                                            <i class="bi bi-arrow-counterclockwise"></i> Filtreleri sıfırla
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="cl-pagination-wrapper mb-4" data-aos="fade-up">
        <span class="text-clr-secondary">
            {{ $languages->count() }} / {{ $stats['total'] }} dil gösteriliyor
        </span>
    </div>

    @can('create', App\Models\Language::class)
        <div class="modal fade modal-custom" id="deleteLanguageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <div class="modal-body text-center p-4">
                        <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <h5 class="mt-3">Dili sil</h5>
                        <p class="text-clr-secondary mb-2"><strong id="deleteLanguageName"></strong> silinecek.</p>
                        <p class="mb-4" id="deleteLanguageWarning"></p>
                        <form method="POST" id="deleteLanguageForm">
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
    @endcan
@endsection

@push('scripts')
<script>
    window.languageDeleteUrl = @js(route('admin.languages.destroy', ['language' => 'LANGUAGE_ID']));
</script>
<script src="{{ versioned_asset('assets/admin/js/languages.js') }}"></script>
@endpush
