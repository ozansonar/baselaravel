@extends('layouts.admin')

@section('title', 'Yönlendirme Yönetimi')
@section('page_title', 'Yönlendirme Yönetimi')
@section('page_description', 'URL yönlendirmelerini yönetin, eski linkleri yeni adreslere yönlendirin')

@section('content')
    @php
        $kullanimEtiketleri = ['used' => 'Kullanılmış', 'unused' => 'Hiç kullanılmamış'];
        $durumEtiketleri = ['active' => 'Etkin', 'inactive' => 'Kapalı'];

        $activeFilters = collect([
            'search'      => ['label' => 'Arama', 'value' => $filters['search']],
            'status_code' => ['label' => 'Kod', 'value' => $filters['status_code']],
            'status'      => ['label' => 'Durum', 'value' => $durumEtiketleri[$filters['status']] ?? ''],
            'usage'       => ['label' => 'Kullanım', 'value' => $kullanimEtiketleri[$filters['usage']] ?? ''],
            'from'        => ['label' => 'Başlangıç', 'value' => $filters['from'] !== '' ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d.m.Y') : ''],
            'to'          => ['label' => 'Bitiş', 'value' => $filters['to'] !== '' ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d.m.Y') : ''],
        ])->filter(fn (array $chip): bool => $chip['value'] !== '');
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yönlendirmeler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Yönlendirme Yönetimi</h1>
            <p class="page-subtitle">Eski URL'leri yeni adreslere yönlendirin, SEO değerinizi koruyun</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($filters['trashed'] === '1')
                <a href="{{ route('admin.redirects.index') }}" class="btn-glass">
                    <i class="bi bi-arrow-left"></i> Listeye dön
                </a>
            @else
                <a href="{{ route('admin.redirects.index', ['trashed' => 1]) }}" class="btn-glass">
                    <i class="bi bi-trash3"></i> Çöp kutusu
                </a>
            @endif
            @can('create', App\Models\Redirect::class)
                <a href="{{ route('admin.redirects.create') }}" class="btn-teal">
                    <i class="bi bi-plus-lg"></i> Yeni Yönlendirme
                </a>
            @endcan
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-signpost-2-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Yönlendirme</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Hit</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_hits'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-lightning-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['today_hits'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: FILTERS & TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.redirects.index') }}" id="filterForm" class="cl-toolbar">
                {{-- Çöp kutusu görünümü süzgeç değiştirirken kaybolmasın. --}}
                @if($filters['trashed'] === '1')
                    <input type="hidden" name="trashed" value="1">
                @endif

                <div class="cl-search {{ $filters['search'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" id="redirectSearch" name="search" value="{{ $filters['search'] }}"
                           placeholder="Eski adres, yeni adres veya not içinde ara..." autocomplete="off"
                           data-validation-engine="validate[maxSize[500]]">
                    @if($filters['search'] !== '')
                        <a href="{{ route('admin.redirects.index', request()->except(['search', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                <div class="cl-filters mt-filters">
                    <div class="mt-field">
                        <span>Durum kodu</span>
                        <select class="cl-filter-select" name="status_code" aria-label="Durum kodu"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($filters['status_code'] === (string) $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Etkinlik</span>
                        <select class="cl-filter-select" name="status" aria-label="Etkinlik durumu"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Etkin</option>
                            <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>Kapalı</option>
                        </select>
                    </div>

                    {{-- Hiç kullanılmamış yönlendirme ya yanlış yazılmıştır ya da
                         artık gereksizdir; ikisi de gözden geçirilmeye değer. --}}
                    <div class="mt-field">
                        <span>Kullanım</span>
                        <select class="cl-filter-select" name="usage" aria-label="Kullanım durumu"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            <option value="used" {{ $filters['usage'] === 'used' ? 'selected' : '' }}>Kullanılmış</option>
                            <option value="unused" {{ $filters['usage'] === 'unused' ? 'selected' : '' }}>Hiç kullanılmamış</option>
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Eklenme (baş.)</span>
                        <input type="date" name="from" class="cl-filter-select" value="{{ $filters['from'] }}"
                               aria-label="Eklenme başlangıç tarihi"
                               onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>Eklenme (bitiş)</span>
                        <input type="date" name="to" class="cl-filter-select" value="{{ $filters['to'] }}"
                               aria-label="Eklenme bitiş tarihi"
                               onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
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
                            @if($filtered)
                                <a href="{{ route('admin.redirects.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            @endif
                            <div class="cl-per-page">
                                <label for="perPage">Göster:</label>
                                <select name="per_page" id="perPage"
                                        onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                                    @foreach($perPageList as $pp)
                                        <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <x-export-menu export="redirects" :total="$redirects->total()" />
                        </div>
                    </div>
                </div>
            </form>

            @include('partials.admin.filter-chips', [
                'chips' => $activeFilters,
                'route' => 'admin.redirects.index',
            ])
        </div>
    </div>

    @if($filters['trashed'] === '1')
        <div class="alert alert-warning" data-aos="fade-up">
            <i class="bi bi-trash3 me-1"></i>
            <strong>Çöp kutusu.</strong> Silinmiş yönlendirmeler burada duruyor ve çalışmıyor;
            geri yüklediğinizde adres yeniden yönlenmeye başlar.
        </div>
    @else
        <div class="alert alert-info" data-aos="fade-up">
            <i class="bi bi-info-circle me-1"></i>
            Bir adres taşındığında ya da silindiğinde buraya bir kayıt ekleyin: ziyaretçi ve arama motoru
            eski bağlantıyı açtığında boş sayfa yerine doğru yere gider.
            <strong>Kullanım</strong> sütunu o yönlendirmenin kaç kez çalıştığını gösterir — hiç
            kullanılmamış bir kayıt genelde yanlış yazılmış bir adrestir.
        </div>
    @endif

    {{-- ==================== SECTION 3: TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">#</th>
                            <th>Eski URL</th>
                            <th>Yeni URL</th>
                            <th class="d-none d-md-table-cell">Durum Kodu</th>
                            <th class="d-none d-lg-table-cell">Hit</th>
                            <th class="d-none d-lg-table-cell">Son Hit</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($redirects as $redirect)
                            <tr>
                                <td class="cl-td-checkbox">{{ $redirect->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="redirect-url-code">{{ $redirect->old_url }}</code>
                                        <button type="button" class="btn-icon-xs js-copy-url" data-url="{{ $redirect->old_url }}" title="Kopyala">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    @if($redirect->note)
                                        <small class="text-clr-muted d-block mt-1">{{ $redirect->note }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($redirect->new_url)
                                        <code class="redirect-url-code">{{ $redirect->new_url }}</code>
                                    @else
                                        <span class="text-clr-muted">—</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="redirect-badge redirect-badge-{{ $redirect->status_code?->value }}"
                                          title="{{ $redirect->status_code?->label() }}">
                                        {{ $redirect->status_code?->value }}
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="fw-semibold">{{ number_format($redirect->hit_count) }}</span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if($redirect->last_hit_at)
                                        <span title="{{ $redirect->last_hit_at->format('d.m.Y H:i') }}">
                                            {{ $redirect->last_hit_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-clr-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input js-toggle-active"
                                               type="checkbox"
                                               data-id="{{ $redirect->id }}"
                                               data-url="{{ route('admin.redirects.toggle-active', $redirect) }}"
                                               {{ $redirect->is_active ? 'checked' : '' }} data-fv-ignore>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="usr-actions justify-content-end">
                                        @if($redirect->trashed())
                                            <form action="{{ route('admin.redirects.restore', $redirect->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="usr-action-btn success" title="Geri yükle">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
                                        @else
                                            @can('update', $redirect)
                                                <a href="{{ route('admin.redirects.edit', $redirect) }}"
                                                   class="usr-action-btn" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan
                                            @can('delete', $redirect)
                                                <form action="{{ route('admin.redirects.destroy', $redirect) }}" method="POST" class="js-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="usr-action-btn danger"
                                                            data-confirm="Bu yönlendirmeyi silmek istediğinize emin misiniz?"
                                                            title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="cl-empty-state">
                                        <i class="bi bi-signpost-2 cl-empty-icon"></i>
                                        @if($filtered)
                                            <h5>Bu süzgeçle eşleşen yönlendirme yok</h5>
                                            <a href="{{ route('admin.redirects.index') }}" class="btn-glass btn-sm mt-2">
                                                <i class="bi bi-arrow-counterclockwise"></i> Süzgeçleri sıfırla
                                            </a>
                                        @else
                                            <h5>Henüz yönlendirme yok</h5>
                                            <p class="text-clr-muted">Yeni bir yönlendirme ekleyerek başlayın</p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sayfalama: ortak parça yalnızca birden çok sayfa varken çiziliyor,
         süzgeç sonucu tek sayfaya sığdığında da sayı görünmeli. --}}
    @if($redirects->hasPages())
        @include('partials.admin.pagination', ['paginator' => $redirects, 'itemLabel' => 'yönlendirme'])
    @elseif($redirects->total() > 0)
        <div class="cl-pagination-wrapper" data-aos="fade-up">
            <div class="cl-pagination-info">
                <span>
                    <strong>{{ number_format($redirects->total(), 0, ',', '.') }}</strong> yönlendirme
                    @if($filtered)
                        <span class="text-clr-secondary">({{ number_format($stats['total'], 0, ',', '.') }} kayıttan süzüldü)</span>
                    @endif
                </span>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script src="{{ asset('assets/admin/js/redirects.js') }}"></script>
@endpush
