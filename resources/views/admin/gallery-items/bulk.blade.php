@extends('layouts.admin')

@section('title', 'Galeriye Toplu Yükleme')

@section('content')
@php
    /** Bayt tavanını kullanıcının okuduğu birime çevirir. */
    $humanLimit = $maxBytes >= 1_048_576
        ? round($maxBytes / 1_048_576) . ' MB'
        : round($maxBytes / 1024) . ' KB';
@endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.gallery-items.index') }}" class="breadcrumb-link">Galeri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Toplu Yükleme</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.gallery-items.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Toplu Yükleme</h1>
                <p class="page-subtitle mb-0">Bir etkinliğin bütün fotoğraflarını tek seferde galeriye ekleyin</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.gallery-items.create') }}" class="btn-glass">
                <i class="bi bi-plus-lg me-1"></i>Tek Öğe Ekle
            </a>
        </div>
    </div>

    <div class="gbu"
         data-gbu
         data-upload-url="{{ route('admin.gallery-items.bulk.store') }}"
         data-save-url="{{ route('admin.gallery-items.bulk.update') }}"
         data-destroy-url="{{ route('admin.gallery-items.bulk.destroy', ['galleryItem' => 'ITEM_ID']) }}"
         data-max-bytes="{{ $maxBytes }}"
         data-max-label="{{ $humanLimit }}"
         data-accept="{{ \App\Http\Requests\Admin\StoreGalleryBulkImageRequest::acceptAttribute() }}">

        {{-- ==================== ORTAK AYARLAR ==================== --}}
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-teal"><i class="bi bi-sliders"></i></div>
                    <div>
                        <h6 class="mb-0">Ortak Ayarlar</h6>
                        <small class="text-muted">Bu ayarlar, şimdi bırakacağınız bütün dosyalara uygulanır</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">

                    {{-- Dil --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="gbuLocale">Dil <span class="text-danger">*</span></label>
                        <select class="form-select" id="gbuLocale" data-gbu-locale data-fv-ignore>
                            @foreach($formLanguages as $language)
                                <option value="{{ $language->code }}">{{ $language->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Öğeler bu dile kaydedilir; çevirisi olmayan öğe ön yüzde varsayılan dilden görünür</div>
                    </div>

                    {{-- Kategori --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="gbuCategory">Kategori</label>
                        <select class="form-select" id="gbuCategory" data-gbu-category data-fv-ignore>
                            <option value="">Seçiniz</option>
                            {{-- Kategoriler de çevrilmiş: seçili dilin kategorileri gösteriliyor,
                                 gerisini JS dil değişince gizliyor. --}}
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" data-locale="{{ $category->locale }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Boş bırakılabilir, sonradan da atanabilir</div>
                    </div>

                    {{-- Durum --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="gbuActive">Durum</label>
                        <select class="form-select" id="gbuActive" data-gbu-active data-fv-ignore>
                            <option value="1" selected>Aktif</option>
                            <option value="0">Pasif</option>
                        </select>
                        <div class="form-text">Pasif seçersen başlıkları düzelttikten sonra yayına alabilirsin</div>
                    </div>

                    {{-- Sıra başlangıcı --}}
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="gbuSortStart">Sıra Başlangıcı</label>
                        <input type="text" class="form-control" id="gbuSortStart" value="0"
                               data-gbu-sort-start data-fv-mask="digits"
                               data-validation-engine="validate[custom[integer],min[0],max[65535]]">
                        <div class="form-text">Her dosya bir artarak numaralanır · Düşük değer = Daha üstte</div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ==================== BIRAKMA ALANI ==================== --}}
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-purple"><i class="bi bi-cloud-arrow-up"></i></div>
                    <div>
                        <h6 class="mb-0">Fotoğrafları Bırak</h6>
                        <small class="text-muted">Bırakılan her dosya anında galeriye eklenir, başlığını aşağıdan düzeltirsin</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="updz" data-gbu-dropzone>
                    <div class="dz-message updz__message">
                        <div class="updz__icon"><i class="bi bi-images"></i></div>
                        <div>
                            <p class="updz__title">Fotoğrafları buraya sürükle veya <u>bilgisayarından seç</u></p>
                            <p class="updz__hint">
                                Sayı sınırı yok · dosya başına en fazla {{ $humanLimit }} ·
                                başlık dosya adından türetilir, aşağıdan düzeltebilirsin
                            </p>
                            <ul class="updz__chips">
                                <li><i class="bi bi-file-earmark-image"></i>JPG · JPEG · PNG · WebP</li>
                                <li><i class="bi bi-camera-video"></i>Video için tekli formu kullanın (YouTube/Vimeo linki)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Yükleme özeti: kaç dosya gitti, kaçı düştü --}}
                <div class="gbu-summary d-none" data-gbu-summary>
                    <span class="gbu-summary__item gbu-summary__item--ok">
                        <i class="bi bi-check-circle-fill"></i><span data-gbu-count-ok>0</span> yüklendi
                    </span>
                    <span class="gbu-summary__item gbu-summary__item--err d-none">
                        <i class="bi bi-exclamation-triangle-fill"></i><span data-gbu-count-err>0</span> başarısız
                    </span>
                    <span class="gbu-summary__track" role="progressbar" aria-label="Toplam yükleme ilerlemesi">
                        <span class="gbu-summary__fill" data-gbu-progress></span>
                    </span>
                </div>
            </div>
        </div>

        {{-- ==================== BAŞLIK IZGARASI ==================== --}}
        <div class="card-dark mb-4 d-none" data-aos="fade-up" data-gbu-panel>
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-orange"><i class="bi bi-pencil-square"></i></div>
                    <div>
                        <h6 class="mb-0">Başlıklar</h6>
                        <small class="text-muted">Dosya adından türetildi · Değiştirmek istediklerini düzelt, gerisine dokunma</small>
                    </div>
                </div>
                <button type="button" class="btn-teal" data-gbu-save>
                    <i class="bi bi-check-lg me-1"></i>Hepsini Kaydet
                </button>
            </div>
            <div class="card-body-custom">
                <div class="gbu-grid" data-gbu-grid></div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/gallery-bulk-upload.js') }}"></script>
@endpush
