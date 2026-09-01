@extends('layouts.admin')

@section('title', 'Genel İçerik Listesi')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Genel İçerik Listesi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Genel İçerik Listesi</h1>
            <p class="page-subtitle">Blog, sayfa, galeri ve SSS içeriklerinin tamamı tek listede</p>
        </div>
    </div>

    {{-- SECTION 1: TÜR SEKMELERİ --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.content-list.index', request()->except(['type', 'page'])) }}"
           class="cl-status-tab {{ ! request('type') ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $counts['all'] }}</span>
        </a>
        @foreach($types as $key => $label)
            <a href="{{ route('admin.content-list.index', array_merge(request()->except('page'), ['type' => $key])) }}"
               class="cl-status-tab {{ request('type') === $key ? 'active' : '' }}">
                <span>{{ $label }}</span>
                <span class="cl-tab-count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    {{-- SECTION 2: FİLTRELER --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.content-list.index') }}" id="filterForm" class="cl-toolbar">
                @if(request('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif

                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Başlık ile ara..." data-fv-ignore>
                </div>

                <div class="cl-filters">
                    <select class="cl-filter-select" name="status" data-submit-form="filterForm" data-fv-ignore>
                        <option value="">Tüm Durumlar</option>
                        <option value="published" @selected(request('status') === 'published')>Yayında</option>
                        <option value="draft" @selected(request('status') === 'draft')>Yayında değil</option>
                    </select>

                    <select class="cl-filter-select" name="locale" data-submit-form="filterForm" data-fv-ignore>
                        <option value="">Tüm Diller</option>
                        @foreach($languages as $language)
                            <option value="{{ $language->code }}" @selected(request('locale') === $language->code)>
                                {{ $language->name }}
                            </option>
                        @endforeach
                    </select>

                    <input type="date" class="cl-filter-select" name="from" value="{{ request('from') }}"
                           data-submit-form="filterForm"
                           title="Başlangıç tarihi" data-fv-ignore>

                    <input type="date" class="cl-filter-select" name="to" value="{{ request('to') }}"
                           data-submit-form="filterForm"
                           title="Bitiş tarihi" data-fv-ignore>
                </div>

                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.content-list.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" data-submit-form="filterForm" data-fv-ignore>
                            @foreach($perPages as $value)
                                <option value="{{ $value }}" @selected($perPage === $value)>{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-export-menu export="content-list" :total="$items->total()" />
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 3: TABLO --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="table table-hover cl-table mb-0">
                    <thead>
                        <tr>
                            <th>Başlık</th>
                            <th class="d-none d-md-table-cell">Tür</th>
                            <th class="d-none d-lg-table-cell">Dil</th>
                            <th>Durum</th>
                            <th class="d-none d-lg-table-cell">Oluşturulma</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>
                                    <span class="cl-content-title">{{ \Illuminate\Support\Str::limit((string) $item->title, 60) }}</span>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="cl-category-badge">{{ $types[$item->type] ?? $item->type }}</span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="usr-meta text-uppercase">{{ $item->locale }}</span>
                                </td>
                                <td>
                                    <span class="usr-status-badge {{ $item->status === 'published' ? 'active' : 'pending' }}">
                                        {{ $item->status === 'published' ? 'Yayında' : 'Yayında değil' }}
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d.m.Y H:i') }}</span>
                                </td>
                                <td>
                                    <div class="usr-actions">
                                        @if(isset($routes[$item->type]) && Route::has($routes[$item->type]))
                                            <a href="{{ route($routes[$item->type], $item->id) }}"
                                               class="usr-action-btn" title="Kaydı aç">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <span class="usr-meta">Bu süzgeçlere uyan içerik yok.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- SECTION 4: SAYFALAMA --}}
    @include('partials.admin.pagination', ['paginator' => $items, 'itemLabel' => 'içerik'])

@endsection
