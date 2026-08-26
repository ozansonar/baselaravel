{{--
    One language's worth of a sliders record.

    Included once per language inside the tab strip, so every field — the image
    included — belongs to that language alone.

    @var \App\Models\Language $language
    @var \App\Models\Slider|null $translation
--}}

@php
    // Server side asks for artwork only on the default language of a new record.
    $imageRequired = $language->is_default && ! $translation?->image;
@endphp

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic-{{ $language->code }}">Temel Bilgiler</option>
                <option value="section-media-{{ $language->code }}">Görsel</option>
                <option value="section-button-{{ $language->code }}">Buton Ayarları</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic-{{ $language->code }}" class="stg-nav-item active" onclick="scrollToSection('section-basic-{{ $language->code }}', this)">
                        <i class="bi bi-sliders"></i>
                        <div><span>Temel Bilgiler</span><small>Başlık, alt başlık, durum</small></div>
                    </a>
                    <a href="#section-media-{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-media-{{ $language->code }}', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Slider görseli</small></div>
                    </a>
                    <a href="#section-button-{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-button-{{ $language->code }}', this)">
                        <i class="bi bi-link-45deg"></i>
                        <div><span>Buton Ayarları</span><small>Buton metni ve URL</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic-{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="title_{{ $language->code }}">
                                    Başlık @if($language->is_default)<span class="text-danger">*</span>@endif
                                </label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.title") is-invalid @enderror"
                                       id="title_{{ $language->code }}" name="translations[{{ $language->code }}][title]" value="{{ old("translations.{$language->code}.title", $translation?->title) }}"
                                       placeholder="Slider başlığını girin..." @if($language->is_default) required @endif>
                                @error("translations.{$language->code}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Alt Başlık --}}
                            <div class="col-12">
                                <label class="form-label" for="subtitle_{{ $language->code }}">Alt Başlık</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.subtitle") is-invalid @enderror"
                                       id="subtitle_{{ $language->code }}" name="translations[{{ $language->code }}][subtitle]" value="{{ old("translations.{$language->code}.subtitle", $translation?->subtitle) }}"
                                       placeholder="Slider alt başlığını girin...">
                                @error("translations.{$language->code}.subtitle")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Sıralama --}}
                            <div class="col-md-6">
                                <label class="form-label" for="sort_order_{{ $language->code }}">Sıralama</label>
                                <input type="number"
                                       class="form-control @error("translations.{$language->code}.sort_order") is-invalid @enderror"
                                       id="sort_order_{{ $language->code }}" name="translations[{{ $language->code }}][sort_order]"
                                       value="{{ old("translations.{$language->code}.sort_order", $translation?->sort_order ?? 0) }}" min="0">
                                @error("translations.{$language->code}.sort_order")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük değer = Daha üstte</div>
                            </div>

                            {{-- Durum --}}
                            <div class="col-md-6">
                                <label class="form-label" for="is_active_{{ $language->code }}">Durum</label>
                                <select class="form-select @error("translations.{$language->code}.is_active") is-invalid @enderror"
                                        id="is_active_{{ $language->code }}" name="translations[{{ $language->code }}][is_active]">
                                    <option value="1" {{ old("translations.{$language->code}.is_active", $translation?->is_active ?? 1) == 1 ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ old("translations.{$language->code}.is_active", $translation?->is_active ?? 1) == 0 ? 'selected' : '' }}>Pasif</option>
                                </select>
                                @error("translations.{$language->code}.is_active")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 2: GÖRSEL --}}
                <div class="card-dark mb-4" id="section-media-{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="image_{{ $language->code }}">
                                    Slider Görseli @if($imageRequired)<span class="text-danger">*</span>@endif
                                </label>
                                <input type="file"
                                       class="form-control @error("translations.{$language->code}.image") is-invalid @enderror"
                                       id="image_{{ $language->code }}" name="translations[{{ $language->code }}][image]" accept="image/*" @if($imageRequired) required @endif>
                                @error("translations.{$language->code}.image")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">PNG, JPG, WebP | Önerilen boyut: 1920x600 piksel</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: BUTON AYARLARI --}}
                <div class="card-dark mb-4" id="section-button-{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="button_text_{{ $language->code }}">Buton Metni</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.button_text") is-invalid @enderror"
                                       id="button_text_{{ $language->code }}" name="translations[{{ $language->code }}][button_text]"
                                       value="{{ old("translations.{$language->code}.button_text", $translation?->button_text) }}"
                                       placeholder="Keşfet">
                                @error("translations.{$language->code}.button_text")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş bırakılırsa buton gösterilmez</div>
                            </div>

                            {{-- Buton URL --}}
                            <div class="col-md-6">
                                <label class="form-label" for="button_url_{{ $language->code }}">Buton URL</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.button_url") is-invalid @enderror"
                                       id="button_url_{{ $language->code }}" name="translations[{{ $language->code }}][button_url]"
                                       value="{{ old("translations.{$language->code}.button_url", $translation?->button_url) }}"
                                       placeholder="/blog/...">
                                @error("translations.{$language->code}.button_url")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Dahili veya harici link girebilirsiniz</div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>{{-- /col-lg-9 --}}
        </div>{{-- /row --}}
