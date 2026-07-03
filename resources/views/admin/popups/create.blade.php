@extends('layouts.admin')

@section('title', 'Yeni Popup')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.popups.index') }}" class="breadcrumb-link">Popup Yönetimi</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Popup</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.popups.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Popup</h1>
                <p class="page-subtitle mb-0">Yeni bir popup modal oluşturun</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.popups.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic">Temel Bilgiler</option>
                <option value="section-media">Görsel</option>
                <option value="section-button">Buton Ayarları</option>
                <option value="section-settings">Popup Ayarları</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                        <i class="bi bi-window-stack"></i>
                        <div><span>Temel Bilgiler</span><small>Başlık, açıklama</small></div>
                    </a>
                    <a href="#section-media" class="stg-nav-item" onclick="scrollToSection('section-media', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Popup görseli</small></div>
                    </a>
                    <a href="#section-button" class="stg-nav-item" onclick="scrollToSection('section-button', this)">
                        <i class="bi bi-link-45deg"></i>
                        <div><span>Buton Ayarları</span><small>Buton metni ve URL</small></div>
                    </a>
                    <a href="#section-settings" class="stg-nav-item" onclick="scrollToSection('section-settings', this)">
                        <i class="bi bi-gear"></i>
                        <div><span>Popup Ayarları</span><small>Boyut, sayfalar, tarih</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-teal"><i class="bi bi-window-stack"></i></div>
                            <div>
                                <h6 class="mb-0">Temel Bilgiler</h6>
                                <small class="text-muted">Popup başlığını ve açıklamasını belirleyin</small>
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
                                       placeholder="Popup başlığını girin..." required>
                                @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Açıklama --}}
                            <div class="col-12">
                                <label class="form-label" for="description">Açıklama</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="3"
                                          placeholder="Popup açıklamasını girin...">{{ old('description') }}</textarea>
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
                                <small class="text-muted">Popup için görsel yükleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="image">Popup Görseli</label>
                                <input type="file"
                                       class="form-control @error('image') is-invalid @enderror"
                                       id="image" name="image" accept="image/*">
                                @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">PNG, JPG, WebP | Maks: 2 MB</div>
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
                                <small class="text-muted">Popup üzerindeki buton metni ve yönlendirme URL'si</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="button_text">Buton Metni</label>
                                <input type="text"
                                       class="form-control @error('button_text') is-invalid @enderror"
                                       id="button_text" name="button_text"
                                       value="{{ old('button_text') }}"
                                       placeholder="Detaylı Bilgi">
                                @error('button_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş bırakılırsa buton gösterilmez</div>
                            </div>

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

                {{-- SECTION 4: POPUP AYARLARI --}}
                <div class="card-dark mb-4" id="section-settings" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-orange"><i class="bi bi-gear"></i></div>
                            <div>
                                <h6 class="mb-0">Popup Ayarları</h6>
                                <small class="text-muted">Boyut, görüntülenme sayfaları, tarih aralığı ve durum</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            {{-- Boyut --}}
                            <div class="col-md-6">
                                <label class="form-label" for="size">Popup Boyutu</label>
                                <select class="form-select @error('size') is-invalid @enderror"
                                        id="size" name="size">
                                    @foreach(\App\Enums\PopupSize::cases() as $size)
                                        <option value="{{ $size->value }}" {{ old('size', 'md') === $size->value ? 'selected' : '' }}>
                                            {{ $size->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('size')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Sıralama --}}
                            <div class="col-md-3">
                                <label class="form-label" for="sort_order">Sıralama</label>
                                <input type="number"
                                       class="form-control @error('sort_order') is-invalid @enderror"
                                       id="sort_order" name="sort_order"
                                       value="{{ old('sort_order', 0) }}" min="0">
                                @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük = Önce gösterilir</div>
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

                            {{-- Sayfalar --}}
                            <div class="col-12">
                                <label class="form-label">
                                    Görüntülenecek Sayfalar <span class="text-danger">*</span>
                                </label>
                                @error('pages')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                                @enderror
                                <div class="row g-2">
                                    @foreach(\App\Enums\PopupPage::cases() as $page)
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="pages[]" value="{{ $page->value }}"
                                                       id="page_{{ $page->value }}"
                                                       {{ in_array($page->value, old('pages', ['all'])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="page_{{ $page->value }}">
                                                    {{ $page->label() }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Başlangıç Tarihi --}}
                            <div class="col-md-6">
                                <label class="form-label" for="start_date">Başlangıç Tarihi</label>
                                <input type="date"
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       id="start_date" name="start_date"
                                       value="{{ old('start_date') }}">
                                @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş = Hemen başlar</div>
                            </div>

                            {{-- Bitiş Tarihi --}}
                            <div class="col-md-6">
                                <label class="form-label" for="end_date">Bitiş Tarihi</label>
                                <input type="date"
                                       class="form-control @error('end_date') is-invalid @enderror"
                                       id="end_date" name="end_date"
                                       value="{{ old('end_date') }}">
                                @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş = Süresiz gösterilir</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                            <a href="{{ route('admin.popups.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>Popup Listesine Dön
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
