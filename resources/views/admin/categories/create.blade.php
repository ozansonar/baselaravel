@extends('layouts.admin')

@section('title', 'Yeni Kategori')
@section('page_title', 'Yeni Kategori')
@section('page_description', 'Yeni kategori oluştur')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.categories.index') }}" class="breadcrumb-link">Kategoriler</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Kategori</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.categories.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Kategori Oluştur</h1>
                <p class="page-subtitle mb-0">Tüm alanları doldurarak yeni bir kategori ekleyin</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic">Temel Bilgiler</option>
                <option value="section-media">Görsel</option>
                <option value="section-seo">SEO Ayarları</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                        <i class="bi bi-folder"></i>
                        <div><span>Temel Bilgiler</span><small>Ad, üst kategori, durum</small></div>
                    </a>
                    <a href="#section-media" class="stg-nav-item" onclick="scrollToSection('section-media', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Kategori görseli</small></div>
                    </a>
                    <a href="#section-seo" class="stg-nav-item" onclick="scrollToSection('section-seo', this)">
                        <i class="bi bi-search"></i>
                        <div><span>SEO Ayarları</span><small>Meta başlık, açıklama</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-teal"><i class="bi bi-folder"></i></div>
                            <div>
                                <h6 class="mb-0">Temel Bilgiler</h6>
                                <small class="text-muted">Kategorinin adını, üst kategorisini ve durumunu belirleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            {{-- Kategori Adı --}}
                            <div class="col-12">
                                <label class="form-label" for="name">
                                    Kategori Adı <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name') }}"
                                       placeholder="Kategori adını girin..." required>
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Üst Kategori --}}
                            <div class="col-md-6">
                                <label class="form-label" for="parent_id">Üst Kategori</label>
                                <select class="form-select @error('parent_id') is-invalid @enderror"
                                        id="parent_id" name="parent_id">
                                    <option value="">— Ana Kategori —</option>
                                    @foreach($parentCategories as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş bırakılırsa ana kategori olarak oluşturulur</div>
                            </div>

                            {{-- Sıralama --}}
                            <div class="col-md-3">
                                <label class="form-label" for="sort_order">Sıralama</label>
                                <input type="number"
                                       class="form-control @error('sort_order') is-invalid @enderror"
                                       id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0">
                                @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük değer = Daha üstte</div>
                            </div>

                            {{-- Durum --}}
                            <div class="col-md-3">
                                <label class="form-label" for="is_active">Durum</label>
                                <select class="form-select @error('is_active') is-invalid @enderror"
                                        id="is_active" name="is_active">
                                    <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ old('is_active', 1) == 0 ? 'selected' : '' }}>Pasif</option>
                                </select>
                                @error('is_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Açıklama --}}
                            <div class="col-12">
                                <label class="form-label" for="description">Açıklama</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="3"
                                          placeholder="Kategori açıklamasını yazın...">{{ old('description') }}</textarea>
                                @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 2: GÖRSEL --}}
                <div class="card-dark mb-4" id="section-media" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-purple"><i class="bi bi-image"></i></div>
                            <div>
                                <h6 class="mb-0">Görsel</h6>
                                <small class="text-muted">Kategori görseli yükleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="image">Kategori Görseli</label>
                                <input type="file"
                                       class="form-control @error('image') is-invalid @enderror"
                                       id="image" name="image" accept="image/*">
                                @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">PNG, JPG, WebP | Maks. 2 MB</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: SEO AYARLARI --}}
                <div class="card-dark mb-4" id="section-seo" data-aos="fade-up">
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
                            <div class="col-12">
                                <label class="form-label" for="meta_title">Meta Başlık</label>
                                <input type="text"
                                       class="form-control @error('meta_title') is-invalid @enderror"
                                       id="meta_title" name="meta_title" value="{{ old('meta_title') }}"
                                       placeholder="SEO için özel başlık (boş bırakılırsa kategori adı kullanılır)">
                                @error('meta_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Önerilen: 50–60 karakter</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="meta_description">Meta Açıklama</label>
                                <textarea class="form-control @error('meta_description') is-invalid @enderror"
                                          id="meta_description" name="meta_description" rows="3"
                                          placeholder="Arama sonuçlarında görünecek açıklama metni...">{{ old('meta_description') }}</textarea>
                                @error('meta_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Önerilen: 120–160 karakter</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                            <a href="{{ route('admin.categories.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>Kategorilere Dön
                            </a>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i>Kaydet
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
@endsection
