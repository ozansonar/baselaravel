@extends('layouts.admin')

@section('title', 'Diller')
@section('page_title', 'Diller')
@section('page_description', 'Sitenin yayınlandığı dilleri yönetin')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item active text-teal">Diller</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Diller</h1>
            <p class="page-subtitle">Sitenin yayınlandığı dilleri yönetin</p>
        </div>
        @can('create', App\Models\Language::class)
            <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
                <i class="bi bi-plus-lg"></i> Dil Ekle
            </button>
        @endcan
    </div>

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-translate"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Tanımlı Dil</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Yayında</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-pause-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Pasif</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['inactive'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-star-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Varsayılan</span>
                    <h3 class="usr-stat-value">{{ strtoupper($stats['default'] ?? '—') }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info" data-aos="fade-up">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Varsayılan dil</strong> her zaman bir tanedir ve pasife alınamaz, silinemez.
        Ziyaretçi tarayıcı diline uyan bir dil yoksa bu dili görür; çevrilmemiş içerik de
        bu dilden gösterilir.
    </div>

    {{-- TABLE --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Dil</th>
                            <th>Kod</th>
                            <th class="d-none d-md-table-cell">Durum</th>
                            <th class="d-none d-lg-table-cell">Arayüz Çevirisi</th>
                            <th class="d-none d-lg-table-cell">İçerik</th>
                            <th class="d-none d-xl-table-cell">Sıra</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($languages as $language)
                            @php
                                $hasFiles = in_array($language->code, $translated, true);
                                $contentCount = $contentStats[$language->code] ?? 0;
                            @endphp
                            <tr>
                                <td>
                                    <span class="fs-5 me-2">{{ $language->flag }}</span>
                                    <span class="fw-semibold">{{ $language->native_name ?: $language->name }}</span>
                                    @if($language->native_name && $language->native_name !== $language->name)
                                        <small class="text-clr-secondary ms-1">({{ $language->name }})</small>
                                    @endif
                                    @if($language->is_default)
                                        <span class="menu-manage-tag menu-manage-tag--info ms-2">
                                            <i class="bi bi-star-fill"></i> Varsayılan
                                        </span>
                                    @endif
                                </td>
                                <td><code>{{ $language->code }}</code></td>
                                <td class="d-none d-md-table-cell">
                                    @if($language->is_active)
                                        <span class="menu-manage-tag menu-manage-tag--success">Yayında</span>
                                    @else
                                        <span class="menu-manage-tag menu-manage-tag--muted">Pasif</span>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if($hasFiles)
                                        <span class="text-neon-green"><i class="bi bi-check-circle"></i> var</span>
                                    @else
                                        <span class="text-neon-orange" title="lang/{{ $language->code }}/ klasörü yok">
                                            <i class="bi bi-exclamation-triangle"></i> yok
                                        </span>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <small class="text-clr-secondary">{{ number_format($contentCount) }} kayıt</small>
                                </td>
                                <td class="d-none d-xl-table-cell">{{ $language->sort_order }}</td>
                                <td class="text-end">
                                    @can('update', $language)
                                        @unless($language->is_default)
                                            <form method="POST" action="{{ route('admin.languages.default', $language) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="usr-action-btn" title="Varsayılan yap">
                                                    <i class="bi bi-star"></i>
                                                </button>
                                            </form>
                                        @endunless
                                        <button type="button" class="usr-action-btn" title="Düzenle"
                                                data-bs-toggle="modal" data-bs-target="#editLanguageModal{{ $language->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $language)
                                        @unless($language->is_default)
                                            <button type="button" class="usr-action-btn danger" title="Sil"
                                                    onclick="openLanguageDelete({{ $language->id }}, @js($language->native_name ?: $language->name), {{ $contentCount }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Edit modals --}}
    @can('update', $languages->first() ?? new App\Models\Language())
        @foreach($languages as $language)
            <div class="modal fade modal-custom" id="editLanguageModal{{ $language->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-theme">
                    <div class="modal-content modal-content-theme">
                        <form method="POST" action="{{ route('admin.languages.update', $language) }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h6 class="modal-title">
                                    <i class="bi bi-pencil me-2 text-teal"></i>{{ $language->native_name ?: $language->name }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body">
                                @include('admin.languages._fields', ['language' => $language])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal">Kaydet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endcan

    {{-- Add modal --}}
    @can('create', App\Models\Language::class)
        <div class="modal fade modal-custom" id="addLanguageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <form method="POST" action="{{ route('admin.languages.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="bi bi-plus-lg me-2 text-teal"></i>Dil Ekle</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body">
                            @include('admin.languages._fields', ['language' => null])

                            <div class="alert alert-warning mt-3 mb-0 py-2">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    Yeni dilin <strong>arayüz metinleri</strong> için
                                    <code>lang/tr/</code> klasörünü yeni dil koduyla kopyalayıp çevirmen gerekir.
                                    O zamana kadar arayüz varsayılan dilde görünür; <strong>içerik</strong>
                                    ise panelden dil sekmeleriyle girilir.
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Delete modal --}}
        <div class="modal fade modal-custom" id="deleteLanguageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <div class="modal-body text-center p-4">
                        <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <h5 class="mt-3">Dili sil</h5>
                        <p class="text-clr-secondary mb-2">
                            <strong id="deleteLanguageName"></strong> silinecek.
                        </p>
                        <p class="text-clr-secondary mb-4" id="deleteLanguageWarning"></p>
                        <form method="POST" id="deleteLanguageForm">
                            @csrf
                            @method('DELETE')
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-danger-solid">Sil</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
<script>
    window.languageDeleteUrl = @js(route('admin.languages.destroy', ['language' => 'LANGUAGE_ID']));
</script>
<script src="{{ versioned_asset('assets/admin/js/languages.js') }}"></script>
@endpush
