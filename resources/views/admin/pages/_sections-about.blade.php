{{-- About Page Sections (hakkimizda) --}}
@php
    $sections = old('sections', $page->sections ?? []);
    $story = $sections['story'] ?? [];
    $values = $sections['values'] ?? [];
    $timeline = $sections['timeline'] ?? [];
    $stats = $sections['stats'] ?? [];
    $team = $sections['team'] ?? [];
    $cta = $sections['cta'] ?? [];
@endphp

<!-- ==================== SECTION: HİKAYE ==================== -->
<div class="card-dark mb-4" id="section-story">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-book"></i></div>
            <div>
                <h6 class="mb-0">Hikaye Bölümü</h6>
                <small class="text-muted">Kurumun hikayesini anlatan ana bölüm</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="sections_story_tag">Etiket</label>
                <input type="text" class="form-control" id="sections_story_tag" name="sections[story][tag]"
                    value="{{ $story['tag'] ?? '' }}" placeholder="Ör: Hikayemiz" data-validation-engine="validate[maxSize[100]]">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="sections_story_experience_year">Tecrübe Yılı</label>
                <input type="text" class="form-control" id="sections_story_experience_year" name="sections[story][experience_year]"
                    value="{{ $story['experience_year'] ?? '' }}" placeholder="Ör: 50+" data-validation-engine="validate[custom[integer],max[9999]]">
            </div>
            <div class="col-12">
                <label class="form-label" for="sections_story_title">Başlık</label>
                <input type="text" class="form-control" id="sections_story_title" name="sections[story][title]"
                    value="{{ $story['title'] ?? '' }}" placeholder="Ör: 1975'ten Bu Yana Doğallığın Adresi" data-validation-engine="validate[maxSize[255]]">
            </div>
            <div class="col-12">
                <label class="form-label" for="sections_story_paragraphs">Hikaye Metni</label>
                <textarea class="form-control" id="sections_story_paragraphs" name="sections[story][paragraphs]"
                    rows="6" placeholder="Her paragrafı yeni satırla ayırın..." data-validation-engine="validate[maxSize[5000]]">{{ $story['paragraphs'] ?? '' }}</textarea>
                <div class="form-text">Paragrafları boş satırla ayırın</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="sections_story_highlight">Vurgulu Alıntı</label>
                <textarea class="form-control" id="sections_story_highlight" name="sections[story][highlight]"
                    rows="2" placeholder="Ör: Doğanın bize verdiği her şeyin en iyisini..." data-validation-engine="validate[maxSize[1000]]">{{ $story['highlight'] ?? '' }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="sections_story_closing">Kapanış Metni</label>
                <textarea class="form-control" id="sections_story_closing" name="sections[story][closing]"
                    rows="2" placeholder="Ör: Bugün, modern hijyen standartlarını..." data-validation-engine="validate[maxSize[1000]]">{{ $story['closing'] ?? '' }}</textarea>
            </div>
        </div>
    </div>
</div>


<!-- ==================== SECTION: DEĞERLER ==================== -->
<div class="card-dark mb-4" id="section-values">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-purple"><i class="bi bi-heart"></i></div>
            <div>
                <h6 class="mb-0">Değerlerimiz</h6>
                <small class="text-muted">Şirketi tanımlayan temel değerler</small>
            </div>
        </div>
        <button type="button" class="btn-glass btn-sm" onclick="addRepeaterItem('values')">
            <i class="bi bi-plus-lg me-1"></i>Ekle
        </button>
    </div>
    <div class="card-body-custom">
        <div id="repeater-values">
            @forelse($values as $i => $value)
            <div class="repeater-item border rounded p-3 mb-3" data-index="{{ $i }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-teal">Değer #{{ $i + 1 }}</span>
                    <button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">İkon</label>
                        <input type="text" class="form-control" name="sections[values][{{ $i }}][icon]"
                            value="{{ $value['icon'] ?? '' }}" placeholder="fa-solid fa-seedling" data-validation-engine="validate[maxSize[100]]">
                        <div class="form-text">FontAwesome ikon class'ı</div>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label">Başlık</label>
                        <input type="text" class="form-control" name="sections[values][{{ $i }}][title]"
                            value="{{ $value['title'] ?? '' }}" placeholder="Ör: %100 Doğal" data-validation-engine="validate[maxSize[255]]">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="sections[values][{{ $i }}][description]"
                            rows="2" placeholder="Değer açıklaması..." data-validation-engine="validate[maxSize[1000]]">{{ $value['description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-muted text-center py-3" id="values-empty">Henüz değer eklenmemiş.</div>
            @endforelse
        </div>
    </div>
</div>


<!-- ==================== SECTION: TARİHÇE ==================== -->
<div class="card-dark mb-4" id="section-timeline">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-orange"><i class="bi bi-clock-history"></i></div>
            <div>
                <h6 class="mb-0">Tarihçe</h6>
                <small class="text-muted">Zaman çizelgesi öğeleri</small>
            </div>
        </div>
        <button type="button" class="btn-glass btn-sm" onclick="addRepeaterItem('timeline')">
            <i class="bi bi-plus-lg me-1"></i>Ekle
        </button>
    </div>
    <div class="card-body-custom">
        <div id="repeater-timeline">
            @forelse($timeline as $i => $item)
            <div class="repeater-item border rounded p-3 mb-3" data-index="{{ $i }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-teal">Dönem #{{ $i + 1 }}</span>
                    <button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Yıl</label>
                        <input type="text" class="form-control" name="sections[timeline][{{ $i }}][year]"
                            value="{{ $item['year'] ?? '' }}" placeholder="Ör: 1975" data-validation-engine="validate[maxSize[20]]">
                    </div>
                    <div class="col-12 col-md-9">
                        <label class="form-label">Başlık</label>
                        <input type="text" class="form-control" name="sections[timeline][{{ $i }}][title]"
                            value="{{ $item['title'] ?? '' }}" placeholder="Ör: Kuruluş" data-validation-engine="validate[maxSize[255]]">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="sections[timeline][{{ $i }}][description]"
                            rows="2" placeholder="Bu dönemin açıklaması..." data-validation-engine="validate[maxSize[1000]]">{{ $item['description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-muted text-center py-3" id="timeline-empty">Henüz tarihçe öğesi eklenmemiş.</div>
            @endforelse
        </div>
    </div>
</div>


<!-- ==================== SECTION: İSTATİSTİKLER ==================== -->
<div class="card-dark mb-4" id="section-stats">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-blue"><i class="bi bi-graph-up"></i></div>
            <div>
                <h6 class="mb-0">İstatistikler</h6>
                <small class="text-muted">Sayısal veriler (sayaç animasyonu ile gösterilir)</small>
            </div>
        </div>
        <button type="button" class="btn-glass btn-sm" onclick="addRepeaterItem('stats')">
            <i class="bi bi-plus-lg me-1"></i>Ekle
        </button>
    </div>
    <div class="card-body-custom">
        <div id="repeater-stats">
            @forelse($stats as $i => $stat)
            <div class="repeater-item border rounded p-3 mb-3" data-index="{{ $i }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-teal">İstatistik #{{ $i + 1 }}</span>
                    <button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Sayı</label>
                        <input type="number" class="form-control" name="sections[stats][{{ $i }}][number]"
                            value="{{ $stat['number'] ?? '' }}" placeholder="Ör: 50" min="0" data-validation-engine="validate[custom[number]]">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Son Ek</label>
                        <input type="text" class="form-control" name="sections[stats][{{ $i }}][suffix]"
                            value="{{ $stat['suffix'] ?? '' }}" placeholder="Ör: +, K+" data-validation-engine="validate[maxSize[20]]">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Etiket</label>
                        <input type="text" class="form-control" name="sections[stats][{{ $i }}][label]"
                            value="{{ $stat['label'] ?? '' }}" placeholder="Ör: Yıllık Deneyim" data-validation-engine="validate[maxSize[255]]">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" name="sections[stats][{{ $i }}][format]" data-fv-ignore>
                            <option value="">Normal</option>
                            <option value="K" {{ ($stat['format'] ?? '') === 'K' ? 'selected' : '' }}>K (binler)</option>
                        </select>
                        <div class="form-text">15000 → 15K</div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-muted text-center py-3" id="stats-empty">Henüz istatistik eklenmemiş.</div>
            @endforelse
        </div>
    </div>
</div>


<!-- ==================== SECTION: EKİP ==================== -->
<div class="card-dark mb-4" id="section-team">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-people"></i></div>
            <div>
                <h6 class="mb-0">Ekip</h6>
                <small class="text-muted">Ekip üyelerini yönetin</small>
            </div>
        </div>
        <button type="button" class="btn-glass btn-sm" onclick="addRepeaterItem('team')">
            <i class="bi bi-plus-lg me-1"></i>Ekle
        </button>
    </div>
    <div class="card-body-custom">
        <div id="repeater-team">
            @forelse($team as $i => $member)
            <div class="repeater-item border rounded p-3 mb-3" data-index="{{ $i }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-teal">Üye #{{ $i + 1 }}</span>
                    <button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="sections[team][{{ $i }}][name]"
                            value="{{ $member['name'] ?? '' }}" placeholder="Ör: Ahmet Yılmaz" data-validation-engine="validate[maxSize[255]]">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Rol / Unvan</label>
                        <input type="text" class="form-control" name="sections[team][{{ $i }}][role]"
                            value="{{ $member['role'] ?? '' }}" placeholder="Ör: Kurucu Baba" data-validation-engine="validate[maxSize[255]]">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="sections[team][{{ $i }}][description]"
                            rows="2" placeholder="Kısa tanıtım metni..." data-validation-engine="validate[maxSize[1000]]">{{ $member['description'] ?? '' }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fotoğraf</label>
                        @if(!empty($member['photo']))
                        <div class="mb-2">
                            <img src="{{ upload_url($member['photo'], 'thumb') }}" alt="{{ $member['name'] ?? '' }}" class="rounded img-fluid" loading="lazy">
                            <input type="hidden" name="sections[team][{{ $i }}][photo_existing]" value="{{ $member['photo'] }}">
                        </div>
                        @endif
                        <input type="file" class="form-control" name="sections[team][{{ $i }}][photo_file]"
                            accept="image/png,image/jpeg,image/webp" data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]" data-max-size="2" data-accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">PNG, JPG, WebP | Maks. 2 MB</div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-muted text-center py-3" id="team-empty">Henüz ekip üyesi eklenmemiş.</div>
            @endforelse
        </div>
    </div>
</div>


<!-- ==================== SECTION: CTA ==================== -->
<div class="card-dark mb-4" id="section-cta">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-purple"><i class="bi bi-megaphone"></i></div>
            <div>
                <h6 class="mb-0">CTA (Aksiyon Çağrısı)</h6>
                <small class="text-muted">Sayfanın sonundaki aksiyon bölümü</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="sections_cta_title">Başlık</label>
                <input type="text" class="form-control" id="sections_cta_title" name="sections[cta][title]"
                    value="{{ $cta['title'] ?? '' }}" placeholder="Ör: Doğallığı Tatmaya Hazır mısınız?" data-validation-engine="validate[maxSize[255]]">
            </div>
            <div class="col-12">
                <label class="form-label" for="sections_cta_description">Açıklama</label>
                <textarea class="form-control" id="sections_cta_description" name="sections[cta][description]"
                    rows="2" placeholder="Ör: Uzman ekibimizle sunduğumuz hizmetleri keşfedin..." data-validation-engine="validate[maxSize[1000]]">{{ $cta['description'] ?? '' }}</textarea>
            </div>
        </div>
    </div>
</div>
