@extends('layouts.admin')

@section('title', 'Yeni Şehir Sayfası')

@section('content')
<form method="POST" action="{{ route('admin.city-pages.store') }}" id="cityPageForm">
    @csrf

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.city-pages.index') }}" class="breadcrumb-link">Şehir Sayfaları</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Şehir Sayfası</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.city-pages.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Şehir Sayfası</h1>
                <p class="page-subtitle mb-0">Hedef şehir için SEO landing page oluşturun</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-glass" id="btnAiFillCity"
                    data-url="{{ route('admin.city-pages.ai-fill') }}"
                    onclick="aiFillCityAll()">
                <i class="bi bi-robot me-1"></i>AI ile İçerik Üret
            </button>
            <button type="submit" name="is_active" value="0" class="btn-glass">
                <i class="bi bi-pause-circle me-1"></i>Pasif Kaydet
            </button>
            <button type="submit" name="is_active" value="1" class="btn-teal">
                <i class="bi bi-send me-1"></i>Yayınla
            </button>
        </div>
    </div>

    {{-- Mobile Section Jumper --}}
    <div class="d-lg-none mb-4">
        <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
            <option value="" disabled selected>Bölüme git...</option>
            <option value="section-basic">Temel Bilgiler</option>
            <option value="section-hero">Hero Bölümü</option>
            <option value="section-content">Sayfa İçeriği</option>
            <option value="section-shipping">Teslimat Bilgileri</option>
            <option value="section-seo">SEO Ayarları</option>
            <option value="section-publish">Yayın Ayarları</option>
        </select>
    </div>

    <div class="row g-4 align-items-start">

        {{-- Sol Navigasyon --}}
        <div class="col-lg-3 d-none d-lg-block">
            <div class="stg-nav-inner position-sticky stg-nav-sticky">
                <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                    <i class="bi bi-geo-alt"></i>
                    <div><span>Temel Bilgiler</span><small>Şehir, slug, tier</small></div>
                </a>
                <a href="#section-hero" class="stg-nav-item" onclick="scrollToSection('section-hero', this)">
                    <i class="bi bi-megaphone"></i>
                    <div><span>Hero Bölümü</span><small>H1 başlık ve özet</small></div>
                </a>
                <a href="#section-content" class="stg-nav-item" onclick="scrollToSection('section-content', this)">
                    <i class="bi bi-body-text"></i>
                    <div><span>Sayfa İçeriği</span><small>Ana metin (HTML)</small></div>
                </a>
                <a href="#section-shipping" class="stg-nav-item" onclick="scrollToSection('section-shipping', this)">
                    <i class="bi bi-truck"></i>
                    <div><span>Teslimat</span><small>Kargo notu, süre</small></div>
                </a>
                <a href="#section-seo" class="stg-nav-item" onclick="scrollToSection('section-seo', this)">
                    <i class="bi bi-search"></i>
                    <div><span>SEO Ayarları</span><small>Meta başlık, açıklama</small></div>
                </a>
                <a href="#section-publish" class="stg-nav-item" onclick="scrollToSection('section-publish', this)">
                    <i class="bi bi-toggles"></i>
                    <div><span>Yayın Ayarları</span><small>Aktif, sıra</small></div>
                </a>
            </div>
        </div>

        <div class="col-12 col-lg-9">

            {{-- SECTION 1: TEMEL BİLGİLER --}}
            <div class="card-dark mb-4" id="section-basic">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <h6 class="mb-0">Temel Bilgiler</h6>
                            <small class="text-muted">Hedef şehir, URL slug ve önem sırası</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="city_name">Şehir Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('city_name') is-invalid @enderror"
                                   id="city_name" name="city_name" value="{{ old('city_name') }}"
                                   placeholder="Örn: İstanbul" maxlength="100" required
                                   oninput="generateCitySlug(this.value); updateSeoPreview()">
                            @error('city_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="region">Bölge</label>
                            <input type="text" class="form-control @error('region') is-invalid @enderror"
                                   id="region" name="region" value="{{ old('region') }}"
                                   placeholder="Örn: Marmara" maxlength="60">
                            @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="city_slug">URL Slug</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ config('app.url') }}/</span>
                                <input type="text" class="form-control @error('city_slug') is-invalid @enderror"
                                       id="city_slug" name="city_slug" value="{{ old('city_slug') }}"
                                       placeholder="otomatik-oluşturulur">
                                <span class="input-group-text">-koy-urunleri</span>
                            </div>
                            @error('city_slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">Şehir adından otomatik üretilir. Sadece küçük harf, sayı ve tire.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="tier">Tier (Öncelik)</label>
                            <select class="form-select @error('tier') is-invalid @enderror" id="tier" name="tier">
                                <option value="1" {{ old('tier', 1) == 1 ? 'selected' : '' }}>Tier 1 — Büyük şehir (öncelikli)</option>
                                <option value="2" {{ old('tier') == 2 ? 'selected' : '' }}>Tier 2 — Orta ölçek</option>
                                <option value="3" {{ old('tier') == 3 ? 'selected' : '' }}>Tier 3 — Küçük şehir</option>
                                <option value="4" {{ old('tier') == 4 ? 'selected' : '' }}>Tier 4 — Çorum çevresi</option>
                            </select>
                            @error('tier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="title">Sayfa Başlığı (görsel) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror"
                                   id="title" name="title" value="{{ old('title') }}"
                                   placeholder="Örn: İstanbul Köy Ürünleri — Doğal Süt, Peynir, Tereyağı"
                                   maxlength="255" required oninput="updateSeoPreview()">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                    </div>
                </div>
            </div>

            {{-- SECTION 2: HERO --}}
            <div class="card-dark mb-4" id="section-hero">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-megaphone"></i></div>
                        <div>
                            <h6 class="mb-0">Hero Bölümü</h6>
                            <small class="text-muted">Sayfanın H1 başlığı ve kısa tanıtım metni</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label" for="hero_heading">H1 Başlık <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('hero_heading') is-invalid @enderror"
                                   id="hero_heading" name="hero_heading" value="{{ old('hero_heading') }}"
                                   placeholder="Örn: İstanbul'a Doğal Köy Ürünleri — Çorum'dan Kapınıza"
                                   maxlength="255" required>
                            @error('hero_heading')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Sayfada görünen büyük başlık (SEO için kritik)</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="hero_description">Hero Açıklaması</label>
                            <textarea class="form-control @error('hero_description') is-invalid @enderror"
                                      id="hero_description" name="hero_description" rows="3" maxlength="500"
                                      placeholder="Hero altı kısa tanıtım (1-2 cümle)">{{ old('hero_description') }}</textarea>
                            @error('hero_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                    </div>
                </div>
            </div>

            {{-- SECTION 3: İÇERİK --}}
            <div class="card-dark mb-4" id="section-content">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-body-text"></i></div>
                        <div>
                            <h6 class="mb-0">Sayfa İçeriği</h6>
                            <small class="text-muted">HTML içerik — H2/H3, paragraflar, listeler</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="content">İçerik (HTML)</label>
                            <textarea class="@error('content') is-invalid @enderror" id="content" name="content" rows="12">{{ old('content') }}</textarea>
                            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">"AI ile İçerik Üret" butonuyla otomatik doldurabilirsiniz.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 4: TESLİMAT --}}
            <div class="card-dark mb-4" id="section-shipping">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-truck"></i></div>
                        <div>
                            <h6 class="mb-0">Teslimat Bilgileri</h6>
                            <small class="text-muted">Şehre özel kargo süresi ve özet not</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="delivery_time">Tahmini Teslim Süresi</label>
                            <input type="text" class="form-control" id="delivery_time" name="delivery_time"
                                   value="{{ old('delivery_time') }}" maxlength="60" placeholder="Örn: 1-2 iş günü">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="shipping_note">Kargo Notu</label>
                            <input type="text" class="form-control" id="shipping_note" name="shipping_note"
                                   value="{{ old('shipping_note') }}" maxlength="255"
                                   placeholder="Örn: Çorum'dan İstanbul'a soğuk zincir kargo ile 1-2 iş günü içinde teslimat.">
                        </div>

                    </div>
                </div>
            </div>

            {{-- SECTION 5: SEO --}}
            <div class="card-dark mb-4" id="section-seo">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-search"></i></div>
                        <div>
                            <h6 class="mb-0">SEO Ayarları</h6>
                            <small class="text-muted">Arama motorları için meta bilgileri</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label">Google Arama Önizlemesi</label>
                            <div class="ca-seo-preview">
                                <div class="ca-seo-url">{{ config('app.url') }}/<span id="seoPreviewSlug">sehir</span>-koy-urunleri</div>
                                <div class="ca-seo-title" id="seoPreviewTitle">Şehir Köy Ürünleri</div>
                                <div class="ca-seo-desc" id="seoPreviewDesc">Şehre özel meta açıklama burada görünecek.</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="meta_title">Meta Başlık</label>
                            <input type="text" class="form-control @error('meta_title') is-invalid @enderror"
                                   id="meta_title" name="meta_title" value="{{ old('meta_title') }}"
                                   maxlength="70" placeholder="SEO başlık (boş bırakılırsa sayfa başlığı kullanılır)"
                                   oninput="updateCharCounter(this, 70); updateSeoPreview()">
                            @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Önerilen: 50-60 karakter</div>
                                <div class="form-text"><span id="meta_title-counter">{{ Str::length(old('meta_title', '')) }}</span>/70</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="meta_description">Meta Açıklama</label>
                            <textarea class="form-control @error('meta_description') is-invalid @enderror"
                                      id="meta_description" name="meta_description" rows="3" maxlength="200"
                                      placeholder="Arama sonuçlarında görünecek açıklama"
                                      oninput="updateCharCounter(this, 200); updateSeoPreview()">{{ old('meta_description') }}</textarea>
                            @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Önerilen: 150-160 karakter</div>
                                <div class="form-text"><span id="meta_description-counter">{{ Str::length(old('meta_description', '')) }}</span>/200</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- SECTION 6: YAYIN --}}
            <div class="card-dark mb-4" id="section-publish">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-toggles"></i></div>
                        <div>
                            <h6 class="mb-0">Yayın Ayarları</h6>
                            <small class="text-muted">Aktiflik durumu ve sıralama</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sort_order">Sıralama</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order"
                                   value="{{ old('sort_order', 0) }}" min="0" max="9999">
                            <div class="form-text">Küçük değer önce listelenir.</div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- FORM ACTIONS --}}
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                        <a href="{{ route('admin.city-pages.index') }}" class="btn-glass">
                            <i class="bi bi-x-lg me-1"></i>Vazgeç
                        </a>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="is_active" value="0" class="btn-glass">
                                <i class="bi bi-pause-circle me-1"></i>Pasif Kaydet
                            </button>
                            <button type="submit" name="is_active" value="1" class="btn-teal">
                                <i class="bi bi-send me-1"></i>Yayınla
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

@include('partials.admin.tinymce', ['tinymceSelector' => '#content'])
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/city-page.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/city-ai-fill.js') }}"></script>
@endpush
