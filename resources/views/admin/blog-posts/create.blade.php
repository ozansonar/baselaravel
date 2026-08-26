@extends('layouts.admin')

@section('title', 'Yeni İçerik Oluştur')

@section('content')
{{-- data-validate hands the form to form-validation.js; the rules themselves
     live on the fields as data-validation-engine attributes. --}}
<form method="POST" action="{{ route('admin.blog-posts.store') }}" enctype="multipart/form-data" id="blogPostForm" data-validate novalidate>
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

          {{-- Her dil kendi sekmesinde --}}
          <x-language-tabs :languages="$formLanguages" id="postLangTabs">
            @foreach($formLanguages as $language)
              <x-language-tab-pane
                :language="$language"
                :active-locale="old('active_locale', $formLanguages->first()?->code)"
                id="postLangTabs">
                @include('admin.blog-posts._translatable-fields', [
                    'language'    => $language,
                    'translation' => null,
                ])
              </x-language-tab-pane>
            @endforeach
          </x-language-tabs>


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

@include('partials.admin.tinymce', ['tinymceSelector' => 'textarea[id^=body_]'])
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/content-add.js') }}"></script>
@endpush
