{{-- TAB: Top Queries --}}
<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
    <div class="card-body-custom">
        <form method="GET" action="{{ route('admin.seo-performance.index') }}" class="cl-toolbar">
            <input type="hidden" name="tab" value="queries">
            <div class="cl-search">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Arama kelimesi ile filtrele...">
            </div>
            <div class="cl-toolbar-actions">
                <button type="submit" class="btn-glass"><i class="bi bi-funnel"></i> Filtrele</button>
                @if($search !== '')
                    <a href="{{ route('admin.seo-performance.index', ['tab' => 'queries']) }}" class="cl-filter-reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="cl-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Arama Kelimesi</th>
                        <th class="text-end">Tıklama</th>
                        <th class="text-end d-none d-md-table-cell">Gösterim</th>
                        <th class="text-end d-none d-lg-table-cell">CTR</th>
                        <th class="text-end">Pozisyon</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queries as $i => $q)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div class="cl-content-info">
                                    <span class="cl-content-title">{{ $q->query }}</span>
                                    <span class="cl-content-meta">{{ $q->date_from->translatedFormat('d M') }} - {{ $q->date_to->translatedFormat('d M Y') }}</span>
                                </div>
                            </td>
                            <td class="text-end"><strong class="text-light">{{ number_format($q->clicks, 0, ',', '.') }}</strong></td>
                            <td class="text-end d-none d-md-table-cell text-muted">{{ number_format($q->impressions, 0, ',', '.') }}</td>
                            <td class="text-end d-none d-lg-table-cell">
                                @php
                                    $ctr = (float) $q->ctr;
                                    $ctrClass = $ctr >= 5 ? 'text-success' : ($ctr >= 2 ? 'text-warning' : 'text-danger');
                                @endphp
                                <span class="{{ $ctrClass }}">%{{ number_format($ctr, 1, ',', '.') }}</span>
                            </td>
                            <td class="text-end">
                                @php
                                    $pos = (float) $q->position;
                                    $posClass = $pos <= 3 ? 'text-success' : ($pos <= 10 ? 'text-warning' : 'text-danger');
                                @endphp
                                <span class="usr-status-badge {{ $pos <= 3 ? 'active' : ($pos <= 10 ? 'pending' : 'inactive') }}">
                                    {{ number_format($pos, 1, ',', '.') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-search d-block fs-1 mb-2"></i>
                                <p class="mb-1">Henüz arama verisi yok.</p>
                                <small>GSC verileri 2-3 gün gecikmeyle gelir. İlk cron çalıştıktan sonra burada görünecek.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
