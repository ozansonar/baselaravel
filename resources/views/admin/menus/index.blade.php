@extends('layouts.admin')

@section('title', 'Menü Yönetimi')
@section('page_title', 'Menü Yönetimi')
@section('page_description', 'Site menülerini ve öğelerini yönetin')

@php
    $locationMeta = [
        'header' => [
            'icon'     => 'bi-layout-text-window-reverse',
            'variant'  => 'teal',
            'label'    => 'Üst Menü',
            'desc'     => 'Sitenin üst menü (navbar) öğelerini yönetir.',
        ],
        'footer' => [
            'icon'     => 'bi-layout-text-window',
            'variant'  => 'blue',
            'label'    => 'Alt Menü',
            'desc'     => 'Site alt bilgisindeki (footer) menü öğelerini yönetir.',
        ],
        'sidebar' => [
            'icon'     => 'bi-layout-sidebar',
            'variant'  => 'purple',
            'label'    => 'Yan Menü',
            'desc'     => 'Yan panelde gösterilen menü öğelerini yönetir.',
        ],
        'mobile' => [
            'icon'     => 'bi-phone',
            'variant'  => 'orange',
            'label'    => 'Mobil Menü',
            'desc'     => 'Mobil cihazlarda gösterilen menü öğelerini yönetir.',
        ],
    ];
@endphp

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Menü Yönetimi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">Menü Yönetimi</h1>
            <p class="page-subtitle">Site genelindeki menüleri ve menü öğelerini yönetin</p>
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-list-nested"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Menü</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif Menü</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-link-45deg"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Öğe</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_items'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Menü Konumu</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['locations'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: MENU CARDS ==================== --}}
    <div class="row g-4">
        @forelse($menus as $menu)
            @php
                $meta = $locationMeta[$menu->location] ?? [
                    'icon'    => 'bi-list',
                    'variant' => 'teal',
                    'label'   => ucfirst($menu->location),
                    'desc'    => 'Özel konumlandırılmış menü öğelerini yönetir.',
                ];
            @endphp
            <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                <div class="menu-manage-card menu-manage-card--{{ $meta['variant'] }}">
                    <div class="menu-manage-card__head">
                        <div class="menu-manage-card__icon">
                            <i class="bi {{ $meta['icon'] }}"></i>
                        </div>
                        <div class="menu-manage-card__count">
                            <span class="menu-manage-card__count-value">{{ $menu->items_count }}</span>
                            <span class="menu-manage-card__count-label">öğe</span>
                        </div>
                    </div>

                    <div class="menu-manage-card__body">
                        <div class="menu-manage-card__tags">
                            <span class="menu-manage-tag menu-manage-tag--{{ $meta['variant'] }}">
                                <i class="bi bi-geo-alt-fill"></i> {{ $meta['label'] }}
                            </span>
                            @php $menuLanguage = $languages[$menu->locale] ?? null; @endphp
                            <span class="menu-manage-tag menu-manage-tag--muted">
                                {{ $menuLanguage?->flag }} {{ $menuLanguage?->native_name ?? strtoupper($menu->locale) }}
                            </span>
                            @if($menu->is_active)
                                <span class="menu-manage-tag menu-manage-tag--success">
                                    <i class="bi bi-check-circle-fill"></i> Aktif
                                </span>
                            @else
                                <span class="menu-manage-tag menu-manage-tag--muted">
                                    <i class="bi bi-pause-circle-fill"></i> Pasif
                                </span>
                            @endif
                        </div>

                        <h3 class="menu-manage-card__title">{{ $menu->name }}</h3>
                        <p class="menu-manage-card__desc">{{ $meta['desc'] }}</p>
                    </div>

                    <div class="menu-manage-card__foot">
                        <a href="{{ route('admin.menus.items.index', $menu) }}" class="btn-teal flex-grow-1">
                            <i class="bi bi-pencil-square"></i> Öğeleri Yönet
                        </a>
                        @if(($missingLanguages[$menu->id] ?? collect())->isNotEmpty())
                            <div class="dropdown">
                                <button type="button" class="btn-glass dropdown-toggle" data-bs-toggle="dropdown"
                                        aria-expanded="false" title="Başka bir dile kopyala">
                                    <i class="bi bi-translate"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header">Bu menüyü kopyala</h6></li>
                                    @foreach($missingLanguages[$menu->id] as $language)
                                        <li>
                                            <form method="POST" action="{{ route('admin.menus.copy', [$menu, $language->code]) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    {{ $language->flag }} {{ $language->native_name }}
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#editMenuModal{{ $menu->id }}" title="Menü Ayarları">
                            <i class="bi bi-gear-fill"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Edit Menu Modal --}}
            <div class="modal fade" id="editMenuModal{{ $menu->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-theme">
                    <div class="modal-content modal-content-theme">
                        <form method="POST" action="{{ route('admin.menus.update', $menu) }}" data-validate novalidate>
                            @csrf
                            @method('PUT')
                            <div class="modal-header modal-header-theme">
                                <h5 class="modal-title">
                                    <i class="bi {{ $meta['icon'] }} me-2"></i> Menü Ayarları — {{ $menu->name }}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body modal-body-theme">
                                <div class="mb-3">
                                    <label for="menu_name_{{ $menu->id }}" class="form-label text-clr-secondary">Menü Adı</label>
                                    <div class="input-group input-group-theme">
                                        <span class="input-group-text"><i class="bi bi-list"></i></span>
                                        <input type="text" class="form-control form-control-theme" id="menu_name_{{ $menu->id }}"
                                               name="name" value="{{ $menu->name }}" data-validation-engine="validate[required,maxSize[100]]">
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="menu_active_{{ $menu->id }}"
                                           name="is_active" value="1" {{ $menu->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label text-clr-secondary" for="menu_active_{{ $menu->id }}">Aktif</label>
                                </div>
                            </div>
                            <div class="modal-footer modal-footer-theme">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal">
                                    <i class="bi bi-check-lg me-1"></i> Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12" data-aos="fade-up">
                <div class="admin-card">
                    <div class="admin-card-body text-center py-5">
                        <i class="bi bi-list-nested" style="font-size: 3rem; opacity: 0.4;"></i>
                        <h5 class="mt-3">Henüz menü yok</h5>
                        <p class="text-clr-secondary mb-0">Site menüleri migration üzerinden otomatik oluşturulur.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
