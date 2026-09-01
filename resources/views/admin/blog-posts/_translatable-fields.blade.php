{{--
    One language's worth of a blog post.

    Included once per language, so title, body, artwork, SEO and even the
    category belong to that language alone — an English post points at the
    English category row.

    @var \App\Models\Language $language
    @var \App\Models\BlogPost|null $translation
--}}
@php
    /**
     * Client side rules for jQuery Validation Engine.
     *
     * Only the tab on screen is validated, so a field is either required or it
     * is not — form-validation.js lifts the rules of the hidden tabs before
     * checking and leaves their fields out of the request.
     */
    $rules = function (array $extra = []): string {
        return 'validate[' . implode(',', array_merge(['required'], $extra)) . ']';
    };
@endphp

        {{-- Mobile Section Jumper --}}
        <div class="d-lg-none mb-4">
          <select class="form-select form-select-sm" data-scroll-select data-fv-ignore>
            <option value="" disabled selected>Bölüme git...</option>
            <option value="section-basic_{{ $language->code }}">Temel Bilgiler</option>
            <option value="section-content_{{ $language->code }}">İçerik Editörü</option>
            <option value="section-media_{{ $language->code }}">Medya Yönetimi</option>
            <option value="section-files_{{ $language->code }}">Dosya Ekleri</option>
            <option value="section-seo_{{ $language->code }}">SEO Ayarları</option>
            <option value="section-publish_{{ $language->code }}">Yayın Ayarları</option>
          </select>
        </div>

        {{-- Form Layout --}}
        <div class="row g-4 align-items-start">

          {{-- Sol Navigasyon (yalnızca desktop) --}}
          <div class="col-lg-3 d-none d-lg-block">
            <div class="stg-nav-inner position-sticky stg-nav-sticky">
              <a href="#section-basic_{{ $language->code }}" class="stg-nav-item active" data-scroll-to="section-basic_{{ $language->code }}">
                <i class="bi bi-text-paragraph"></i>
                <div><span>Temel Bilgiler</span><small>Başlık, slug, kategori</small></div>
              </a>
              <a href="#section-content_{{ $language->code }}" class="stg-nav-item" data-scroll-to="section-content_{{ $language->code }}">
                <i class="bi bi-body-text"></i>
                <div><span>İçerik Editörü</span><small>Ana metin ve özet</small></div>
              </a>
              <a href="#section-media_{{ $language->code }}" class="stg-nav-item" data-scroll-to="section-media_{{ $language->code }}">
                <i class="bi bi-images"></i>
                <div><span>Medya Yönetimi</span><small>Kapak görseli</small></div>
              </a>
              <a href="#section-files_{{ $language->code }}" class="stg-nav-item" data-scroll-to="section-files_{{ $language->code }}">
                <i class="bi bi-paperclip"></i>
                <div><span>Dosya Ekleri</span><small>Belge, tablo, video</small></div>
              </a>
              <a href="#section-seo_{{ $language->code }}" class="stg-nav-item" data-scroll-to="section-seo_{{ $language->code }}">
                <i class="bi bi-search"></i>
                <div><span>SEO Ayarları</span><small>Meta başlık, açıklama</small></div>
              </a>
              <a href="#section-publish_{{ $language->code }}" class="stg-nav-item" data-scroll-to="section-publish_{{ $language->code }}">
                <i class="bi bi-calendar-event"></i>
                <div><span>Yayın Ayarları</span><small>Durum, tarih</small></div>
              </a>
            </div>
          </div>

          {{-- Form İçeriği --}}
          <div class="col-12 col-lg-9">

          <!-- ==================== SECTION 1: TEMEL BİLGİLER ==================== -->
          <div class="card-dark mb-4" id="section-basic_{{ $language->code }}">
            <div class="card-header-custom">
              <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                <div>
                  <h6 class="mb-0">Temel Bilgiler</h6>
                  <small class="text-muted">İçeriğin başlığını, URL yapısını ve kategorisini belirleyin</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Başlık -->
                <div class="col-12">
                  <label class="form-label" for="title_{{ $language->code }}">
                    İçerik Başlığı <span class="text-danger">*</span>
                  </label>
                  <input
                    type="text"
                    class="form-control @error("translations.{$language->code}.title") is-invalid @enderror"
                    id="title_{{ $language->code }}"
                    name="translations[{{ $language->code }}][title]"
                    value="{{ old("translations.{$language->code}.title", $translation?->title) }}"
                    placeholder="İçeriğin ana başlığını yazın..."
                    maxlength="120"
                    data-validation-engine="{{ $rules(['maxSize[120]']) }}"
                    data-slug-source data-slug-target="slug_{{ $language->code }}"
                    data-char-counter="120" data-seo-preview>
                  @error("translations.{$language->code}.title")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Dikkat çekici ve SEO uyumlu bir başlık girin</div>
                    <div class="form-text"><span id="title_{{ $language->code }}-counter">{{ Str::length(old("translations.{$language->code}.title", $translation?->title ?? '')) }}</span>/120</div>
                  </div>
                </div>

                <!-- Slug -->
                <div class="col-12">
                  <label class="form-label" for="slug_{{ $language->code }}">URL (Slug)</label>
                  <div class="input-group">
                    <span class="input-group-text">{{ config('app.url') }}/blog/</span>
                    <input
                      type="text"
                      class="form-control @error("translations.{$language->code}.slug") is-invalid @enderror"
                      id="slug_{{ $language->code }}"
                      name="translations[{{ $language->code }}][slug]"
                      value="{{ old("translations.{$language->code}.slug", $translation?->slug) }}"
                      placeholder="otomatik-oluşturulur"
                      data-validation-engine="validate[custom[slug],maxSize[191]]"
                      data-prompt-target="slug_error_{{ $language->code }}"
                      data-slug-field>
                  </div>
                  <div id="slug_error_{{ $language->code }}"></div>
                  @error("translations.{$language->code}.slug")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="form-text">Başlık yazıldığında otomatik oluşturulur, isterseniz düzenleyebilirsiniz</div>
                </div>

                <!-- Kategori -->
                <div class="col-12">
                  <label class="form-label" for="blog_category_id_{{ $language->code }}">
                    Kategori <span class="text-danger">*</span>
                  </label>
                  <select class="form-select @error("translations.{$language->code}.blog_category_id") is-invalid @enderror" id="blog_category_id_{{ $language->code }}" name="translations[{{ $language->code }}][blog_category_id]" data-validation-engine="{{ $rules() }}">
                    <option value="">Kategori seçin...</option>
                    {{-- Categories are translated too, so a post is tied to the category
                       row in its own language. --}}
                    @foreach($categories->where('locale', $language->code) as $category)
                    <option value="{{ $category->id }}" {{ old("translations.{$language->code}.blog_category_id", $translation?->blog_category_id) == $category->id ? 'selected' : '' }}>
                      {{ $category->name }}
                    </option>
                    @endforeach
                  </select>
                  @error("translations.{$language->code}.blog_category_id")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
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
                  <small class="text-muted">İçeriğin ana metnini ve kısa özetini yazın</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Kısa Özet -->
                <div class="col-12">
                  <label class="form-label" for="excerpt_{{ $language->code }}">
                    Kısa Özet
                  </label>
                  <textarea
                    class="form-control @error("translations.{$language->code}.excerpt") is-invalid @enderror"
                    id="excerpt_{{ $language->code }}"
                    name="translations[{{ $language->code }}][excerpt]"
                    rows="3"
                    maxlength="300"
                    placeholder="İçeriğin kısa bir özetini yazın (listeleme sayfalarında görünür)..."
                    data-validation-engine="validate[maxSize[300]]"
                    data-char-counter="300"
                  >{{ old("translations.{$language->code}.excerpt", $translation?->excerpt) }}</textarea>
                  @error("translations.{$language->code}.excerpt")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Arama sonuçlarında ve listelerde gösterilecek kısa açıklama</div>
                    <div class="form-text"><span id="excerpt_{{ $language->code }}-counter">{{ Str::length(old("translations.{$language->code}.excerpt", $translation?->excerpt ?? '')) }}</span>/300</div>
                  </div>
                </div>

                <!-- Ana İçerik -->
                <div class="col-12">
                  <label class="form-label" for="body_{{ $language->code }}">
                    Ana İçerik <span class="text-danger">*</span>
                  </label>
                  <textarea
                    class="@error("translations.{$language->code}.body") is-invalid @enderror"
                    id="body_{{ $language->code }}"
                    name="translations[{{ $language->code }}][body]"
                    rows="12"
                    data-validation-engine="{{ $rules() }}"
                    data-prompt-target="body_error_{{ $language->code }}"
                  >{{ old("translations.{$language->code}.body", $translation?->body) }}</textarea>
                  {{-- TinyMCE hides the textarea, so the message needs its own slot. --}}
                  <div id="body_error_{{ $language->code }}"></div>
                  @error("translations.{$language->code}.body")
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
                  <small class="text-muted">Kapak görseli yükleyin</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Kapak Görseli -->
                <div class="col-12">
                  {{-- Kutunun kendisi ortak bileşende: aynı alan slider, sayfa,
                       popup ve galeride de var, beşi ayrı ayrı yazılıyordu. --}}
                  <x-image-field
                    :name="'translations[' . $language->code . '][image]'"
                    :id="'image_' . $language->code"
                    label="Kapak Görseli"
                    :current="$translation?->image"
                    :title="$translation?->title ?: 'Kapak görseli'"
                    :gallery="'kapak-' . $language->code"
                    :remove-name="'translations[' . $language->code . '][remove_image]'" />
                </div>

              </div>
            </div>
          </div>


          <!-- ==================== SECTION 4: DOSYA EKLERİ ==================== -->
          @include('admin.partials.content-files', [
              'language'       => $language,
              'translation'    => $translation,
              'attachableType' => \App\Enums\AttachableContent::BlogPost,
              'fileLimits'     => $fileLimits,
              'pendingFiles'   => $pendingFiles,
          ])


          <!-- ==================== SECTION 5: SEO AYARLARI ==================== -->
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

                <!-- SEO Önizleme -->
                <div class="col-12">
                  <label class="form-label">Google Arama Önizlemesi</label>
                  <div class="ca-seo-preview">
                    <div class="ca-seo-url">{{ config('app.url') }}/blog/<span id="seoPreviewSlug_{{ $language->code }}">yeni-icerik</span></div>
                    <div class="ca-seo-title" id="seoPreviewTitle_{{ $language->code }}">İçerik Başlığı Buraya Gelecek</div>
                    <div class="ca-seo-desc" id="seoPreviewDesc_{{ $language->code }}">İçeriğinizin meta açıklaması burada görünecek. Arama sonuçlarında kullanıcıların göreceği metin budur.</div>
                  </div>
                </div>

                <!-- Meta Başlık -->
                <div class="col-12">
                  <label class="form-label" for="meta_title_{{ $language->code }}">Meta Başlık</label>
                  <input
                    type="text"
                    class="form-control @error("translations.{$language->code}.meta_title") is-invalid @enderror"
                    id="meta_title_{{ $language->code }}"
                    name="translations[{{ $language->code }}][meta_title]"
                    value="{{ old("translations.{$language->code}.meta_title", $translation?->meta_title) }}"
                    maxlength="{{ $seoTitleMax }}"
                    placeholder="SEO için özel başlık (boş bırakılırsa içerik başlığı kullanılır)"
                    data-validation-engine="validate[maxSize[{{ $seoTitleMax }}]]"
                    data-char-counter="{{ $seoTitleMax }}" data-seo-preview>
                  @error("translations.{$language->code}.meta_title")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Önerilen: {{ $seoTitleMin }}-{{ $seoTitleMax }} karakter</div>
                    <div class="form-text"><span id="meta_title_{{ $language->code }}-counter">{{ Str::length(old("translations.{$language->code}.meta_title", $translation?->meta_title ?? '')) }}</span>/{{ $seoTitleMax }}</div>
                  </div>
                </div>

                <!-- Meta Açıklama -->
                <div class="col-12">
                  <label class="form-label" for="meta_description_{{ $language->code }}">Meta Açıklama</label>
                  <textarea
                    class="form-control @error("translations.{$language->code}.meta_description") is-invalid @enderror"
                    id="meta_description_{{ $language->code }}"
                    name="translations[{{ $language->code }}][meta_description]"
                    rows="3"
                    maxlength="{{ $seoDescMax }}"
                    placeholder="Arama sonuçlarında görünecek açıklama metni..."
                    data-validation-engine="validate[maxSize[{{ $seoDescMax }}]]"
                    data-char-counter="{{ $seoDescMax }}" data-seo-preview
                  >{{ old("translations.{$language->code}.meta_description", $translation?->meta_description) }}</textarea>
                  @error("translations.{$language->code}.meta_description")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="d-flex justify-content-between mt-1">
                    <div class="form-text">Önerilen: {{ $seoDescMin }}-{{ $seoDescMax }} karakter</div>
                    <div class="form-text"><span id="meta_description_{{ $language->code }}-counter">{{ Str::length(old("translations.{$language->code}.meta_description", $translation?->meta_description ?? '')) }}</span>/{{ $seoDescMax }}</div>
                  </div>
                </div>

                {{-- Denetim paneli: alanların hemen altında, çünkü bulgular
                     çoğunlukla bu alanlara işaret ediyor. --}}
                <div class="col-12">
                  @include('partials.admin.seo-panel', [
                      'seoLocale' => $language->code,
                      'seoType'   => 'blog_post',
                  ])
                </div>

              </div>
            </div>
          </div>


          <!-- ==================== SECTION 6: YAYIN AYARLARI ==================== -->
          <div class="card-dark mb-4" id="section-publish_{{ $language->code }}">
            <div class="card-header-custom">
              <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-calendar-event"></i></div>
                <div>
                  <h6 class="mb-0">Yayın Ayarları</h6>
                  <small class="text-muted">İçeriğin yayın durumu ve tarihini ayarlayın</small>
                </div>
              </div>
            </div>
            <div class="card-body-custom">
              <div class="row g-3">

                <!-- Yayın Durumu -->
                <div class="col-12 col-md-6">
                  <label class="form-label" for="status_{{ $language->code }}">
                    Yayın Durumu <span class="text-danger">*</span>
                  </label>
                  <select class="form-select @error("translations.{$language->code}.status") is-invalid @enderror"
                          id="status_{{ $language->code }}"
                          name="translations[{{ $language->code }}][status]"
                          data-fv-ignore>
                    {{-- "Zamanlanmış" saklanan bir durum değil: yayında olup tarihi
                         ileride olan yazı listede öyle görünür. --}}
                    @foreach([\App\Enums\ContentStatus::Draft, \App\Enums\ContentStatus::Published, \App\Enums\ContentStatus::Archived] as $contentStatus)
                      <option value="{{ $contentStatus->value }}"
                        {{ old("translations.{$language->code}.status", $translation?->status?->value ?? \App\Enums\ContentStatus::Draft->value) === $contentStatus->value ? 'selected' : '' }}>
                        {{ $contentStatus->label() }}
                      </option>
                    @endforeach
                  </select>
                  @error("translations.{$language->code}.status")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="form-text">Her dil kendi durumunu taşır</div>
                </div>

                <!-- Yayın Tarihi -->
                <div class="col-12 col-md-6">
                  <label class="form-label" for="published_at_{{ $language->code }}">Yayın Tarihi</label>
                  <input
                    type="datetime-local"
                    class="form-control @error("translations.{$language->code}.published_at") is-invalid @enderror"
                    id="published_at_{{ $language->code }}"
                    name="translations[{{ $language->code }}][published_at]"
                    value="{{ old("translations.{$language->code}.published_at", $translation?->published_at?->format('Y-m-d\TH:i')) }}"
                   data-validation-engine="validate[custom[dateTime]]">
                  @error("translations.{$language->code}.published_at")
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="form-text">İleri bir tarih seçersen yazı o tarihte yayınlanır; boş bırakırsan hemen</div>
                </div>

              </div>
            </div>
          </div>

          </div><!-- /col-12 col-lg-9 -->
        </div><!-- /row -->
