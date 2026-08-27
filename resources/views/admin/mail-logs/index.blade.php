@extends('layouts.admin')

@section('title', 'Mail Logları')
@section('page_title', 'Mail Logları')
@section('page_description', 'Sistemden gönderilen tüm e-postaların loglarını görüntüleyin')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Mail Logları</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Mail Logları</h1>
            <p class="page-subtitle">Sistemden gönderilen tüm e-postaların loglarını görüntüleyin</p>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-envelope"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Mail</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                    <span class="usr-stat-change">Bugün {{ $stats['today'] }} mail</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Gönderildi</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['sent'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
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
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Beklemede</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['pending'] ?? 0 }}">0</h3>
                    <span class="usr-stat-change">Kuyrukta {{ $queuedJobs }} iş</span>
                </div>
            </div>
        </div>
    </div>


    <!-- Sayfanın nasıl çalıştığı -->
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Beklemedeki mailler kuyrukta sıralarını bekliyor.</strong>
            Gönderim kuyruğu dakikada bir çalışan zamanlanmış görevle ilerler; sıra
            gelmeden mail çıkmaz. Beklemeyi kısa kesmek için satırdaki
            <i class="bi bi-send-fill text-teal"></i> <strong>Şimdi Gönder</strong>
            düğmesini kullanabilirsiniz. Başarısız olanlar ise
            <i class="bi bi-arrow-repeat"></i> ile aynı alıcıya yeniden gönderilir.
            @if($queuedJobs > 0)
                Şu an kuyrukta <strong>{{ $queuedJobs }}</strong> iş var.
            @endif
        </div>
    </div>

    <!-- ==================== SECTION 2: STATUS TABS ==================== -->
    @php
        $currentStatus = request('status', '');
        $totalCount = array_sum($statusCounts);

        $statusTabs = [
            '' => ['label' => 'Tümü', 'icon' => '', 'color' => '', 'count' => $totalCount],
            'sent' => ['label' => 'Gönderildi', 'icon' => 'bi-check-circle', 'color' => 'text-neon-green', 'count' => $statusCounts['sent'] ?? 0],
            'failed' => ['label' => 'Başarısız', 'icon' => 'bi-x-circle', 'color' => 'text-neon-red', 'count' => $statusCounts['failed'] ?? 0],
            'pending' => ['label' => 'Beklemede', 'icon' => 'bi-clock', 'color' => 'text-neon-orange', 'count' => $statusCounts['pending'] ?? 0],
        ];
    @endphp

    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        @foreach($statusTabs as $statusValue => $tab)
            <a href="{{ route('admin.mail-logs.index', array_merge(request()->except(['status', 'page']), $statusValue ? ['status' => $statusValue] : [])) }}"
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
            <form method="GET" action="{{ route('admin.mail-logs.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="mailLogSearch" placeholder="E-posta adresi, konu veya mailable sınıfı ile ara..." value="{{ request('search') }}">
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="date_filter" id="filterDate" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Tarihler</option>
                        <option value="today" {{ request('date_filter') === 'today' ? 'selected' : '' }}>Bugün</option>
                        <option value="week" {{ request('date_filter') === 'week' ? 'selected' : '' }}>Bu Hafta</option>
                        <option value="month" {{ request('date_filter') === 'month' ? 'selected' : '' }}>Bu Ay</option>
                        <option value="quarter" {{ request('date_filter') === 'quarter' ? 'selected' : '' }}>Son 3 Ay</option>
                    </select>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.mail-logs.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" id="perPage" onchange="document.getElementById('filterForm').submit()">
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- ==================== SECTION 4: MAIL LOGS TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>E-posta</th>
                            <th>Durum</th>
                            <th class="d-none d-lg-table-cell">Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mailLogs as $log)
                            <tr>
                                <td data-label="E-posta">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="ml-type-icon {{ $log->mailable_color }}">
                                            <i class="bi {{ $log->mailable_icon }}"></i>
                                        </div>
                                        <div class="ml-mail-info">
                                            <span class="ml-mail-type">{{ $log->mailable_label }}</span>
                                            @if($log->subject)
                                                <span class="ml-mail-subject">{{ \Illuminate\Support\Str::limit($log->subject, 60) }}</span>
                                            @endif
                                            {{-- Alıcı konunun altında: ayrı sütun tabloyu taşırıyordu. --}}
                                            <span class="ml-recipient"><i class="bi bi-arrow-right-short"></i>{{ $log->to }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Durum">
                                    <span class="ord-status-badge {{ $log->status->cssClass() }}"><i class="bi {{ $log->status->icon() }}"></i> {{ $log->status->label() }}</span>
                                    @if($log->status === \App\Enums\MailLogStatus::Failed && $log->error_message)
                                        {{-- Hata, tıklamadan görünmeli: listeye bakan kişi neden gitmediğini merak ediyor. --}}
                                        <span class="ml-error-text" title="{{ $log->error_message }}">
                                            <i class="bi bi-exclamation-circle me-1"></i>{{ \Illuminate\Support\Str::limit($log->error_message, 45) }}
                                        </span>
                                    @elseif($log->status === \App\Enums\MailLogStatus::Pending)
                                        <span class="ml-pending-text"><i class="bi bi-hourglass-split me-1"></i>Kuyrukta sırasını bekliyor</span>
                                    @endif
                                </td>
                                <td data-label="Tarih" class="d-none d-lg-table-cell">
                                    <div class="ml-date-info">
                                        <span class="ml-date">{{ $log->created_at->translatedFormat('d M Y') }}</span>
                                        <span class="ml-time">{{ $log->created_at->format('H:i') }} · {{ $log->created_at->diffForHumans() }}</span>
                                        @if($log->status === \App\Enums\MailLogStatus::Sent && $log->sent_at)
                                            <span class="ml-time"><i class="bi bi-check2 me-1"></i>Gönderim: {{ $log->sent_at->format('H:i') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        <a href="{{ route('admin.mail-logs.show', $log) }}" class="usr-action-btn" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($log->status === \App\Enums\MailLogStatus::Pending)
                                            <button type="button" class="usr-action-btn success ml-send-now" title="Şimdi Gönder"
                                                    data-url="{{ route('admin.mail-logs.send-now', $log) }}"
                                                    data-recipient="{{ $log->to }}"
                                                    data-subject="{{ $log->subject }}">
                                                <i class="bi bi-send-fill"></i>
                                            </button>
                                        @elseif($log->body)
                                            <button type="button" class="usr-action-btn ml-resend" title="Yeniden Gönder"
                                                    data-url="{{ route('admin.mail-logs.resend', $log) }}"
                                                    data-recipient="{{ $log->to }}"
                                                    data-subject="{{ $log->subject }}">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-envelope-x d-block fs-1 mb-2"></i>
                                    Mail logu bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @include('partials.admin.pagination', ['paginator' => $mailLogs, 'itemLabel' => 'kayıt'])
    </div>


@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/mail-logs.js') }}"></script>
@endpush
