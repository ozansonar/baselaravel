@extends('layouts.admin')

@section('title', 'Görsel Düzenle — Kütüphane')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-pencil-square text-warning me-2"></i> Görseli Düzenle
            </h2>
            <small class="text-muted">{{ $image->mediaTypeLabel() }} · {{ $image->usage_count }} kez kullanıldı</small>
        </div>
        <a href="{{ route('admin.image-library.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form method="POST" action="{{ route('admin.image-library.update', $image->id) }}" id="ilEditForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="ai_generated_tags" id="aiGeneratedTagsFlag" value="{{ $image->ai_generated_tags ? '1' : '0' }}">

        <div class="row g-4">
            <div class="col-md-5">
                <div class="card-dark">
                    <div class="card-body-custom text-center">
                        <img src="{{ upload_url($image->image_path, 'lg') }}" alt="{{ $image->alt_text ?? '' }}"
                             class="img-fluid rounded" style="max-height: 500px;">
                        <div class="mt-3 small text-muted">
                            @if($image->last_used_at)
                                Son kullanım: {{ $image->last_used_at->diffForHumans() }}
                            @else
                                <span class="text-success"><i class="bi bi-check-circle"></i> Hiç kullanılmadı</span>
                            @endif
                            @if($image->ai_generated_tags)
                                · <span class="text-info"><i class="bi bi-stars"></i> AI etiketli</span>
                            @endif
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn-glass btn-glass-sm" id="ilAutoTagBtn">
                                <i class="bi bi-stars me-1"></i>
                                <span data-default>AI ile Otomatik Tag'le</span>
                                <span data-loading class="d-none"><i class="bi bi-hourglass-split me-1"></i> Analiz ediliyor...</span>
                            </button>
                            <small class="d-block text-muted mt-1" style="font-size: 0.7rem;">
                                Maliyet: ~$0.00015 (Gemini Vision)
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card-dark">
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Medya Tipi <span class="text-danger">*</span></label>
                                <select name="media_type" class="form-select form-control-theme" required>
                                    @foreach(\App\Models\MediaLibraryImage::MEDIA_TYPES as $t)
                                        <option value="{{ $t }}" @selected(old('media_type', $image->media_type) === $t)>{{ ucfirst($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tema</label>
                                <select name="theme" class="form-select form-control-theme">
                                    <option value="">— yok —</option>
                                    @foreach(\App\Models\MediaLibraryImage::THEMES as $th)
                                        <option value="{{ $th }}" @selected(old('theme', $image->theme) === $th)>{{ (new \App\Models\MediaLibraryImage(['theme' => $th]))->themeLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mood</label>
                                <select name="mood" class="form-select form-control-theme">
                                    <option value="">— yok —</option>
                                    @foreach(\App\Models\MediaLibraryImage::MOODS as $m)
                                        <option value="{{ $m }}" @selected(old('mood', $image->mood) === $m)>{{ ucfirst($m) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mevsim</label>
                                <select name="season" class="form-select form-control-theme">
                                    <option value="">— yok —</option>
                                    @foreach(\App\Models\MediaLibraryImage::SEASONS as $s)
                                        <option value="{{ $s }}" @selected(old('season', $image->season) === $s)>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Tag'ler (virgülle)</label>
                                <input type="text" name="tags" class="form-control form-control-theme"
                                       value="{{ old('tags', $image->tagListString()) }}"
                                       maxlength="500" placeholder="süt, sabah, sağım, ...">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Açıklama</label>
                                <textarea name="description" class="form-control form-control-theme" rows="3" maxlength="2000">{{ old('description', $image->description) }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Görünürlük</label>
                                <select name="visibility" class="form-select form-control-theme">
                                    <option value="private" @selected(old('visibility', $image->visibility) === 'private')>🔒 Sadece Bulk</option>
                                    <option value="public"  @selected(old('visibility', $image->visibility) === 'public')>🌐 Halka Açık</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Alt Text</label>
                                <input type="text" name="alt_text" class="form-control form-control-theme"
                                       value="{{ old('alt_text', $image->alt_text) }}" maxlength="250">
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2 justify-content-end">
                            <a href="{{ route('admin.image-library.index') }}" class="btn-glass">İptal</a>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i> Güncelle
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Benzer Görseller --}}
                @if($similar->isNotEmpty())
                    <div class="card-dark mt-3">
                        <div class="card-header-custom">
                            <div class="form-section-header mb-0">
                                <div class="form-section-icon bg-icon-purple"><i class="bi bi-collection"></i></div>
                                <div>
                                    <h6 class="mb-0">Benzer Görseller</h6>
                                    <small class="text-muted">Tag/tema/mood eşleşmesine göre — bu görsele yakın olan {{ $similar->count() }} öğe</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="il-similar-grid">
                                @foreach($similar as $sim)
                                    <a href="{{ route('admin.image-library.edit', $sim->id) }}" class="il-similar-card" title="Skor: {{ $sim->similarity_score }}">
                                        <img src="{{ upload_url($sim->image_path, 'thumb') }}" loading="lazy">
                                        <span class="il-similar-score">{{ $sim->similarity_score }}</span>
                                        @if($sim->is_video)
                                            <span class="il-similar-video"><i class="bi bi-play-fill"></i></span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Kullanım Geçmişi --}}
                <div class="card-dark mt-3">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-blue"><i class="bi bi-clock-history"></i></div>
                            <div>
                                <h6 class="mb-0">Kullanım Geçmişi</h6>
                                <small class="text-muted">
                                    @if($usageHistory->isEmpty())
                                        Henüz hiçbir post'ta kullanılmadı
                                    @else
                                        {{ $usageHistory->count() }} post'ta kullanıldı
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom p-0">
                        @if($usageHistory->isEmpty())
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clock-history display-4 d-block mb-2 opacity-25"></i>
                                <small>Bu görsel henüz hiçbir Instagram post'unda kullanılmadı.<br>
                                Bulk import sırasında otomatik seçildiğinde burada listelenecek.</small>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="color:#e5e7eb;">
                                    <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th style="width:90px;">Tip</th>
                                            <th>Caption</th>
                                            <th style="width:140px;">Planlanan</th>
                                            <th style="width:90px;">Durum</th>
                                            <th style="width:60px;" class="text-end"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($usageHistory as $post)
                                            <tr>
                                                <td><code>#{{ $post->id }}</code></td>
                                                <td>
                                                    @php
                                                        $typeStr = $post->media_type instanceof \App\Enums\InstagramMediaType
                                                            ? $post->media_type->value
                                                            : (string) $post->media_type;
                                                    @endphp
                                                    <span class="badge bg-secondary">{{ ucfirst($typeStr) }}</span>
                                                </td>
                                                <td><small>{{ Str::limit($post->caption ?? '—', 60) }}</small></td>
                                                <td>
                                                    <small>
                                                        {{ optional($post->scheduled_at)->format('d.m.Y H:i') ?? '—' }}
                                                    </small>
                                                </td>
                                                <td>
                                                    @php
                                                        $statusStr = $post->status instanceof \App\Enums\InstagramPostStatus
                                                            ? $post->status->value
                                                            : (string) $post->status;
                                                        $statusClass = match($statusStr) {
                                                            'published' => 'success',
                                                            'failed'    => 'danger',
                                                            'scheduled' => 'info',
                                                            'draft'     => 'secondary',
                                                            default     => 'secondary',
                                                        };
                                                    @endphp
                                                    <span class="badge bg-{{ $statusClass }}">{{ $statusStr }}</span>
                                                </td>
                                                <td class="text-end">
                                                    @if(Route::has('admin.instagram-posts.edit'))
                                                        <a href="{{ route('admin.instagram-posts.edit', $post->id) }}"
                                                           class="btn-glass btn-glass-sm" target="_blank" title="Post'u aç">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
.il-similar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 8px;
}
.il-similar-card {
    position: relative;
    aspect-ratio: 1 / 1;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.08);
    transition: all 0.15s;
    display: block;
}
.il-similar-card:hover { border-color: rgba(245,158,11,0.5); transform: translateY(-2px); }
.il-similar-card img { width: 100%; height: 100%; object-fit: cover; }
.il-similar-score {
    position: absolute; top: 4px; right: 4px;
    background: rgba(245,158,11,0.85); color: #000;
    padding: 1px 6px; border-radius: 8px;
    font-size: 0.7rem; font-weight: 700;
}
.il-similar-video {
    position: absolute; bottom: 4px; left: 4px;
    background: rgba(0,0,0,0.7); color: #fff;
    padding: 2px 6px; border-radius: 8px;
    font-size: 0.7rem;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    var btn = document.getElementById('ilAutoTagBtn');
    if (! btn) return;

    btn.addEventListener('click', function () {
        var defaultLbl = btn.querySelector('[data-default]');
        var loadLbl    = btn.querySelector('[data-loading]');
        btn.disabled = true;
        if (defaultLbl) defaultLbl.classList.add('d-none');
        if (loadLbl) loadLbl.classList.remove('d-none');

        fetch('{{ route('admin.image-library.auto-tag', $image->id) }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (r) {
            if (! r.ok || ! r.data.success) {
                alert('AI hatası: ' + (r.data.message || 'bilinmeyen'));
                return;
            }
            // Form alanlarına AI önerilerini doldur
            var f = document.getElementById('ilEditForm');
            if (r.data.tags && r.data.tags.length) {
                f.querySelector('[name="tags"]').value = r.data.tags.join(', ');
            }
            if (r.data.theme) f.querySelector('[name="theme"]').value = r.data.theme;
            if (r.data.mood)  f.querySelector('[name="mood"]').value  = r.data.mood;
            if (r.data.season) f.querySelector('[name="season"]').value = r.data.season;
            if (r.data.description) f.querySelector('[name="description"]').value = r.data.description;
            if (r.data.alt_text) f.querySelector('[name="alt_text"]').value = r.data.alt_text;

            // AI dolduğu işaretle (kullanıcı kaydedince DB'ye yazılır)
            var aiFlag = document.getElementById('aiGeneratedTagsFlag');
            if (aiFlag) aiFlag.value = '1';

            // Kullanıcıya bilgilendirme
            var msg = '✓ AI önerileri form alanlarına yerleştirildi.\n\n' +
                      'Tag: ' + ((r.data.tags || []).join(', ') || '—') + '\n' +
                      'Tema: ' + (r.data.theme || '—') + '\n' +
                      'Mood: ' + (r.data.mood || '—') + '\n' +
                      'Mevsim: ' + (r.data.season || '—') + '\n\n' +
                      'Beğenmediğin alanları manuel düzeltebilir, sonra "Güncelle" ile kaydet.';
            alert(msg);
        })
        .catch(function (e) {
            alert('İstek başarısız: ' + e.message);
        })
        .finally(function () {
            btn.disabled = false;
            if (defaultLbl) defaultLbl.classList.remove('d-none');
            if (loadLbl) loadLbl.classList.add('d-none');
        });
    });
})();
</script>
@endpush
