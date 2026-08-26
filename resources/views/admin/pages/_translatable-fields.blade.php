{{--
    One language's worth of a page.

    Included once per language inside the tab strip, so every field — the image
    included — belongs to that language alone. Ids carry the language code
    because the same markup is on the page several times over.

    @var \App\Models\Language $language
    @var \App\Models\Page|null $translation
--}}
            <!-- ==================== SECTION 1: TEMEL BİLGİLER ==================== -->
            <div class="card-dark mb-4" id="section-basic_{{ $language->code }}">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                        <div>
                            <h6 class="mb-0">Temel Bilgiler</h6>
                            <small class="text-muted">Sayfanın başlığını ve URL yapısını belirleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label" for="title_{{ $language->code }}">
                                Sayfa Başlığı <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error("translations.{$language->code}.title") is-invalid @enderror"
                                id="title_{{ $language->code }}"
                                name="translations[{{ $language->code }}][title]"
                                       data-validation-engine="validate[required,maxSize[255]]"
                                value="{{ old("translations.{$language->code}.title", $translation?->title) }}"
                                placeholder="Sayfanın ana başlığını yazın..."
                                oninput="generateSlug(this.value); updateCharCounter(this, 120); updateSeoPreview()"
                            >
                            @error("translations.{$language->code}.title")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Dikkat çekici ve SEO uyumlu bir başlık girin</div>
                                <div class="form-text"><span id="title-counter_{{ $language->code }}">0</span>/120</div>
                            </div>
                        </div>

                        <!-- Slug -->
                        <div class="col-12">
                            <label class="form-label" for="slug_{{ $language->code }}">URL (Slug)</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('/') }}/sayfa/</span>
                                <input
                                    type="text"
                                    class="form-control @error("translations.{$language->code}.slug") is-invalid @enderror"
                                    id="slug_{{ $language->code }}"
                                    name="translations[{{ $language->code }}][slug]"
                                       data-validation-engine="validate[custom[slug],maxSize[255]]"
                                    value="{{ old("translations.{$language->code}.slug", $translation?->slug) }}"
                                    placeholder="otomatik-olusturulur"
                                    oninput="updateSeoPreview()"
                                >
                            </div>
                            @error("translations.{$language->code}.slug")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Başlık yazıldığında otomatik oluşturulur, isterseniz düzenleyebilirsiniz</div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 2: İÇERİK EDİTÖRÜ ==================== -->
            <div class="card-dark mb-4" id="section-content_{{ $language->code }}">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-body-text"></i></div>
                        <div>
                            <h6 class="mb-0">İçerik Editörü</h6>
                            <small class="text-muted">Sayfanın ana metnini ve kısa özetini yazın</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Excerpt -->
                        <div class="col-12">
                            <label class="form-label" for="excerpt_{{ $language->code }}">Kısa Özet</label>
                            <textarea
                                class="form-control @error("translations.{$language->code}.excerpt") is-invalid @enderror"
                                id="excerpt_{{ $language->code }}"
                                name="translations[{{ $language->code }}][excerpt]"
                                       data-validation-engine="validate[maxSize[500]]"
                                rows="3"
                                placeholder="Sayfanın kısa bir özetini yazın..."
                                oninput="updateCharCounter(this, 300)"
                            >{{ old("translations.{$language->code}.excerpt", $translation?->excerpt) }}</textarea>
                            @error("translations.{$language->code}.excerpt")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Listeleme sayfalarında gösterilecek kısa açıklama</div>
                                <div class="form-text"><span id="excerpt-counter_{{ $language->code }}">0</span>/300</div>
                            </div>
                        </div>

                        <!-- Content (TinyMCE) -->
                        <div class="col-12">
                            <label class="form-label" for="content_{{ $language->code }}">
                                Ana İçerik <span class="text-danger">*</span>
                            </label>
                            <textarea
                                class="@error("translations.{$language->code}.content") is-invalid @enderror"
                                id="content_{{ $language->code }}"
                                name="translations[{{ $language->code }}][content]"
                                       data-validation-engine="validate[required]"
                                data-prompt-target="content_error_{{ $language->code }}"
                                rows="12"
                            >{{ old("translations.{$language->code}.content", $translation?->content) }}</textarea>
                            {{-- TinyMCE hides the textarea, so the message needs its own slot. --}}
                            <div id="content_error_{{ $language->code }}"></div>
                            @error("translations.{$language->code}.content")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 3: MEDYA YÖNETİMİ ==================== -->
            <div class="card-dark mb-4" id="section-media_{{ $language->code }}">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-images"></i></div>
                        <div>
                            <h6 class="mb-0">Medya Yönetimi</h6>
                            <small class="text-muted">Kapak görselini yükleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Cover Image Upload -->
                        <div class="col-12">
                            <label class="form-label" for="image_{{ $language->code }}">
                                Kapak Görseli
                            </label>
                            <input
                                type="file"
                                class="form-control @error("translations.{$language->code}.image") is-invalid @enderror"
                                id="image_{{ $language->code }}"
                                name="translations[{{ $language->code }}][image]"
                                accept="image/png,image/jpeg,image/webp"
                            >
                            @error("translations.{$language->code}.image")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">PNG, JPG, WebP | Maks. 1 MB | Önerilen: 1200x630 px</div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 4: SEO AYARLARI ==================== -->
            <div class="card-dark mb-4" id="section-seo_{{ $language->code }}">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-search"></i></div>
                        <div>
                            <h6 class="mb-0">SEO Ayarları</h6>
                            <small class="text-muted">Arama motorları için meta bilgilerini düzenleyin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- SEO Preview -->
                        <div class="col-12">
                            <label class="form-label">Google Arama Önizlemesi</label>
                            <div class="ca-seo-preview">
                                <div class="ca-seo-url">{{ url('/') }}/sayfa/<span id="seoPreviewSlug">sayfa-url</span></div>
                                <div class="ca-seo-title" id="seoPreviewTitle">Sayfa Başlığı</div>
                                <div class="ca-seo-desc" id="seoPreviewDesc">Sayfanızın meta açıklaması burada görünecek.</div>
                            </div>
                        </div>

                        <!-- Meta Title -->
                        <div class="col-12">
                            <label class="form-label" for="meta_title_{{ $language->code }}">Meta Başlık</label>
                            <input
                                type="text"
                                class="form-control @error("translations.{$language->code}.meta_title") is-invalid @enderror"
                                id="meta_title_{{ $language->code }}"
                                name="translations[{{ $language->code }}][meta_title]"
                                       data-validation-engine="validate[maxSize[70]]"
                                value="{{ old("translations.{$language->code}.meta_title", $translation?->meta_title) }}"
                                placeholder="SEO için özel başlık (boş bırakılırsa sayfa başlığı kullanılır)"
                                oninput="updateSeoPreview(); updateCharCounter(this, 60)"
                            >
                            @error("translations.{$language->code}.meta_title")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Önerilen: 50–60 karakter</div>
                                <div class="form-text"><span id="meta_title-counter_{{ $language->code }}">0</span>/60</div>
                            </div>
                        </div>

                        <!-- Meta Description -->
                        <div class="col-12">
                            <label class="form-label" for="meta_description_{{ $language->code }}">Meta Açıklama</label>
                            <textarea
                                class="form-control @error("translations.{$language->code}.meta_description") is-invalid @enderror"
                                id="meta_description_{{ $language->code }}"
                                name="translations[{{ $language->code }}][meta_description]"
                                       data-validation-engine="validate[maxSize[160]]"
                                rows="3"
                                placeholder="Arama sonuçlarında görünecek açıklama metni..."
                                oninput="updateSeoPreview(); updateCharCounter(this, 160)"
                            >{{ old("translations.{$language->code}.meta_description", $translation?->meta_description) }}</textarea>
                            @error("translations.{$language->code}.meta_description")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Önerilen: 120–160 karakter</div>
                                <div class="form-text"><span id="meta_description-counter_{{ $language->code }}">0</span>/160</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 5: YAYIN AYARLARI ==================== -->
            <div class="card-dark mb-4" id="section-publish_{{ $language->code }}">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <h6 class="mb-0">Yayın Ayarları</h6>
                            <small class="text-muted">Sayfanın yayın durumunu ve tarihini ayarlayın</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Status -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="status_{{ $language->code }}">
                                Yayın Durumu <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error("translations.{$language->code}.status") is-invalid @enderror" id="status_{{ $language->code }}" name="translations[{{ $language->code }}][status]" data-fv-ignore>
                                @foreach(\App\Enums\ContentStatus::cases() as $status)
                                <option value="{{ $status->value }}" {{ old("translations.{$language->code}.status", $translation?->status?->value ?? 'published') === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                                @endforeach
                            </select>
                            @error("translations.{$language->code}.status")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Published At -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="published_at_{{ $language->code }}">Yayın Tarihi</label>
                            <input
                                type="datetime-local"
                                class="form-control @error("translations.{$language->code}.published_at") is-invalid @enderror"
                                id="published_at_{{ $language->code }}"
                                name="translations[{{ $language->code }}][published_at]" data-fv-ignore
                                value="{{ old("translations.{$language->code}.published_at", $translation?->published_at?->format('Y-m-d\TH:i')) }}"
                            >
                            @error("translations.{$language->code}.published_at")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>


            <!-- ==================== SECTION 6: GELİŞMİŞ AYARLAR ==================== -->
            <div class="card-dark mb-4" id="section-advanced_{{ $language->code }}">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-gear"></i></div>
                        <div>
                            <h6 class="mb-0">Gelişmiş Ayarlar</h6>
                            <small class="text-muted">Sıralama ve diğer seçenekler</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">

                        <!-- Sort Order -->
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sort_order_{{ $language->code }}">Sıralama</label>
                            <input
                                type="number"
                                class="form-control @error("translations.{$language->code}.sort_order") is-invalid @enderror"
                                id="sort_order_{{ $language->code }}"
                                name="translations[{{ $language->code }}][sort_order]" data-validation-engine="validate[custom[integer],min[0],max[65535]]" data-fv-ignore data-fv-default="0"
                                value="{{ old("translations.{$language->code}.sort_order", $translation?->sort_order ?? 0) }}"
                                placeholder="0"
                                min="0"
                                max="999"
                            >
                            @error("translations.{$language->code}.sort_order")
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Düşük değer = Daha üstte görünür</div>
                        </div>

                    </div>
                </div>
            </div>

