{{--
    One language's worth of a category.

    @var \App\Models\Language $language
    @var \App\Models\GalleryCategory|null $translation
--}}

    {{-- Mobile Section Jumper --}}
    <div class="d-lg-none mb-4">
        <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0" data-fv-ignore>
            <option value="" disabled selected>Bölüme git...</option>
            <option value="section-basic_{{ $language->code }}">Temel Bilgiler</option>
            <option value="section-settings_{{ $language->code }}">Ayarlar</option>
        </select>
    </div>

    {{-- Form Layout --}}
    <div class="row g-4 align-items-start">

        {{-- Sol Navigasyon (yalnızca desktop) --}}
        <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right" data-aos-delay="100">
            <div class="stg-nav-inner position-sticky stg-nav-sticky">
                <a href="#section-basic_{{ $language->code }}" class="stg-nav-item active" onclick="scrollToSection('section-basic_{{ $language->code }}', this)">
                    <i class="bi bi-text-paragraph"></i>
                    <div><span>Temel Bilgiler</span><small>Kategori adı</small></div>
                </a>
                <a href="#section-settings_{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-settings_{{ $language->code }}', this)">
                    <i class="bi bi-gear"></i>
                    <div><span>Ayarlar</span><small>Sıralama, görünürlük</small></div>
                </a>
            </div>
        </div>

        {{-- Form İçeriği --}}
        <div class="col-12 col-lg-9">

    {{-- SECTION 1: TEMEL BİLGİLER --}}
    <div class="card-dark mb-4" id="section-basic_{{ $language->code }}">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                <div>
                    <h6 class="mb-0">Temel Bilgiler</h6>
                    <small class="text-muted">Kategori adını belirleyin</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">

                {{-- Kategori Adı --}}
                <div class="col-12">
                    <label class="form-label" for="name_{{ $language->code }}">
                        Kategori Adı <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        class="form-control @error("translations.{$language->code}.name") is-invalid @enderror"
                        id="name_{{ $language->code }}"
                        name="translations[{{ $language->code }}][name]"
                                       data-validation-engine="validate[required,maxSize[255]]"
                        value="{{ old("translations.{$language->code}.name", $translation?->name) }}"
                        placeholder="Kategori adını yazın..."
                    >
                    @error("translations.{$language->code}.name")
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
        </div>
    </div>

    {{-- SECTION 2: AYARLAR --}}
    <div class="card-dark mb-4" id="section-settings_{{ $language->code }}">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-purple"><i class="bi bi-gear"></i></div>
                <div>
                    <h6 class="mb-0">Ayarlar</h6>
                    <small class="text-muted">Sıralama ve görünürlük ayarlarını yapın</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">

                {{-- Sıralama --}}
                <div class="col-12 col-md-6">
                    <label class="form-label" for="sort_order_{{ $language->code }}">Sıralama</label>
                    <input
                        type="number"
                        class="form-control @error("translations.{$language->code}.sort_order") is-invalid @enderror"
                        id="sort_order_{{ $language->code }}"
                        name="translations[{{ $language->code }}][sort_order]" data-validation-engine="validate[custom[integer],min[0],max[65535]]" data-fv-ignore data-fv-default="0"
                        value="{{ old("translations.{$language->code}.sort_order", $translation?->sort_order ?? 0) }}"
                        min="0"
                        max="999"
                    >
                    @error("translations.{$language->code}.sort_order")
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Düşük sayı önce gösterilir (0-999)</div>
                </div>

                {{-- Durum --}}
                <div class="col-12 col-md-6">
                    <label class="form-label">Durum</label>
                    <div class="ca-toggle-list">
                        <div class="ca-toggle-item">
                            <div class="ca-toggle-info">
                                <span>Aktif</span>
                                <small>Kategori sitede görünür olsun</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                {{-- An unchecked box posts nothing, which used to leave the
                                     old value untouched; this makes "pasif" savable. --}}
                                <input type="hidden" name="translations[{{ $language->code }}][is_active]" value="0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="is_active_{{ $language->code }}"
                                    name="translations[{{ $language->code }}][is_active]" data-fv-ignore
                                    value="1"
                                    {{ old("translations.{$language->code}.is_active", $translation?->is_active ?? true) ? 'checked' : '' }}
                                >
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

        </div><!-- /col-12 col-lg-9 -->
    </div><!-- /row -->
