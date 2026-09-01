@extends('layouts.admin')

@section('title', 'Hata Kayıtları')
@section('page_title', 'Hata Kayıtları')
@section('page_description', 'Sunucuda oluşan hatalar: ne, nerede, kaç kez')

@section('content')
    @php
        $activeStatus = $filters['status'];
        $hasFilter = $filters['q'] !== '' || $filters['exception'] !== '' || $filters['source'] !== ''
            || $filters['from'] !== '' || $filters['to'] !== '';
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Hata Kayıtları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Hata Kayıtları</h1>
            <p class="page-subtitle">Sunucuda oluşan hatalar: ne, nerede, kaç kez</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($hasFilter)
                <a href="{{ route('admin.error-logs.index') }}" class="btn-glass">
                    <i class="bi bi-arrow-counterclockwise"></i> Filtreleri Sıfırla
                </a>
            @endif
            @can('deleteAny', \App\Models\ErrorLog::class)
                @if($stats['resolved'] > 0)
                    <form method="POST" action="{{ route('admin.error-logs.purge') }}" id="elPurgeForm">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn-glass"
                                data-confirm-submit="elPurgeForm"
                                data-confirm-title="Çözülmüş kayıtları temizle"
                                data-confirm-message="Çözüldü işaretli {{ $stats['resolved'] }} kayıt silinecek. Aynı hatalar yeniden oluşursa listede yeniden görünürler."
                                data-confirm-text="Evet, Temizle"
                                data-confirm-icon="bi bi-trash3">
                            <i class="bi bi-check2-square"></i> Çözülmüşleri Temizle
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-bug-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Açık Hata</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['open'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check2-circle"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Çözülmüş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['resolved'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-calendar-day"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün Görülen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['today'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            {{-- Satır sayısı değil tekrar sayısı: "üç hatam var" ile "üç hatam
                 var ve bu ay dokuz bin kez patladılar" aynı şey değil. --}}
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-arrow-repeat"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Tekrar</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['occurrences'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Sayfanın ne olduğu, bildirimden farkı ve kayıtların ne kadar kalacağı --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Aynı hata tek satırda toplanır.</strong>
            Bir kusur bin kez tekrar etse de listede tek satır görünür; kaç kez olduğu
            <em>Tekrar</em> sütununda yazar. Bildirimler {{ $throttleMinutes }} dakikada
            bir gönderildiği için tekrarların çoğu bildirime düşmez — bu liste onları da sayar.
            {{ $retentionDays }} gündür tekrar etmeyen kayıtlar haftalık temizlik görevinde silinir.
            @if($stats['oldest'])
                Listedeki en eski kayıt {{ $stats['oldest']->translatedFormat('d F Y') }} tarihinde görülmüş.
            @endif
        </div>
    </div>

    {{-- ==================== SECTION 2: STATUS TABS ==================== --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.error-logs.index', array_merge(request()->except('page'), ['status' => 'open'])) }}"
           class="cl-status-tab {{ $activeStatus === 'open' ? 'active' : '' }}">
            <i class="bi bi-exclamation-octagon text-danger"></i>
            <span>Açık</span>
            <span class="cl-tab-count">{{ $stats['open'] }}</span>
        </a>
        <a href="{{ route('admin.error-logs.index', array_merge(request()->except('page'), ['status' => 'resolved'])) }}"
           class="cl-status-tab {{ $activeStatus === 'resolved' ? 'active' : '' }}">
            <i class="bi bi-check2-circle text-success"></i>
            <span>Çözüldü</span>
            <span class="cl-tab-count">{{ $stats['resolved'] }}</span>
        </a>
        <a href="{{ route('admin.error-logs.index', array_merge(request()->except('page'), ['status' => 'all'])) }}"
           class="cl-status-tab {{ $activeStatus === 'all' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $stats['open'] + $stats['resolved'] }}</span>
        </a>
    </div>

    {{-- ==================== SECTION 3: TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.error-logs.index') }}" id="elFilterForm" class="cl-toolbar">
                <input type="hidden" name="status" value="{{ $activeStatus }}">

                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $filters['q'] }}"
                           placeholder="Mesaj, hata türü, dosya veya adres içinde ara..." data-fv-ignore>
                </div>

                <div class="cl-filters al-filters">
                    <div class="al-field">
                        <span>Hata türü</span>
                        <select class="cl-filter-select" name="exception" aria-label="Hata türü"
                                data-submit-form="elFilterForm" data-fv-ignore>
                            <option value="">Tüm hata türleri</option>
                            @foreach($exceptionOptions as $class => $count)
                                <option value="{{ $class }}" {{ $filters['exception'] === (string) $class ? 'selected' : '' }}>
                                    {{ class_basename($class) }} ({{ $count }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Paket içinde patlayan bir hatanın sebebi neredeyse her
                         zaman onu çağıran kendi kodumuz; ama düzeltilecek yer
                         orası değil. Ayrım listeyi hızlı daraltıyor. --}}
                    <div class="al-field">
                        <span>Kaynak</span>
                        <select class="cl-filter-select" name="source" aria-label="Kaynak"
                                data-submit-form="elFilterForm" data-fv-ignore>
                            <option value="">Tümü</option>
                            <option value="app" {{ $filters['source'] === 'app' ? 'selected' : '' }}>Proje kodu</option>
                            <option value="vendor" {{ $filters['source'] === 'vendor' ? 'selected' : '' }}>Paket (vendor)</option>
                        </select>
                    </div>

                    <div class="al-field">
                        <span>Başlangıç</span>
                        <input type="date" class="cl-filter-select" name="from" value="{{ $filters['from'] }}"
                               aria-label="Başlangıç tarihi" data-fv-ignore>
                    </div>

                    <div class="al-field">
                        <span>Bitiş</span>
                        <input type="date" class="cl-filter-select" name="to" value="{{ $filters['to'] }}"
                               aria-label="Bitiş tarihi" data-fv-ignore>
                    </div>

                    <div class="al-field al-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            <a href="{{ route('admin.error-logs.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <div class="cl-per-page">
                                <label for="elPerPage">Göster:</label>
                                <select id="elPerPage" name="per_page" data-submit-form="elFilterForm" data-fv-ignore>
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <x-export-menu export="error-logs" :total="$logs->total()" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== SECTION 4: TABLE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table al-table el-table">
                    <thead>
                        <tr>
                            <th>Hata</th>
                            <th class="d-none d-lg-table-cell">Konum</th>
                            <th class="d-none d-xxl-table-cell">Kaynak</th>
                            <th>Tekrar</th>
                            <th class="d-none d-xl-table-cell">Son Görülme</th>
                            <th>Durum</th>
                            <th class="cl-th-actions">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td data-label="Hata" class="el-error-cell">
                                    <a href="{{ route('admin.error-logs.show', $log->id) }}" class="al-label">
                                        {{ $log->shortException() }}
                                    </a>
                                    <span class="cl-content-meta" title="{{ $log->message }}">{{ $log->summary(140) }}</span>
                                </td>
                                <td data-label="Konum" class="d-none d-lg-table-cell">
                                    <span class="al-ip el-loc" title="{{ $log->location() }}">{{ $log->shortLocation() }}</span>
                                </td>
                                <td data-label="Kaynak" class="d-none d-xxl-table-cell">
                                    <span class="cl-category-badge">{{ $log->isVendor() ? 'Paket' : 'Proje kodu' }}</span>
                                </td>
                                <td data-label="Tekrar">
                                    <span class="el-count {{ $log->occurrences > 1 ? 'el-count--many' : '' }}">
                                        {{ number_format($log->occurrences, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td data-label="Son Görülme" class="d-none d-xl-table-cell">
                                    <span class="al-time">{{ $log->last_seen_at?->translatedFormat('d M Y H:i') }}</span>
                                    <span class="cl-content-meta">{{ $log->last_seen_at?->diffForHumans() }}</span>
                                </td>
                                <td data-label="Durum">
                                    @if($log->isResolved())
                                        <span class="usr-status-badge active"><i class="bi bi-check2-circle me-1"></i>Çözüldü</span>
                                    @else
                                        <span class="usr-status-badge suspended"><i class="bi bi-exclamation-octagon me-1"></i>Açık</span>
                                    @endif
                                </td>
                                <td data-label="İşlemler">
                                    <div class="usr-actions">
                                        <a class="usr-action-btn" title="Detay" href="{{ route('admin.error-logs.show', $log->id) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('update', $log)
                                            @if($log->isResolved())
                                                <form method="POST" action="{{ route('admin.error-logs.reopen', $log->id) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="usr-action-btn" title="Yeniden aç">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.error-logs.resolve', $log->id) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="usr-action-btn" title="Çözüldü işaretle">
                                                        <i class="bi bi-check2"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                        @can('delete', $log)
                                            <form method="POST" action="{{ route('admin.error-logs.destroy', $log->id) }}"
                                                  id="elDelete{{ $log->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="usr-action-btn danger"
                                                        data-confirm-submit="elDelete{{ $log->id }}"
                                                        data-confirm-title="Hata kaydını sil"
                                                        data-confirm-message="Bu kayıt silinecek. Aynı hata yeniden oluşursa listede yeniden görünür."
                                                        data-confirm-text="Evet, Sil"
                                                        data-confirm-icon="bi bi-trash3"
                                                        title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-shield-check d-block fs-1 mb-2 opacity-50"></i>
                                    @if($hasFilter)
                                        Bu filtreyle eşleşen kayıt yok.
                                        <br>
                                        <a href="{{ route('admin.error-logs.index') }}" class="text-teal">Filtreleri temizle</a>
                                    @elseif($activeStatus === 'open')
                                        Açık hata yok. Sunucuda beklenmedik bir hata oluştuğunda burada görünecek.
                                    @elseif($activeStatus === 'resolved')
                                        Çözüldü işaretli kayıt yok.
                                    @else
                                        Henüz kayıt yok. Sunucuda beklenmedik bir hata oluştuğunda burada görünecek.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.admin.pagination', ['paginator' => $logs, 'itemLabel' => 'hata'])
        </div>
    </div>

    {{-- ==================== SECTION 5: ÖZET ==================== --}}
    @if($topRepeating !== [])
        <div class="row g-4 mb-4">
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="50">
                <div class="nt-card h-100">
                    <div class="nt-card-header">
                        <div class="nt-card-icon c-red"><i class="bi bi-arrow-repeat"></i></div>
                        <h6>En Çok Tekrar Edenler</h6>
                    </div>
                    <div class="nt-card-body">
                        <div class="nt-summary-list">
                            @foreach($topRepeating as $item)
                                <div class="nt-summary-row">
                                    <div class="nt-summary-dot c-red"></div>
                                    <span class="nt-summary-label" title="{{ $item['location'] }}">{{ $item['label'] }}</span>
                                    <div class="nt-summary-bar">
                                        <div class="nt-summary-fill c-red nt-summary-fill--{{ $item['percent'] }}"></div>
                                    </div>
                                    <span class="nt-summary-num">{{ number_format($item['count'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="nt-card h-100">
                    <div class="nt-card-header">
                        <div class="nt-card-icon c-teal"><i class="bi bi-lightbulb"></i></div>
                        <h6>Bu Liste Nasıl Okunur?</h6>
                    </div>
                    <div class="nt-card-body">
                        <div class="nt-hints">
                            <div class="nt-hint">
                                <div class="nt-hint__icon c-red"><i class="bi bi-arrow-repeat"></i></div>
                                <div>
                                    <strong>Önce tekrarı yüksek olana bakın</strong>
                                    <span>Bir kez olan hata talihsizlik, binlerce kez olan kusur.</span>
                                </div>
                            </div>
                            <div class="nt-hint">
                                <div class="nt-hint__icon c-purple"><i class="bi bi-code-slash"></i></div>
                                <div>
                                    <strong>Kaynağı "Proje kodu" olanlar sizin</strong>
                                    <span>Paket içindeki hatanın çözümü çoğu zaman onu çağıran kodda.</span>
                                </div>
                            </div>
                            <div class="nt-hint">
                                <div class="nt-hint__icon c-green"><i class="bi bi-check2-circle"></i></div>
                                <div>
                                    <strong>Düzelttiğinizi işaretleyin</strong>
                                    <span>Hata yeniden oluşursa işaret kendiliğinden kalkar.</span>
                                </div>
                            </div>
                            <div class="nt-hint">
                                <div class="nt-hint__icon c-orange"><i class="bi bi-recycle"></i></div>
                                <div>
                                    <strong>Liste kendiliğinden temizlenir</strong>
                                    <span>{{ $retentionDays }} gündür tekrar etmeyen kayıtlar her pazar silinir.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
