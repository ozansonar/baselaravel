@extends('layouts.admin')

@section('title', 'Mail Şablonları')
@section('page_title', 'Mail Şablonları')
@section('page_description', 'Sistemden gönderilen e-postaların konu ve içeriklerini düzenleyin')

@section('content')

    @php
        // Süzgeçlerden herhangi biri açıksa hem başlıktaki sıfırlama düğmesi
        // hem de boş liste metni bunu bilmeli.
        $hasFilter = collect($filters)->filter(fn ($value) => (string) $value !== '')->isNotEmpty();

        $originLabels = [
            'customized' => 'Özelleştirildi',
            'default'    => 'Varsayılan',
        ];

        // Açık süzgeçler rozet olarak listeleniyor; her rozet yalnızca kendi
        // süzgecini düşürüyor.
        $activeFilters = collect([
            'status' => [
                'label' => 'Durum',
                'value' => match ($filters['status']) {
                    'active'   => 'Aktif',
                    'inactive' => 'Pasif',
                    default    => '',
                },
            ],
            'search' => [
                'label' => 'Arama',
                'value' => $filters['search'],
            ],
            'variable' => [
                'label' => 'Değişken',
                'value' => $filters['variable'] !== '' ? '{' . $filters['variable'] . '}' : '',
            ],
            'origin' => [
                'label' => 'İçerik',
                'value' => $originLabels[$filters['origin']] ?? '',
            ],
            'sort' => [
                'label' => 'Sıralama',
                'value' => $sortOptions[$filters['sort']] ?? '',
            ],
        ])->filter(fn (array $chip): bool => $chip['value'] !== '');
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Mail Şablonları</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Mail Şablonları</h1>
            <p class="page-subtitle">Sistemden gönderilen e-postaların konu ve içeriklerini düzenleyin</p>
        </div>
        @if($hasFilter)
            <a href="{{ route('admin.mail-templates.index') }}" class="btn-glass">
                <i class="bi bi-arrow-counterclockwise"></i> Filtreleri Sıfırla
            </a>
        @endif
    </div>

    {{-- ==================== SECTION 1: STAT CARDS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal"><i class="bi bi-envelope-paper"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Şablon</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                    <span class="usr-stat-change">Gönderime açık</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-pause-circle"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Pasif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['inactive'] }}">0</h3>
                    <span class="usr-stat-change">Bu mailler gönderilmez</span>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-pencil-square"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Özelleştirildi</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['customized'] }}">0</h3>
                    <span class="usr-stat-change">Varsayılandan farklı</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Sayfanın nasıl çalıştığı --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Şablonlar gönderim anında doldurulur.</strong>
            Konu ve içerikte yazan <code>{değişken}</code> ifadeleri mail çıkarken
            gerçek değerleriyle değiştirilir; kart üzerindeki etiketler o şablonun
            hangi değişkenleri tanıdığını gösterir. Pasif bir şablonun maili hiç
            gönderilmez. İçeriği bozarsanız düzenleme ekranındaki
            <strong>Varsayılana Dön</strong> ile kurulumdaki hâline dönebilirsiniz.
        </div>
    </div>

    {{-- ==================== SECTION 2: STATUS TABS ==================== --}}
    @php
        $statusTabs = [
            ''         => ['label' => 'Tümü', 'icon' => '', 'color' => '', 'count' => $statusCounts['all']],
            'active'   => ['label' => 'Aktif', 'icon' => 'bi-check-circle', 'color' => 'text-neon-green', 'count' => $statusCounts['active']],
            'inactive' => ['label' => 'Pasif', 'icon' => 'bi-pause-circle', 'color' => 'text-neon-orange', 'count' => $statusCounts['inactive']],
        ];
    @endphp

    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        @foreach($statusTabs as $statusValue => $tab)
            <a href="{{ route('admin.mail-templates.index', array_merge(request()->except(['status', 'page']), $statusValue ? ['status' => $statusValue] : [])) }}"
               class="cl-status-tab {{ $filters['status'] === $statusValue ? 'active' : '' }}">
                @if($tab['icon'])
                    <i class="bi {{ $tab['icon'] }} {{ $tab['color'] }}"></i>
                @endif
                <span>{{ $tab['label'] }}</span>
                <span class="cl-tab-count">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.mail-templates.index') }}" class="cl-toolbar" id="filterForm">
                @if($filters['status'] !== '')
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif

                <div class="cl-search {{ $filters['search'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="mtSearchInput"
                           placeholder="Şablon adı, anahtar, açıklama veya konu ile ara..."
                           value="{{ $filters['search'] }}">
                    @if($filters['search'] !== '')
                        <a href="{{ route('admin.mail-templates.index', request()->except(['search', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                {{-- Alanların hepsi başlıklı: seçim kutuları aynı hizada başlar,
                     aynı hizada biter. --}}
                <div class="cl-filters mt-filters">
                    <div class="mt-field">
                        <span>Değişken</span>
                        <select class="cl-filter-select" name="variable" aria-label="Değişken"
                                data-select2-search="always"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tüm değişkenler</option>
                            @foreach($variableOptions as $variableKey => $option)
                                <option value="{{ $variableKey }}" {{ $filters['variable'] === (string) $variableKey ? 'selected' : '' }}>
                                    {{ '{' . $variableKey . '}' }} — {{ $option['label'] }} ({{ $option['count'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>İçerik</span>
                        <select class="cl-filter-select" name="origin" aria-label="İçerik durumu"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tüm içerikler</option>
                            <option value="customized" {{ $filters['origin'] === 'customized' ? 'selected' : '' }}>Özelleştirildi</option>
                            <option value="default" {{ $filters['origin'] === 'default' ? 'selected' : '' }}>Varsayılan</option>
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Sıralama</span>
                        <select class="cl-filter-select" name="sort" aria-label="Sıralama"
                                onchange="document.getElementById('filterForm').submit()">
                            @foreach($sortOptions as $sortValue => $sortLabel)
                                <option value="{{ $sortValue }}" {{ ($filters['sort'] ?: 'name') === $sortValue ? 'selected' : '' }}>{{ $sortLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field mt-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            <a href="{{ route('admin.mail-templates.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <span class="mt-result-count">{{ $templates->count() }} şablon</span>
                        </div>
                    </div>
                </div>
            </form>

            @include('partials.admin.filter-chips', [
                'chips' => $activeFilters,
                'route' => 'admin.mail-templates.index',
            ])
        </div>
    </div>

    {{-- ==================== SECTION 4: TEMPLATE CARDS ==================== --}}
    @if($templates->isEmpty())
        <div class="card-dark" data-aos="fade-up" data-aos-delay="200">
            <div class="card-body-custom text-center py-5">
                <i class="bi bi-envelope-x d-block fs-1 mb-2 text-muted"></i>
                <h6 class="text-muted mb-1">Sonuç bulunamadı</h6>
                @if($hasFilter)
                    <p class="text-muted mb-3">Bu filtreyle eşleşen şablon yok.</p>
                    <a href="{{ route('admin.mail-templates.index') }}" class="btn-glass">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Filtreleri Temizle
                    </a>
                @else
                    <p class="text-muted mb-0">Henüz mail şablonu tanımlanmamış.</p>
                @endif
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($templates as $template)
                @php
                    $origin = $origins[$template->id];
                    $variables = $template->variableKeys();
                    // Uzun değişken listesi kartı diğerlerinden bir baş yukarı
                    // çekiyordu; fazlası sayıyla özetleniyor.
                    $shownVariables = array_slice($variables, 0, 6);
                    $hiddenCount = count($variables) - count($shownVariables);
                @endphp
                <div class="col-xxl-4 col-lg-6" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 100 }}">
                    <article class="mt-tpl-card {{ $template->is_active ? '' : 'mt-tpl-card--inactive' }}">
                        <div class="mt-tpl-header">
                            <div class="mt-tpl-icon mt-tpl-icon--{{ $template->color }}">
                                <i class="bi {{ $template->icon }}"></i>
                            </div>
                            <div class="mt-tpl-title">
                                <h6>{{ $template->name }}</h6>
                                <code>{{ $template->key }}</code>
                            </div>
                            <span class="mt-tpl-badge {{ $template->is_active ? 'mt-tpl-badge--active' : 'mt-tpl-badge--inactive' }}">
                                <i class="bi {{ $template->is_active ? 'bi-check-circle-fill' : 'bi-pause-circle-fill' }}"></i>
                                {{ $template->is_active ? 'Aktif' : 'Pasif' }}
                            </span>
                        </div>

                        @if($template->description)
                            <p class="mt-tpl-desc">{{ $template->description }}</p>
                        @endif

                        <div class="mt-tpl-subject">
                            <small class="mt-tpl-subject-label">Konu</small>
                            <span>{{ Str::limit($template->subject, 70) }}</span>
                        </div>

                        <div class="mt-tpl-vars">
                            <small class="mt-tpl-vars-label">Değişkenler ({{ count($variables) }})</small>
                            <div class="mt-tpl-vars-list">
                                @forelse($shownVariables as $variableKey)
                                    <span class="mt-tpl-var-tag">{{ '{' . $variableKey . '}' }}</span>
                                @empty
                                    <span class="mt-tpl-vars-empty">Bu şablon değişken kullanmıyor.</span>
                                @endforelse
                                @if($hiddenCount > 0)
                                    <span class="mt-tpl-var-tag mt-tpl-var-tag--more">+{{ $hiddenCount }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-tpl-meta">
                            @if($origin['has_default'])
                                <span class="mt-tpl-origin {{ $origin['customized'] ? 'mt-tpl-origin--custom' : '' }}">
                                    <i class="bi {{ $origin['customized'] ? 'bi-pencil-fill' : 'bi-box-seam' }}"></i>
                                    {{ $origin['customized'] ? 'Özelleştirildi' : 'Varsayılan içerik' }}
                                </span>
                            @endif
                            <span class="mt-tpl-updated" title="{{ $template->updated_at?->translatedFormat('d F Y H:i') }}">
                                <i class="bi bi-clock-history"></i>{{ $template->updated_at?->diffForHumans() }}
                            </span>
                        </div>

                        <div class="mt-tpl-actions">
                            <button type="button" class="mt-tpl-btn mt-tpl-btn--ghost mt-preview-btn"
                                    data-url="{{ route('admin.mail-templates.preview', $template) }}"
                                    data-name="{{ $template->name }}">
                                <i class="bi bi-eye"></i> Önizle
                            </button>
                            <a href="{{ route('admin.mail-templates.edit', $template) }}" class="mt-tpl-btn mt-tpl-btn--primary">
                                <i class="bi bi-pencil-square"></i> Düzenle
                            </a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Kartlardan açılan önizleme; düzenleme ekranına gitmeden içerik görülsün. --}}
    <div class="modal fade" id="mtPreviewModal" tabindex="-1" aria-labelledby="mtPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mtPreviewModalLabel">
                        <i class="bi bi-eye me-2 text-teal"></i><span id="mtPreviewTitle">E-posta Önizleme</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="mt-preview-subject-bar">
                        <small class="text-muted">Konu:</small>
                        <strong id="mtPreviewSubject"></strong>
                    </div>
                    <iframe id="mtPreviewFrame" class="mt-preview-frame" title="E-posta önizleme"></iframe>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/mail-templates.js') }}"></script>
@endpush
