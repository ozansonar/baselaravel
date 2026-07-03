@extends('layouts.admin')

@section('title', 'Yeni Galeri Öğesi')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.gallery-items.index') }}" class="breadcrumb-link">Galeri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Öğe</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.gallery-items.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Galeri Öğesi</h1>
                <p class="page-subtitle mb-0">Galeriye yeni bir fotoğraf veya video ekleyin</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.gallery-items.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic">Temel Bilgiler</option>
                <option value="section-media">Görsel</option>
                <option value="section-video">Video Bilgileri</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                        <i class="bi bi-info-circle"></i>
                        <div><span>Temel Bilgiler</span><small>Başlık, tür, kategori, durum</small></div>
                    </a>
                    <a href="#section-media" class="stg-nav-item" onclick="scrollToSection('section-media', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Fotoğraf yükleme</small></div>
                    </a>
                    <a href="#section-video" class="stg-nav-item" onclick="scrollToSection('section-video', this)">
                        <i class="bi bi-camera-video"></i>
                        <div><span>Video Bilgileri</span><small>Video URL ve süre</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-teal"><i class="bi bi-info-circle"></i></div>
                            <div>
                                <h6 class="mb-0">Temel Bilgiler</h6>
                                <small class="text-muted">Galeri öğesinin başlığını, türünü ve kategorisini belirleyin</small>
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
                                       placeholder="Galeri öğesi başlığını girin..." required>
                                @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Açıklama --}}
                            <div class="col-12">
                                <label class="form-label" for="description">Açıklama</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="3"
                                          placeholder="Kısa bir açıklama girin...">{{ old('description') }}</textarea>
                                @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Tür --}}
                            <div class="col-md-6">
                                <label class="form-label" for="type">
                                    Tür <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('type') is-invalid @enderror"
                                        id="type" name="type" required>
                                    @foreach($types as $type)
                                        <option value="{{ $type->value }}" {{ old('type', 'photo') === $type->value ? 'selected' : '' }}>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Kategori --}}
                            <div class="col-md-6">
                                <label class="form-label" for="gallery_category_id">Kategori</label>
                                <select class="form-select @error('gallery_category_id') is-invalid @enderror"
                                        id="gallery_category_id" name="gallery_category_id">
                                    <option value="">Seçiniz</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ (int) old('gallery_category_id') === $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('gallery_category_id')
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
                                <small class="text-muted">Galeri öğesi için görsel yükleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="image">
                                    Görsel <span class="text-danger">*</span>
                                </label>
                                <input type="file"
                                       class="form-control @error('image') is-invalid @enderror"
                                       id="image" name="image" accept="image/*" required>
                                @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">PNG, JPG, WebP | Maks: 4 MB</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: VİDEO BİLGİLERİ --}}
                <div class="card-dark mb-4" id="section-video" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-blue"><i class="bi bi-camera-video"></i></div>
                            <div>
                                <h6 class="mb-0">Video Bilgileri</h6>
                                <small class="text-muted">Video türü seçildiyse URL ve süre bilgilerini girin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            {{-- Video URL --}}
                            <div class="col-md-8">
                                <label class="form-label" for="video_url">Video URL</label>
                                <input type="url"
                                       class="form-control @error('video_url') is-invalid @enderror"
                                       id="video_url" name="video_url"
                                       value="{{ old('video_url') }}"
                                       placeholder="https://www.youtube.com/watch?v=...">
                                @error('video_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">YouTube veya Vimeo video linki</div>
                            </div>

                            {{-- Süre --}}
                            <div class="col-md-4">
                                <label class="form-label" for="duration">Süre</label>
                                <input type="text"
                                       class="form-control @error('duration') is-invalid @enderror"
                                       id="duration" name="duration"
                                       value="{{ old('duration') }}"
                                       placeholder="03:45">
                                @error('duration')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Dakika:Saniye formatında</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                            <a href="{{ route('admin.gallery-items.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>Galeriye Dön
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
