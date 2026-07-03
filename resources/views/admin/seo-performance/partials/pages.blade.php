{{-- TAB: Per-Page Performance --}}
<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
    <div class="card-body-custom">
        <form method="GET" action="{{ route('admin.seo-performance.index') }}" class="cl-toolbar">
            <input type="hidden" name="tab" value="pages">

            <div class="cl-search">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="URL yolu ile filtrele... (örn: /urun/koy)">
            </div>

            <div class="cl-filters">
                <select class="cl-filter-select" name="type" onchange="this.form.submit()">
                    <option value="">Tüm Sayfalar</option>
                    <option value="home"     {{ $type === 'home' ? 'selected' : '' }}>Anasayfa</option>
                    <option value="product"  {{ $type === 'product' ? 'selected' : '' }}>Ürünler</option>
                    <option value="blog"     {{ $type === 'blog' ? 'selected' : '' }}>Blog</option>
                    <option value="city"     {{ $type === 'city' ? 'selected' : '' }}>Şehir Landing</option>
                    <option value="category" {{ $type === 'category' ? 'selected' : '' }}>Kategoriler</option>
                    <option value="page"     {{ $type === 'page' ? 'selected' : '' }}>Statik Sayfalar</option>
                    <option value="other"    {{ $type === 'other' ? 'selected' : '' }}>Diğer</option>
                </select>
            </div>

            <div class="cl-toolbar-actions">
                @if($search !== '' || $type !== '')
                    <a href="{{ route('admin.seo-performance.index', ['tab' => 'pages']) }}" class="cl-filter-reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Düşük CTR uyarı kutusu --}}
@if($lowCtrPages->isNotEmpty())
    <div class="card-dark mb-4 topic-pool-alert topic-pool-alert--warning">
        <div class="card-body-custom">
            <div class="d-flex align-items-start gap-3 flex-wrap">
                <div class="topic-pool-alert__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 topic-pool-alert__title">CTR Düşük — {{ $lowCtrPages->count() }} sayfa görünüyor ama tıklanmıyor</h6>
                    <p class="mb-0 small text-muted">Pozisyon ≤10 ama CTR &lt;%2 olan sayfalar — meta description'ı yenilemen önerilir.</p>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="cl-table">
                <thead>
                    <tr>
                        <th>Sayfa</th>
                        <th class="d-none d-md-table-cell">Tip</th>
                        <th class="text-end">Tıklama</th>
                        <th class="text-end d-none d-md-table-cell">Gösterim</th>
                        <th class="text-end d-none d-lg-table-cell">CTR</th>
                        <th class="text-end">Pozisyon</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $p)
                        <tr>
                            <td>
                                <div class="cl-content-info">
                                    <a href="{{ $p->page_url }}" target="_blank" rel="noopener noreferrer" class="cl-content-title text-decoration-none">
                                        {{ Str::limit($p->page_path, 60) }}
                                        <i class="bi bi-box-arrow-up-right small ms-1 text-muted"></i>
                                    </a>
                                    <span class="cl-content-meta">{{ $p->date_from->translatedFormat('d M') }} - {{ $p->date_to->translatedFormat('d M Y') }}</span>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                @php
                                    $typeLabels = [
                                        'home' => ['label' => 'Anasayfa', 'icon' => 'bi-house'],
                                        'product' => ['label' => 'Ürün', 'icon' => 'bi-box-seam'],
                                        'blog' => ['label' => 'Blog', 'icon' => 'bi-file-text'],
                                        'city' => ['label' => 'Şehir', 'icon' => 'bi-geo-alt'],
                                        'category' => ['label' => 'Kategori', 'icon' => 'bi-collection'],
                                        'page' => ['label' => 'Sayfa', 'icon' => 'bi-file-earmark'],
                                        'other' => ['label' => 'Diğer', 'icon' => 'bi-three-dots'],
                                    ];
                                    $info = $typeLabels[$p->page_type] ?? $typeLabels['other'];
                                @endphp
                                <span class="cl-category-badge">
                                    <i class="bi {{ $info['icon'] }} me-1"></i>{{ $info['label'] }}
                                </span>
                            </td>
                            <td class="text-end"><strong class="text-light">{{ number_format($p->clicks, 0, ',', '.') }}</strong></td>
                            <td class="text-end d-none d-md-table-cell text-muted">{{ number_format($p->impressions, 0, ',', '.') }}</td>
                            <td class="text-end d-none d-lg-table-cell">
                                @php
                                    $ctr = (float) $p->ctr;
                                    $ctrClass = $ctr >= 5 ? 'text-success' : ($ctr >= 2 ? 'text-warning' : 'text-danger');
                                @endphp
                                <span class="{{ $ctrClass }}">%{{ number_format($ctr, 1, ',', '.') }}</span>
                            </td>
                            <td class="text-end">
                                @php
                                    $pos = (float) $p->position;
                                @endphp
                                <span class="usr-status-badge {{ $pos <= 3 ? 'active' : ($pos <= 10 ? 'pending' : 'inactive') }}">
                                    {{ number_format($pos, 1, ',', '.') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-file-earmark-text d-block fs-1 mb-2"></i>
                                <p class="mb-1">Henüz sayfa verisi yok.</p>
                                <small>GSC verileri 2-3 gün gecikmeyle gelir. İlk cron çalıştıktan sonra burada görünecek.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($pages->hasPages())
        @include('partials.admin.pagination', ['paginator' => $pages, 'itemLabel' => 'sayfa'])
    @endif
</div>
