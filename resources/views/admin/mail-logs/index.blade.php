@extends('layouts.admin')

@section('title', 'Mail Logları')
@section('page_title', 'Mail Logları')
@section('page_description', 'Sistemden gönderilen tüm e-postaların loglarını görüntüleyin')

@section('content')

    @php
        // Süzgeçlerden herhangi biri açıksa hem başlıktaki sıfırlama düğmesi
        // hem de boş liste metni bunu bilmeli.
        $hasFilter = collect($filters)->filter(fn ($value) => (string) $value !== '')->isNotEmpty();

        $quickDateLabels = [
            'today'   => 'Bugün',
            'week'    => 'Bu Hafta',
            'month'   => 'Bu Ay',
            'quarter' => 'Son 3 Ay',
        ];

        // Açık süzgeçler rozet olarak listeleniyor: yedi kutuyu tek tek okumak
        // yerine ne olduğu bir bakışta görülsün, istenmeyeni tek tıkla atılsın.
        $activeFilters = collect([
            'status' => [
                'label' => 'Durum',
                'value' => $filters['status'] !== ''
                    ? (\App\Enums\MailLogStatus::tryFrom($filters['status'])?->label() ?? $filters['status'])
                    : '',
            ],
            'search' => [
                'label' => 'Arama',
                'value' => $filters['search'],
            ],
            'mailable' => [
                'label' => 'Mail türü',
                'value' => $filters['mailable'] !== ''
                    ? ($mailableOptions[$filters['mailable']]['label'] ?? $filters['mailable'])
                    : '',
            ],
            'recipient' => [
                'label' => 'Alıcı',
                'value' => $filters['recipient'],
            ],
            'user_id' => [
                'label' => 'Tetikleyen',
                'value' => match (true) {
                    $filters['user_id'] === ''  => '',
                    $filters['user_id'] === '0' => 'Sistem',
                    default => (function () use ($filters, $userOptions) {
                        $user = $userOptions->firstWhere('id', (int) $filters['user_id']);

                        return $user
                            ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email)
                            : 'Kullanıcı #' . $filters['user_id'];
                    })(),
                },
            ],
            'date_filter' => [
                'label' => 'Hızlı tarih',
                'value' => $quickDateLabels[$filters['date_filter']] ?? '',
            ],
            'from' => [
                'label' => 'Başlangıç',
                'value' => $filters['from'] !== '' ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d.m.Y') : '',
            ],
            'to' => [
                'label' => 'Bitiş',
                'value' => $filters['to'] !== '' ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d.m.Y') : '',
            ],
        ])->filter(fn (array $chip): bool => $chip['value'] !== '');
    @endphp

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
        @if($hasFilter)
            <a href="{{ route('admin.mail-logs.index') }}" class="btn-glass">
                <i class="bi bi-arrow-counterclockwise"></i> Filtreleri Sıfırla
            </a>
        @endif
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
        // Sekme sayıları açık süzgeçlere göre: "Başarısız 3" yazıyorsa o
        // süzgeçle gerçekten 3 kayıt gelmeli.
        $currentStatus = $filters['status'];
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
                @if($currentStatus !== '')
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif

                <div class="cl-search {{ $filters['search'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="mailLogSearch"
                           placeholder="Alıcı, konu, mailable sınıfı veya hata metni ile ara..."
                           value="{{ $filters['search'] }}">
                    @if($filters['search'] !== '')
                        <a href="{{ route('admin.mail-logs.index', request()->except(['search', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                {{-- Alanların hepsi başlıklı: seçim kutuları ile tarih alanları
                     aynı hizada başlasın, aynı hizada bitsin. --}}
                <div class="cl-filters ml-filters">
                    <div class="ml-field">
                        <span>Mail türü</span>
                        <select class="cl-filter-select" name="mailable" aria-label="Mail türü"
                                data-select2-search="always"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tüm mail türleri</option>
                            @foreach($mailableOptions as $class => $option)
                                <option value="{{ $class }}" {{ $filters['mailable'] === (string) $class ? 'selected' : '' }}>
                                    {{ $option['label'] }} ({{ $option['count'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ml-field">
                        <span>Alıcı</span>
                        <select class="cl-filter-select" name="recipient" aria-label="Alıcı"
                                data-select2-search="always"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tüm alıcılar</option>
                            {{-- Listede olmayan bir adres elle geldiyse (eski bağlantı,
                                 çok sayıda alıcı) seçim kaybolmasın. --}}
                            @if($filters['recipient'] !== '' && !array_key_exists($filters['recipient'], $recipientOptions))
                                <option value="{{ $filters['recipient'] }}" selected>{{ $filters['recipient'] }}</option>
                            @endif
                            @foreach($recipientOptions as $address => $count)
                                <option value="{{ $address }}" {{ $filters['recipient'] === (string) $address ? 'selected' : '' }}>
                                    {{ $address }} ({{ $count }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ml-field">
                        <span>Tetikleyen</span>
                        <select class="cl-filter-select" name="user_id" aria-label="Tetikleyen kullanıcı"
                                data-select2-search="always"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tüm kullanıcılar</option>
                            <option value="0" {{ $filters['user_id'] === '0' ? 'selected' : '' }}>Sistem (kullanıcısız)</option>
                            @foreach($userOptions as $user)
                                <option value="{{ $user->id }}" {{ $filters['user_id'] === (string) $user->id ? 'selected' : '' }}>
                                    {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ml-field">
                        <span>Hızlı tarih</span>
                        <select class="cl-filter-select" name="date_filter" id="filterDate" aria-label="Hızlı tarih"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tüm Tarihler</option>
                            <option value="today" {{ $filters['date_filter'] === 'today' ? 'selected' : '' }}>Bugün</option>
                            <option value="week" {{ $filters['date_filter'] === 'week' ? 'selected' : '' }}>Bu Hafta</option>
                            <option value="month" {{ $filters['date_filter'] === 'month' ? 'selected' : '' }}>Bu Ay</option>
                            <option value="quarter" {{ $filters['date_filter'] === 'quarter' ? 'selected' : '' }}>Son 3 Ay</option>
                        </select>
                    </div>

                    {{-- Tarih kutularının temizleme düğmesi başlık satırında: tarayıcının
                         takvim ikonu alanın sağını kapladığı için kutu içine sığmıyor.
                         Seçim kutularında bu düğme Select2'nin kendisinden geliyor. --}}
                    <div class="ml-field">
                        <span>
                            Başlangıç
                            @if($filters['from'] !== '')
                                <a href="{{ route('admin.mail-logs.index', request()->except(['from', 'page'])) }}"
                                   class="ml-field-clear" title="Başlangıç tarihini temizle" aria-label="Başlangıç tarihini temizle">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </span>
                        <input type="date" class="cl-filter-select" name="from" value="{{ $filters['from'] }}" aria-label="Başlangıç tarihi">
                    </div>

                    <div class="ml-field">
                        <span>
                            Bitiş
                            @if($filters['to'] !== '')
                                <a href="{{ route('admin.mail-logs.index', request()->except(['to', 'page'])) }}"
                                   class="ml-field-clear" title="Bitiş tarihini temizle" aria-label="Bitiş tarihini temizle">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </span>
                        <input type="date" class="cl-filter-select" name="to" value="{{ $filters['to'] }}" aria-label="Bitiş tarihi">
                    </div>

                    <div class="ml-field ml-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            <a href="{{ route('admin.mail-logs.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <div class="cl-per-page">
                                <label for="perPage">Göster:</label>
                                <select name="per_page" id="perPage" onchange="document.getElementById('filterForm').submit()">
                                    @foreach($perPageOptions as $pp)
                                        <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Elle tarih verildiğinde hazır aralık işlemez; kullanıcı iki
                     tarih süzgecinin çakıştığını sanmasın. --}}
                @if($filters['date_filter'] !== '' && ($filters['from'] !== '' || $filters['to'] !== ''))
                    <p class="ml-filter-hint">
                        <i class="bi bi-info-circle me-1"></i>Tarih aralığı verildiği için hazır aralık uygulanmadı.
                    </p>
                @endif
            </form>

            @if($activeFilters->isNotEmpty())
                <div class="ml-active-filters">
                    <span class="ml-active-filters__title">Açık süzgeçler:</span>
                    @foreach($activeFilters as $key => $chip)
                        <span class="ml-filter-chip">
                            <span class="ml-filter-chip__label">{{ $chip['label'] }}:</span>
                            <span class="ml-filter-chip__value">{{ $chip['value'] }}</span>
                            <a href="{{ route('admin.mail-logs.index', request()->except([$key, 'page'])) }}"
                               class="ml-filter-chip__remove" title="{{ $chip['label'] }} süzgecini kaldır"
                               aria-label="{{ $chip['label'] }} süzgecini kaldır">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </span>
                    @endforeach
                    @if($activeFilters->count() > 1)
                        <a href="{{ route('admin.mail-logs.index') }}" class="ml-filter-chip ml-filter-chip--reset">
                            <i class="bi bi-arrow-counterclockwise"></i> Tümünü temizle
                        </a>
                    @endif
                </div>
            @endif
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
                                    @if($hasFilter)
                                        Bu filtreyle eşleşen mail logu yok.
                                        <br>
                                        <a href="{{ route('admin.mail-logs.index') }}" class="text-teal">Filtreleri temizle</a>
                                    @else
                                        Mail logu bulunamadı.
                                    @endif
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
