@extends('layouts.admin')

@section('title', 'Tüm Ziyaretler')
@section('page_title', 'Tüm Ziyaretler')
@section('page_description', 'Filtreleyebileceğiniz detaylı ziyaret log kaydı')

@section('content')
    @php
        $cihazEtiketleri = ['desktop' => 'Masaüstü', 'mobile' => 'Mobil', 'tablet' => 'Tablet', 'bot' => 'Bot', 'other' => 'Diğer'];
        $trafikEtiketleri = ['0' => 'Sadece insan', '1' => 'Sadece bot'];
        $ziyaretciEtiketleri = ['member' => 'Üye girişli', 'guest' => 'Misafir'];

        $activeFilters = collect([
            'url'         => ['label' => 'Arama', 'value' => $filters['url']],
            'is_bot'      => ['label' => 'Trafik', 'value' => $trafikEtiketleri[$filters['is_bot']] ?? ''],
            'device_type' => ['label' => 'Cihaz', 'value' => $cihazEtiketleri[$filters['device_type']] ?? ''],
            'browser'     => ['label' => 'Tarayıcı', 'value' => $filters['browser']],
            'os'          => ['label' => 'Sistem', 'value' => $filters['os']],
            'referrer'    => ['label' => 'Kaynak', 'value' => $filters['referrer'] === 'direct' ? 'Doğrudan' : $filters['referrer']],
            'visitor'     => ['label' => 'Ziyaretçi', 'value' => $ziyaretciEtiketleri[$filters['visitor']] ?? ''],
            'from'        => ['label' => 'Başlangıç', 'value' => $filters['from'] !== '' ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d.m.Y') : ''],
            'to'          => ['label' => 'Bitiş', 'value' => $filters['to'] !== '' ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d.m.Y') : ''],
        ])->filter(fn (array $chip): bool => $chip['value'] !== '');

        // Hızlı aralıklar: en çok sorulan üç soru tek tıkla.
        $bugun = now()->format('Y-m-d');
        $hizliAraliklar = [
            'Bugün'      => ['from' => $bugun, 'to' => $bugun],
            'Son 7 gün'  => ['from' => now()->subDays(6)->format('Y-m-d'), 'to' => $bugun],
            'Son 30 gün' => ['from' => now()->subDays(29)->format('Y-m-d'), 'to' => $bugun],
        ];
    @endphp

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.analytics.index') }}" class="breadcrumb-link">Analitik</a>
            </li>
            <li class="breadcrumb-item active text-teal">Tüm Ziyaretler</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-list-ul text-teal me-2"></i>Tüm Ziyaretler</h1>
            <p class="page-subtitle">Filtreleyebileceğiniz detaylı ziyaret log kaydı</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.analytics.index') }}" class="btn-glass">
                <i class="bi bi-bar-chart-line"></i> Analitik Paneli
            </a>
        </div>
    </div>


    <!-- ==================== STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-teal">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $totalAll }}">{{ number_format($totalAll) }}</h3>
                <span class="anl-kpi-label">Toplam Kayıt</span>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-blue">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $totalHumans }}">{{ number_format($totalHumans) }}</h3>
                <span class="anl-kpi-label">İnsan Ziyareti</span>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-orange">
                        <i class="bi bi-robot"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $totalBots }}">{{ number_format($totalBots) }}</h3>
                <span class="anl-kpi-label">Bot Ziyareti</span>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="anl-kpi-card">
                <div class="anl-kpi-header">
                    <div class="anl-kpi-icon anl-kpi-icon-purple">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
                <h3 class="anl-kpi-value" data-target="{{ $todayCount }}">{{ number_format($todayCount) }}</h3>
                <span class="anl-kpi-label">Bugün</span>
            </div>
        </div>
    </div>


    <div class="alert alert-info mb-4" data-aos="fade-up" data-aos-delay="120">
        <i class="bi bi-info-circle me-1"></i>
        Her satır tek bir <strong>sayfa görüntülemesi</strong>; aynı ziyaretçi beş sayfa gezdiyse burada
        beş kayıt olur. Aynı oturumun kayıtlarını görmek için oturum kodunu aramaya yazabilirsiniz.
        <span class="d-block mt-1">
            <strong>Yönetici, editör ve moderatör hesaplarıyla yapılan gezinmeler kaydedilmez</strong> —
            kendi ziyaretleriniz istatistikleri şişirmesin diye. Siteyi ziyaretçi gözünden görmek için
            oturumu kapatın ya da gizli sekme kullanın. IP adresleri
            <a href="{{ route('admin.settings.index') }}" class="alert-link">saklama süresi</a> dolduğunda
            maskelenir; maskelenmiş kayıtlar kalkanla işaretlidir.
        </span>
    </div>

    <!-- ==================== FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            {{-- Hızlı aralıklar süzgecin üstünde: "bugün ne oldu" sorusu tarih
                 kutularıyla uğraşmadan cevaplansın. --}}
            <div class="cl-chip-row mb-3">
                @foreach($hizliAraliklar as $etiket => $aralik)
                    @php $seciliMi = $filters['from'] === $aralik['from'] && $filters['to'] === $aralik['to']; @endphp
                    <a href="{{ route('admin.analytics.visits', array_merge(request()->except(['from', 'to', 'page']), $aralik)) }}"
                       class="cmp-chip {{ $seciliMi ? 'cmp-chip--aktif' : '' }}">{{ $etiket }}</a>
                @endforeach
                @if($filters['from'] !== '' || $filters['to'] !== '')
                    <a href="{{ route('admin.analytics.visits', request()->except(['from', 'to', 'page'])) }}"
                       class="cmp-chip">Tarih süzgecini kaldır</a>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.analytics.visits') }}" class="cl-toolbar" id="visitsFilterForm">
                <div class="cl-search {{ $filters['url'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="url" id="urlSearch"
                           placeholder="Sayfa yolu, IP veya oturum kodu ile ara..."
                           value="{{ $filters['url'] }}" autocomplete="off"
                           data-validation-engine="validate[maxSize[191]]">
                    @if($filters['url'] !== '')
                        <a href="{{ route('admin.analytics.visits', request()->except(['url', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                <div class="cl-filters mt-filters">
                    <div class="mt-field">
                        <span>Trafik</span>
                        <select class="cl-filter-select" name="is_bot" id="filterBot" aria-label="Trafik türü"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            <option value="0" {{ $filters['is_bot'] === '0' ? 'selected' : '' }}>Sadece insan</option>
                            <option value="1" {{ $filters['is_bot'] === '1' ? 'selected' : '' }}>Sadece bot</option>
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Cihaz</span>
                        <select class="cl-filter-select" name="device_type" id="filterDevice" aria-label="Cihaz türü"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            @foreach($cihazEtiketleri as $val => $label)
                                <option value="{{ $val }}" {{ $filters['device_type'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Ziyaretçi</span>
                        <select class="cl-filter-select" name="visitor" aria-label="Ziyaretçi türü"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            @foreach($ziyaretciEtiketleri as $val => $label)
                                <option value="{{ $val }}" {{ $filters['visitor'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tarayıcı, sistem ve kaynak listeleri veriden geliyor: elle
                         yazılan bir liste yeni bir tarayıcı çıktığında eksik kalırdı. --}}
                    <div class="mt-field">
                        <span>Tarayıcı</span>
                        <select class="cl-filter-select" name="browser" aria-label="Tarayıcı"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            @foreach($filterOptions['browsers'] as $secenek)
                                <option value="{{ $secenek }}" {{ $filters['browser'] === $secenek ? 'selected' : '' }}>{{ $secenek }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Sistem</span>
                        <select class="cl-filter-select" name="os" aria-label="İşletim sistemi"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            @foreach($filterOptions['systems'] as $secenek)
                                <option value="{{ $secenek }}" {{ $filters['os'] === $secenek ? 'selected' : '' }}>{{ $secenek }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Kaynak</span>
                        <select class="cl-filter-select" name="referrer" aria-label="Geliş kaynağı"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            <option value="">Tümü</option>
                            <option value="direct" {{ $filters['referrer'] === 'direct' ? 'selected' : '' }}>Doğrudan</option>
                            @foreach($filterOptions['referrers'] as $secenek)
                                <option value="{{ $secenek }}" {{ $filters['referrer'] === $secenek ? 'selected' : '' }}>{{ $secenek }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Başlangıç</span>
                        <input type="date" name="from" class="cl-filter-select" value="{{ $filters['from'] }}"
                               aria-label="Başlangıç tarihi"
                               onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>Bitiş</span>
                        <input type="date" name="to" class="cl-filter-select" value="{{ $filters['to'] }}"
                               aria-label="Bitiş tarihi"
                               onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>Sıralama</span>
                        <select class="cl-filter-select" name="sort" aria-label="Sıralama"
                                onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                            @foreach($sortOptions as $sortValue => $sortLabel)
                                <option value="{{ $sortValue }}" {{ ($filters['sort'] ?: 'recent') === $sortValue ? 'selected' : '' }}>
                                    {{ $sortLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field mt-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            @if($filtered)
                                <a href="{{ route('admin.analytics.visits') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            @endif
                            <div class="cl-per-page">
                                <label for="perPage">Göster:</label>
                                <select name="per_page" id="perPage"
                                        onchange="document.getElementById('visitsFilterForm').submit()" data-fv-ignore>
                                    @foreach($perPageList as $pp)
                                        <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            @include('partials.admin.filter-chips', [
                'chips' => $activeFilters,
                'route' => 'admin.analytics.visits',
            ])
        </div>
    </div>


    <!-- ==================== VISITS TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sayfa</th>
                            <th class="d-none d-lg-table-cell">IP</th>
                            <th>Cihaz</th>
                            <th class="d-none d-lg-table-cell">Ziyaretçi</th>
                            <th class="d-none d-md-table-cell">Tarayıcı</th>
                            <th class="d-none d-xl-table-cell">İşletim Sistemi</th>
                            <th class="d-none d-lg-table-cell">Kaynak</th>
                            <th class="d-none d-xxl-table-cell">Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visits as $visit)
                            @php
                                $deviceIcons = [
                                    'desktop' => 'bi-display',
                                    'mobile'  => 'bi-phone',
                                    'tablet'  => 'bi-tablet',
                                    'bot'     => 'bi-robot',
                                    'other'   => 'bi-question-circle',
                                ];
                                $deviceColors = [
                                    'desktop' => 'text-neon-blue',
                                    'mobile'  => 'text-neon-green',
                                    'tablet'  => 'text-neon-purple',
                                    'bot'     => 'text-neon-orange',
                                    'other'   => 'text-clr-secondary',
                                ];
                                $deviceLabels = [
                                    'desktop' => 'Masaüstü',
                                    'mobile'  => 'Mobil',
                                    'tablet'  => 'Tablet',
                                    'bot'     => 'Bot',
                                    'other'   => 'Diğer',
                                ];
                                $icon = $deviceIcons[$visit->device_type] ?? 'bi-question-circle';
                                $color = $deviceColors[$visit->device_type] ?? 'text-clr-secondary';
                                $deviceLabel = $deviceLabels[$visit->device_type] ?? 'Diğer';
                            @endphp
                            <tr>
                                {{-- Tarih --}}
                                <td class="text-nowrap">
                                    <span class="fw-medium">{{ $visit->viewed_at->format('d.m.Y') }}</span>
                                    <small class="d-block text-clr-muted">{{ $visit->viewed_at->format('H:i:s') }}</small>
                                </td>

                                {{-- Sayfa --}}
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ $visit->url }}" target="_blank" class="anl-visit-url" title="{{ $visit->url }}">
                                            {{ Str::limit($visit->url_path, 40) }}
                                        </a>
                                        @if($visit->is_bot)
                                            <span class="status-badge pending">{{ $visit->bot_name ?? 'bot' }}</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- IP --}}
                                <td class="d-none d-lg-table-cell">
                                    <code class="anl-visit-ip">{{ $visit->ip_address }}</code>
                                    @if($visit->ip_masked)
                                        <i class="bi bi-shield-check text-neon-green ms-1" title="KVKK maskelenmiş"></i>
                                    @endif
                                </td>

                                {{-- Cihaz --}}
                                <td>
                                    <span class="anl-visit-device {{ $color }}">
                                        <i class="bi {{ $icon }}"></i>
                                        <span class="d-none d-sm-inline">{{ $deviceLabel }}</span>
                                    </span>
                                </td>

                                {{-- Ziyaretçi: üyeyse kim olduğu, değilse misafir. --}}
                                <td class="d-none d-lg-table-cell">
                                    @if($visit->user)
                                        <span class="fw-medium">{{ $visit->user->full_name ?: $visit->user->email }}</span>
                                    @elseif($visit->is_bot)
                                        <span class="text-clr-muted">—</span>
                                    @else
                                        <span class="text-clr-secondary">Misafir</span>
                                    @endif
                                </td>

                                {{-- Tarayıcı --}}
                                <td class="d-none d-md-table-cell">
                                    @if($visit->browser)
                                        <span class="text-clr-primary">{{ $visit->browser }}</span>
                                        <small class="text-clr-muted">{{ $visit->browser_version }}</small>
                                    @else
                                        <span class="text-clr-muted">—</span>
                                    @endif
                                </td>

                                {{-- OS --}}
                                <td class="d-none d-xl-table-cell">
                                    <span class="text-clr-secondary">{{ $visit->os ?? '—' }}</span>
                                </td>

                                {{-- Kaynak --}}
                                <td class="d-none d-lg-table-cell">
                                    @if($visit->referrer_domain)
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="bi bi-box-arrow-in-right text-clr-muted"></i>
                                            <span class="text-clr-secondary">{{ $visit->referrer_domain }}</span>
                                        </div>
                                    @else
                                        <span class="text-clr-muted">direct</span>
                                    @endif
                                </td>

                                {{-- Session --}}
                                <td class="d-none d-xxl-table-cell">
                                    <code class="anl-visit-session">{{ Str::limit($visit->session_id, 10, '') }}</code>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="bi bi-inbox anl-visit-empty-icon"></i>
                                        <p class="text-clr-secondary mb-0">
                                            {{ $filtered ? 'Bu süzgeçle eşleşen ziyaret yok.' : 'Henüz ziyaret kaydı yok.' }}
                                        </p>
                                        @if($filtered)
                                            <a href="{{ route('admin.analytics.visits') }}" class="btn-glass btn-sm mt-2">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Filtreleri Temizle
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Ortak sayfalama yalnızca birden çok sayfa varken çiziliyor; süzgeç
         sonucu tek sayfaya sığdığında da kaç kayıt olduğu görünmeli. --}}
    @if($visits->hasPages())
        @include('partials.admin.pagination', ['paginator' => $visits, 'itemLabel' => 'ziyaret'])
    @elseif($visits->total() > 0)
        <div class="cl-pagination-wrapper" data-aos="fade-up">
            <div class="cl-pagination-info">
                <span>
                    <strong>{{ number_format($visits->total(), 0, ',', '.') }}</strong> ziyaret
                    @if($filtered)
                        <span class="text-clr-secondary">({{ number_format($totalAll, 0, ',', '.') }} kayıttan süzüldü)</span>
                    @endif
                </span>
            </div>
        </div>
    @endif

@endsection
