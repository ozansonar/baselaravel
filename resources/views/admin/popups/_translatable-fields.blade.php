{{--
    One language's worth of a popups record.

    Included once per language, so every field — the image included — belongs to
    that language alone.

    @var \App\Models\Language $language
    @var \App\Models\Popup|null $translation
--}}

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4" data-aos="fade-up">
            <select class="form-select form-select-sm" onchange="scrollToSection(this.value, null); this.selectedIndex=0" data-fv-ignore>
                <option value="" disabled selected>Bölüme git...</option>
                <option value="section-basic_{{ $language->code }}">Temel Bilgiler</option>
                <option value="section-media_{{ $language->code }}">Görsel</option>
                <option value="section-button_{{ $language->code }}">Buton Ayarları</option>
                <option value="section-settings_{{ $language->code }}">Popup Ayarları</option>
            </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

            {{-- Sol Navigasyon (desktop) --}}
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="stg-nav-inner position-sticky stg-nav-sticky">
                    <a href="#section-basic_{{ $language->code }}" class="stg-nav-item active" onclick="scrollToSection('section-basic_{{ $language->code }}', this)">
                        <i class="bi bi-window-stack"></i>
                        <div><span>Temel Bilgiler</span><small>Başlık, açıklama</small></div>
                    </a>
                    <a href="#section-media_{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-media_{{ $language->code }}', this)">
                        <i class="bi bi-image"></i>
                        <div><span>Görsel</span><small>Popup görseli</small></div>
                    </a>
                    <a href="#section-button_{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-button_{{ $language->code }}', this)">
                        <i class="bi bi-link-45deg"></i>
                        <div><span>Buton Ayarları</span><small>Buton metni ve URL</small></div>
                    </a>
                    <a href="#section-settings_{{ $language->code }}" class="stg-nav-item" onclick="scrollToSection('section-settings_{{ $language->code }}', this)">
                        <i class="bi bi-gear"></i>
                        <div><span>Popup Ayarları</span><small>Boyut, sayfalar, tarih</small></div>
                    </a>
                </div>
            </div>

            {{-- Form İçeriği --}}
            <div class="col-12 col-lg-9">

                {{-- SECTION 1: TEMEL BİLGİLER --}}
                <div class="card-dark mb-4" id="section-basic_{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="title_{{ $language->code }}">
                                    Başlık <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.title") is-invalid @enderror"
                                       id="title_{{ $language->code }}" name="translations[{{ $language->code }}][title]"
                                       data-validation-engine="validate[required,maxSize[255]]" value="{{ old("translations.{$language->code}.title", $translation?->title) }}"
                                       placeholder="Popup başlığını girin...">
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
                                          placeholder="Popup açıklamasını girin...">{{ old("translations.{$language->code}.description", $translation?->description) }}</textarea>
                                @error("translations.{$language->code}.description")
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
                                <small class="text-muted">Popup için görsel yükleyin</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-image-field
                                    :name="'translations[' . $language->code . '][image]'"
                                    :id="'image_' . $language->code"
                                    label="Popup Görseli"
                                    :current="$translation?->image"
                                    :title="$translation?->title ?: 'Popup görseli'"
                                    :gallery="'popup-' . $language->code"
                                    :remove-name="'translations[' . $language->code . '][remove_image]'" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: BUTON AYARLARI --}}
                <div class="card-dark mb-4" id="section-button_{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="button_text_{{ $language->code }}">Buton Metni</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.button_text") is-invalid @enderror"
                                       id="button_text_{{ $language->code }}" name="translations[{{ $language->code }}][button_text]"
                                       data-validation-engine="validate[maxSize[100]]"
                                       value="{{ old("translations.{$language->code}.button_text", $translation?->button_text) }}"
                                       placeholder="Detaylı Bilgi">
                                @error("translations.{$language->code}.button_text")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş bırakılırsa buton gösterilmez</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="button_url_{{ $language->code }}">Buton URL</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.button_url") is-invalid @enderror"
                                       id="button_url_{{ $language->code }}" name="translations[{{ $language->code }}][button_url]"
                                       data-validation-engine="validate[maxSize[500]]"
                                       value="{{ old("translations.{$language->code}.button_url", $translation?->button_url) }}"
                                       placeholder="hakkimizda">
                                @error("translations.{$language->code}.button_url")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Dil ön eki yazmayın: "iletisim" yazın, ziyaretçinin diline göre /tr/ ya da /en/ eklenir. Harici adresler olduğu gibi kullanılır.</div>
                                <div class="form-text">Dahili veya harici link girebilirsiniz</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 4: POPUP AYARLARI --}}
                <div class="card-dark mb-4" id="section-settings_{{ $language->code }}" data-aos="fade-up">
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
                                <label class="form-label" for="size_{{ $language->code }}">Popup Boyutu</label>
                                <select class="form-select @error("translations.{$language->code}.size") is-invalid @enderror"
                                        id="size_{{ $language->code }}" name="translations[{{ $language->code }}][size]" data-fv-ignore>
                                    @foreach(\App\Enums\PopupSize::cases() as $size)
                                        <option value="{{ $size->value }}" {{ old("translations.{$language->code}.size", $translation?->size?->value ?? 'md') === $size->value ? 'selected' : '' }}>
                                            {{ $size->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("translations.{$language->code}.size")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Gösterim sıklığı --}}
                            <div class="col-md-6">
                                <label class="form-label" for="display_mode_{{ $language->code }}">Gösterim Sıklığı</label>
                                <select class="form-select @error("translations.{$language->code}.display_mode") is-invalid @enderror"
                                        id="display_mode_{{ $language->code }}" name="translations[{{ $language->code }}][display_mode]"
                                        data-fv-ignore
                                        onchange="document.getElementById('display_mode_hint_{{ $language->code }}').textContent = this.selectedOptions[0].dataset.hint || ''">
                                    @php
                                        $seciliMod = old("translations.{$language->code}.display_mode", $translation?->display_mode?->value ?? \App\Enums\PopupDisplayMode::Session->value);
                                    @endphp
                                    @foreach(\App\Enums\PopupDisplayMode::cases() as $mode)
                                        <option value="{{ $mode->value }}" data-hint="{{ $mode->description() }}"
                                                {{ $seciliMod === $mode->value ? 'selected' : '' }}>
                                            {{ $mode->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text" id="display_mode_hint_{{ $language->code }}">
                                    {{ (\App\Enums\PopupDisplayMode::tryFrom($seciliMod) ?? \App\Enums\PopupDisplayMode::Session)->description() }}
                                </div>
                                @error("translations.{$language->code}.display_mode")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Sıralama --}}
                            <div class="col-md-3">
                                <label class="form-label" for="sort_order_{{ $language->code }}">Sıralama</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.sort_order") is-invalid @enderror"
                                       id="sort_order_{{ $language->code }}" name="translations[{{ $language->code }}][sort_order]" data-fv-mask="digits" data-validation-engine="validate[custom[integer],min[0],max[65535]]" data-fv-default="0"
                                       value="{{ old("translations.{$language->code}.sort_order", $translation?->sort_order ?? 0) }}">
                                @error("translations.{$language->code}.sort_order")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük = Önce gösterilir</div>
                            </div>

                            {{-- Durum --}}
                            <div class="col-md-3">
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

                            {{-- Sayfalar --}}
                            <div class="col-12">
                                <label class="form-label">
                                    Görüntülenecek Sayfalar <span class="text-danger">*</span>
                                </label>
                                @error("translations.{$language->code}.pages")
                                <div class="text-danger small mb-2">{{ $message }}</div>
                                @enderror
                                <div class="row g-2">
                                    @foreach(\App\Enums\PopupPage::cases() as $page)
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="translations[{{ $language->code }}][pages][]" value="{{ $page->value }}"
                                                       id="page_{{ $page->value }}_{{ $language->code }}"
                                                       {{ in_array($page->value, (array) old("translations.{$language->code}.pages", $translation?->pages ?? ['all']), true) ? 'checked' : '' }} data-fv-ignore>
                                                <label class="form-check-label" for="page_{{ $page->value }}_{{ $language->code }}">
                                                    {{ $page->label() }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Başlangıç Tarihi --}}
                            <div class="col-md-6">
                                <label class="form-label" for="start_date_{{ $language->code }}">Başlangıç Tarihi</label>
                                <input type="date"
                                       class="form-control @error("translations.{$language->code}.start_date") is-invalid @enderror"
                                       id="start_date_{{ $language->code }}" name="translations[{{ $language->code }}][start_date]" data-fv-ignore
                                       value="{{ old("translations.{$language->code}.start_date", $translation?->start_date?->format('Y-m-d')) }}">
                                @error("translations.{$language->code}.start_date")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş = Hemen başlar</div>
                            </div>

                            {{-- Bitiş Tarihi --}}
                            <div class="col-md-6">
                                <label class="form-label" for="end_date_{{ $language->code }}">Bitiş Tarihi</label>
                                <input type="date"
                                       class="form-control @error("translations.{$language->code}.end_date") is-invalid @enderror"
                                       id="end_date_{{ $language->code }}" name="translations[{{ $language->code }}][end_date]" data-fv-ignore
                                       value="{{ old("translations.{$language->code}.end_date", $translation?->end_date?->format('Y-m-d')) }}">
                                @error("translations.{$language->code}.end_date")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Boş = Süresiz gösterilir</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /col-12 col-lg-9 -->
        </div><!-- /row -->
