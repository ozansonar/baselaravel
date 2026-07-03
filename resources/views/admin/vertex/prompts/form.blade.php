@extends('layouts.admin')

@section('title', $mode === 'edit' ? 'Şablon Düzenle' : 'Yeni Şablon')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.vertex.index') }}" class="breadcrumb-link">Vertex</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.vertex.prompts.index') }}" class="breadcrumb-link">Şablonlar</a></li>
            <li class="breadcrumb-item active text-teal">{{ $mode === 'edit' ? 'Düzenle' : 'Yeni' }}</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">
                <i class="bi bi-{{ $mode === 'edit' ? 'pencil-square' : 'plus-square' }} text-teal me-2"></i>
                {{ $mode === 'edit' ? 'Şablon Düzenle: ' . $prompt->name : 'Yeni Vertex Şablonu' }}
            </h1>
        </div>
        <div class="d-flex gap-2">
            @if($mode === 'edit' && $versionCount > 0)
                <button type="button" class="btn-glass" id="vtxVersionsBtn">
                    <i class="bi bi-clock-history me-1"></i> Geçmiş <span class="badge bg-teal-soft ms-1">{{ $versionCount }}</span>
                </button>
            @endif
            <a href="{{ route('admin.vertex.prompts.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Listeye Dön
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:1.25rem;"></i>
            <div class="flex-grow-1">
                <strong>Kaydedilemedi — aşağıdaki hataları düzelt:</strong>
                <ul class="mb-0 mt-1 ps-3 small">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST"
          action="{{ $mode === 'edit' ? route('admin.vertex.prompts.update', $prompt) : route('admin.vertex.prompts.store') }}"
          enctype="multipart/form-data" data-aos="fade-up">
        @csrf
        @if($mode === 'edit') @method('PUT') @endif

        {{-- ── Temel Bilgiler ── --}}
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-blue"><i class="bi bi-card-text"></i></div>
                    <div><h6 class="mb-0">Temel Bilgiler</h6></div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label text-clr-secondary">Şablon Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-theme @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name', $prompt->name) }}" required maxlength="120">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   {{ old('is_active', $prompt->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label text-clr-secondary" for="is_active">Aktif</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label text-clr-secondary">Kısa Açıklama</label>
                        <input type="text" class="form-control form-control-theme @error('description') is-invalid @enderror"
                               id="description" name="description" value="{{ old('description', $prompt->description) }}" maxlength="255">
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="tags" class="form-label text-clr-secondary">
                            Etiketler <small class="text-muted">(virgülle ayır — bulk import eşleşmesi için)</small>
                        </label>
                        <input type="text" class="form-control form-control-theme @error('tags') is-invalid @enderror"
                               id="tags" name="tags" value="{{ old('tags', is_array($prompt->tags) ? implode(', ', $prompt->tags) : '') }}" maxlength="500"
                               placeholder="ürün, peynir, çiftlik, doğal...">
                        @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="prompt" class="form-label text-clr-secondary">
                            Prompt <span class="text-danger">*</span>
                            <small class="text-muted">(<span id="promptCounter">0</span> / 20.000)</small>
                        </label>
                        <textarea class="form-control form-control-theme @error('prompt') is-invalid @enderror"
                                  id="prompt" name="prompt" rows="14" required minlength="10" maxlength="20000">{{ old('prompt', $prompt->prompt) }}</textarea>
                        @error('prompt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Rastgele Seçenek Listeleri ── --}}
        <div class="card-dark mb-4 d-none" id="randomOptionsCard">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-orange"><i class="bi bi-shuffle"></i></div>
                    <div>
                        <h6 class="mb-0">Rastgele Seçenek Listeleri</h6>
                        <small class="text-muted">Prompt'taki <code>@{{ degisken }}</code> değişkenleri için seçenek havuzu — üretimde PHP rastgele seçer</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div id="randomOptionsContainer"></div>
                <div class="d-none" id="randomOptionsEmpty">
                    <p class="text-clr-muted mb-0"><i class="bi bi-info-circle me-1"></i>Prompt'ta <code>@{{ degisken }}</code> formatında değişken bulunamadı. Değişken ekledikten sonra burası otomatik gösterilecek.</p>
                </div>
                <small class="form-text text-clr-secondary mt-3 d-block">
                    <i class="bi bi-info-circle me-1"></i>
                    Her satıra bir seçenek yaz. Üretim sırasında kullanıcı "Rastgele" seçeneğini seçerse PHP <code>array_rand()</code> ile gerçek rastgele seçim yapar.
                    Seçenek listesi boş bırakılan değişkenler için kullanıcıdan manuel değer istenecektir.
                </small>
            </div>
        </div>

        {{-- ── Instagram İçerik Şablonları ── --}}
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-pink"><i class="bi bi-instagram"></i></div>
                    <div>
                        <h6 class="mb-0">Instagram İçerik Şablonları</h6>
                        <small class="text-muted">Görselle birlikte saklanacak caption, başlık ve hashtag'ler — bulk import'ta direkt kullanılır</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="ig_title_template" class="form-label text-clr-secondary">
                            Başlık Şablonu
                            <small class="text-muted">(opsiyonel, <code>@{{ degisken_adi }}</code> kullanılabilir)</small>
                        </label>
                        <input type="text" class="form-control form-control-theme @error('ig_title_template') is-invalid @enderror"
                               id="ig_title_template" name="ig_title_template"
                               value="{{ old('ig_title_template', $prompt->ig_title_template) }}" maxlength="500"
                               placeholder="@{{ozel_gun_adi}} Kutlu Olsun!">
                        @error('ig_title_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="ig_caption_template" class="form-label text-clr-secondary">
                            Caption Şablonu
                            <small class="text-muted">(Instagram açıklaması)</small>
                        </label>
                        <textarea class="form-control form-control-theme @error('ig_caption_template') is-invalid @enderror"
                                  id="ig_caption_template" name="ig_caption_template" rows="4" maxlength="5000"
                                  placeholder="@{{ozel_gun_adi}} vesilesiyle tüm sevenlerimize...">{{ old('ig_caption_template', $prompt->ig_caption_template) }}</textarea>
                        @error('ig_caption_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="ig_hashtags_template" class="form-label text-clr-secondary">
                            Hashtag Şablonu
                            <small class="text-muted">(her satır veya boşlukla ayrılmış)</small>
                        </label>
                        <textarea class="form-control form-control-theme @error('ig_hashtags_template') is-invalid @enderror"
                                  id="ig_hashtags_template" name="ig_hashtags_template" rows="3" maxlength="2000"
                                  placeholder="#@{{ozel_gun_adi}} #çiftlik #doğal #organik"
                                >{{ old('ig_hashtags_template', $prompt->ig_hashtags_template) }}</textarea>
                        @error('ig_hashtags_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <small class="form-text text-clr-secondary mt-3 d-block">
                    <i class="bi bi-info-circle me-1"></i>
                    Prompt'ta kullandığın aynı değişkenler burada da geçerli: <code>@{{ozel_gun_adi}}</code>, <code>@{{ozel_gun_temasi}}</code> vb.
                    Batch oluşturulurken değişkenler render edilip her görselin yanına kaydedilir.
                    Bulk import'ta <code>gorsel_kaynagi=vertex</code> kullandığında caption/hashtag otomatik gelir.
                </small>
            </div>
        </div>

        {{-- ── Referans Görseller (Çoklu) ── --}}
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-purple"><i class="bi bi-images"></i></div>
                    <div>
                        <h6 class="mb-0">Referans Görseller (Opsiyonel)</h6>
                        <small class="text-muted">Image-to-image akışı — Vertex'e <code>fileData</code> part'ı olarak birden fazla görsel iletilebilir (en fazla 10)</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">

                {{-- Mevcut görseller grid (sadece edit modunda dolu olur) --}}
                @php $existingImages = is_array($prompt->reference_images) ? $prompt->reference_images : []; @endphp
                @if($existingImages !== [])
                    <div class="mb-3">
                        <label class="form-label text-clr-secondary mb-2">
                            <i class="bi bi-collection text-info me-1"></i>
                            Mevcut Görseller ({{ count($existingImages) }})
                        </label>
                        <div class="row g-3" id="existingImagesGrid">
                            @foreach($existingImages as $idx => $img)
                                @php
                                    $imgPath = $img['path'] ?? null;
                                    $imgMime = $img['mime'] ?? 'image/png';
                                @endphp
                                @if($imgPath)
                                    <div class="col-md-3 col-sm-4 col-6" data-existing-image-card="{{ $idx }}">
                                        <div class="position-relative" style="background:#1a1a1a; border:1px solid rgba(255,255,255,0.06); border-radius:8px; padding:8px;">
                                            <a href="{{ upload_url($imgPath) }}" target="_blank" rel="noopener">
                                                <img src="{{ upload_url($imgPath) }}" alt="Referans {{ $idx + 1 }}"
                                                     style="width:100%; height:140px; object-fit:contain; background:#fff; border-radius:4px;">
                                            </a>
                                            <div class="mt-2 d-flex align-items-center justify-content-between">
                                                <small class="text-clr-muted text-truncate" style="max-width:60%;" title="{{ $imgPath }}">
                                                    <i class="bi bi-file-image me-1"></i>{{ basename($imgPath) }}
                                                </small>
                                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-existing-image"
                                                        data-index="{{ $idx }}" title="Kaldır">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <small class="badge bg-dark mt-1">{{ $imgMime }}</small>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <small class="form-text text-clr-secondary mt-2 d-block">
                            <i class="bi bi-info-circle me-1"></i>
                            "Kaldır" butonu kart'ı işaretler — <strong>Güncelle</strong> bastığında kalıcı silinir.
                        </small>
                    </div>
                    <hr style="border-color:rgba(255,255,255,0.06);">
                @endif

                {{-- Hidden inputs: hangi index'ler silinecek (JS doldurur) --}}
                <div id="removeIndicesContainer"></div>

                {{-- Yeni görsel ekleme bölümü --}}
                <div>
                    <label class="form-label text-clr-secondary mb-2">
                        <i class="bi bi-plus-square text-teal me-1"></i>
                        @if($existingImages !== [])
                            Yeni Görsel Ekle
                        @else
                            Görselleri Yükle
                        @endif
                    </label>

                    <div id="newImagesContainer">
                        {{-- JS buraya file input'ları ekler --}}
                    </div>

                    <button type="button" class="btn-glass btn-sm" id="addImageBtn">
                        <i class="bi bi-plus-lg me-1"></i> Görsel Ekle
                    </button>

                    @error('reference_images')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('reference_images.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                    <small class="form-text text-clr-secondary mt-2 d-block">
                        <strong>JPG/PNG/WebP, her biri max 50 MB.</strong>
                        Yüklediğin dosyalar <strong>byte-for-byte</strong> <code>public/uploads/vertex-prompts/</code> altına taşınır — sıkıştırma/dönüştürme YOK.
                        Vertex API public URL olarak okur, her görsel <code>fileData</code> part'ı olarak gönderilir.
                    </small>
                </div>
            </div>
        </div>

        {{-- ── Generation Config (Üretim Ayarları) ── --}}
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-teal"><i class="bi bi-sliders2"></i></div>
                    <div>
                        <h6 class="mb-0">Üretim Ayarları</h6>
                        <small class="text-muted">Vertex AI'ın görseli üretirken kullanacağı teknik parametreler</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">

                {{-- ─── Görsel Formatı: 3 sütun (Model + Oran + Boyut) ─── --}}
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="model_preset" class="form-label text-clr-secondary">
                            <i class="bi bi-cpu text-teal me-1"></i> AI Modeli
                        </label>
                        @php $currentModel = old('model', $prompt->model ?? 'gemini-3.1-flash-image-preview'); @endphp
                        @php
                            $modelPresets = [
                                'gemini-3.1-flash-image-preview' => 'Nano Banana 2 — En yeni (HOT) · ~$0.07/görsel',
                                'gemini-3-pro-image-preview'     => 'Nano Banana Pro — En kaliteli (HOT) · ~$0.10/görsel',
                                'gemini-2.5-flash-image'         => 'Nano Banana — Stabil (GA) · ~$0.04/görsel',
                                'gemini-2.5-flash-image-preview' => 'Nano Banana Preview — Eski preview · ~$0.04/görsel',
                            ];
                            $isCustomModel = ! array_key_exists($currentModel, $modelPresets);
                        @endphp
                        <select class="form-select form-select-theme mb-2" id="model_preset">
                            <option value="">— Hızlı seçim (önerilen modeller) —</option>
                            @foreach($modelPresets as $id => $label)
                                <option value="{{ $id }}" @selected(! $isCustomModel && $currentModel === $id)>{{ $label }}</option>
                            @endforeach
                            <option value="__custom" @selected($isCustomModel)>🔧 Özel model ID gir...</option>
                        </select>
                        <input type="text" class="form-control form-control-theme" id="model" name="model"
                               value="{{ $currentModel }}" maxlength="80"
                               placeholder="örn: gemini-3.1-flash-image-preview">
                        <small class="form-text text-clr-secondary">
                            <strong>Önerilen:</strong> <code>gemini-3.1-flash-image-preview</code> (Nano Banana 2).
                            Yukarıdaki listeden hızlı seç veya kendi model ID'ni gir.
                            <span class="d-block mt-1 text-clr-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Imagen 4 / Imagen 3 / Virtual try-on gibi modeller farklı endpoint (<code>:predict</code>) kullanır — bu modülde çalışmaz, sadece <strong>Gemini image</strong> (Nano Banana) modelleri desteklenir.
                            </span>
                        </small>
                    </div>

                    <div class="col-md-3">
                        <label for="aspect_ratio" class="form-label text-clr-secondary">
                            <i class="bi bi-aspect-ratio text-teal me-1"></i> En-Boy Oranı
                        </label>
                        <select class="form-select form-select-theme" id="aspect_ratio" name="aspect_ratio">
                            @php $ar = old('aspect_ratio', $prompt->aspect_ratio ?? '1:1'); @endphp
                            <option value="1:1"  @selected($ar === '1:1')>1:1 — Kare (Instagram Post)</option>
                            <option value="4:5"  @selected($ar === '4:5')>4:5 — Dikey (Instagram Post)</option>
                            <option value="5:4"  @selected($ar === '5:4')>5:4 — Yatay (Instagram Post)</option>
                            <option value="9:16" @selected($ar === '9:16')>9:16 — Dikey (Story / Reels)</option>
                            <option value="16:9" @selected($ar === '16:9')>16:9 — Yatay (YouTube / Web)</option>
                            <option value="3:2"  @selected($ar === '3:2')>3:2 — Yatay (Fotoğraf Klasik)</option>
                            <option value="2:3"  @selected($ar === '2:3')>2:3 — Dikey (Fotoğraf Klasik)</option>
                            <option value="4:3"  @selected($ar === '4:3')>4:3 — Klasik Yatay</option>
                            <option value="3:4"  @selected($ar === '3:4')>3:4 — Klasik Dikey</option>
                        </select>
                        <small class="form-text text-clr-secondary">Görselin şekli — nerede kullanacağına göre seç.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="image_size" class="form-label text-clr-secondary">
                            <i class="bi bi-arrows-fullscreen text-teal me-1"></i> Çözünürlük
                        </label>
                        <select class="form-select form-select-theme" id="image_size" name="image_size">
                            @php $is = old('image_size', $prompt->image_size ?? '1K'); @endphp
                            <option value="1K" @selected($is === '1K')>1K — Standart (~1024px, hızlı)</option>
                            <option value="2K" @selected($is === '2K')>2K — Yüksek (~2048px, kaliteli)</option>
                        </select>
                        <small class="form-text text-clr-secondary">
                            <strong>1K:</strong> hızlı + ucuz, web/sosyal için yeterli.
                            <strong>2K:</strong> baskı/zoom için, daha yavaş.
                        </small>
                    </div>
                </div>

                <hr class="my-4" style="border-color:rgba(255,255,255,0.06);">

                {{-- ─── Dosya & Yaratıcılık: 2 sütun ─── --}}
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="mime_type" class="form-label text-clr-secondary">
                            <i class="bi bi-file-earmark-image text-teal me-1"></i> Dosya Formatı
                        </label>
                        <select class="form-select form-select-theme" id="mime_type" name="mime_type">
                            @php $mt = old('mime_type', $prompt->mime_type ?? 'image/png'); @endphp
                            <option value="image/png"  @selected($mt === 'image/png')>PNG — Kayıpsız, şeffaflık destekli</option>
                            <option value="image/jpeg" @selected($mt === 'image/jpeg')>JPEG — Küçük dosya, fotoğraf için ideal</option>
                            <option value="image/webp" @selected($mt === 'image/webp')>WebP — Modern, küçük + kaliteli</option>
                        </select>
                        <small class="form-text text-clr-secondary">Görselin kaydedileceği dosya tipi.</small>
                    </div>

                    <div class="col-md-4">
                        <label for="temperature" class="form-label text-clr-secondary">
                            <i class="bi bi-thermometer-half text-teal me-1"></i> Yaratıcılık
                            <small class="text-clr-muted">(Temperature)</small>
                        </label>
                        <input type="number" step="0.01" min="0" max="2" class="form-control form-control-theme"
                               id="temperature" name="temperature" value="{{ old('temperature', $prompt->temperature ?? '1.00') }}">
                        <small class="form-text text-clr-secondary">
                            <strong>0:</strong> her seferinde aynı sonuç (tahmin edilebilir).
                            <strong>1:</strong> dengeli (önerilen).
                            <strong>2:</strong> çok yaratıcı/sürprizli.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label for="top_p" class="form-label text-clr-secondary">
                            <i class="bi bi-shuffle text-teal me-1"></i> Çeşitlilik
                            <small class="text-clr-muted">(Top P)</small>
                        </label>
                        <input type="number" step="0.01" min="0" max="1" class="form-control form-control-theme"
                               id="top_p" name="top_p" value="{{ old('top_p', $prompt->top_p ?? '0.95') }}">
                        <small class="form-text text-clr-secondary">
                            AI'ın kelime/öğe seçim aralığı. <strong>0.95</strong> önerilir.
                            Düşürürsen daha tutarlı, yükseltirsen daha geniş çeşitlilik.
                        </small>
                    </div>
                </div>

                <hr class="my-4" style="border-color:rgba(255,255,255,0.06);">

                {{-- ─── Performans & İçerik: 3 sütun ─── --}}
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="thinking_level" class="form-label text-clr-secondary">
                            <i class="bi bi-lightbulb text-teal me-1"></i> Düşünme Seviyesi
                        </label>
                        <select class="form-select form-select-theme" id="thinking_level" name="thinking_level">
                            @php $tl = old('thinking_level', $prompt->thinking_level ?? 'MINIMAL'); @endphp
                            <option value="MINIMAL" @selected($tl === 'MINIMAL')>MINIMAL — Hızlı, ucuz (önerilen)</option>
                            <option value="LOW"     @selected($tl === 'LOW')>LOW — Az düşünür</option>
                            <option value="MEDIUM"  @selected($tl === 'MEDIUM')>MEDIUM — Orta detay</option>
                            <option value="HIGH"    @selected($tl === 'HIGH')>HIGH — Yavaş, en kaliteli</option>
                        </select>
                        <small class="form-text text-clr-secondary">
                            AI'ın görseli üretmeden önce ne kadar "kafa yoracağı".
                            Yükseldikçe kalite artar ama süre ve maliyet de artar.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label for="person_generation" class="form-label text-clr-secondary">
                            <i class="bi bi-people text-teal me-1"></i> İnsan İçerik İzni
                        </label>
                        <select class="form-select form-select-theme" id="person_generation" name="person_generation">
                            @php $pg = old('person_generation', $prompt->person_generation ?? 'ALLOW_ALL'); @endphp
                            <option value="ALLOW_ALL"   @selected($pg === 'ALLOW_ALL')>Hepsine İzin Ver (yetişkin + çocuk)</option>
                            <option value="ALLOW_ADULT" @selected($pg === 'ALLOW_ADULT')>Sadece Yetişkin</option>
                            <option value="BLOCK_ALL"   @selected($pg === 'BLOCK_ALL')>İnsan Üretme</option>
                        </select>
                        <small class="form-text text-clr-secondary">
                            Görselde insan figürü olup olmayacağı.
                            <strong>Hepsi:</strong> aile/çocuk sahneleri için.
                            <strong>İnsan Üretme:</strong> sadece ürün/manzara.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label for="max_output_tokens" class="form-label text-clr-secondary">
                            <i class="bi bi-bar-chart text-teal me-1"></i> Maksimum Token
                        </label>
                        <input type="number" min="256" max="65536" class="form-control form-control-theme"
                               id="max_output_tokens" name="max_output_tokens" value="{{ old('max_output_tokens', $prompt->max_output_tokens ?? 32768) }}">
                        <small class="form-text text-clr-secondary">
                            AI'ın üretebileceği maksimum veri boyutu.
                            <strong>32768 çoğu durumda yeterli.</strong> Değiştirmen gerekmez.
                        </small>
                    </div>
                </div>

                <hr class="my-4" style="border-color:rgba(255,255,255,0.06);">

                {{-- ─── Güvenlik Switch (full width) ─── --}}
                <div class="alert alert-warning d-flex align-items-start gap-3 mb-0" style="background:rgba(245,158,11,0.08); border-color:rgba(245,158,11,0.3);">
                    <i class="bi bi-shield-exclamation fs-3 text-warning"></i>
                    <div class="flex-grow-1">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="safety_off" name="safety_off" value="1"
                                   {{ old('safety_off', $prompt->safety_off ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label text-clr-secondary fw-semibold" for="safety_off">
                                Güvenlik Filtrelerini Kapat
                            </label>
                        </div>
                        <small class="text-clr-secondary d-block">
                            <strong>Açık (önerilen):</strong> Google'ın nefret söylemi, şiddet, cinsellik ve taciz filtreleri devre dışı —
                            ürün/yemek/peyzaj fotoğrafı üretiminde "false positive" engellemeleri önler.<br>
                            <strong>Kapalı:</strong> Google'ın varsayılan filtreleri açık kalır, normal kullanım için bu da yeterli.<br>
                            <em class="text-clr-muted">Teknik karşılık: <code>safetySettings.threshold = OFF</code> tüm kategorilerde (HARM_CATEGORY_*).</em>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-teal">
                <i class="bi bi-check-lg me-1"></i> {{ $mode === 'edit' ? 'Güncelle' : 'Kaydet' }}
            </button>
            <a href="{{ route('admin.vertex.prompts.index') }}" class="btn-glass">Vazgeç</a>
        </div>
    </form>

    @if($mode === 'edit')
        {{-- Version History Overlay --}}
        <div class="vpv-overlay" id="vpvOverlay">
            <div class="vpv-panel">
                <div class="vpv-header">
                    <h5 class="vpv-title"><i class="bi bi-clock-history me-2"></i>Versiyon Geçmişi</h5>
                    <button type="button" class="vpv-close" id="vpvClose"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="vpv-body" id="vpvBody">
                    <div class="vpv-loading"><div class="spinner-border text-teal" role="status"></div></div>
                </div>
            </div>
        </div>

        {{-- Version Detail Overlay --}}
        <div class="vpv-overlay" id="vpvDetailOverlay">
            <div class="vpv-panel vpv-panel-wide">
                <div class="vpv-header">
                    <h5 class="vpv-title" id="vpvDetailTitle"><i class="bi bi-file-diff me-2"></i>Versiyon Detayı</h5>
                    <button type="button" class="vpv-close" id="vpvDetailClose"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="vpv-body" id="vpvDetailBody">
                    <div class="vpv-loading"><div class="spinner-border text-teal" role="status"></div></div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    // ── Mevcut görselleri kaldırma (edit modu) ──
    var removeContainer = document.getElementById('removeIndicesContainer');
    document.querySelectorAll('.js-remove-existing-image').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idx = btn.dataset.index;
            if (! idx) return;
            // Hidden input ekle: name="reference_images_remove[]" value="{idx}"
            if (! document.querySelector('input[name="reference_images_remove[]"][value="' + idx + '"]')) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'reference_images_remove[]';
                hidden.value = idx;
                removeContainer.appendChild(hidden);
            }
            // Kartı görsel olarak işaretle (silinecek görünümü)
            var card = document.querySelector('[data-existing-image-card="' + idx + '"]');
            if (card) {
                card.style.opacity = '0.35';
                card.style.outline = '2px solid #dc3545';
                btn.outerHTML = '<span class="badge bg-danger"><i class="bi bi-trash-fill me-1"></i>Silinecek</span>';
            }
        });
    });

    // ── Yeni görsel input'ları dinamik olarak ekle ──
    var addBtn = document.getElementById('addImageBtn');
    var newContainer = document.getElementById('newImagesContainer');
    var maxNewInputs = 10;
    var newInputCount = 0;

    function addNewImageInput() {
        if (newInputCount >= maxNewInputs) {
            if (window.AdminModal && AdminModal.status) {
                AdminModal.status({ title: 'Limit', message: 'En fazla 10 görsel ekleyebilirsin.', type: 'warning' });
            }
            return;
        }
        var idx = newInputCount++;
        var wrap = document.createElement('div');
        wrap.className = 'd-flex align-items-center gap-2 mb-2';
        wrap.dataset.newImageRow = idx;
        wrap.innerHTML =
            '<input type="file" class="form-control form-control-theme flex-grow-1" ' +
            'name="reference_images[]" accept="image/jpeg,image/png,image/webp">' +
            '<button type="button" class="btn-glass btn-sm js-remove-new-input" title="Bu satırı kaldır">' +
                '<i class="bi bi-x-lg"></i>' +
            '</button>';
        newContainer.appendChild(wrap);

        wrap.querySelector('.js-remove-new-input').addEventListener('click', function () {
            wrap.remove();
            newInputCount--;
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', addNewImageInput);
        // Yeni şablon oluştururken otomatik 1 input açık olsun
        @if($mode === 'create' && $existingImages === [])
            addNewImageInput();
        @endif
    }

    // Prompt karakter sayacı
    var promptEl = document.getElementById('prompt');
    var counterEl = document.getElementById('promptCounter');
    if (promptEl && counterEl) {
        var update = function () {
            counterEl.textContent = promptEl.value.length.toLocaleString('tr-TR');
            counterEl.classList.remove('text-warning', 'text-danger', 'fw-bold');
            if (promptEl.value.length > 19000) counterEl.classList.add('text-danger', 'fw-bold');
            else if (promptEl.value.length > 16000) counterEl.classList.add('text-warning');
        };
        promptEl.addEventListener('input', update);
        update();
    }

    // ── Rastgele Seçenek Listeleri (random_options) ──
    var randomCard = document.getElementById('randomOptionsCard');
    var randomContainer = document.getElementById('randomOptionsContainer');
    var randomEmpty = document.getElementById('randomOptionsEmpty');
    var VAR_REGEX = /\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/g;

    @php
        $existingRandomOptions = old('random_options') ?: (is_array($prompt->random_options) ? $prompt->random_options : []);
        $randomJson = json_encode($existingRandomOptions, JSON_UNESCAPED_UNICODE);
    @endphp
    var savedRandomOptions = {!! $randomJson !!};

    function parseVarsFromPrompt(text) {
        var seen = {};
        var list = [];
        var m;
        VAR_REGEX.lastIndex = 0;
        while ((m = VAR_REGEX.exec(text)) !== null) {
            if (! seen[m[1]]) {
                seen[m[1]] = true;
                list.push(m[1]);
            }
        }
        return list;
    }

    function humanizeVar(name) {
        var s = name.replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase();
        return s.split('_').filter(function (p) { return p !== ''; })
            .map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); })
            .join(' ');
    }

    function syncRandomOptions() {
        if (! randomCard || ! randomContainer || ! promptEl) return;
        var vars = parseVarsFromPrompt(promptEl.value);

        if (vars.length === 0) {
            randomCard.classList.add('d-none');
            return;
        }

        randomCard.classList.remove('d-none');

        // Store current values before clearing
        var current = {};
        randomContainer.querySelectorAll('textarea[data-ro-var]').forEach(function (ta) {
            current[ta.dataset.roVar] = ta.value;
        });

        randomContainer.innerHTML = '';

        vars.forEach(function (name) {
            var existing = current[name] !== undefined
                ? current[name]
                : (savedRandomOptions[name]
                    ? (Array.isArray(savedRandomOptions[name]) ? savedRandomOptions[name].join('\n') : String(savedRandomOptions[name]))
                    : '');

            var wrap = document.createElement('div');
            wrap.className = 'mb-3';

            var lbl = document.createElement('label');
            lbl.className = 'form-label text-clr-secondary';
            lbl.innerHTML = '<i class="bi bi-shuffle text-orange me-1"></i>' +
                humanizeVar(name) + ' <code class="text-clr-muted ms-1">{{' + name + '}}</code>';

            var ta = document.createElement('textarea');
            ta.className = 'form-control form-control-theme';
            ta.name = 'random_options[' + name + ']';
            ta.rows = 4;
            ta.placeholder = 'Her satıra bir seçenek yaz...\nÖrn:\nBuzağı Bakımı\nSüt Kalitesi\nPeynir Yapımı';
            ta.dataset.roVar = name;
            ta.value = existing;

            var hint = document.createElement('small');
            hint.className = 'form-text text-clr-muted';
            var lineCount = existing.split('\n').filter(function (l) { return l.trim() !== ''; }).length;
            hint.textContent = lineCount > 0 ? lineCount + ' seçenek tanımlı' : 'Henüz seçenek yok — boş bırakırsan manuel giriş istenecek';

            ta.addEventListener('input', function () {
                var c = ta.value.split('\n').filter(function (l) { return l.trim() !== ''; }).length;
                hint.textContent = c > 0 ? c + ' seçenek tanımlı' : 'Henüz seçenek yok — boş bırakırsan manuel giriş istenecek';
            });

            wrap.appendChild(lbl);
            wrap.appendChild(ta);
            wrap.appendChild(hint);
            randomContainer.appendChild(wrap);
        });
    }

    if (promptEl) {
        promptEl.addEventListener('input', syncRandomOptions);
        syncRandomOptions();
    }

    // Model preset → text input senkronizasyonu
    var presetEl = document.getElementById('model_preset');
    var modelEl = document.getElementById('model');
    if (presetEl && modelEl) {
        presetEl.addEventListener('change', function () {
            var val = presetEl.value;
            if (val === '') {
                return; // başlık option seçildi, hiçbir şey yapma
            }
            if (val === '__custom') {
                modelEl.value = '';
                modelEl.focus();
                return;
            }
            modelEl.value = val;
        });

        // Kullanıcı text input'a manuel yazınca preset'i "Özel"e çevir
        modelEl.addEventListener('input', function () {
            var val = modelEl.value.trim();
            var found = false;
            for (var i = 0; i < presetEl.options.length; i++) {
                if (presetEl.options[i].value === val) {
                    presetEl.value = val;
                    found = true;
                    break;
                }
            }
            if (! found) {
                presetEl.value = '__custom';
            }
        });
    }
})();
</script>
@if($mode === 'edit')
<script>
(function () {
    var versionsBtn = document.getElementById('vtxVersionsBtn');
    var overlay = document.getElementById('vpvOverlay');
    var closeBtn = document.getElementById('vpvClose');
    var body = document.getElementById('vpvBody');

    var detailOverlay = document.getElementById('vpvDetailOverlay');
    var detailClose = document.getElementById('vpvDetailClose');
    var detailTitle = document.getElementById('vpvDetailTitle');
    var detailBody = document.getElementById('vpvDetailBody');

    var versionsUrl = '{{ route("admin.vertex.prompts.versions", $prompt) }}';
    var csrfToken = '{{ csrf_token() }}';

    if (!versionsBtn) return;

    versionsBtn.addEventListener('click', function () {
        overlay.classList.add('vpv-open');
        document.body.classList.add('overflow-hidden');
        loadVersions();
    });

    closeBtn.addEventListener('click', closeList);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeList(); });

    detailClose.addEventListener('click', closeDetail);
    detailOverlay.addEventListener('click', function (e) { if (e.target === detailOverlay) closeDetail(); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (detailOverlay.classList.contains('vpv-open')) closeDetail();
            else if (overlay.classList.contains('vpv-open')) closeList();
        }
    });

    function closeList() {
        overlay.classList.remove('vpv-open');
        document.body.classList.remove('overflow-hidden');
    }

    function closeDetail() {
        detailOverlay.classList.remove('vpv-open');
        if (!overlay.classList.contains('vpv-open')) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    function loadVersions() {
        body.innerHTML = '<div class="vpv-loading"><div class="spinner-border text-teal" role="status"></div></div>';

        fetch(versionsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (txt) {
                        throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 200));
                    });
                }
                return r.json();
            })
            .then(function (data) {
                if (data.error) {
                    body.innerHTML = '<div class="vpv-empty text-danger"><i class="bi bi-exclamation-triangle"></i><p>Hata: ' + escHtml(data.error) + '</p></div>';
                    return;
                }
                if (!data.versions || data.versions.length === 0) {
                    body.innerHTML = '<div class="vpv-empty"><i class="bi bi-inbox"></i><p>Henüz versiyon geçmişi yok.</p></div>';
                    return;
                }
                body.innerHTML = buildVersionList(data.versions);
            })
            .catch(function (err) {
                console.error('Version load error:', err);
                body.innerHTML = '<div class="vpv-empty text-danger"><i class="bi bi-exclamation-triangle"></i><p>Yüklenemedi.</p><pre class="small text-start mt-2 mx-3" style="max-height:200px;overflow:auto;font-size:11px;opacity:.7">' + escHtml(String(err)) + '</pre></div>';
            });
    }

    function buildVersionList(versions) {
        var html = '<div class="vpv-timeline">';
        for (var i = 0; i < versions.length; i++) {
            var v = versions[i];
            var changesHtml = '';
            if (v.changes && v.changes.length > 0) {
                changesHtml = '<div class="vpv-changes">';
                for (var c = 0; c < v.changes.length; c++) {
                    changesHtml += '<span class="vpv-change-badge">' + escHtml(v.changes[c]) + '</span>';
                }
                changesHtml += '</div>';
            }
            html += '<div class="vpv-item" data-version-id="' + v.id + '">';
            html += '<div class="vpv-item-dot"></div>';
            html += '<div class="vpv-item-content">';
            html += '<div class="vpv-item-header">';
            html += '<span class="vpv-item-number">v' + v.version_number + '</span>';
            html += '<span class="vpv-item-date">' + escHtml(v.created_at) + '</span>';
            html += '</div>';
            html += '<div class="vpv-item-meta">';
            html += '<span class="vpv-item-author"><i class="bi bi-person me-1"></i>' + escHtml(v.author) + '</span>';
            html += changesHtml;
            html += '</div>';
            html += '<div class="vpv-item-actions">';
            html += '<button type="button" class="vpv-btn vpv-btn-view" data-version-id="' + v.id + '"><i class="bi bi-eye me-1"></i>Detay</button>';
            html += '<button type="button" class="vpv-btn vpv-btn-restore" data-version-id="' + v.id + '" data-version-num="' + v.version_number + '"><i class="bi bi-arrow-counterclockwise me-1"></i>Geri Yükle</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    body.addEventListener('click', function (e) {
        var viewBtn = e.target.closest('.vpv-btn-view');
        if (viewBtn) {
            loadVersionDetail(viewBtn.dataset.versionId);
            return;
        }

        var restoreBtn = e.target.closest('.vpv-btn-restore');
        if (restoreBtn) {
            handleRestore(restoreBtn.dataset.versionId, restoreBtn.dataset.versionNum);
        }
    });

    detailBody.addEventListener('click', function (e) {
        var restoreBtn = e.target.closest('.vpv-btn-restore');
        if (restoreBtn) {
            handleRestore(restoreBtn.dataset.versionId, restoreBtn.dataset.versionNum);
        }
    });

    function loadVersionDetail(versionId) {
        detailOverlay.classList.add('vpv-open');
        detailBody.innerHTML = '<div class="vpv-loading"><div class="spinner-border text-teal" role="status"></div></div>';

        fetch(versionsUrl + '/' + versionId, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (txt) {
                        throw new Error('HTTP ' + r.status);
                    });
                }
                return r.json();
            })
            .then(function (v) {
                detailTitle.innerHTML = '<i class="bi bi-file-diff me-2"></i>Versiyon #' + v.version_number;
                detailBody.innerHTML = buildVersionDetail(v);
            })
            .catch(function () {
                detailBody.innerHTML = '<div class="vpv-empty text-danger"><i class="bi bi-exclamation-triangle"></i><p>Yüklenemedi.</p></div>';
            });
    }

    function buildVersionDetail(v) {
        var d = v.data;
        var html = '<div class="vpv-detail-meta">';
        html += '<span><i class="bi bi-person me-1"></i>' + escHtml(v.author) + '</span>';
        html += '<span><i class="bi bi-calendar3 me-1"></i>' + escHtml(v.created_at) + '</span>';
        html += '</div>';

        html += '<div class="vpv-detail-grid">';
        html += detailField('Ad', d.name);
        html += detailField('Açıklama', d.description);
        html += detailField('Model', d.model);
        html += detailField('Oran', d.aspect_ratio);
        html += detailField('Çözünürlük', d.image_size);
        html += detailField('Format', d.mime_type);
        html += detailField('Yaratıcılık', d.temperature);
        html += detailField('Top P', d.top_p);
        html += detailField('Düşünme', d.thinking_level);
        html += detailField('İnsan İzni', d.person_generation);
        html += detailField('Güvenlik Kapalı', d.safety_off ? 'Evet' : 'Hayır');
        html += detailField('Aktif', d.is_active ? 'Evet' : 'Hayır');
        html += '</div>';

        if (d.prompt) {
            html += '<div class="vpv-detail-section">';
            html += '<div class="vpv-detail-label"><i class="bi bi-chat-square-text me-1"></i>Prompt</div>';
            html += '<pre class="vpv-detail-pre">' + escHtml(d.prompt) + '</pre>';
            html += '</div>';
        }

        if (d.ig_title_template) {
            html += '<div class="vpv-detail-section">';
            html += '<div class="vpv-detail-label"><i class="bi bi-type-bold me-1"></i>IG Başlık</div>';
            html += '<div class="vpv-detail-text">' + escHtml(d.ig_title_template) + '</div>';
            html += '</div>';
        }

        if (d.ig_caption_template) {
            html += '<div class="vpv-detail-section">';
            html += '<div class="vpv-detail-label"><i class="bi bi-chat-quote me-1"></i>IG Caption</div>';
            html += '<pre class="vpv-detail-pre">' + escHtml(d.ig_caption_template) + '</pre>';
            html += '</div>';
        }

        if (d.ig_hashtags_template) {
            html += '<div class="vpv-detail-section">';
            html += '<div class="vpv-detail-label"><i class="bi bi-hash me-1"></i>IG Hashtag</div>';
            html += '<div class="vpv-detail-text">' + escHtml(d.ig_hashtags_template) + '</div>';
            html += '</div>';
        }

        if (d.tags && d.tags.length) {
            html += '<div class="vpv-detail-section">';
            html += '<div class="vpv-detail-label"><i class="bi bi-tags me-1"></i>Etiketler</div>';
            html += '<div class="vpv-detail-text">';
            for (var t = 0; t < d.tags.length; t++) {
                html += '<span class="vpv-change-badge">' + escHtml(d.tags[t]) + '</span>';
            }
            html += '</div>';
            html += '</div>';
        }

        if (d.reference_images && d.reference_images.length) {
            html += '<div class="vpv-detail-section">';
            html += '<div class="vpv-detail-label"><i class="bi bi-images me-1"></i>Referans Görseller (' + d.reference_images.length + ')</div>';
            html += '<div class="vpv-ref-grid">';
            for (var ri = 0; ri < d.reference_images.length; ri++) {
                var img = d.reference_images[ri];
                if (img.path) {
                    html += '<div class="vpv-ref-item">';
                    html += '<img src="/uploads/' + escHtml(img.path) + '" alt="Ref ' + (ri + 1) + '" loading="lazy">';
                    html += '<small>' + escHtml(img.mime || '') + '</small>';
                    html += '</div>';
                }
            }
            html += '</div>';
            html += '</div>';
        }

        html += '<div class="vpv-detail-footer">';
        html += '<button type="button" class="vpv-btn vpv-btn-restore" data-version-id="' + v.id + '" data-version-num="' + v.version_number + '">';
        html += '<i class="bi bi-arrow-counterclockwise me-1"></i>Bu Versiyonu Geri Yükle</button>';
        html += '</div>';

        return html;
    }

    function detailField(label, value) {
        return '<div class="vpv-detail-field">'
            + '<span class="vpv-detail-field-label">' + escHtml(label) + '</span>'
            + '<span class="vpv-detail-field-value">' + escHtml(String(value || '-')) + '</span>'
            + '</div>';
    }

    function handleRestore(versionId, versionNum) {
        AdminModal.confirm({
            title: 'Versiyonu Geri Yükle',
            message: 'Versiyon #' + versionNum + ' geri yüklenecek. Mevcut durum otomatik olarak yeni bir versiyon olarak kaydedilir. Devam etmek istiyor musun?',
            confirmText: 'Geri Yükle',
            type: 'warning',
        }).then(function (confirmed) {
            if (!confirmed) return;

            fetch(versionsUrl + '/' + versionId + '/restore', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    AdminModal.status({
                        title: 'Geri Yüklendi',
                        message: data.message,
                        type: 'success',
                    }).then(function () { location.reload(); });
                } else {
                    AdminModal.status({ title: 'Hata', message: data.message || 'Bir hata oluştu.', type: 'danger' });
                }
            })
            .catch(function () {
                AdminModal.status({ title: 'Hata', message: 'Bağlantı hatası.', type: 'danger' });
            });
        });
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
</script>
@endif
@endpush
