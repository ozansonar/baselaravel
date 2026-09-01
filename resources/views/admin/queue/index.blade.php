@extends('layouts.admin')

@section('title', 'Kuyruk')
@section('page_title', 'Kuyruk')
@section('page_description', 'Bekleyen ve başarısız işler — mail gönderimi buradan geçer')

@section('content')
    @php
        $hasFilter = $filters['search'] !== '' || $filters['queue'] !== '';
        $canManage = auth()->user()?->can('manage-queue') ?? false;
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Kuyruk</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Kuyruk</h1>
            <p class="page-subtitle">Bekleyen ve başarısız işler — mail gönderimi buradan geçer</p>
        </div>
        <div class="d-flex gap-2">
            @if($hasFilter)
                <a href="{{ route('admin.queue.index') }}" class="btn-glass">
                    <i class="bi bi-arrow-counterclockwise"></i> Filtreleri Sıfırla
                </a>
            @endif
            @if($canManage)
                <form method="POST" action="{{ route('admin.queue.run') }}" id="qRunForm" class="d-inline">
                    @csrf
                    <button type="submit" class="btn-teal" id="qRunBtn">
                        <i class="bi bi-play-fill"></i> Kuyruğu Şimdi İşle
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bekleyen İş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['pending'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon {{ $isStuck ? 'usr-stat-icon-red' : 'usr-stat-icon-blue' }}"><i class="bi bi-clock-history"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">En Eski İşin Yaşı</span>
                    @if($stats['oldest_minutes'] === null)
                        <h3 class="usr-stat-value">—</h3>
                    @else
                        <h3 class="usr-stat-value" data-count="{{ $stats['oldest_minutes'] }}">0</h3>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Son 24 Saatte Başarısız</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['failed_today'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-x-octagon-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Başarısız</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['failed_total'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Kuyruk tıkandıysa en görünür yerde söylensin: bekleyen iş sayısı tek
         başına normal, birikip yaşlanması ise cron'un çalışmadığı demek. --}}
    @if($isStuck)
        <div class="alert alert-danger d-flex align-items-start gap-3 mb-4" data-aos="fade-up" data-aos-delay="50">
            <i class="bi bi-exclamation-octagon-fill fs-4"></i>
            <div>
                <strong>Kuyruk ilerlemiyor.</strong>
                En eski iş {{ $stats['oldest_minutes'] }} dakikadır bekliyor. Kuyruğu her dakika
                boşaltan zamanlanmış görev çalışmıyor olabilir — hosting panelindeki cron
                tanımını kontrol edin.
            </div>
        </div>
    @endif

    {{-- Ekranın ne anlattığı --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Mail gönderimi kuyruktan geçer.</strong>
            Kuyruk her dakika çalışan zamanlanmış görevle boşalır; bu sunucuda ayrı bir
            worker süreci yoktur. Bir iş tüm denemelerini tüketirse aşağıdaki listeye
            düşer ve hata metniyle birlikte saklanır. "Doğrulama maili gelmedi" gibi
            şikâyetlerin cevabı çoğunlukla buradadır.
        </div>
    </div>

    {{-- ==================== SECTION 2: TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.queue.index') }}" id="qFilterForm" class="cl-toolbar">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="İş adı, hata metni veya kimlik içinde ara..." data-fv-ignore>
                </div>

                <div class="cl-filters">
                    <select class="cl-filter-select" name="queue" aria-label="Kuyruk"
                            data-submit-form="qFilterForm" data-fv-ignore>
                        <option value="">Tüm kuyruklar</option>
                        @foreach($queueOptions as $queue)
                            <option value="{{ $queue }}" {{ $filters['queue'] === $queue ? 'selected' : '' }}>{{ $queue }}</option>
                        @endforeach
                    </select>

                    <div class="cl-toolbar-actions ms-auto">
                        <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                        <a href="{{ route('admin.queue.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <div class="cl-per-page">
                            <label for="qPerPage">Göster:</label>
                            <select id="qPerPage" name="per_page" data-submit-form="qFilterForm" data-fv-ignore>
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-export-menu export="failed-jobs" :total="$jobs->total()" />
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== SECTION 3: TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-3">
            <h6 class="mb-0"><i class="bi bi-x-octagon me-2 text-teal"></i>Başarısız İşler</h6>
            @if($canManage && $stats['failed_total'] > 0)
                <form method="POST" action="{{ route('admin.queue.flush') }}" id="qFlushForm" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn-glass" id="qFlushBtn">
                        <i class="bi bi-trash"></i> Listeyi Temizle
                    </button>
                </form>
            @endif
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Zaman</th>
                            <th>İş</th>
                            <th class="d-none d-lg-table-cell">Kuyruk</th>
                            <th class="d-none d-xl-table-cell">Deneme</th>
                            <th>Hata</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jobs as $job)
                            <tr>
                                <td data-label="Zaman">
                                    <span class="al-time">{{ $job['failed_at']->translatedFormat('d M Y H:i') }}</span>
                                    <span class="cl-content-meta">{{ $job['failed_at']->diffForHumans() }}</span>
                                </td>
                                <td data-label="İş">
                                    <span class="al-label">{{ class_basename($job['job']) }}</span>
                                    <span class="cl-content-meta">{{ $job['job'] }}</span>
                                </td>
                                <td data-label="Kuyruk" class="d-none d-lg-table-cell">
                                    <span class="cl-category-badge">{{ $job['queue'] }}</span>
                                </td>
                                <td data-label="Deneme" class="d-none d-xl-table-cell">
                                    <span class="al-ip">{{ $job['attempts'] }}</span>
                                </td>
                                <td data-label="Hata">
                                    <span class="al-label">{{ \Illuminate\Support\Str::limit($job['error'], 90) }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <button type="button" class="usr-action-btn qs-detail" title="Hatanın tamamı"
                                                data-url="{{ route('admin.queue.show', $job['uuid']) }}">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @if($canManage)
                                            <form method="POST" action="{{ route('admin.queue.retry', $job['uuid']) }}" class="d-inline qs-retry-form">
                                                @csrf
                                                <button type="button" class="usr-action-btn qs-retry" title="Yeniden dene"
                                                        data-job="{{ class_basename($job['job']) }}">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.queue.destroy', $job['uuid']) }}" class="d-inline qs-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="usr-action-btn qs-delete" title="Sil"
                                                        data-job="{{ class_basename($job['job']) }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-check-circle d-block fs-1 mb-2 opacity-50"></i>
                                    @if($hasFilter)
                                        Bu filtreyle eşleşen kayıt yok.
                                        <br>
                                        <a href="{{ route('admin.queue.index') }}" class="text-teal">Filtreleri temizle</a>
                                    @else
                                        Başarısız iş yok. Kuyruk sorunsuz çalışıyor.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.admin.pagination', ['paginator' => $jobs, 'itemLabel' => 'iş'])
        </div>
    </div>

    {{-- Hata ayrıntısı --}}
    <div class="modal fade modal-custom" id="qDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qDetailTitle">Hata Ayrıntısı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="text-clr-muted mb-2" id="qDetailMeta"></p>
                    <pre class="qs-exception" id="qDetailException">Yükleniyor…</pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/queue.js') }}"></script>
@endpush
