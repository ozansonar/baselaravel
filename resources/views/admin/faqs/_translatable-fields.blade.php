{{--
    One language's worth of an FAQ entry.

    Included once per language inside the tab strip, so the question, the answer
    and the per-language settings all belong to that language alone.

    @var \App\Models\Language $language
    @var \App\Models\Faq|null $translation
--}}
                {{-- Mobile Section Jumper --}}
                <div class="d-lg-none mb-4">
                    <select class="form-select form-select-sm" data-scroll-select data-fv-ignore>
                        <option value="" disabled selected>Bölüme git...</option>
                        <option value="section-basic_{{ $language->code }}">Soru & Cevap</option>
                        <option value="section-settings_{{ $language->code }}">Ayarlar</option>
                    </select>
                </div>

                {{-- Form Layout --}}
                <div class="row g-4 align-items-start">

                    {{-- Sol Navigasyon (yalnızca desktop) --}}
                    <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right" data-aos-delay="100">
                        <div class="stg-nav-inner position-sticky stg-nav-sticky">
                            <a href="#section-basic_{{ $language->code }}" class="stg-nav-item active" data-scroll-to="section-basic_{{ $language->code }}">
                                <i class="bi bi-question-circle"></i>
                                <div><span>Soru & Cevap</span><small>Soru metni, cevabı</small></div>
                            </a>
                            <a href="#section-settings_{{ $language->code }}" class="stg-nav-item" data-scroll-to="section-settings_{{ $language->code }}">
                                <i class="bi bi-gear"></i>
                                <div><span>Ayarlar</span><small>Sıralama, durum</small></div>
                            </a>
                        </div>
                    </div>

                    {{-- Form İçeriği --}}
                    <div class="col-12 col-lg-9">

                {{-- Soru & Cevap --}}
                <div class="card-dark mb-4" id="section-basic_{{ $language->code }}" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-teal"><i class="bi bi-question-circle"></i></div>
                            <div>
                                <h6 class="mb-0">Soru & Cevap</h6>
                                <small class="text-muted">Soru metnini ve cevabını yazın</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="question_{{ $language->code }}">
                                    Soru <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.question") is-invalid @enderror"
                                       id="question_{{ $language->code }}" name="translations[{{ $language->code }}][question]"
                                       data-validation-engine="validate[required,maxSize[191]]" value="{{ old("translations.{$language->code}.question", $translation?->question) }}"
                                       placeholder="Soruyu girin...">
                                @error("translations.{$language->code}.question")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="answer_{{ $language->code }}">
                                    Cevap <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control @error("translations.{$language->code}.answer") is-invalid @enderror"
                                          id="answer_{{ $language->code }}" name="translations[{{ $language->code }}][answer]"
                                       data-validation-engine="validate[required,maxSize[10000]]" rows="6"
                                          placeholder="Cevabı yazın...">{{ old("translations.{$language->code}.answer", $translation?->answer) }}</textarea>
                                @error("translations.{$language->code}.answer")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ayarlar --}}
                <div class="card-dark mb-4" id="section-settings_{{ $language->code }}" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-purple"><i class="bi bi-gear"></i></div>
                            <div>
                                <h6 class="mb-0">Ayarlar</h6>
                                <small class="text-muted">Sıralama ve durum bilgisi</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sort_order_{{ $language->code }}">Sıralama</label>
                                <input type="text"
                                       class="form-control @error("translations.{$language->code}.sort_order") is-invalid @enderror"
                                       id="sort_order_{{ $language->code }}" name="translations[{{ $language->code }}][sort_order]" data-fv-mask="digits" data-validation-engine="validate[custom[integer],min[0],max[65535]]" data-fv-default="0"
                                       value="{{ old("translations.{$language->code}.sort_order", $translation?->sort_order ?? 0) }}">
                                @error("translations.{$language->code}.sort_order")
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük değer = Daha üstte</div>
                            </div>
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

                    </div><!-- /col-12 col-lg-9 -->
                </div><!-- /row -->
