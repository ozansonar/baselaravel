@extends('layouts.admin')

@section('title', 'AI İçerik Logları')
@section('page_title', 'AI İçerik Logları')
@section('page_description', 'Gemini AI ile oluşturulan blog içeriklerinin detaylı logları')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI İçerik Logları</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">AI İçerik Logları</h1>
            <p class="page-subtitle">Gemini AI ile oluşturulan blog içeriklerinin detaylı logları</p>
        </div>
    </div>

    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam İstek</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarılı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['success'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarısız</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['failed'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['today'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: STATUS TABS ==================== -->
    @php
        $currentStatus = request('status', '');
        $totalCount = $stats['total'];

        $statusTabs = [
            '' => ['label' => 'Tümü', 'icon' => '', 'color' => '', 'count' => $totalCount],
            'success' => ['label' => 'Başarılı', 'icon' => 'bi-check-circle', 'color' => 'text-neon-green', 'count' => $statusCounts['success'] ?? 0],
            'failed' => ['label' => 'Başarısız', 'icon' => 'bi-x-circle', 'color' => 'text-neon-red', 'count' => $statusCounts['failed'] ?? 0],
        ];
    @endphp

    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        @foreach($statusTabs as $statusValue => $tab)
            <a href="{{ route('admin.ai-logs.index', array_merge(request()->except(['status', 'page']), $statusValue ? ['status' => $statusValue] : [])) }}"
               class="cl-status-tab {{ $currentStatus === $statusValue ? 'active' : '' }}">
                @if($tab['icon'])
                    <i class="bi {{ $tab['icon'] }} {{ $tab['color'] }}"></i>
                @endif
                <span>{{ $tab['label'] }}</span>
                <span class="cl-tab-count">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>


    <!-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.ai-logs.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="aiLogSearch" placeholder="Ürün adı veya hata mesajı ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="source" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Kaynaklar</option>
                        <option value="product_fill"   {{ request('source') === 'product_fill'   ? 'selected' : '' }}>Ürün Doldurma</option>
                        <option value="blog_fill"      {{ request('source') === 'blog_fill'      ? 'selected' : '' }}>Blog Doldurma</option>
                        <option value="blog_scheduled" {{ request('source') === 'blog_scheduled' ? 'selected' : '' }}>Otomatik Blog</option>
                        <option value="page_fill"      {{ request('source') === 'page_fill'      ? 'selected' : '' }}>Sayfa Doldurma</option>
                    </select>
                    <select class="cl-filter-select" name="date_filter" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Tarihler</option>
                        <option value="today"   {{ request('date_filter') === 'today'   ? 'selected' : '' }}>Bugün</option>
                        <option value="week"    {{ request('date_filter') === 'week'    ? 'selected' : '' }}>Bu Hafta</option>
                        <option value="month"   {{ request('date_filter') === 'month'   ? 'selected' : '' }}>Bu Ay</option>
                        <option value="quarter" {{ request('date_filter') === 'quarter' ? 'selected' : '' }}>Son 3 Ay</option>
                    </select>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.ai-logs.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" onchange="document.getElementById('filterForm').submit()">
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- ==================== SECTION 4: LOGS TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Ürün / Başlık</th>
                            <th class="d-none d-md-table-cell">Seçilen Ürün</th>
                            <th>Durum</th>
                            <th class="d-none d-sm-table-cell">Kaynak</th>
                            <th class="d-none d-md-table-cell">Süre</th>
                            <th class="d-none d-lg-table-cell">Model</th>
                            <th class="d-none d-xl-table-cell">Deneme</th>
                            <th class="d-none d-lg-table-cell">Token</th>
                            <th class="d-none d-lg-table-cell">Blog Yazısı</th>
                            <th>Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td data-label="Ürün">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $log->product_name ?? '-' }}</span>
                                        <span class="cl-content-meta">
                                            @if($log->city_name)
                                                <i class="bi bi-geo-alt-fill text-teal me-1"></i>{{ $log->city_name }}
                                            @elseif($log->source && $log->source->value === 'blog_scheduled')
                                                <i class="bi bi-globe me-1"></i><span class="text-muted">Genel yazı</span>
                                            @endif
                                        </span>
                                        @if($log->error_message)
                                            <span class="cl-content-meta text-danger">{{ Str::limit($log->error_message, 60) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Seçilen Ürün" class="d-none d-md-table-cell">
                                    @if($log->selected_product)
                                        <span class="badge bg-success-subtle text-success-emphasis">
                                            <i class="bi bi-box-seam me-1"></i>{{ $log->selected_product }}
                                        </span>
                                    @else
                                        <span class="usr-meta">-</span>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    <span class="ord-status-badge {{ $log->status->cssClass() }}">
                                        <i class="bi {{ $log->status->icon() }}"></i> {{ $log->status->label() }}
                                    </span>
                                </td>
                                <td data-label="Kaynak" class="d-none d-sm-table-cell">
                                    @if($log->source)
                                        <span class="ord-status-badge {{ $log->source->cssClass() }}">
                                            <i class="bi {{ $log->source->icon() }}"></i> {{ $log->source->label() }}
                                        </span>
                                    @else
                                        <span class="usr-meta">-</span>
                                    @endif
                                </td>
                                <td data-label="Süre" class="d-none d-md-table-cell">
                                    <span class="usr-meta">{{ $log->duration_formatted }}</span>
                                </td>
                                <td data-label="Model" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ $log->used_model ?? '-' }}</span>
                                </td>
                                <td data-label="Deneme" class="d-none d-xl-table-cell">
                                    <span class="usr-meta">{{ $log->attempt_count ?? '-' }}</span>
                                </td>
                                <td data-label="Token" class="d-none d-lg-table-cell">
                                    <span class="usr-meta">{{ $log->token_count ? number_format($log->token_count) : '-' }}</span>
                                </td>
                                <td data-label="Blog Yazısı" class="d-none d-lg-table-cell">
                                    @if($log->blogPost)
                                        <a href="{{ route('admin.blog-posts.edit', $log->blog_post_id) }}" class="text-teal" title="{{ $log->blogPost->title }}">
                                            <i class="bi bi-journal-text me-1"></i>{{ Str::limit($log->blogPost->title, 30) }}
                                        </a>
                                    @else
                                        <span class="usr-meta">-</span>
                                    @endif
                                </td>
                                <td data-label="Tarih">
                                    <div class="d-flex flex-column">
                                        <span class="usr-meta">{{ $log->created_at->translatedFormat('d M Y') }}</span>
                                        <span class="usr-meta" style="font-size:11px">{{ $log->created_at->format('H:i:s') }}</span>
                                    </div>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <a href="{{ route('admin.ai-logs.show', $log) }}" class="usr-action-btn" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if(in_array($log->status, [\App\Enums\AiLogStatus::ParseError, \App\Enums\AiLogStatus::PostFailed, \App\Enums\AiLogStatus::CategoryMissing], true))
                                            <button type="button"
                                                    class="usr-action-btn ai-log-retry-btn text-warning"
                                                    title="Tekrar Dene (raw_response'tan yeniden parse + yayınla)"
                                                    data-retry-url="{{ route('admin.ai-logs.retry', $log) }}"
                                                    data-topic="{{ $log->product_name }}">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    <i class="bi bi-robot d-block fs-1 mb-2"></i>
                                    AI log kaydı bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @include('partials.admin.pagination', ['paginator' => $logs, 'itemLabel' => 'kayıt'])
    </div>

@endsection

@push('scripts')
<script>
document.getElementById('aiLogSearch')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('filterForm').submit();
    }
});

// ParseError/PostFailed log'larını tekrar işleme alma — AdminModal onayı + fetch retry
(function () {
    'use strict';
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    document.querySelectorAll('.ai-log-retry-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url   = btn.dataset.retryUrl;
            var topic = btn.dataset.topic || '';

            AdminModal.confirm({
                title: 'Tekrar Dene',
                message: 'Bu log\'un raw_response\'u yeniden parse edilip blog yayınlanacak.',
                detailTitle: topic,
                type: 'warning',
                confirmText: 'Evet, Yayınla',
                confirmIcon: 'bi bi-arrow-clockwise',
            }).then(function (confirmed) {
                if (! confirmed) return;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (r) {
                    if (r.ok && r.data.success) {
                        AdminModal.status({
                            title: 'Başarılı',
                            message: r.data.message,
                            type: 'success',
                        });
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        AdminModal.status({
                            title: 'Tekrar Deneme Başarısız',
                            message: r.data.message || 'Bilinmeyen hata',
                            type: 'danger',
                        });
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                    }
                })
                .catch(function (e) {
                    AdminModal.status({
                        title: 'Bağlantı Hatası',
                        message: e.message,
                        type: 'danger',
                    });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                });
            });
        });
    });
})();
</script>
@endpush
