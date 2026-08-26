{{--
    One language's worth of a category.

    @var \App\Models\Language $language
    @var \App\Models\GalleryCategory|null $translation
--}}

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
                                       data-validation-engine="validate[maxSize[255]]"
                        value="{{ old("translations.{$language->code}.name", $isEdit ? $category->name : '') }}"
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
                        value="{{ old("translations.{$language->code}.sort_order", $isEdit ? $category->sort_order : 0) }}"
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
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="is_active_{{ $language->code }}"
                                    name="translations[{{ $language->code }}][is_active]" data-fv-ignore
                                    value="1"
                                    {{ old("translations.{$language->code}.is_active", $isEdit ? $category->is_active : true) ? 'checked' : '' }}
                                >
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

