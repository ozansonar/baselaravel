@extends('layouts.admin')

@section('title', 'Hashtag Havuzu')
@section('page_title', 'Hashtag Havuzu')
@section('page_description', 'Instagram AI caption üretiminde kullanılacak hashtag listesi')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Hashtag Havuzu</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-hash me-2"></i>Hashtag Havuzu</h1>
            <p class="page-subtitle">
                AI caption üretirken bu havuzdaki <strong>aktif</strong> hashtag'ler context'e verilir.
                Manuel ekle, kategorile, kullanım istatistiklerini gör.
            </p>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle me-1"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}</div>
    @endif

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-hash"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam</span>
                    <h3 class="usr-stat-value">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif</span>
                    <h3 class="usr-stat-value">{{ $stats['active'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-fire"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">En Çok Kullanılan</span>
                    <h3 class="usr-stat-value">
                        @if($stats['most_used'])
                            #{{ Str::limit($stats['most_used']->tag, 14) }}
                        @else — @endif
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-snow"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Hiç Kullanılmamış</span>
                    <h3 class="usr-stat-value">{{ $stats['never_used'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ADD FORMS --}}
    <div class="row g-4 mb-4">
        {{-- Tek hashtag ekle --}}
        <div class="col-lg-5">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-plus-circle"></i></div>
                        <div>
                            <h6 class="mb-0">Tek Hashtag Ekle</h6>
                            <small class="text-muted">Hızlı ekleme — # yazma, sadece kelime</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <form method="POST" action="{{ route('admin.hashtag-pool.store') }}" class="d-flex gap-2 flex-wrap">
                        @csrf
                        <input type="text" name="tag" class="form-control flex-grow-1" placeholder="Ör: organikgıda" required style="min-width:140px;">
                        <select name="category" class="form-control" style="max-width:130px;">
                            @foreach($categoryLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-teal"><i class="bi bi-plus-lg"></i></button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Toplu ekle --}}
        <div class="col-lg-7">
            <div class="card-dark h-100">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-collection"></i></div>
                        <div>
                            <h6 class="mb-0">Toplu Hashtag Ekle</h6>
                            <small class="text-muted">Boşluk veya virgülle ayrılmış liste yapıştır</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <form method="POST" action="{{ route('admin.hashtag-pool.bulk-store') }}">
                        @csrf
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <select name="category" class="form-control" style="max-width:160px;">
                                @foreach($categoryLabels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }} kategorisi</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-teal ms-auto"><i class="bi bi-cloud-upload me-1"></i> Tümünü Ekle</button>
                        </div>
                        <textarea name="tags" rows="2" class="form-control" placeholder="organik doğal köyürünleri, çorum, sağlıklı, glütensiz..." required></textarea>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTERS --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.hashtag-pool.index') }}" class="cl-toolbar">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Hashtag ara...">
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="category" onchange="this.form.submit()">
                        <option value="">Tüm Kategoriler</option>
                        @foreach($categoryLabels as $key => $label)
                            <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cl-toolbar-actions">
                    <button type="submit" class="btn-glass"><i class="bi bi-funnel"></i> Filtrele</button>
                    @if($search !== '' || $category !== '')
                        <a href="{{ route('admin.hashtag-pool.index') }}" class="cl-filter-reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Hashtag</th>
                            <th>Kategori</th>
                            <th class="text-end">Kullanım</th>
                            <th class="d-none d-md-table-cell">Son Kullanım</th>
                            <th>Durum</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($hashtags as $h)
                            <tr>
                                <td>
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">#{{ $h->tag }}</span>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.hashtag-pool.update', $h) }}" class="d-inline-block">
                                        @csrf
                                        @method('PUT')
                                        <select name="category" class="cl-filter-select" onchange="this.form.submit()" style="min-width:130px;">
                                            @foreach($categoryLabels as $key => $label)
                                                <option value="{{ $key }}" {{ $h->category === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end">
                                    @if($h->usage_count > 0)
                                        <strong class="text-light">{{ number_format($h->usage_count, 0, ',', '.') }}</strong>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell text-muted small">
                                    @if($h->last_used_at)
                                        {{ $h->last_used_at->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($h->is_active)
                                        <span class="usr-status-badge active"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                                    @else
                                        <span class="usr-status-badge inactive"><i class="bi bi-pause-circle me-1"></i>Pasif</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="usr-actions">
                                        <form method="POST" action="{{ route('admin.hashtag-pool.toggle', $h) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="usr-action-btn" title="{{ $h->is_active ? 'Pasifleştir' : 'Aktifleştir' }}">
                                                <i class="bi {{ $h->is_active ? 'bi-pause' : 'bi-play' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.hashtag-pool.destroy', $h) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="usr-action-btn danger" title="Sil" onclick="return confirm('Silinsin mi?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-hash d-block fs-1 mb-2"></i>
                                    <p class="mb-1">Hashtag yok.</p>
                                    <small>Yukarıdaki forma birkaç tane ekleyerek başla.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $hashtags, 'itemLabel' => 'hashtag'])
    </div>

@endsection
