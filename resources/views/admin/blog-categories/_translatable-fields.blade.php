{{--
    One language's worth of a category.

    @var \App\Models\Language $language
    @var \App\Models\BlogCategory|null $translation
--}}
@php
    $code = $language->code;

    /**
     * Client side rules for jQuery Validation Engine.
     *
     * Only the tab on screen is validated, so a field is either required or it
     * is not.
     */
    $rules = function (array $extra = []): string {
        return 'validate[' . implode(',', array_merge(['required'], $extra)) . ']';
    };
@endphp

    {{-- Mobile Section Jumper --}}
    <div class="d-lg-none mb-4">
        <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0" data-fv-ignore>
            <option value="" disabled selected>Bölüme git...</option>
            <option value="section-basic_{{ $code }}">Temel Bilgiler</option>
            <option value="section-settings_{{ $code }}">Ayarlar</option>
        </select>
    </div>

    {{-- Form Layout --}}
    <div class="row g-4 align-items-start">

        {{-- Sol Navigasyon (yalnızca desktop) --}}
        <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right" data-aos-delay="100">
            <div class="stg-nav-inner position-sticky stg-nav-sticky">
                <a href="#section-basic_{{ $code }}" class="stg-nav-item active" onclick="scrollToSection('section-basic_{{ $code }}', this)">
                    <i class="bi bi-text-paragraph"></i>
                    <div><span>Temel Bilgiler</span><small>Kategori adı, ikon</small></div>
                </a>
                <a href="#section-settings_{{ $code }}" class="stg-nav-item" onclick="scrollToSection('section-settings_{{ $code }}', this)">
                    <i class="bi bi-gear"></i>
                    <div><span>Ayarlar</span><small>Sıralama, görünürlük</small></div>
                </a>
            </div>
        </div>

        {{-- Form İçeriği --}}
        <div class="col-12 col-lg-9">

            <!-- ==================== SECTION 1: TEMEL BİLGİLER ==================== -->
            <div class="card-dark mb-4" id="section-basic_{{ $code }}" data-aos="fade-up" data-aos-delay="50">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                        <div>
                            <h6 class="mb-0">Temel Bilgiler</h6>
                            <small class="text-muted">Kategori adı ve ikon bilgilerini belirleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Kategori Adı -->
                        <div class="col-12">
                            <label class="form-label" for="name_{{ $code }}">
                                Kategori Adı <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error("translations.{$code}.name") is-invalid @enderror"
                                id="name_{{ $code }}"
                                name="translations[{{ $code }}][name]"
                                value="{{ old("translations.{$code}.name", $translation?->name) }}"
                                placeholder="Kategori adını yazın..."
                                maxlength="255"
                                data-validation-engine="{{ $rules(['maxSize[255]']) }}"
                            >
                            @error("translations.{$code}.name")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Sitede ve listelerde bu adla görünür</div>
                        </div>

                        <!-- İkon -->
                        <div class="col-12">
                            <label class="form-label" for="icon_{{ $code }}">İkon Sınıfı</label>
                            <div class="input-group">
                                <span class="input-group-text" id="iconPreview_{{ $code }}">
                                    <i class="{{ old("translations.{$code}.icon", $translation?->icon) ?: 'bi bi-tag' }}"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control @error("translations.{$code}.icon") is-invalid @enderror"
                                    id="icon_{{ $code }}"
                                    name="translations[{{ $code }}][icon]"
                                    value="{{ old("translations.{$code}.icon", $translation?->icon) }}"
                                    placeholder="bi bi-heart-fill"
                                    maxlength="100"
                                    data-validation-engine="validate[maxSize[100]]"
                                    data-prompt-target="icon_error_{{ $code }}"
                                    oninput="updateIconPreview(this)"
                                >
                            </div>
                            <div id="icon_error_{{ $code }}"></div>
                            @error("translations.{$code}.icon")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Bootstrap Icons (ör: <code>bi bi-heart-fill</code>) veya Font Awesome
                                (ör: <code>fa-solid fa-bullhorn</code>) sınıf adı
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 2: AYARLAR ==================== -->
            <div class="card-dark mb-4" id="section-settings_{{ $code }}" data-aos="fade-up" data-aos-delay="50">
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

                        <!-- Sıralama -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sort_order_{{ $code }}">Sıralama</label>
                            <input
                                type="number"
                                class="form-control @error("translations.{$code}.sort_order") is-invalid @enderror"
                                id="sort_order_{{ $code }}"
                                name="translations[{{ $code }}][sort_order]" data-fv-default="0"
                                value="{{ old("translations.{$code}.sort_order", $translation?->sort_order ?? 0) }}"
                                min="0"
                                max="999"
                                data-validation-engine="validate[custom[integer],min[0],max[999]]"
                                data-fv-ignore
                            >
                            @error("translations.{$code}.sort_order")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Düşük sayı önce gösterilir (0-999)</div>
                        </div>

                        <!-- Durum -->
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
                                        <input type="hidden" name="translations[{{ $code }}][is_active]" value="0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="is_active_{{ $code }}"
                                            name="translations[{{ $code }}][is_active]"
                                            value="1"
                                            data-fv-ignore
                                            {{ old("translations.{$code}.is_active", $translation?->is_active ?? true) ? 'checked' : '' }}
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
