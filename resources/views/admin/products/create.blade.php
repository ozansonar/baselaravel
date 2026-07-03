@extends('layouts.admin')

@section('title', 'Yeni Ürün')
@section('page_title', 'Yeni Ürün Oluştur')
@section('page_description', 'Tüm alanları doldurarak yeni bir ürün ekleyin')

@section('content')
<form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" id="productForm">
    @csrf
    {{-- Hidden field for rich editor content --}}
    <textarea name="description" id="descriptionField" class="d-none">{{ old('description') }}</textarea>

    {{-- Page Header --}}
    <div class="d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.products.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" id="btnAiFillAll" class="btn-glass"
                    data-url="{{ route('admin.products.ai-fill') }}"
                    onclick="aiFillProductAll()">
                <i class="bi bi-stars me-1"></i><span class="ai-fill-label">AI ile Tümünü Doldur</span>
            </button>
            <button type="submit" name="status" value="draft" class="btn-glass">
                <i class="bi bi-file-earmark me-1"></i>Taslak Kaydet
            </button>
            <button type="submit" class="btn-teal">
                <i class="bi bi-check-lg me-1"></i>Yayınla
            </button>
        </div>
    </div>

    {{-- Mobile Section Jumper --}}
    <div class="d-lg-none mb-4">
        <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
            <option value="" disabled selected>Bölüme git...</option>
            <option value="section-basic">Temel Bilgiler</option>
            <option value="section-pricing">Fiyatlandırma</option>
            <option value="section-stock">Stok & Kargo</option>
            <option value="section-media">Medya Yönetimi</option>
            <option value="section-features">Ürün Özellikleri</option>
            <option value="section-nutrition">Besin Değerleri</option>
            <option value="section-shipping">Kargo & İade</option>
            <option value="section-seo">SEO Ayarları</option>
            <option value="section-advanced">Gelişmiş Ayarlar</option>
        </select>
    </div>

    {{-- Form Layout --}}
    <div class="row g-4 align-items-start">

        {{-- Sol Navigasyon (desktop) --}}
        <div class="col-lg-3 d-none d-lg-block">
            <div class="stg-nav-inner position-sticky stg-nav-sticky">
                <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
                    <i class="bi bi-box-seam"></i>
                    <div><span>Temel Bilgiler</span><small>Ad, kategori, açıklama</small></div>
                </a>
                <a href="#section-pricing" class="stg-nav-item" onclick="scrollToSection('section-pricing', this)">
                    <i class="bi bi-currency-dollar"></i>
                    <div><span>Fiyatlandırma</span><small>Fiyat, indirim, vergi</small></div>
                </a>
                <a href="#section-stock" class="stg-nav-item" onclick="scrollToSection('section-stock', this)">
                    <i class="bi bi-truck"></i>
                    <div><span>Stok & Kargo</span><small>Miktar, boyut, ağırlık</small></div>
                </a>
                <a href="#section-media" class="stg-nav-item" onclick="scrollToSection('section-media', this)">
                    <i class="bi bi-images"></i>
                    <div><span>Medya Yönetimi</span><small>Görseller, galeri</small></div>
                </a>
                <a href="#section-features" class="stg-nav-item" onclick="scrollToSection('section-features', this)">
                    <i class="bi bi-patch-check"></i>
                    <div><span>Ürün Özellikleri</span><small>Öne çıkan özellikler</small></div>
                </a>
                <a href="#section-nutrition" class="stg-nav-item" onclick="scrollToSection('section-nutrition', this)">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <div><span>Besin Değerleri</span><small>Besin tablosu</small></div>
                </a>
                <a href="#section-shipping" class="stg-nav-item" onclick="scrollToSection('section-shipping', this)">
                    <i class="bi bi-truck"></i>
                    <div><span>Kargo & İade</span><small>Kargo, iade bilgileri</small></div>
                </a>
                <a href="#section-seo" class="stg-nav-item" onclick="scrollToSection('section-seo', this)">
                    <i class="bi bi-search"></i>
                    <div><span>SEO Ayarları</span><small>Meta başlık, açıklama</small></div>
                </a>
                <a href="#section-advanced" class="stg-nav-item" onclick="scrollToSection('section-advanced', this)">
                    <i class="bi bi-gear"></i>
                    <div><span>Gelişmiş Ayarlar</span><small>Durum, görünürlük</small></div>
                </a>
            </div>
        </div>

        {{-- Form İçeriği --}}
        <div class="col-12 col-lg-9">

            {{-- ==================== SECTION 1: TEMEL BİLGİLER ==================== --}}
            <div class="card-dark mb-4" id="section-basic">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-box-seam"></i></div>
                        <div>
                            <h6 class="mb-0">Temel Bilgiler</h6>
                            <small class="text-muted">Ürünün adını, kategorisini ve açıklamasını belirleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        {{-- Ürün Adı --}}
                        <div class="col-12">
                            <label class="form-label" for="productName">
                                Ürün Adı <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="productName" name="name" value="{{ old('name') }}" required
                                   placeholder="Ürün adını girin..."
                                   oninput="generateProductSlug(this.value); updateCharCounter(this, 150)">
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Ürünü tanımlayan net ve SEO uyumlu bir ad girin</div>
                                <div class="form-text"><span id="productName-counter">0</span>/150</div>
                            </div>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Slug --}}
                        <div class="col-12">
                            <label class="form-label" for="productSlug">URL (Slug)</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ config('app.url') }}/urun/</span>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                       id="productSlug" name="slug" value="{{ old('slug') }}" placeholder="otomatik-oluşturulur">
                            </div>
                            <div class="form-text">Ürün adından otomatik oluşturulur, isterseniz düzenleyebilirsiniz</div>
                            @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Kategori --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productCategory">
                                Kategori <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('category_id') is-invalid @enderror"
                                    id="productCategory" name="category_id" required>
                                <option value="">Kategori seçin...</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Birim --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productUnit">Birim</label>
                            <input type="text" class="form-control @error('unit') is-invalid @enderror"
                                   id="productUnit" name="unit" value="{{ old('unit', 'adet') }}"
                                   placeholder="adet, kg, litre...">
                            @error('unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Kısa Açıklama --}}
                        <div class="col-12">
                            <label class="form-label" for="productExcerpt">
                                Kısa Açıklama <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control @error('short_description') is-invalid @enderror"
                                      id="productExcerpt" name="short_description" rows="3"
                                      placeholder="Ürünün kısa açıklamasını yazın (listeleme sayfalarında görünür)..."
                                      oninput="updateCharCounter(this, 300)">{{ old('short_description') }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Ürün kartlarında ve arama sonuçlarında görünecek açıklama</div>
                                <div class="form-text"><span id="productExcerpt-counter">0</span>/300</div>
                            </div>
                            @error('short_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Detaylı Açıklama (Rich Editor) --}}
                        <div class="col-12">
                            <label class="form-label">
                                Detaylı Açıklama <span class="text-danger">*</span>
                            </label>
                            @error('description')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            <div class="ca-editor-wrapper">
                                <div class="ca-editor-toolbar">
                                    <div class="ca-toolbar-group">
                                        <button type="button" class="ca-toolbar-btn" onclick="execFormat('bold')" title="Kalın"><i class="bi bi-type-bold"></i></button>
                                        <button type="button" class="ca-toolbar-btn" onclick="execFormat('italic')" title="İtalik"><i class="bi bi-type-italic"></i></button>
                                        <button type="button" class="ca-toolbar-btn" onclick="execFormat('underline')" title="Altı çizili"><i class="bi bi-type-underline"></i></button>
                                    </div>
                                    <div class="ca-toolbar-divider"></div>
                                    <div class="ca-toolbar-group">
                                        <select class="ca-toolbar-select" onchange="execHeading(this.value)" title="Başlık stili">
                                            <option value="">Paragraf</option>
                                            <option value="h2">Başlık 2</option>
                                            <option value="h3">Başlık 3</option>
                                            <option value="h4">Başlık 4</option>
                                        </select>
                                    </div>
                                    <div class="ca-toolbar-divider"></div>
                                    <div class="ca-toolbar-group">
                                        <button type="button" class="ca-toolbar-btn" onclick="execFormat('insertUnorderedList')" title="Madde işareti"><i class="bi bi-list-ul"></i></button>
                                        <button type="button" class="ca-toolbar-btn" onclick="execFormat('insertOrderedList')" title="Numaralı liste"><i class="bi bi-list-ol"></i></button>
                                    </div>
                                    <div class="ca-toolbar-divider"></div>
                                    <div class="ca-toolbar-group">
                                        <button type="button" class="ca-toolbar-btn" onclick="insertLink()" title="Bağlantı ekle"><i class="bi bi-link-45deg"></i></button>
                                        <button type="button" class="ca-toolbar-btn" onclick="insertEditorImage()" title="Görsel ekle"><i class="bi bi-image"></i></button>
                                        <button type="button" class="ca-toolbar-btn" onclick="insertTable()" title="Tablo ekle"><i class="bi bi-table"></i></button>
                                    </div>
                                </div>
                                <div class="ca-editor-body" id="productEditor" contenteditable="true">{!! old('description') !!}</div>
                                <div class="ca-editor-footer">
                                    <span><i class="bi bi-fonts me-1"></i><span id="wordCount">0</span> kelime</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            {{-- ==================== SECTION 2: FİYATLANDIRMA ==================== --}}
            <div class="card-dark mb-4" id="section-pricing">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-green"><i class="bi bi-currency-dollar"></i></div>
                        <div>
                            <h6 class="mb-0">Fiyatlandırma</h6>
                            <small class="text-muted">Ürün fiyatını, indirim ve vergi bilgilerini ayarlayın</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        {{-- Satış Fiyatı --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productPrice">
                                Satış Fiyatı <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control @error('price') is-invalid @enderror"
                                       id="productPrice" name="price" value="{{ old('price') }}"
                                       placeholder="0.00" step="0.01" min="0" required
                                       oninput="calculateProfit(); calculateDiscount()">
                                <span class="input-group-text">₺</span>
                            </div>
                            @error('price')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Karşılaştırma Fiyatı (discounted_price) --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productComparePrice">Karşılaştırma Fiyatı</label>
                            <div class="input-group">
                                <input type="number" class="form-control @error('discounted_price') is-invalid @enderror"
                                       id="productComparePrice" name="discounted_price" value="{{ old('discounted_price') }}"
                                       placeholder="0.00" step="0.01" min="0"
                                       oninput="calculateDiscount()">
                                <span class="input-group-text">₺</span>
                            </div>
                            <div class="form-text">Üzeri çizili olarak gösterilecek eski fiyat</div>
                            @error('discounted_price')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Maliyet --}}
                        <div class="col-md-4">
                            <label class="form-label" for="productCost">Maliyet Fiyatı</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="productCost"
                                       placeholder="0.00" step="0.01" min="0" oninput="calculateProfit()">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>

                        {{-- Kâr Marjı --}}
                        <div class="col-md-4">
                            <label class="form-label">Kâr Marjı</label>
                            <div class="prd-profit-display">
                                <div class="prd-profit-bar">
                                    <div class="prd-profit-fill" id="profitBar"></div>
                                </div>
                                <span class="prd-profit-text" id="profitText">%0</span>
                            </div>
                        </div>

                        {{-- İndirim Yüzdesi --}}
                        <div class="col-md-4">
                            <label class="form-label">İndirim</label>
                            <div class="prd-discount-display" id="discountDisplay">
                                <i class="bi bi-tag-fill me-1"></i>
                                <span id="discountText">%0 indirim</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            {{-- ==================== SECTION 3: STOK & KARGO ==================== --}}
            <div class="card-dark mb-4" id="section-stock">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-truck"></i></div>
                        <div>
                            <h6 class="mb-0">Stok & Kargo</h6>
                            <small class="text-muted">Stok miktarı, SKU ve kargo bilgilerini düzenleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        {{-- SKU --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productSku">
                                SKU (Stok Kodu) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('sku') is-invalid @enderror"
                                       id="productSku" name="sku" value="{{ old('sku') }}" placeholder="SKU-0001" required>
                                <button class="btn-glass" type="button" onclick="generateSku()" title="Otomatik SKU Oluştur">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            @error('sku')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Barkod --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productBarcode">Barkod (EAN/UPC)</label>
                            <input type="text" class="form-control" id="productBarcode" placeholder="Barkod numarası girin...">
                        </div>

                        {{-- Stok Takibi --}}
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="trackStock" checked onchange="toggleStockFields()">
                                <label class="form-check-label" for="trackStock">Stok takibi yap</label>
                            </div>
                        </div>

                        {{-- Stok Alanları --}}
                        <div id="stockFields">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="productStock">Stok Miktarı <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('stock') is-invalid @enderror"
                                           id="productStock" name="stock" value="{{ old('stock', 0) }}" placeholder="0" min="0" required>
                                    @error('stock')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="productLowStock">Düşük Stok Uyarısı</label>
                                    <input type="number" class="form-control" id="productLowStock" placeholder="10" min="0">
                                    <div class="form-text">Bu sayının altına düşünce uyarı verilir</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="productMaxOrder">Maks. Sipariş Adedi</label>
                                    <input type="number" class="form-control" id="productMaxOrder" placeholder="Sınırsız" min="1">
                                </div>
                            </div>
                        </div>

                        {{-- Kargo Bilgileri --}}
                        <div class="col-12 mt-3">
                            <h6 class="prd-subsection-title"><i class="bi bi-box me-2"></i>Kargo & Boyut Bilgileri</h6>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label" for="productWeight">Ağırlık</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="productWeight" placeholder="0" step="0.01" min="0">
                                <select class="input-group-text prd-unit-select" id="weightUnit">
                                    <option value="g">g</option>
                                    <option value="kg" selected>kg</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label" for="productLength">Uzunluk</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="productLength" placeholder="0" step="0.1" min="0">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label" for="productWidth">Genişlik</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="productWidth" placeholder="0" step="0.1" min="0">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label" for="productHeight">Yükseklik</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="productHeight" placeholder="0" step="0.1" min="0">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>

                        {{-- Ücretsiz Kargo --}}
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="freeShipping">
                                <label class="form-check-label" for="freeShipping">Ücretsiz kargo</label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            {{-- ==================== SECTION 4: MEDYA YÖNETİMİ ==================== --}}
            <div class="card-dark mb-4" id="section-media">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-images"></i></div>
                        <div>
                            <h6 class="mb-0">Medya Yönetimi</h6>
                            <small class="text-muted">Ürün görsellerini ve galeri resimlerini yükleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        {{-- Ana Ürün Görseli --}}
                        <div class="col-12">
                            <label class="form-label" for="mainImageInput">
                                Ana Ürün Görseli <span class="text-danger">*</span>
                            </label>
                            @error('image')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            <input type="file" class="form-control" id="mainImageInput" name="image"
                                   accept="image/png,image/jpeg,image/webp">
                            <div class="form-text">PNG, JPG, WebP | Maks. 2 MB | Önerilen: 800×800 px</div>
                        </div>

                        {{-- Galeri Görselleri (Dropzone.js) --}}
                        <div class="col-12">
                            <label class="form-label">Galeri Görselleri</label>
                            <div class="form-text mb-2">Ürüne ait ek görselleri sürükleyerek veya tıklayarak yükleyin (Maks. 8 görsel)</div>
                            @error('images.*')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            <div class="dropzone ca-dropzone-area" id="galleryDropzone">
                                <div class="dz-message">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>
                                    <span>Sürükle & bırak veya <strong>dosya seç</strong></span>
                                    <small class="ms-2">JPG, PNG, WebP &bull; Maks. 2 MB</small>
                                </div>
                            </div>
                            <input type="file" class="d-none" id="galleryInput" name="images[]" multiple>
                        </div>

                    </div>
                </div>
            </div>


            {{-- ==================== SECTION 5: ÜRÜN ÖZELLİKLERİ ==================== --}}
            <div class="card-dark mb-4" id="section-features">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-patch-check"></i></div>
                        <div>
                            <h6 class="mb-0">Ürün Özellikleri</h6>
                            <small class="text-muted">Ürün detayında gösterilecek öne çıkan özellikler</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">

                    {{-- Özellikler Toggle --}}
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="hasFeatures" onchange="toggleFeaturesSection()">
                        <label class="form-check-label" for="hasFeatures">Bu ürün için özellikler ekle</label>
                    </div>

                    {{-- Özellikler Tablosu --}}
                    <div id="featuresSection" class="d-none">
                        <div class="table-responsive">
                            <table class="table cl-table mb-0" id="featuresTable">
                                <thead>
                                    <tr>
                                        <th width="250">İkon (CSS class)</th>
                                        <th>Özellik Metni</th>
                                        <th class="cl-th-actions">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="featuresTableBody">
                                    <tr>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="features[0][icon]"
                                                   value="fa-solid fa-leaf"
                                                   placeholder="Ör: fa-solid fa-leaf">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="features[0][text]"
                                                   placeholder="Ör: %100 Doğal & Organik">
                                        </td>
                                        <td>
                                            <button type="button" class="usr-action-btn danger" onclick="removeFeatureRow(this)" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn-glass mt-3" onclick="addFeatureRow()">
                            <i class="bi bi-plus-lg me-1"></i> Yeni Özellik Ekle
                        </button>

                        <div class="form-text mt-2">
                            <strong>Popüler ikonlar:</strong>
                            fa-solid fa-leaf, fa-solid fa-ban, fa-solid fa-snowflake, fa-solid fa-undo, fa-solid fa-truck, fa-solid fa-shield-halved, fa-solid fa-heart, fa-solid fa-star
                        </div>
                    </div>

                </div>
            </div>


            {{-- ==================== SECTION 6: BESİN DEĞERLERİ ==================== --}}
            <div class="card-dark mb-4" id="section-nutrition">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-green"><i class="bi bi-clipboard2-pulse"></i></div>
                        <div>
                            <h6 class="mb-0">Besin Değerleri</h6>
                            <small class="text-muted">Ürünün besin değerleri tablosunu oluşturun</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">

                    {{-- Besin Değerleri Toggle --}}
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="hasNutrition" onchange="toggleNutritionSection()">
                        <label class="form-check-label" for="hasNutrition">Bu ürün için besin değerleri tablosu ekle</label>
                    </div>

                    {{-- Besin Değerleri Tablosu --}}
                    <div id="nutritionSection" class="d-none">
                        <div class="table-responsive">
                            <table class="table cl-table mb-0" id="nutritionTable">
                                <thead>
                                    <tr>
                                        <th>Besin Değeri</th>
                                        <th>100g Başına</th>
                                        <th>Günlük Değer %</th>
                                        <th class="cl-th-actions">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="nutritionTableBody">
                                    <tr>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="nutritions[0][name]"
                                                   placeholder="Ör: Enerji, Protein, Yağ...">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="nutritions[0][per_value]"
                                                   placeholder="Ör: 350 kcal">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="nutritions[0][daily_value]"
                                                   placeholder="Ör: %17">
                                        </td>
                                        <td>
                                            <button type="button" class="usr-action-btn danger" onclick="removeNutritionRow(this)" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn-glass mt-3" onclick="addNutritionRow()">
                            <i class="bi bi-plus-lg me-1"></i> Yeni Satır Ekle
                        </button>
                    </div>

                </div>
            </div>


            {{-- ==================== SECTION 6: KARGO & İADE ==================== --}}
            <div class="card-dark mb-4" id="section-shipping">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-truck"></i></div>
                        <div>
                            <h6 class="mb-0">Kargo & İade Bilgileri</h6>
                            <small class="text-muted">Ürüne özel kargo ve iade bilgilerini girin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">

                    {{-- Kargo & İade Toggle --}}
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="hasShipping" onchange="toggleShippingSection()">
                        <label class="form-check-label" for="hasShipping">Bu ürün için kargo & iade bilgileri ekle</label>
                    </div>

                    {{-- Kargo Bilgileri --}}
                    <div id="shippingSection" class="d-none">
                        <h6 class="mb-3"><i class="bi bi-truck me-2"></i>Kargo Bilgileri</h6>
                        <div class="table-responsive">
                            <table class="table cl-table mb-0" id="shippingTable">
                                <thead>
                                    <tr>
                                        <th>Kargo Bilgisi</th>
                                        <th class="cl-th-actions">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="shippingTableBody">
                                    <tr>
                                        <td>
                                            <input type="hidden" name="shipping_items[0][type]" value="shipping">
                                            <input type="text" class="form-control form-control-sm"
                                                   name="shipping_items[0][content]"
                                                   placeholder="Ör: {{ number_format((float) \App\Models\Setting::getValue('shipping_free_limit', '500'), 0, ',', '.') }}₺ üzeri siparişlerde ücretsiz kargo">
                                        </td>
                                        <td>
                                            <button type="button" class="usr-action-btn danger" onclick="removeShippingRow(this, 'shippingTableBody')" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn-glass mt-3 mb-4" onclick="addShippingRow('shipping')">
                            <i class="bi bi-plus-lg me-1"></i> Yeni Kargo Bilgisi Ekle
                        </button>

                        {{-- İade Bilgileri --}}
                        <h6 class="mb-3"><i class="bi bi-arrow-return-left me-2"></i>İade Politikası</h6>
                        <div class="table-responsive">
                            <table class="table cl-table mb-0" id="returnTable">
                                <thead>
                                    <tr>
                                        <th>İade Bilgisi</th>
                                        <th class="cl-th-actions">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="returnTableBody">
                                    <tr>
                                        <td>
                                            <input type="hidden" name="shipping_items[1][type]" value="return">
                                            <input type="text" class="form-control form-control-sm"
                                                   name="shipping_items[1][content]"
                                                   placeholder="Ör: 7 gün içinde koşulsuz iade">
                                        </td>
                                        <td>
                                            <button type="button" class="usr-action-btn danger" onclick="removeShippingRow(this, 'returnTableBody')" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn-glass mt-3" onclick="addShippingRow('return')">
                            <i class="bi bi-plus-lg me-1"></i> Yeni İade Bilgisi Ekle
                        </button>
                    </div>

                </div>
            </div>


            {{-- ==================== SECTION 7: SEO AYARLARI ==================== --}}
            <div class="card-dark mb-4" id="section-seo">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-search"></i></div>
                        <div>
                            <h6 class="mb-0">SEO Ayarları</h6>
                            <small class="text-muted">Arama motorları için meta bilgilerini optimize edin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        {{-- SEO Önizleme --}}
                        <div class="col-12">
                            <label class="form-label">Google Önizleme</label>
                            <div class="ca-seo-preview">
                                <span class="ca-seo-url">{{ config('app.url') }}/urun/<span id="seoSlugPreview">urun-adi</span></span>
                                <h6 class="ca-seo-title" id="seoPreviewTitle">Ürün Adı | Site Adı</h6>
                                <p class="ca-seo-desc" id="seoPreviewDesc">Ürünün meta açıklaması burada görünecektir. Arama motorlarında gösterilecek açıklama metni...</p>
                            </div>
                        </div>

                        {{-- Meta Başlık --}}
                        <div class="col-12">
                            <label class="form-label" for="seoTitle">
                                Meta Başlık <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('meta_title') is-invalid @enderror"
                                   id="seoTitle" name="meta_title" value="{{ old('meta_title') }}"
                                   placeholder="SEO uyumlu başlık yazın..."
                                   oninput="updateSeoPreview(); updateCharCounter(this, 60)">
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Google'da görünecek başlık (50-60 karakter önerilir)</div>
                                <div class="form-text"><span id="seoTitle-counter">0</span>/60</div>
                            </div>
                            @error('meta_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Meta Açıklama --}}
                        <div class="col-12">
                            <label class="form-label" for="seoDescription">Meta Açıklama</label>
                            <textarea class="form-control @error('meta_description') is-invalid @enderror"
                                      id="seoDescription" name="meta_description" rows="3"
                                      placeholder="Arama sonuçlarında görünecek açıklamayı yazın..."
                                      oninput="updateSeoPreview(); updateCharCounter(this, 160)">{{ old('meta_description') }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Google'da görünecek açıklama (120-160 karakter önerilir)</div>
                                <div class="form-text"><span id="seoDescription-counter">0</span>/160</div>
                            </div>
                            @error('meta_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>


            {{-- ==================== SECTION 7: GELİŞMİŞ AYARLAR ==================== --}}

            <div class="card-dark mb-4" id="section-advanced">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-red"><i class="bi bi-gear"></i></div>
                        <div>
                            <h6 class="mb-0">Gelişmiş Ayarlar</h6>
                            <small class="text-muted">Ürün durumu, görünürlük ve ek ayarlar</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        {{-- Yayın Durumu --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productStatus">Yayın Durumu</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="productStatus" name="status">
                                @foreach(\App\Enums\ProductStatus::cases() as $status)
                                <option value="{{ $status->value }}" {{ old('status', 'active') === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                                @endforeach
                            </select>
                            @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Sıralama --}}
                        <div class="col-md-6">
                            <label class="form-label" for="productOrder">Sıralama</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror"
                                   id="productOrder" name="sort_order" value="{{ old('sort_order', 0) }}" placeholder="0" min="0">
                            <div class="form-text">Düşük sayı önce gösterilir</div>
                            @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Toggles --}}
                        <div class="col-12">
                            <h6 class="prd-subsection-title"><i class="bi bi-toggles me-2"></i>Ek Seçenekler</h6>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="featuredProduct"
                                       name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                                <label class="form-check-label" for="featuredProduct">Öne çıkan ürün</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="newBadge"
                                       name="is_new" value="1" {{ old('is_new') ? 'checked' : '' }}>
                                <label class="form-check-label" for="newBadge">"Yeni" rozeti göster</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="allowReviews"
                                       name="allow_reviews" value="1"
                                       {{ old('allow_reviews', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="allowReviews">Değerlendirmelere izin ver</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="relatedProducts"
                                       name="show_related" value="1"
                                       {{ old('show_related', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="relatedProducts">İlgili ürünleri göster</label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            {{-- ==================== BOTTOM ACTIONS ==================== --}}
            <div class="prd-bottom-actions">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <a href="{{ route('admin.products.index') }}" class="btn-glass">
                        <i class="bi bi-arrow-left me-1"></i> Ürünlere Dön
                    </a>
                    <div class="d-flex gap-2">
                        <button type="submit" name="status" value="draft" class="btn-glass">
                            <i class="bi bi-file-earmark me-1"></i> Taslak Kaydet
                        </button>
                        <button type="submit" class="btn-teal">
                            <i class="bi bi-check-lg me-1"></i> Yayınla
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/product-add.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/product-ai-fill.js') }}"></script>
@endpush
