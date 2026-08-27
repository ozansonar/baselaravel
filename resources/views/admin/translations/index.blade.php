@extends('layouts.admin')

@section('title', 'Dil Yazıları')
@section('page_title', 'Dil Yazıları')
@section('page_description', 'Sitedeki sabit arayüz metinlerini dile göre düzenleyin')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item active text-teal">Dil Yazıları</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Dil Yazıları</h1>
            <p class="page-subtitle">Butonlar, etiketler, başlıklar — sitedeki sabit metinler</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('viewAny', App\Models\Language::class)
                <a href="{{ route('admin.languages.index') }}" class="btn-glass">
                    <i class="bi bi-translate"></i> Diller
                </a>
            @endcan
            @can('update', App\Models\Translation::class)
                <button type="button" class="btn-glass text-neon-red" data-bs-toggle="modal" data-bs-target="#resetModal">
                    <i class="bi bi-arrow-counterclockwise"></i> Varsayılana Dön
                </button>
                <button type="submit" form="translationForm" class="btn-teal">
                    <i class="bi bi-save"></i> Kaydet
                </button>
            @endcan
        </div>
    </div>

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-fonts"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Metin</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-pencil-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Değiştirilen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['changed'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Çevrilmemiş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['missing'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-translate"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Yayındaki Dil</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['languages'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- LANGUAGE TABS --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        @foreach($languages as $language)
            <a href="{{ route('admin.translations.index', ['locale' => $language->code]) }}"
               class="cl-status-tab {{ $locale === $language->code ? 'active' : '' }}">
                <span>{{ $language->flag }} {{ $language->native_name ?: $language->name }}</span>
                @if(($overrideCounts[$language->code] ?? 0) > 0)
                    <span class="cl-tab-count">{{ $overrideCounts[$language->code] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- SEARCH --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <div class="cl-toolbar">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="search" id="translationSearch" placeholder="Metin veya anahtar ara… (örn: giriş, nav.home)"
                           autocomplete="off" data-fv-ignore>
                </div>
                <div class="cl-toolbar-actions">
                    <label class="d-flex align-items-center gap-2 mb-0">
                        <input type="checkbox" id="onlyChanged" data-fv-ignore>
                        <span class="text-clr-secondary small">Yalnızca değiştirilenler</span>
                    </label>
                    <span class="text-clr-secondary small ms-3" id="searchCount"></span>
                </div>
            </div>
        </div>
    </div>

    @php $canEdit = auth()->user()->can('update', App\Models\Translation::class); @endphp

    <form method="POST" action="{{ route('admin.translations.update') }}" id="translationForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="locale" value="{{ $locale }}">

        @foreach($sections as $sectionKey => $section)
            <div class="card-dark mb-4 translation-section" data-section="{{ $sectionKey }}" data-aos="fade-up">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h6><i class="bi {{ $section['icon'] }} me-2 text-teal"></i>{{ $section['label'] }}</h6>
                    <span class="text-clr-secondary small">{{ count($section['rows']) }} metin</span>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        @foreach($section['rows'] as $row)
                            <div class="col-lg-6 translation-row"
                                 data-key="{{ $row['key'] }}"
                                 data-search="{{ mb_strtolower($row['key'] . ' ' . $row['label'] . ' ' . $row['value'] . ' ' . $row['reference']) }}"
                                 data-changed="{{ $row['overridden'] ? '1' : '0' }}">
                                <div class="stg-field">
                                    <label class="stg-label d-flex justify-content-between align-items-center" for="k{{ md5($row['key']) }}">
                                        <span>{{ $row['label'] }}</span>
                                        <span class="d-flex align-items-center gap-1">
                                            @if($row['overridden'])
                                                <span class="menu-manage-tag menu-manage-tag--success translation-badge">değiştirildi</span>
                                            @endif
                                            @if($row['missing'])
                                                <span class="menu-manage-tag menu-manage-tag--warning">çevrilmemiş</span>
                                            @endif
                                        </span>
                                    </label>

                                    @if($row['multiline'])
                                        <textarea class="stg-textarea" id="k{{ md5($row['key']) }}"
                                                  name="values[{{ $row['key'] }}]" rows="3"
                                                  data-validation-engine="validate[maxSize[5000]]"
                                                  data-default="{{ $row['default'] }}"
                                                  {{ $canEdit ? '' : 'disabled' }}>{{ $row['value'] }}</textarea>
                                    @else
                                        <input type="text" class="stg-input" id="k{{ md5($row['key']) }}"
                                               name="values[{{ $row['key'] }}]" value="{{ $row['value'] }}"
                                               data-validation-engine="validate[maxSize[5000]]"
                                               data-default="{{ $row['default'] }}"
                                               {{ $canEdit ? '' : 'disabled' }}>
                                    @endif

                                    <small class="stg-hint d-flex justify-content-between gap-2">
                                        <code class="translation-key">{{ $row['key'] }}</code>
                                        @if($row['overridden'] && $canEdit)
                                            <button type="button" class="btn-link-teal translation-revert"
                                                    title="Bu metni varsayılana döndür">
                                                <i class="bi bi-arrow-counterclockwise"></i> varsayılan
                                            </button>
                                        @endif
                                    </small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="translation-empty card-dark mb-4 d-none" id="noResults">
            <div class="card-body-custom text-center py-5">
                <i class="bi bi-search d-block mb-2" style="font-size:2rem"></i>
                <p class="mb-0 text-clr-secondary">Aramanla eşleşen metin yok.</p>
            </div>
        </div>

        @can('update', App\Models\Translation::class)
            <div class="d-flex justify-content-end gap-2 mb-4">
                <button type="submit" class="btn-teal btn-lg"><i class="bi bi-save"></i> Kaydet</button>
            </div>
        @endcan
    </form>

    @can('update', App\Models\Translation::class)
        <div class="modal fade modal-custom" id="resetModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <div class="modal-body text-center p-4">
                        <div class="delete-modal-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
                        <h5 class="mt-3">Varsayılana dön</h5>
                        <p class="text-clr-secondary mb-4">
                            Bu dilde değiştirdiğin <strong>{{ $stats['changed'] }}</strong> metin silinir ve
                            hepsi projeyle gelen hâline döner. Diğer diller etkilenmez.
                        </p>
                        <form method="POST" action="{{ route('admin.translations.reset') }}">
                            @csrf
                            <input type="hidden" name="locale" value="{{ $locale }}">
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-danger-solid">Varsayılana Dön</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('styles')
<style>
    .translation-key { font-size: .7rem; opacity: .55; }
    .translation-badge { font-size: .65rem; }
    .btn-link-teal {
        background: none; border: 0; padding: 0;
        color: var(--neon-teal, #2ee6a8); font-size: .7rem; cursor: pointer;
    }
    .btn-link-teal:hover { text-decoration: underline; }
    .translation-row.is-hidden, .translation-section.is-hidden { display: none; }
</style>
@endpush

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/translations.js') }}"></script>
@endpush
