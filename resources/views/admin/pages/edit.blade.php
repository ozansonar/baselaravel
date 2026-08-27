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
    <select class="form-select form-select-sm" onchange="scrollToSection(this.value); this.selectedIndex=0" data-fv-ignore>
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

<form id="pageForm" method="POST" action="{{ route('admin.pages.update', $page) }}" enctype="multipart/form-data" data-validate novalidate>
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

            {{-- Her dil kendi sekmesinde. Çevirisi olmayan dil boş açılır ve
                 kaydedilene kadar o dilde satır oluşmaz. --}}

            <x-language-tabs :languages="$formLanguages" :model="$page" id="pageLangTabs">
                @foreach($formLanguages as $language)
                    <x-language-tab-pane
                        :language="$language"
                        :active-locale="old('active_locale', $formLanguages->first()?->code)"
                        id="pageLangTabs">
                        @include('admin.pages._translatable-fields', [
                            'language'    => $language,
                            'translation' => $page->translation($language->code),
                        ])
                    </x-language-tab-pane>
                @endforeach
            </x-language-tabs>


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

@include('partials.admin.tinymce', ['tinymceSelector' => 'textarea[id^=content_]'])

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/page-form.js') }}"></script>
@if($page->slug === 'hakkimizda')
<script src="{{ versioned_asset('assets/admin/js/page-sections.js') }}"></script>
@endif
@endpush

@endsection
