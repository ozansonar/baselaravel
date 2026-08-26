{{--
    One language's worth of a gallery-items record.

    Included once per language, so every field — the image included — belongs to
    that language alone.

    @var \App\Models\Language $language
    @var \App\Models\GalleryItem|null $translation
--}}

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0">
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic_{{ $language->code }}">Temel Bilgiler</option>
                <option value="section-media_{{ $language->code }}">Görsel</option>
                <option value="section-video_{{ $language->code }}">Video Bilgileri</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic_{{ $language->code }}" class="stg-nav-item active" onclick="scrollToSection('section-basic_{{ $language->code }}', this)">
                        <i class="bi bi-info-circle"></i>
                        <div><span>Temel Bilgiler</span><small>Başlık, tür, kategori, durum</small></div>
                    </a>
                    <a href="#section-media_{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-media_{{ $language->code }}', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Fotoğraf yükleme</small></div>
                    </a>
                    <a href="#section-video_{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-video_{{ $language->code }}', this)">
                        <i class="bi bi-camera-video"></i>
                        <div><span>Video Bilgileri</span><small>Video URL ve süre</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic_{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="title_{{ $language->code }}">
                                    Başlık <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.title") is-invalid @enderror"
                                       id="title_{{ $language->code }}" name="translations[{{ $language->code }}][title]"
                                       data-validation-engine="validate[maxSize[255],condRequired[description_{{ $language->code }},video_url_{{ $language->code }}]]" value="{{ old("translations.{$language->code}.title", $translation?->title) }}"
                                       placeholder="Galeri öğesi başlığını girin...">
                                @error("translations.{$language->code}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Açıklama --}}
                            <div class="col-12">
                                <label class="form-label" for="description_{{ $language->code }}">Açıklama</label>
                                <textarea class="form-control @error("translations.{$language->code}.description") is-invalid @enderror"
                                          id="description_{{ $language->code }}" name="translations[{{ $language->code }}][description]"
                                       data-validation-engine="validate[maxSize[2000]]" rows="3"
                                          placeholder="Kısa bir açıklama girin...">{{ old("translations.{$language->code}.description", $translation?->description) }}</textarea>
                                @error("translations.{$language->code}.description")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Tür --}}
                            <div class="col-md-6">
                                <label class="form-label" for="type_{{ $language->code }}">
                                    Tür <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error("translations.{$language->code}.type") is-invalid @enderror"
                                        id="type_{{ $language->code }}" name="translations[{{ $language->code }}][type]" data-fv-ignore>
                                    @foreach($types as $type)
                                        <option value="{{ $type->value }}" {{ old("translations.{$language->code}.type", 'photo') === $type->value ? 'selected' : '' }}>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("translations.{$language->code}.type")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Kategori --}}
                            <div class="col-md-6">
                                <label class="form-label" for="gallery_category_id_{{ $language->code }}">Kategori</label>
                                <select class="form-select @error("translations.{$language->code}.gallery_category_id") is-invalid @enderror"
                                        id="gallery_category_id_{{ $language->code }}" name="translations[{{ $language->code }}][gallery_category_id]" data-fv-ignore>
                                    <option value="">Seçiniz</option>
                                    {{-- Categories are translated too, so this tab only offers the
                                         category rows belonging to the same language. --}}
                                    @foreach($categories->where('locale', $language->code) as $cat)
                                        <option value="{{ $cat->id }}" {{ (int) old("translations.{$language->code}.gallery_category_id", $translation?->gallery_category_id) === $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("translations.{$language->code}.gallery_category_id")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Sıralama --}}
                            <div class="col-md-6">
                                <label class="form-label" for="sort_order_{{ $language->code }}">Sıralama</label>
                                <input type="number"
                                       class="form-control @error("translations.{$language->code}.sort_order") is-invalid @enderror"
                                       id="sort_order_{{ $language->code }}" name="translations[{{ $language->code }}][sort_order]" data-validation-engine="validate[custom[integer],min[0],max[65535]]" data-fv-ignore data-fv-default="0"
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
                                        id="is_active_{{ $language->code }}" name="translations[{{ $language->code }}][is_active]" data-fv-ignore>
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
                <div class="card-dark mb-4" id="section-media_{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="image_{{ $language->code }}">
                                    Görsel <span class="text-danger">*</span>
                                </label>
                                <input type="file"
                                       class="form-control @error("translations.{$language->code}.image") is-invalid @enderror"
                                       id="image_{{ $language->code }}" name="translations[{{ $language->code }}][image]" accept="image/*">
                                @error("translations.{$language->code}.image")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">PNG, JPG, WebP | Maks: 4 MB</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: VİDEO BİLGİLERİ --}}
                <div class="card-dark mb-4" id="section-video_{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="video_url_{{ $language->code }}">Video URL</label>
                                <input type="url"
                                       class="form-control @error("translations.{$language->code}.video_url") is-invalid @enderror"
                                       id="video_url_{{ $language->code }}" name="translations[{{ $language->code }}][video_url]"
                                       data-validation-engine="validate[custom[url],maxSize[500]]"
                                       value="{{ old("translations.{$language->code}.video_url", $translation?->video_url) }}"
                                       placeholder="https://www.youtube.com/watch?v=...">
                                @error("translations.{$language->code}.video_url")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">YouTube veya Vimeo video linki</div>
                            </div>

                            {{-- Süre --}}
                            <div class="col-md-4">
                                <label class="form-label" for="duration_{{ $language->code }}">Süre</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.duration") is-invalid @enderror"
                                       id="duration_{{ $language->code }}" name="translations[{{ $language->code }}][duration]"
                                       data-validation-engine="validate[custom[integer],min[0],max[65535]]"
                                       value="{{ old("translations.{$language->code}.duration", $translation?->duration) }}"
                                       placeholder="03:45">
                                @error("translations.{$language->code}.duration")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Dakika:Saniye formatında</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /col-12 col-lg-9 -->
        </div><!-- /row -->
