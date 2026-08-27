@extends('layouts.admin')

@section('title', 'Sistem Yedekleri')
@section('page_title', 'Sistem Yedekleri')
@section('page_description', 'Veritabanı ve yükleme klasörü yedeklerini görüntüleyin, indirin ve yönetin')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Sistem Yedekleri</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Sistem Yedekleri</h1>
            <p class="page-subtitle">Veritabanı + <code>uploads</code> klasörü tek ZIP dosyasında — otomatik veya manuel</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-glass" data-bs-toggle="collapse" data-bs-target="#bkGuide"
                    aria-expanded="false" aria-controls="bkGuide">
                <i class="bi bi-question-circle"></i> Nasıl çalışır?
            </button>
            <button type="button" class="btn-teal" id="bkRunBtn" data-create-url="{{ route('admin.backups.create') }}">
                <i class="bi bi-play-fill"></i>
                <span data-default>Şimdi Yedek Al</span>
                <span data-loading class="d-none"><i class="bi bi-arrow-repeat bk-spin"></i> Alınıyor…</span>
            </button>
        </div>
    </div>

    {{-- İşlem sonucu --}}
    <div id="bkResult" class="d-none mb-4" role="status" aria-live="polite"></div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-archive-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Yedek</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['count'] }}">0</h3>
                    <span class="usr-stat-change">{{ $stats['count'] > 0 ? 'dosya saklanıyor' : 'henüz yedek yok' }}</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-hdd-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Boyut</span>
                    <h3 class="usr-stat-value">{{ $stats['total_size_human'] }}</h3>
                    <span class="usr-stat-change">disk üzerinde</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon {{ $stats['latest'] ? 'usr-stat-icon-green' : 'usr-stat-icon-orange' }}">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Son Yedek</span>
                    <h3 class="usr-stat-value bk-stat-text">{{ $stats['latest_age'] ?? 'Yok' }}</h3>
                    <span class="usr-stat-change">
                        {{ $stats['latest']?->translatedFormat('d M Y H:i') ?? 'İlk yedeği siz alın' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Saklama Süresi</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['retention_days'] }}" data-suffix=" gün">0 gün</h3>
                    <span class="usr-stat-change">
                        Sonraki otomatik yedek: {{ $stats['next_run']->translatedFormat('d M, H:i') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: NASIL ÇALIŞIR ==================== --}}
    <div class="collapse mb-4" id="bkGuide">
        <div class="card-dark">
            <div class="card-header-custom">
                <h6><i class="bi bi-info-circle-fill me-2 text-teal"></i>Yedek sistemi nasıl çalışır?</h6>
            </div>
            <div class="card-body-custom">
                <div class="row g-4 bk-guide">
                    <div class="col-lg-4 col-md-6">
                        <div class="bk-guide-item">
                            <i class="bi bi-alarm-fill"></i>
                            <div>
                                <strong>Otomatik</strong>
                                <p>Zamanlanmış görev her gece {{ $stats['next_run']->format('H:i') }} saatinde yeni bir yedek alır.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bk-guide-item">
                            <i class="bi bi-box-seam-fill"></i>
                            <div>
                                <strong>İçerik</strong>
                                <p>Tüm veritabanı (<code>database.sql</code>) ve <code>public/uploads</code> klasörü tek ZIP dosyasında.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bk-guide-item">
                            <i class="bi bi-hourglass-split"></i>
                            <div>
                                <strong>Saklama</strong>
                                <p>{{ $stats['retention_days'] }} günden eski yedekler otomatik silinir (<code>backup_retention_days</code> ayarı).</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bk-guide-item">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <div>
                                <strong>Geri yükleme</strong>
                                <p>ZIP'i indirin; <code>uploads</code> klasörünü yerine çıkarın, <code>database.sql</code> dosyasını içe aktarın.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bk-guide-item">
                            <i class="bi bi-bell-fill"></i>
                            <div>
                                <strong>Bildirim</strong>
                                <p>Yedek alındığında ya da başarısız olduğunda panel bildirimi ve Telegram özeti gönderilir.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bk-guide-item">
                            <i class="bi bi-shield-lock-fill"></i>
                            <div>
                                <strong>Güvenlik</strong>
                                <p>Yedekler web erişimine kapalı <code>storage/app/backups</code> klasöründe tutulur.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 3: TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.backups.index') }}" id="bkFilterForm" class="cl-toolbar">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Dosya adı ile ara…">
                </div>

                <div class="cl-filters">
                    <select class="cl-filter-select" name="sort" onchange="document.getElementById('bkFilterForm').submit()">
                        @foreach([
                            'newest'   => 'Önce en yeni',
                            'oldest'   => 'Önce en eski',
                            'largest'  => 'Önce en büyük',
                            'smallest' => 'Önce en küçük',
                        ] as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['sort'] ?: 'newest') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>

                    <div class="cl-toolbar-actions ms-auto">
                        <button type="submit" class="usr-action-btn" title="Ara"><i class="bi bi-search"></i></button>
                        <a href="{{ route('admin.backups.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== SECTION 4: LİSTE ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table bk-table">
                    <thead>
                        <tr>
                            <th>Dosya</th>
                            <th class="d-none d-xl-table-cell">İçerik</th>
                            <th class="d-none d-sm-table-cell">Boyut</th>
                            <th class="d-none d-md-table-cell">Tarih</th>
                            <th class="d-none d-lg-table-cell">Saklama</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backups as $backup)
                            @php
                                $remaining = $backup['expires_in_days'];
                                $retentionBadge = match (true) {
                                    $remaining <= 0 => 'suspended',
                                    $remaining <= 2 => 'pending',
                                    default         => 'active',
                                };
                                $retentionLabel = match (true) {
                                    $remaining <= 0 => 'Bugün silinecek',
                                    $remaining === 1 => 'Yarın silinecek',
                                    default         => $remaining . ' gün kaldı',
                                };
                            @endphp
                            <tr>
                                <td data-label="Dosya">
                                    <div class="cl-content-cell">
                                        <div class="bk-file-icon">
                                            <i class="bi bi-file-earmark-zip-fill"></i>
                                        </div>
                                        <div class="cl-content-info">
                                            <span class="cl-content-title bk-file-name">{{ $backup['name'] }}</span>
                                            <span class="cl-content-meta">
                                                <span class="d-sm-none">
                                                    <i class="bi bi-hdd me-1"></i>{{ $backup['size_human'] }}
                                                    <span class="cl-separator">|</span>
                                                </span>
                                                <i class="bi bi-clock me-1"></i>{{ $backup['age'] }}
                                                @if($backup['contents'] && $backup['contents']['total_files'] > 0)
                                                    <span class="d-none d-md-inline">
                                                        <span class="cl-separator">|</span>
                                                        <i class="bi bi-files me-1"></i>{{ number_format($backup['contents']['total_files'], 0, ',', '.') }} dosya
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="İçerik" class="d-none d-xl-table-cell">
                                    @if($backup['contents'])
                                        <div class="bk-parts">
                                            <span class="bk-part"><i class="bi bi-database"></i>{{ $backup['contents']['db_size_human'] }}</span>
                                            <span class="bk-part"><i class="bi bi-images"></i>{{ $backup['contents']['files_size_human'] }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Boyut" class="d-none d-sm-table-cell">
                                    <span class="cl-category-badge">{{ $backup['size_human'] }}</span>
                                </td>
                                <td data-label="Tarih" class="d-none d-md-table-cell">
                                    <span class="usr-meta">{{ $backup['created_at']->translatedFormat('d M Y H:i') }}</span>
                                </td>
                                <td data-label="Saklama" class="d-none d-lg-table-cell">
                                    <span class="usr-status-badge {{ $retentionBadge }}">{{ $retentionLabel }}</span>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <a class="usr-action-btn success" title="İndir"
                                           href="{{ route('admin.backups.download', $backup['name']) }}">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.backups.destroy', $backup['name']) }}"
                                              class="bk-delete-form" data-filename="{{ $backup['name'] }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="usr-action-btn danger" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-cloud-arrow-down d-block fs-1 mb-2 opacity-50"></i>
                                    @if($filters['q'])
                                        <strong>“{{ $filters['q'] }}”</strong> ile eşleşen yedek bulunamadı.
                                        <br>
                                        <a href="{{ route('admin.backups.index') }}" class="text-teal">Filtreyi temizle</a>
                                    @else
                                        Henüz yedek yok.
                                        <br>
                                        <small>Yukarıdaki “Şimdi Yedek Al” düğmesiyle ilk yedeğinizi oluşturabilirsiniz.</small>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($backups !== [])
                <div class="cl-pagination-wrapper">
                    <div class="cl-pagination-info">
                        <strong>{{ count($backups) }}</strong> yedek listeleniyor ·
                        Toplam boyut: <strong>{{ $stats['total_size_human'] }}</strong> ·
                        {{ $stats['retention_days'] }} günden eski yedekler otomatik silinir
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/backups.js') }}"></script>
@endpush
