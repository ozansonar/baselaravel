@extends('layouts.admin')

@section('title', 'Yeni Slider')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.sliders.index') }}" class="breadcrumb-link">Sliderlar</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Slider</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.sliders.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Slider</h1>
                <p class="page-subtitle mb-0">Yeni bir slider oluşturun</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.sliders.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic">Temel Bilgiler</option>
                <option value="section-media">Görsel</option>
                <option value="section-button">Buton Ayarları</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                        <i class="bi bi-sliders"></i>
                        <div><span>Temel Bilgiler</span><small>Başlık, alt başlık, durum</small></div>
                    </a>
                    <a href="#section-media" class="stg-nav-item" onclick="scrollToSection('section-media', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Slider görseli</small></div>
                    </a>
                    <a href="#section-button" class="stg-nav-item" onclick="scrollToSection('section-button', this)">
                        <i class="bi bi-link-45deg"></i>
                        <div><span>Buton Ayarları</span><small>Buton metni ve URL</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-teal"><i class="bi bi-sliders"></i></div>
                            <div>
                                <h6 class="mb-0">Temel Bilgiler</h6>
                                <small class="text-muted">Slider başlığını, alt başlığını ve durumunu belirleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            {{-- Başlık --}}
                            <div class="col-12">
                                <label class="form-label" for="title">
                                    Başlık <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('title') is-invalid @enderror"
                                       id="title" name="title" value="{{ old('title') }}"
                                       placeholder="Slider başlığını girin..." required>
                                @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Alt Başlık --}}
                            <div class="col-12">
                                <label class="form-label" for="subtitle">Alt Başlık</label>
                                <input type="text"
                                       class="form-control @error('subtitle') is-invalid @enderror"
                                       id="subtitle" name="subtitle" value="{{ old('subtitle') }}"
                                       placeholder="Slider alt başlığını girin...">
                                @error('subtitle')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Sıralama --}}
                            <div class="col-md-6">
                                <label class="form-label" for="sort_order">Sıralama</label>
                                <input type="number"
                                       class="form-control @error('sort_order') is-invalid @enderror"
                                       id="sort_order" name="sort_order"
                                       value="{{ old('sort_order', 0) }}" min="0">
                                @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük değer = Daha üstte</div>
                            </div>

                            {{-- Durum --}}
                            <div class="col-md-6">
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
                                <small class="text-muted">Slider için görsel yükleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="image">
                                    Slider Görseli <span class="text-danger">*</span>
                                </label>
                                <input type="file"
                                       class="form-control @error('image') is-invalid @enderror"
                                       id="image" name="image" accept="image/*" required>
                                @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">PNG, JPG, WebP | Önerilen boyut: 1920x600 piksel</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: BUTON AYARLARI --}}
                <div class="card-dark mb-4" id="section-button" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-blue"><i class="bi bi-link-45deg"></i></div>
                            <div>
                                <h6 class="mb-0">Buton Ayarları</h6>
                                <small class="text-muted">Slider üzerindeki buton metni ve yönlendirme URL'si</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            {{-- Buton Metni --}}
                            <div class="col-md-6">
                                <label class="form-label" for="button_text">Buton Metni</label>
                                <input type="text"
                                       class="form-control @error('button_text') is-invalid @enderror"
                                       id="button_text" name="button_text"
                                       value="{{ old('button_text') }}"
                                       placeholder="Keşfet">
                                @error('button_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş bırakılırsa buton gösterilmez</div>
                            </div>

                            {{-- Buton URL --}}
                            <div class="col-md-6">
                                <label class="form-label" for="button_url">Buton URL</label>
                                <input type="text"
                                       class="form-control @error('button_url') is-invalid @enderror"
                                       id="button_url" name="button_url"
                                       value="{{ old('button_url') }}"
                                       placeholder="/urunler/...">
                                @error('button_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Dahili veya harici link girebilirsiniz</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                            <a href="{{ route('admin.sliders.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>Sliderlara Dön
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
