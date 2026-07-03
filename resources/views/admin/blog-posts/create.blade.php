@extends('layouts.admin')

@section('title', 'Yeni İçerik Oluştur')

@section('content')
<form method="POST" action="{{ route('admin.blog-posts.store') }}" enctype="multipart/form-data" id="blogPostForm">
    @csrf

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('admin.blog-posts.index') }}" class="breadcrumb-link">İçerikler</a>
          </li>
          <li class="breadcrumb-item active text-teal">Yeni İçerik Ekle</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
          <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass" title="Geri Dön">
            <i class="bi bi-arrow-left"></i>
          </a>
          <div>
            <h1 class="page-title mb-0">Yeni İçerik Oluştur</h1>
            <p class="page-subtitle mb-0">Tüm alanları doldurarak yeni bir içerik yayınlayın</p>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn-glass" id="btnAiFillBlog"
                  data-url="{{ route('admin.blog-posts.ai-fill') }}"
                  onclick="aiFillBlogAll()">
            <i class="bi bi-robot me-1"></i>AI ile Doldur
          </button>
          <button type="submit" name="is_published" value="0" class="btn-glass">
            <i class="bi bi-file-earmark me-1"></i>Taslak Kaydet
          </button>
          <button type="submit" name="is_published" value="1" class="btn-teal">
            <i class="bi bi-send me-1"></i>Yayınla
          </button>
        </div>
      </div>

      <!-- Mobile Section Jumper -->
      <div class="d-lg-none mb-4">
        <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
          <option value="" disabled selected>Bölüme git...</option>
          <option value="section-basic">Temel Bilgiler</option>
          <option value="section-content">İçerik Editörü</option>
          <option value="section-media">Medya Yönetimi</option>
          <option value="section-seo">SEO Ayarları</option>
          <option value="section-publish">Yayın Ayarları</option>
        </select>
      </div>

      <!-- Form Layout -->
      <div class="row g-4 align-items-start">

        <!-- Sol Navigasyon (yalnızca desktop) -->
        <div class="col-lg-3 d-none d-lg-block">
          <div class="stg-nav-inner position-sticky stg-nav-sticky">
            <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
              <i class="bi bi-text-paragraph"></i>
              <div><span>Temel Bilgiler</span><small>Başlık, slug, kategori</small></div>
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
          </div>
        </div>

        <!-- Form İçeriği -->
        <div class="col-12 col-lg-9">

          <!-- ==================== SECTION 1: TEMEL BİLGİLER ==================== -->
          <div class="card-dark mb-4" id="section-basic">
            <div class="card-header-custom">
              <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                <div>
                  <h6 class="mb-0">Temel Bilgiler</h6>
                  <small class="text-muted">İçeriğin başlığını, URL yapısını ve kategorisini belirleyin</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Başlık -->
                <div class="col-12">
                  <label class="form-label" for="title">
                    İçerik Başlığı <span class="text-danger">*</span>
                  </label>
                  <input
                    type="text"
                    class="form-control @error('title') is-invalid @enderror"
                    id="title"
                    name="title"
                    value="{{ old('title') }}"
                    placeholder="İçeriğin ana başlığını yazın..."
                    maxlength="120"
                    required
                    oninput="updateCharCounter(this, 120); generateSlug(this.value); updateSeoPreview()"
                  >
                  @error('title')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Dikkat çekici ve SEO uyumlu bir başlık girin</div>
                    <div class="form-text"><span id="title-counter">{{ Str::length(old('title', '')) }}</span>/120</div>
                  </div>
                </div>

                <!-- Slug -->
                <div class="col-12">
                  <label class="form-label" for="slug">URL (Slug)</label>
                  <div class="input-group">
                    <span class="input-group-text">{{ config('app.url') }}/blog/</span>
                    <input
                      type="text"
                      class="form-control @error('slug') is-invalid @enderror"
                      id="slug"
                      name="slug"
                      value="{{ old('slug') }}"
                      placeholder="otomatik-oluşturulur"
                    >
                  </div>
                  @error('slug')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="form-text">Başlık yazıldığında otomatik oluşturulur, isterseniz düzenleyebilirsiniz</div>
                </div>

                <!-- Kategori -->
                <div class="col-12">
                  <label class="form-label" for="blog_category_id">
                    Kategori <span class="text-danger">*</span>
                  </label>
                  <select class="form-select @error('blog_category_id') is-invalid @enderror" id="blog_category_id" name="blog_category_id" required>
                    <option value="">Kategori seçin...</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ old('blog_category_id') == $category->id ? 'selected' : '' }}>
                      {{ $category->name }}
                    </option>
                    @endforeach
                  </select>
                  @error('blog_category_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
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
                  <small class="text-muted">İçeriğin ana metnini ve kısa özetini yazın</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Kısa Özet -->
                <div class="col-12">
                  <label class="form-label" for="excerpt">
                    Kısa Özet
                  </label>
                  <textarea
                    class="form-control @error('excerpt') is-invalid @enderror"
                    id="excerpt"
                    name="excerpt"
                    rows="3"
                    maxlength="300"
                    placeholder="İçeriğin kısa bir özetini yazın (listeleme sayfalarında görünür)..."
                    oninput="updateCharCounter(this, 300)"
                  >{{ old('excerpt') }}</textarea>
                  @error('excerpt')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Arama sonuçlarında ve listelerde gösterilecek kısa açıklama</div>
                    <div class="form-text"><span id="excerpt-counter">{{ Str::length(old('excerpt', '')) }}</span>/300</div>
                  </div>
                </div>

                <!-- Ana İçerik -->
                <div class="col-12">
                  <label class="form-label" for="body">
                    Ana İçerik <span class="text-danger">*</span>
                  </label>
                  <textarea
                    class="@error('body') is-invalid @enderror"
                    id="body"
                    name="body"
                    rows="12"
                  >{{ old('body') }}</textarea>
                  @error('body')
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
                  <small class="text-muted">Kapak görseli yükleyin</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Kapak Görseli -->
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
                  <div class="form-text">PNG, JPG, WebP | Maks. 2 MB | Önerilen: 1200x630 px</div>
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

                <!-- SEO Önizleme -->
                <div class="col-12">
                  <label class="form-label">Google Arama Önizlemesi</label>
                  <div class="ca-seo-preview">
                    <div class="ca-seo-url">{{ config('app.url') }}/blog/<span id="seoPreviewSlug">yeni-icerik</span></div>
                    <div class="ca-seo-title" id="seoPreviewTitle">İçerik Başlığı Buraya Gelecek</div>
                    <div class="ca-seo-desc" id="seoPreviewDesc">İçeriğinizin meta açıklaması burada görünecek. Arama sonuçlarında kullanıcıların göreceği metin budur.</div>
                  </div>
                </div>

                <!-- Meta Başlık -->
                <div class="col-12">
                  <label class="form-label" for="meta_title">Meta Başlık</label>
                  <input
                    type="text"
                    class="form-control @error('meta_title') is-invalid @enderror"
                    id="meta_title"
                    name="meta_title"
                    value="{{ old('meta_title') }}"
                    maxlength="60"
                    placeholder="SEO için özel başlık (boş bırakılırsa içerik başlığı kullanılır)"
                    oninput="updateCharCounter(this, 60); updateSeoPreview()"
                  >
                  @error('meta_title')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Önerilen: 50-60 karakter</div>
                    <div class="form-text"><span id="meta_title-counter">{{ Str::length(old('meta_title', '')) }}</span>/60</div>
                  </div>
                </div>

                <!-- Meta Açıklama -->
                <div class="col-12">
                  <label class="form-label" for="meta_description">Meta Açıklama</label>
                  <textarea
                    class="form-control @error('meta_description') is-invalid @enderror"
                    id="meta_description"
                    name="meta_description"
                    rows="3"
                    maxlength="160"
                    placeholder="Arama sonuçlarında görünecek açıklama metni..."
                    oninput="updateCharCounter(this, 160); updateSeoPreview()"
                  >{{ old('meta_description') }}</textarea>
                  @error('meta_description')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Önerilen: 120-160 karakter</div>
                    <div class="form-text"><span id="meta_description-counter">{{ Str::length(old('meta_description', '')) }}</span>/160</div>
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
                  <small class="text-muted">İçeriğin yayın durumu ve tarihini ayarlayın</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Yayın Tarihi -->
                <div class="col-12 col-md-6">
                  <label class="form-label" for="published_at">Yayın Tarihi</label>
                  <input
                    type="datetime-local"
                    class="form-control @error('published_at') is-invalid @enderror"
                    id="published_at"
                    name="published_at"
                    value="{{ old('published_at') }}"
                  >
                  @error('published_at')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="form-text">Boş bırakılırsa hemen yayınlanır</div>
                </div>

              </div>
            </div>
          </div>


          <!-- ==================== FORM ACTIONS ==================== -->
          <div class="card-dark mb-4">
            <div class="card-body-custom">
              <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                <div class="d-flex gap-2">
                  <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass">
                    <i class="bi bi-x-lg me-1"></i>Vazgeç
                  </a>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="submit" name="is_published" value="0" class="btn-glass">
                    <i class="bi bi-file-earmark me-1"></i>Taslak Kaydet
                  </button>
                  <button type="submit" name="is_published" value="1" class="btn-teal">
                    <i class="bi bi-send me-1"></i>İçeriği Yayınla
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /col-12 col-lg-9 -->
      </div><!-- /row -->
</form>

@include('partials.admin.tinymce', ['tinymceSelector' => '#body'])
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/content-add.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/blog-ai-fill.js') }}"></script>
@endpush
