@extends('layouts.admin')

@section('title', 'Sayfa Düzenle')

@section('content')

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.pages.index') }}" class="breadcrumb-link">Sayfalar</a>
        </li>
        <li class="breadcrumb-item active text-teal">Düzenle</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.pages.index') }}" class="btn-glass" title="Geri Dön">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-0">Sayfa Düzenle</h1>
            <p class="page-subtitle mb-0">{{ $page->title }}</p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.pages.index') }}" class="btn-glass">
            <i class="bi bi-x-lg me-1"></i>Vazgeç
        </a>
        <button type="submit" form="pageForm" class="btn-teal">
            <i class="bi bi-check-lg me-1"></i>Güncelle
        </button>
    </div>
</div>

<!-- Mobile Section Jumper -->
<div class="d-lg-none mb-4">
    <select class="form-select form-select-sm" onchange="scrollToSection(this.value); this.selectedIndex=0">
        <option value="" disabled selected>Bölüme git...</option>
        <option value="section-basic">Temel Bilgiler</option>
        <option value="section-content">İçerik Editörü</option>
        <option value="section-media">Medya Yönetimi</option>
        <option value="section-seo">SEO Ayarları</option>
        <option value="section-publish">Yayın Ayarları</option>
        <option value="section-advanced">Gelişmiş Ayarlar</option>
        @if($page->slug === 'hakkimizda')
        <option disabled>── Sayfa Bölümleri ──</option>
        <option value="section-story">Hikaye</option>
        <option value="section-values">Değerler</option>
        <option value="section-timeline">Tarihçe</option>
        <option value="section-stats">İstatistikler</option>
        <option value="section-team">Ekip</option>
        <option value="section-cta">CTA</option>
        @endif
    </select>
</div>

<form id="pageForm" method="POST" action="{{ route('admin.pages.update', $page) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <!-- Form Layout -->
    <div class="row g-4 align-items-start">

        <!-- Left Navigation (desktop only) -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="stg-nav-inner position-sticky stg-nav-sticky">
                <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                    <i class="bi bi-text-paragraph"></i>
                    <div><span>Temel Bilgiler</span><small>Başlık, slug</small></div>
                </a>
                <a href="#section-content" class="stg-nav-item" onclick="scrollToSection('section-content', this)">
                    <i class="bi bi-body-text"></i>
                    <div><span>İçerik Editörü</span><small>Ana metin ve özet</small></div>
                </a>
                <a href="#section-media" class="stg-nav-item" onclick="scrollToSection('section-media', this)">
                    <i class="bi bi-images"></i>
                    <div><span>Medya Yönetimi</span><small>Kapak görseli</small></div>
                </a>
                <a href="#section-seo" class="stg-nav-item" onclick="scrollToSection('section-seo', this)">
                    <i class="bi bi-search"></i>
                    <div><span>SEO Ayarları</span><small>Meta başlık, açıklama</small></div>
                </a>
                <a href="#section-publish" class="stg-nav-item" onclick="scrollToSection('section-publish', this)">
                    <i class="bi bi-calendar-event"></i>
                    <div><span>Yayın Ayarları</span><small>Durum, tarih</small></div>
                </a>
                <a href="#section-advanced" class="stg-nav-item" onclick="scrollToSection('section-advanced', this)">
                    <i class="bi bi-gear"></i>
                    <div><span>Gelişmiş Ayarlar</span><small>Sıralama</small></div>
                </a>
                @if($page->slug === 'hakkimizda')
                <hr class="my-2 border-secondary">
                <small class="text-muted d-block px-3 mb-2">Sayfa Bölümleri</small>
                <a href="#section-story" class="stg-nav-item" onclick="scrollToSection('section-story', this)">
                    <i class="bi bi-book"></i>
                    <div><span>Hikaye</span><small>Ana hikaye metni</small></div>
                </a>
                <a href="#section-values" class="stg-nav-item" onclick="scrollToSection('section-values', this)">
                    <i class="bi bi-heart"></i>
                    <div><span>Değerler</span><small>Temel değerler</small></div>
                </a>
                <a href="#section-timeline" class="stg-nav-item" onclick="scrollToSection('section-timeline', this)">
                    <i class="bi bi-clock-history"></i>
                    <div><span>Tarihçe</span><small>Zaman çizelgesi</small></div>
                </a>
                <a href="#section-stats" class="stg-nav-item" onclick="scrollToSection('section-stats', this)">
                    <i class="bi bi-graph-up"></i>
                    <div><span>İstatistikler</span><small>Sayısal veriler</small></div>
                </a>
                <a href="#section-team" class="stg-nav-item" onclick="scrollToSection('section-team', this)">
                    <i class="bi bi-people"></i>
                    <div><span>Ekip</span><small>Ekip üyeleri</small></div>
                </a>
                <a href="#section-cta" class="stg-nav-item" onclick="scrollToSection('section-cta', this)">
                    <i class="bi bi-megaphone"></i>
                    <div><span>CTA</span><small>Aksiyon çağrısı</small></div>
                </a>
                @endif
            </div>
        </div>

        <!-- Form Content -->
        <div class="col-12 col-lg-9">

            <!-- ==================== SECTION 1: TEMEL BİLGİLER ==================== -->
            <div class="card-dark mb-4" id="section-basic">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                        <div>
                            <h6 class="mb-0">Temel Bilgiler</h6>
                            <small class="text-muted">Sayfanın başlığını ve URL yapısını belirleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label" for="title">
                                Sayfa Başlığı <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('title') is-invalid @enderror"
                                id="title"
                                name="title"
                                value="{{ old('title', $page->title) }}"
                                placeholder="Sayfanın ana başlığını yazın..."
                                required
                                oninput="generateSlug(this.value); updateCharCounter(this, 120); updateSeoPreview()"
                            >
                            @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Dikkat çekici ve SEO uyumlu bir başlık girin</div>
                                <div class="form-text"><span id="title-counter">0</span>/120</div>
                            </div>
                        </div>

                        <!-- Slug -->
                        <div class="col-12">
                            <label class="form-label" for="slug">URL (Slug)</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('/') }}/sayfa/</span>
                                <input
                                    type="text"
                                    class="form-control @error('slug') is-invalid @enderror"
                                    id="slug"
                                    name="slug"
                                    value="{{ old('slug', $page->slug) }}"
                                    placeholder="otomatik-olusturulur"
                                    oninput="updateSeoPreview()"
                                >
                            </div>
                            @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Başlık yazıldığında otomatik oluşturulur, isterseniz düzenleyebilirsiniz</div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 2: İÇERİK EDİTÖRÜ ==================== -->
            <div class="card-dark mb-4" id="section-content">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-body-text"></i></div>
                        <div>
                            <h6 class="mb-0">İçerik Editörü</h6>
                            <small class="text-muted">Sayfanın ana metnini ve kısa özetini yazın</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Excerpt -->
                        <div class="col-12">
                            <label class="form-label" for="excerpt">Kısa Özet</label>
                            <textarea
                                class="form-control @error('excerpt') is-invalid @enderror"
                                id="excerpt"
                                name="excerpt"
                                rows="3"
                                placeholder="Sayfanın kısa bir özetini yazın..."
                                oninput="updateCharCounter(this, 300)"
                            >{{ old('excerpt', $page->excerpt) }}</textarea>
                            @error('excerpt')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Listeleme sayfalarında gösterilecek kısa açıklama</div>
                                <div class="form-text"><span id="excerpt-counter">0</span>/300</div>
                            </div>
                        </div>

                        <!-- Content (TinyMCE) -->
                        <div class="col-12">
                            <label class="form-label" for="content">
                                Ana İçerik <span class="text-danger">*</span>
                            </label>
                            <textarea
                                class="@error('content') is-invalid @enderror"
                                id="content"
                                name="content"
                                rows="12"
                            >{{ old('content', $page->content) }}</textarea>
                            @error('content')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 3: MEDYA YÖNETİMİ ==================== -->
            <div class="card-dark mb-4" id="section-media">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-images"></i></div>
                        <div>
                            <h6 class="mb-0">Medya Yönetimi</h6>
                            <small class="text-muted">Kapak görselini yükleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Current Image Preview -->
                        @if($page->image)
                        <div class="col-12">
                            <label class="form-label">Mevcut Görsel</label>
                            <div class="ca-current-image-preview">
                                <img src="{{ upload_url($page->image, 'sm') }}" alt="{{ $page->title }}" class="rounded img-fluid" loading="lazy">
                            </div>
                        </div>
                        @endif

                        <!-- Cover Image Upload -->
                        <div class="col-12">
                            <label class="form-label" for="image">
                                Kapak Görseli
                            </label>
                            <input
                                type="file"
                                class="form-control @error('image') is-invalid @enderror"
                                id="image"
                                name="image"
                                accept="image/png,image/jpeg,image/webp"
                            >
                            @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">PNG, JPG, WebP | Maks. 1 MB | Önerilen: 1200x630 px</div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 4: SEO AYARLARI ==================== -->
            <div class="card-dark mb-4" id="section-seo">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-search"></i></div>
                        <div>
                            <h6 class="mb-0">SEO Ayarları</h6>
                            <small class="text-muted">Arama motorları için meta bilgilerini düzenleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- SEO Preview -->
                        <div class="col-12">
                            <label class="form-label">Google Arama Önizlemesi</label>
                            <div class="ca-seo-preview">
                                <div class="ca-seo-url">{{ url('/') }}/sayfa/<span id="seoPreviewSlug">{{ $page->slug }}</span></div>
                                <div class="ca-seo-title" id="seoPreviewTitle">{{ $page->meta_title ?: $page->title }}</div>
                                <div class="ca-seo-desc" id="seoPreviewDesc">{{ $page->meta_description ?: 'Sayfanızın meta açıklaması burada görünecek.' }}</div>
                            </div>
                        </div>

                        <!-- Meta Title -->
                        <div class="col-12">
                            <label class="form-label" for="meta_title">Meta Başlık</label>
                            <input
                                type="text"
                                class="form-control @error('meta_title') is-invalid @enderror"
                                id="meta_title"
                                name="meta_title"
                                value="{{ old('meta_title', $page->meta_title) }}"
                                placeholder="SEO için özel başlık (boş bırakılırsa sayfa başlığı kullanılır)"
                                oninput="updateSeoPreview(); updateCharCounter(this, 60)"
                            >
                            @error('meta_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Önerilen: 50–60 karakter</div>
                                <div class="form-text"><span id="meta_title-counter">0</span>/60</div>
                            </div>
                        </div>

                        <!-- Meta Description -->
                        <div class="col-12">
                            <label class="form-label" for="meta_description">Meta Açıklama</label>
                            <textarea
                                class="form-control @error('meta_description') is-invalid @enderror"
                                id="meta_description"
                                name="meta_description"
                                rows="3"
                                placeholder="Arama sonuçlarında görünecek açıklama metni..."
                                oninput="updateSeoPreview(); updateCharCounter(this, 160)"
                            >{{ old('meta_description', $page->meta_description) }}</textarea>
                            @error('meta_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Önerilen: 120–160 karakter</div>
                                <div class="form-text"><span id="meta_description-counter">0</span>/160</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 5: YAYIN AYARLARI ==================== -->
            <div class="card-dark mb-4" id="section-publish">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <h6 class="mb-0">Yayın Ayarları</h6>
                            <small class="text-muted">Sayfanın yayın durumunu ve tarihini ayarlayın</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Status -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="status">
                                Yayın Durumu <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                @foreach(\App\Enums\ContentStatus::cases() as $status)
                                <option value="{{ $status->value }}"
                                    {{ old('status', $page->status->value) === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                                @endforeach
                            </select>
                            @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Published At -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="published_at">Yayın Tarihi</label>
                            <input
                                type="datetime-local"
                                class="form-control @error('published_at') is-invalid @enderror"
                                id="published_at"
                                name="published_at"
                                value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}"
                            >
                            @error('published_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 6: GELİŞMİŞ AYARLAR ==================== -->
            <div class="card-dark mb-4" id="section-advanced">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-gear"></i></div>
                        <div>
                            <h6 class="mb-0">Gelişmiş Ayarlar</h6>
                            <small class="text-muted">Sıralama ve diğer seçenekler</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Sort Order -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sort_order">Sıralama</label>
                            <input
                                type="number"
                                class="form-control @error('sort_order') is-invalid @enderror"
                                id="sort_order"
                                name="sort_order"
                                value="{{ old('sort_order', $page->sort_order) }}"
                                placeholder="0"
                                min="0"
                                max="999"
                            >
                            @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Düşük değer = Daha üstte görünür</div>
                        </div>

                    </div>
                </div>
            </div>


            @if($page->slug === 'hakkimizda')
            @include('admin.pages._sections-about')
            @endif

            <!-- ==================== FORM ACTIONS ==================== -->
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                        <a href="{{ route('admin.pages.index') }}" class="btn-glass">
                            <i class="bi bi-x-lg me-1"></i>Vazgeç
                        </a>
                        <button type="submit" class="btn-teal">
                            <i class="bi bi-check-lg me-1"></i>Güncelle
                        </button>
                    </div>
                </div>
            </div>

        </div><!-- /col-12 col-lg-9 -->
    </div><!-- /row -->
</form>

@include('partials.admin.tinymce')

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/page-form.js') }}"></script>
@if($page->slug === 'hakkimizda')
<script src="{{ versioned_asset('assets/admin/js/page-sections.js') }}"></script>
@endif
@endpush

@endsection
