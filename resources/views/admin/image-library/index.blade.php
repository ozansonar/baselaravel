@extends('layouts.admin')

@section('title', 'Görsel Kütüphanesi')

@section('content')
<div class="container-fluid py-4 il-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-images text-warning me-2"></i> Görsel Kütüphanesi
            </h2>
            <small class="text-muted">Bulk Instagram import için önceden hazırlanmış görseller — AI maliyeti $0</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="dropdown">
                <button class="btn-glass dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots me-1"></i> Yedek
                </button>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><a class="dropdown-item" href="{{ route('admin.image-library.export') }}">
                        <i class="bi bi-download me-1"></i> ZIP olarak Export
                    </a></li>
                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#ilImportModal">
                        <i class="bi bi-upload me-1"></i> ZIP'ten Import
                    </button></li>
                </ul>
            </div>
            <a href="{{ route('admin.image-library.analyze') }}" class="btn-glass">
                <i class="bi bi-graph-up me-1"></i> Analiz
            </a>
            <a href="{{ route('admin.image-library.upload-form') }}" class="btn-teal">
                <i class="bi bi-cloud-upload me-1"></i> Görsel Yükle
            </a>
        </div>
    </div>

    {{-- Kullanım rehberi --}}
    <div class="alert alert-info il-help">
        <strong><i class="bi bi-info-circle me-1"></i> Görsel Kütüphanesi — Eksiksiz Kullanım Rehberi</strong>
        <div class="row g-3 mt-2 small">
            <div class="col-md-6">
                <strong class="d-block mb-1">📤 Yükleme & Etiketleme</strong>
                <ol class="ps-3 mb-0">
                    <li>"Görsel Yükle" ile çoklu (max 30) yükle</li>
                    <li>Aynı temadakileri grup halinde yükle (form-level etiket tüm görsellere uygulanır)</li>
                    <li>Edit sayfasında "AI ile Otomatik Tag'le" → tag/tema/mood otomatik dolar</li>
                    <li>AI önerilerini onayla/düzelt → Güncelle</li>
                </ol>
            </div>
            <div class="col-md-6">
                <strong class="d-block mb-1">🚀 Kullanım Yolları (3 mod)</strong>
                <ol class="ps-3 mb-0">
                    <li><strong>Konu-İlk:</strong> Excel/TSV'de <code>gorsel_kaynagi=kutuphane</code> + konu metni → otomatik eşleşme</li>
                    <li><strong>Görsel-İlk:</strong> "Kütüphaneden Plan Oluştur" → tarih aralığı + tip dağılımı seç → plan</li>
                    <li><strong>Analiz:</strong> "Analiz" sayfasından eksik görsel raporu + AI çekim önerisi al</li>
                </ol>
            </div>
            <div class="col-12">
                <strong class="d-block mb-1">💡 Akıllı Davranışlar</strong>
                <ul class="ps-3 mb-0">
                    <li>Bulk import sırasında <strong>least-used</strong> + en eski kullanılan görsel önce seçilir → rotasyon doğal</li>
                    <li>Tüm görseller tükenip recycle başlarsa <strong>Telegram bildirimi</strong> gelir → yeni görsel eklemen tetiklenir</li>
                    <li>Eksik kategori varsa <strong>Analiz</strong> sayfasında AI çekim listesi hazırlar (~$0.001)</li>
                    <li>Maliyet: AI görsel <strong>$0</strong> — sadece caption text AI (~$0.0001/post)</li>
                </ul>
            </div>
            <div class="col-12">
                <strong class="d-block mb-1">📌 İlişkili Sayfalar</strong>
                <a href="{{ route('admin.image-library.analyze') }}" class="badge bg-info me-1">
                    📊 Analiz — eksik görsel raporu
                </a>
                @if(Route::has('admin.special-days.index'))
                    <a href="{{ route('admin.special-days.index') }}" class="badge bg-warning text-dark me-1">
                        📅 Özel Günler — bayram/kandil için ayrı galeri
                    </a>
                @endif
                @if(Route::has('admin.instagram-posts.bulk.form'))
                    <a href="{{ route('admin.instagram-posts.bulk.form') }}" class="badge bg-success me-1">
                        🚀 Bulk Import — kütüphaneyi kullanmaya başla
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Stok Özeti --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="il-stat il-stat--total">
                <span class="il-stat__label">Toplam</span>
                <span class="il-stat__value">{{ $stats['total'] }}</span>
                <small class="il-stat__sub">{{ $stats['total_usage'] }} toplam kullanım</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="il-stat il-stat--feed">
                <span class="il-stat__label"><i class="bi bi-image-fill"></i> Feed</span>
                <span class="il-stat__value">{{ $stats['by_type']['feed'] }}</span>
                <small class="il-stat__sub">1080×1080</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="il-stat il-stat--reels">
                <span class="il-stat__label"><i class="bi bi-camera-reels-fill"></i> Reels</span>
                <span class="il-stat__value">{{ $stats['by_type']['reels'] }}</span>
                <small class="il-stat__sub">1080×1920</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="il-stat il-stat--story">
                <span class="il-stat__label"><i class="bi bi-circle-fill"></i> Story</span>
                <span class="il-stat__value">{{ $stats['by_type']['story'] }}</span>
                <small class="il-stat__sub">1080×1920</small>
            </div>
        </div>
    </div>

    {{-- Toplu Aksiyonlar --}}
    @php
        $untaggedCount = \App\Models\MediaLibraryImage::query()->where('ai_generated_tags', false)->count();
    @endphp
    @if($untaggedCount > 0)
        <div class="alert alert-warning d-flex align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
                <strong><i class="bi bi-stars me-1"></i> {{ $untaggedCount }} görsel henüz AI ile etiketlenmedi</strong>
                <small class="d-block text-muted mt-1">
                    Toplu AI tag (~${{ number_format($untaggedCount * 0.00015, 4) }} maliyet) ile hepsini bir kerede etiketle.
                    İşlem arka planda çalışır, bittiğinde Telegram'a bildirim gelir.
                </small>
            </div>
            <button type="button" class="btn-teal" id="ilBatchAutoTagBtn">
                <i class="bi bi-stars me-1"></i> Toplu AI Tag'le ({{ $untaggedCount }})
            </button>
        </div>
    @endif

    {{-- Filtreler --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.image-library.index') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted">Tip</label>
                    <select name="media_type" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach(\App\Models\MediaLibraryImage::MEDIA_TYPES as $t)
                            <option value="{{ $t }}" @selected(request('media_type') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Tema</label>
                    <select name="theme" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach(\App\Models\MediaLibraryImage::THEMES as $th)
                            <option value="{{ $th }}" @selected(request('theme') === $th)>{{ (new \App\Models\MediaLibraryImage(['theme' => $th]))->themeLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Mood</label>
                    <select name="mood" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach(\App\Models\MediaLibraryImage::MOODS as $m)
                            <option value="{{ $m }}" @selected(request('mood') === $m)>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Mevsim</label>
                    <select name="season" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach(\App\Models\MediaLibraryImage::SEASONS as $s)
                            <option value="{{ $s }}" @selected(request('season') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Kullanım</label>
                    <select name="usage" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        <option value="never" @selected(request('usage') === 'never')>Hiç kullanılmamış ({{ $stats['never_used'] }})</option>
                        <option value="used" @selected(request('usage') === 'used')>Kullanılmış</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Görünürlük</label>
                    <select name="visibility" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        <option value="private" @selected(request('visibility') === 'private')>🔒 Sadece Bulk</option>
                        <option value="public" @selected(request('visibility') === 'public')>🌐 Halka Açık</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn-teal btn-sm flex-fill"><i class="bi bi-funnel"></i> Filtrele</button>
                    @if(request()->hasAny(['media_type','theme','mood','season','usage','q']))
                        <a href="{{ route('admin.image-library.index') }}" class="btn-glass btn-glass-sm" title="Temizle"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
                <div class="col-12 mt-2">
                    <input type="text" name="q" class="form-control form-control-sm form-control-theme"
                           value="{{ request('q') }}" placeholder="Tag, açıklama veya alt-text içinde ara...">
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Edit Toolbar (görsel seçildiğinde görünür) --}}
    <div class="il-bulk-toolbar" id="ilBulkToolbar" hidden>
        <div class="il-bulk-toolbar__inner">
            <span class="il-bulk-toolbar__count">
                <strong id="ilBulkSelectedCount">0</strong> seçili
            </span>
            <select class="form-select form-select-sm il-bulk-toolbar__select" id="ilBulkAction">
                <option value="">— İşlem seç —</option>
                <option value="set_theme">Tema değiştir</option>
                <option value="set_mood">Mood değiştir</option>
                <option value="set_season">Mevsim değiştir</option>
                <option value="set_media_type">Tip değiştir</option>
                <option value="delete">Toplu sil</option>
            </select>
            <select class="form-select form-select-sm il-bulk-toolbar__value" id="ilBulkValue" hidden>
                <option value="">— Değer —</option>
            </select>
            <button type="button" class="btn-teal btn-sm" id="ilBulkApplyBtn" disabled>
                <i class="bi bi-check-lg me-1"></i> Uygula
            </button>
            <button type="button" class="btn-glass btn-glass-sm" id="ilBulkClearBtn">
                <i class="bi bi-x-lg me-1"></i> Seçimi Temizle
            </button>
        </div>
    </div>

    {{-- Tema Drag-Drop Şeridi --}}
    <div class="il-theme-strip" id="ilThemeStrip">
        <small class="il-theme-strip__label">
            <i class="bi bi-grip-vertical me-1"></i> Görselleri sürükleyip bir temaya bırak (toplu tema atama):
        </small>
        <div class="il-theme-strip__items">
            @foreach(\App\Models\MediaLibraryImage::THEMES as $theme)
                <div class="il-theme-drop" data-theme="{{ $theme }}">
                    {{ (new \App\Models\MediaLibraryImage(['theme' => $theme]))->themeLabel() }}
                </div>
            @endforeach
            <div class="il-theme-drop il-theme-drop--clear" data-theme="">
                <i class="bi bi-x-lg"></i> Tema Yok
            </div>
        </div>
    </div>

    {{-- Görsel Grid --}}
    @if($images->isEmpty())
        <div class="card-dark">
            <div class="card-body-custom text-center py-5 text-muted">
                <i class="bi bi-images display-3 d-block mb-2 opacity-25"></i>
                <p class="mb-2">Henüz görsel yok.</p>
                <a href="{{ route('admin.image-library.upload-form') }}" class="btn-teal">
                    <i class="bi bi-cloud-upload me-1"></i> İlk görseli yükle
                </a>
            </div>
        </div>
    @else
        <div class="il-grid">
            @foreach($images as $img)
                @php
                    $cardMeta = [
                        'type'     => $img->mediaTypeLabel(),
                        'theme'    => $img->themeLabel(),
                        'mood'     => $img->moodLabel(),
                        'tags'     => implode(', ', $img->tagList()),
                        'usage'    => $img->usage_count,
                        'alt'      => $img->alt_text ?? '',
                        'duration' => $img->durationLabel(),
                    ];
                @endphp
                <div class="il-card" data-id="{{ $img->id }}" draggable="true"
                     data-full-url="{{ upload_url($img->image_path, 'lg') }}"
                     data-video-url="{{ $img->is_video && $img->video_path ? '/uploads/' . $img->video_path : '' }}"
                     data-is-video="{{ $img->is_video ? '1' : '0' }}"
                     data-edit-url="{{ route('admin.image-library.edit', $img->id) }}"
                     data-meta='@json($cardMeta)'>
                    <label class="il-card__select" title="Seç">
                        <input type="checkbox" class="il-card__checkbox" value="{{ $img->id }}">
                    </label>
                    <button type="button" class="il-card__thumb il-card__thumb--btn">
                        <img src="{{ upload_url($img->image_path, 'md') }}" alt="{{ $img->alt_text ?? '' }}" loading="lazy">
                        <span class="il-card__type il-card__type--{{ $img->media_type }}">{{ $img->mediaTypeLabel() }}</span>
                        @if($img->is_video)
                            <span class="il-card__video-badge">
                                <i class="bi bi-play-circle-fill"></i>
                                @if($img->durationLabel()) {{ $img->durationLabel() }} @endif
                            </span>
                        @endif
                    </button>
                    <div class="il-card__body">
                        @if($img->theme)
                            <small class="il-card__theme">{{ $img->themeLabel() }}</small>
                        @endif
                        @if(! empty($img->tagList()))
                            <div class="il-card__tags">
                                @foreach(array_slice($img->tagList(), 0, 3) as $tag)
                                    <span class="il-tag">{{ $tag }}</span>
                                @endforeach
                                @if(count($img->tagList()) > 3)
                                    <span class="il-tag il-tag--more">+{{ count($img->tagList()) - 3 }}</span>
                                @endif
                            </div>
                        @endif
                        <div class="il-card__meta">
                            <span title="Kullanım">
                                <i class="bi bi-eye"></i> {{ $img->usage_count }}
                            </span>
                            @if($img->season)
                                <span class="ms-2 text-muted">{{ $img->seasonLabel() }}</span>
                            @endif
                            <button type="button" class="il-card__delete ms-auto" data-id="{{ $img->id }}" title="Sil">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">{{ $images->total() }} görsel — sayfa {{ $images->currentPage() }}/{{ $images->lastPage() }}</small>
            {{ $images->links() }}
        </div>
    @endif

    {{-- Import Modal --}}
    <div class="modal fade" id="ilImportModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-1"></i> Kütüphaneden Import (ZIP)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('admin.image-library.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <strong><i class="bi bi-info-circle me-1"></i> Notlar:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>ZIP, daha önce <em>"Export"</em> ile alınmış olmalı (manifest.json içermeli)</li>
                                <li>Aynı dosya adı ile DB'de varsa atlanır (deduplication)</li>
                                <li>Yeni görseller <code>image-library/imported-YYYYMMDD-HHMMSS/</code> klasörüne yazılır</li>
                                <li>Usage count import'ta sıfırlanır (görsel taze başlar)</li>
                                <li>Max 512 MB</li>
                            </ul>
                        </div>
                        <input type="file" name="zip_file" accept=".zip" class="form-control form-control-theme" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn-teal"><i class="bi bi-upload me-1"></i> Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Lightbox --}}
    <div class="il-lightbox" id="ilLightbox" hidden>
        <button type="button" class="il-lightbox__close" id="ilLightboxClose" title="Kapat (Esc)">
            <i class="bi bi-x-lg"></i>
        </button>
        <button type="button" class="il-lightbox__nav il-lightbox__nav--prev" id="ilLightboxPrev" title="Önceki (←)">
            <i class="bi bi-chevron-left"></i>
        </button>
        <button type="button" class="il-lightbox__nav il-lightbox__nav--next" id="ilLightboxNext" title="Sonraki (→)">
            <i class="bi bi-chevron-right"></i>
        </button>
        <div class="il-lightbox__inner">
            <img class="il-lightbox__img" id="ilLightboxImg" src="" alt="" hidden>
            <video class="il-lightbox__video" id="ilLightboxVideo" controls hidden></video>
            <div class="il-lightbox__meta">
                <div class="il-lightbox__title" id="ilLightboxTitle"></div>
                <div class="il-lightbox__details" id="ilLightboxDetails"></div>
                <div class="il-lightbox__actions">
                    <a href="" class="btn-glass btn-glass-sm" id="ilLightboxEdit">
                        <i class="bi bi-pencil me-1"></i> Düzenle
                    </a>
                    <span class="il-lightbox__counter" id="ilLightboxCounter">1 / 1</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.il-help { background: rgba(13,202,240,0.06); border: 1px solid rgba(13,202,240,0.2); color: #cfe9f7; }
.il-help strong { color: #93c5fd; }

/* Stok kartları */
.il-stat {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 14px 16px;
    display: flex; flex-direction: column;
    height: 100%;
}
.il-stat__label { font-size: 0.78rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.il-stat__value { font-size: 2rem; font-weight: 700; color: #e5e7eb; line-height: 1.2; margin: 4px 0; }
.il-stat__sub   { font-size: 0.72rem; color: #6b7280; }

.il-stat--total { border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.04); }
.il-stat--total .il-stat__value { color: #fcd34d; }
.il-stat--feed  .il-stat__value { color: #93c5fd; }
.il-stat--reels .il-stat__value { color: #c4b5fd; }
.il-stat--story .il-stat__value { color: #f9a8d4; }

/* Görsel grid */
.il-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
}
.il-card {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s ease;
    display: flex; flex-direction: column;
}
.il-card:hover { border-color: rgba(245,158,11,0.4); transform: translateY(-2px); }
.il-card__thumb {
    position: relative; display: block;
    aspect-ratio: 1 / 1; background: #000;
    overflow: hidden;
}
.il-card__thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.il-card:hover .il-card__thumb img { transform: scale(1.05); }
.il-card__type {
    position: absolute; top: 8px; left: 8px;
    padding: 3px 8px; border-radius: 12px;
    font-size: 0.7rem; font-weight: 600;
    backdrop-filter: blur(6px);
    background: rgba(0,0,0,0.6); color: #fff;
}
.il-card__type--feed  { background: rgba(59,130,246,0.85); }
.il-card__type--reels { background: rgba(168,85,247,0.85); }
.il-card__type--story { background: rgba(236,72,153,0.85); }

.il-card__body { padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
.il-card__theme {
    color: #fcd34d; font-weight: 600; font-size: 0.75rem;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.il-card__tags { display: flex; flex-wrap: wrap; gap: 4px; }
.il-tag {
    padding: 2px 7px; border-radius: 10px; font-size: 0.7rem;
    background: rgba(34,197,94,0.12); color: #86efac;
    border: 1px solid rgba(34,197,94,0.25);
}
.il-tag--more { background: rgba(255,255,255,0.05); color: #9ca3af; border-color: rgba(255,255,255,0.1); }

.il-card__meta {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.78rem; color: #d1d5db; margin-top: auto;
}
.il-card__delete {
    background: none; border: none; color: #fca5a5;
    cursor: pointer; padding: 2px 6px; border-radius: 4px;
    transition: background 0.15s;
}
.il-card__delete:hover { background: rgba(239,68,68,0.15); }

/* Bulk select checkbox */
.il-card { position: relative; }
.il-card__select {
    position: absolute; top: 8px; right: 8px; z-index: 5;
    width: 24px; height: 24px;
    background: rgba(0,0,0,0.7); border: 2px solid rgba(255,255,255,0.3);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    margin: 0;
    backdrop-filter: blur(4px);
}
.il-card__select:hover { background: rgba(0,0,0,0.85); border-color: rgba(245,158,11,0.6); }
.il-card__checkbox { margin: 0; width: 14px; height: 14px; cursor: pointer; accent-color: #f59e0b; }
.il-card.is-selected { box-shadow: 0 0 0 3px #f59e0b; border-color: #f59e0b; }
.il-card.is-selected .il-card__select { background: #f59e0b; border-color: #f59e0b; }

/* Bulk toolbar */
.il-bulk-toolbar {
    position: sticky;
    top: 60px;
    z-index: 100;
    margin-bottom: 1rem;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.il-bulk-toolbar__inner {
    background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.08));
    border: 1px solid rgba(245,158,11,0.4);
    border-radius: 10px;
    padding: 12px 16px;
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.il-bulk-toolbar__count {
    color: #fcd34d; font-size: 0.95rem;
    padding-right: 12px;
    border-right: 1px solid rgba(255,255,255,0.15);
}
.il-bulk-toolbar__count strong { font-size: 1.2rem; }
.il-bulk-toolbar__select, .il-bulk-toolbar__value {
    width: auto; min-width: 160px;
    background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.15);
    color: #e5e7eb;
}

/* Lightbox */
.il-card__thumb--btn {
    background: none; border: none; padding: 0; width: 100%;
    cursor: zoom-in;
}
.il-lightbox {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.95);
    display: flex; align-items: center; justify-content: center;
    animation: lbFade 0.2s ease;
}
@keyframes lbFade { from { opacity: 0; } to { opacity: 1; } }
.il-lightbox__close, .il-lightbox__nav {
    position: absolute; z-index: 2;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
    color: #fff; cursor: pointer; border-radius: 50%;
    width: 48px; height: 48px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
    backdrop-filter: blur(8px);
}
.il-lightbox__close:hover, .il-lightbox__nav:hover { background: rgba(255,255,255,0.25); transform: scale(1.05); }
.il-lightbox__close { top: 20px; right: 20px; font-size: 1.2rem; }
.il-lightbox__nav { top: 50%; transform: translateY(-50%); font-size: 1.5rem; }
.il-lightbox__nav--prev { left: 20px; }
.il-lightbox__nav--next { right: 20px; }
.il-lightbox__nav:hover { transform: translateY(-50%) scale(1.05); }

.il-lightbox__inner {
    max-width: 90vw; max-height: 90vh;
    display: flex; flex-direction: column; align-items: center; gap: 16px;
}
.il-lightbox__img {
    max-width: 100%; max-height: 80vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.il-lightbox__meta {
    background: rgba(0,0,0,0.7); backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; padding: 12px 16px;
    color: #e5e7eb; min-width: 320px; max-width: 600px;
}
.il-lightbox__title { font-size: 0.9rem; color: #fcd34d; font-weight: 600; margin-bottom: 4px; }
.il-lightbox__details { font-size: 0.82rem; color: #d1d5db; line-height: 1.5; }
.il-lightbox__actions {
    margin-top: 10px; padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; gap: 10px;
}
.il-lightbox__counter {
    margin-left: auto;
    font-size: 0.78rem; color: #9ca3af;
    background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 10px;
}

@media (max-width: 768px) {
    .il-lightbox__nav { width: 40px; height: 40px; }
    .il-lightbox__meta { min-width: 280px; }
}

/* Video badge — Reels için play icon overlay */
.il-card__video-badge {
    position: absolute; bottom: 8px; left: 8px;
    background: rgba(0,0,0,0.75); color: #fff;
    padding: 4px 10px; border-radius: 12px;
    font-size: 0.78rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.15);
}
.il-card__video-badge i { color: #f9a8d4; font-size: 1rem; }

/* Tema Drag-Drop Şeridi */
.il-theme-strip {
    background: rgba(0,0,0,0.3);
    border: 1px dashed rgba(255,255,255,0.15);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 1rem;
    display: flex; flex-direction: column; gap: 8px;
}
.il-theme-strip__label { color: #9ca3af; font-size: 0.82rem; }
.il-theme-strip__items {
    display: flex; flex-wrap: wrap; gap: 8px;
}
.il-theme-drop {
    padding: 8px 14px;
    border: 1px dashed rgba(255,255,255,0.15);
    border-radius: 20px;
    color: #d1d5db; font-size: 0.85rem; font-weight: 600;
    background: rgba(255,255,255,0.03);
    transition: all 0.15s;
    cursor: pointer;
}
.il-theme-drop:hover { border-color: rgba(245,158,11,0.5); color: #fcd34d; }
.il-theme-drop.is-drag-over {
    background: rgba(245,158,11,0.2);
    border-color: #f59e0b;
    border-style: solid;
    color: #fff;
    transform: scale(1.05);
}
.il-theme-drop--clear { background: rgba(239,68,68,0.06); border-color: rgba(239,68,68,0.25); color: #fca5a5; }
.il-theme-drop--clear:hover { border-color: rgba(239,68,68,0.5); color: #fff; }

.il-card { transition: opacity 0.2s; }
.il-card.is-dragging { opacity: 0.4; cursor: grabbing; }
.il-card[draggable="true"] { cursor: grab; }
.il-card[draggable="true"]:active { cursor: grabbing; }

/* Lightbox video element */
.il-lightbox__video {
    max-width: 100%; max-height: 80vh;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    background: #000;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    // ── Bulk Edit (çoklu seçim + toplu işlem) ──
    var bulkToolbar     = document.getElementById('ilBulkToolbar');
    var bulkSelectedEl  = document.getElementById('ilBulkSelectedCount');
    var bulkActionSel   = document.getElementById('ilBulkAction');
    var bulkValueSel    = document.getElementById('ilBulkValue');
    var bulkApplyBtn    = document.getElementById('ilBulkApplyBtn');
    var bulkClearBtn    = document.getElementById('ilBulkClearBtn');

    var THEMES  = @json(\App\Models\MediaLibraryImage::THEMES);
    var MOODS   = @json(\App\Models\MediaLibraryImage::MOODS);
    var SEASONS = @json(\App\Models\MediaLibraryImage::SEASONS);
    var TYPES   = @json(\App\Models\MediaLibraryImage::MEDIA_TYPES);

    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.il-card__checkbox:checked')).map(function (c) {
            return parseInt(c.value, 10);
        });
    }

    function updateBulkUI() {
        var ids = getSelectedIds();
        var count = ids.length;
        if (bulkSelectedEl) bulkSelectedEl.textContent = String(count);
        if (bulkToolbar) bulkToolbar.hidden = count === 0;

        // Card'lara is-selected class ekle
        document.querySelectorAll('.il-card').forEach(function (card) {
            var cb = card.querySelector('.il-card__checkbox');
            card.classList.toggle('is-selected', cb && cb.checked);
        });

        // Apply butonu durumu
        if (bulkApplyBtn) {
            var hasAction = bulkActionSel && bulkActionSel.value !== '';
            var hasValue  = (bulkActionSel && bulkActionSel.value === 'delete')
                           || (bulkValueSel && bulkValueSel.value !== '');
            bulkApplyBtn.disabled = ! (count > 0 && hasAction && hasValue);
        }
    }

    document.querySelectorAll('.il-card__checkbox').forEach(function (cb) {
        cb.addEventListener('change', updateBulkUI);
        // Card label tıklamayı engelle (link'e gitmesin)
        cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    if (bulkActionSel) {
        bulkActionSel.addEventListener('change', function () {
            var action = this.value;
            if (! bulkValueSel) return;

            var options = [];
            if (action === 'set_theme')      options = THEMES;
            else if (action === 'set_mood')   options = MOODS;
            else if (action === 'set_season') options = SEASONS;
            else if (action === 'set_media_type') options = TYPES;

            if (options.length > 0) {
                bulkValueSel.innerHTML = '<option value="">— Değer —</option>' +
                    options.map(function (v) {
                        return '<option value="' + v + '">' + v.charAt(0).toUpperCase() + v.slice(1) + '</option>';
                    }).join('');
                bulkValueSel.hidden = false;
            } else {
                // delete için değer gerekmiyor
                bulkValueSel.hidden = true;
                bulkValueSel.value = '';
            }
            updateBulkUI();
        });
    }

    if (bulkValueSel) bulkValueSel.addEventListener('change', updateBulkUI);

    if (bulkClearBtn) {
        bulkClearBtn.addEventListener('click', function () {
            document.querySelectorAll('.il-card__checkbox').forEach(function (cb) { cb.checked = false; });
            updateBulkUI();
        });
    }

    if (bulkApplyBtn) {
        bulkApplyBtn.addEventListener('click', function () {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            var action = bulkActionSel.value;
            var value  = bulkValueSel.value || null;

            var actionLabel = bulkActionSel.options[bulkActionSel.selectedIndex].text;
            var msg = ids.length + ' görsele \'' + actionLabel + '\' uygulanacak';
            if (action === 'delete') msg = ids.length + ' görsel SİLİNECEK (geri alınamaz)';
            else if (value) msg += ' (değer: ' + value + ')';
            msg += '. Devam edilsin mi?';

            if (! confirm(msg)) return;

            bulkApplyBtn.disabled = true;
            bulkApplyBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> İşleniyor...';

            fetch('{{ route('admin.image-library.bulk-update') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ ids: ids, action: action, value: value }),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (r) {
                alert(r.data.message);
                if (r.ok && r.data.success) {
                    setTimeout(function () { window.location.reload(); }, 800);
                }
            })
            .catch(function (e) { alert('İstek başarısız: ' + e.message); })
            .finally(function () {
                bulkApplyBtn.disabled = false;
                bulkApplyBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Uygula';
            });
        });
    }

    // Toplu Auto-Tag
    var batchBtn = document.getElementById('ilBatchAutoTagBtn');
    if (batchBtn) {
        batchBtn.addEventListener('click', function () {
            if (! confirm('Etiketsiz tüm görseller arka planda AI ile etiketlenecek.\nBitince Telegram bildirimi gelir.\n\nDevam edilsin mi?')) return;
            batchBtn.disabled = true;
            batchBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> İşleniyor...';

            fetch('{{ route('admin.image-library.batch-auto-tag') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ scope: 'untagged' }),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (r) {
                alert(r.data.message);
                if (r.ok && r.data.success) {
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            })
            .catch(function (e) { alert('İstek başarısız: ' + e.message); })
            .finally(function () {
                batchBtn.disabled = false;
                batchBtn.innerHTML = '<i class="bi bi-stars me-1"></i> Toplu AI Tag\'le';
            });
        });
    }

    // ── Lightbox ──
    var lightbox       = document.getElementById('ilLightbox');
    var lightboxImg    = document.getElementById('ilLightboxImg');
    var lightboxVideo  = document.getElementById('ilLightboxVideo');
    var lightboxTitle  = document.getElementById('ilLightboxTitle');
    var lightboxDetails= document.getElementById('ilLightboxDetails');
    var lightboxEdit   = document.getElementById('ilLightboxEdit');
    var lightboxCounter= document.getElementById('ilLightboxCounter');
    var lightboxClose  = document.getElementById('ilLightboxClose');
    var lightboxPrev   = document.getElementById('ilLightboxPrev');
    var lightboxNext   = document.getElementById('ilLightboxNext');

    var allCards = Array.from(document.querySelectorAll('.il-card'));
    var currentIdx = -1;

    function showLightbox(idx) {
        if (idx < 0 || idx >= allCards.length) return;
        currentIdx = idx;
        var card = allCards[idx];
        var fullUrl  = card.dataset.fullUrl;
        var videoUrl = card.dataset.videoUrl;
        var isVideo  = card.dataset.isVideo === '1';
        var editUrl  = card.dataset.editUrl;
        var meta;
        try { meta = JSON.parse(card.dataset.meta || '{}'); } catch (e) { meta = {}; }

        if (isVideo && videoUrl) {
            // Video modu
            lightboxImg.hidden = true;
            lightboxImg.src = '';
            lightboxVideo.src = videoUrl;
            lightboxVideo.poster = fullUrl;
            lightboxVideo.hidden = false;
            lightboxVideo.load();
        } else {
            // Görsel modu
            lightboxVideo.pause();
            lightboxVideo.src = '';
            lightboxVideo.hidden = true;
            lightboxImg.src = fullUrl;
            lightboxImg.alt = meta.alt || '';
            lightboxImg.hidden = false;
        }

        var typeLabel = meta.type;
        if (isVideo) typeLabel = '🎬 ' + typeLabel + (meta.duration ? ' · ' + meta.duration : '');
        lightboxTitle.textContent = typeLabel + (meta.theme ? ' · ' + meta.theme : '');

        var details = [];
        if (meta.tags) details.push('<i class="bi bi-tags me-1"></i> ' + meta.tags);
        if (meta.mood) details.push('<i class="bi bi-palette me-1"></i> ' + meta.mood);
        details.push('<i class="bi bi-eye me-1"></i> ' + (meta.usage || 0) + ' kullanım');
        lightboxDetails.innerHTML = details.join(' &nbsp;·&nbsp; ');

        lightboxEdit.href = editUrl;
        lightboxCounter.textContent = (idx + 1) + ' / ' + allCards.length;

        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function hideLightbox() {
        if (lightboxVideo) {
            lightboxVideo.pause();
            lightboxVideo.src = '';
        }
        lightbox.hidden = true;
        document.body.style.overflow = '';
        currentIdx = -1;
    }

    function navigate(delta) {
        var newIdx = currentIdx + delta;
        if (newIdx < 0) newIdx = allCards.length - 1; // döngüsel
        if (newIdx >= allCards.length) newIdx = 0;
        showLightbox(newIdx);
    }

    // Card thumbnail tıklaması → lightbox
    document.querySelectorAll('.il-card__thumb--btn').forEach(function (btn, idx) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            // Card'ın allCards listesindeki gerçek indeksini bul
            var card = btn.closest('.il-card');
            var cardIdx = allCards.indexOf(card);
            if (cardIdx >= 0) showLightbox(cardIdx);
        });
    });

    if (lightboxClose) lightboxClose.addEventListener('click', hideLightbox);
    if (lightboxPrev)  lightboxPrev.addEventListener('click', function () { navigate(-1); });
    if (lightboxNext)  lightboxNext.addEventListener('click', function () { navigate(1); });

    // Klavye gezinmesi
    document.addEventListener('keydown', function (e) {
        if (lightbox.hidden) return;
        if (e.key === 'Escape') hideLightbox();
        else if (e.key === 'ArrowLeft') navigate(-1);
        else if (e.key === 'ArrowRight') navigate(1);
    });

    // Backdrop tıklama → kapat
    lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) hideLightbox();
    });

    // ── Drag-Drop Tema Atama ──
    var draggedCardId = null;

    document.querySelectorAll('.il-card[draggable="true"]').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
            draggedCardId = this.dataset.id;
            this.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', draggedCardId);
        });
        card.addEventListener('dragend', function () {
            this.classList.remove('is-dragging');
            draggedCardId = null;
        });
    });

    document.querySelectorAll('.il-theme-drop').forEach(function (zone) {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('is-drag-over');
        });
        zone.addEventListener('dragleave', function () {
            this.classList.remove('is-drag-over');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('is-drag-over');

            var theme = this.dataset.theme || '';
            var ids = [];

            // Eğer çoklu seçim varsa hepsi, yoksa sadece sürüklenen
            var selected = getSelectedIds();
            if (selected.length > 0 && draggedCardId && selected.indexOf(parseInt(draggedCardId, 10)) >= 0) {
                ids = selected;
            } else if (draggedCardId) {
                ids = [parseInt(draggedCardId, 10)];
            }

            if (ids.length === 0) return;

            var msg = ids.length + ' görsel "' + (theme || 'tema yok') + '" temasına atanacak. Devam?';
            if (! confirm(msg)) return;

            fetch('{{ route('admin.image-library.bulk-update') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ ids: ids, action: 'set_theme', value: theme }),
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    setTimeout(function () { window.location.reload(); }, 400);
                } else {
                    alert(d.message || 'Atama başarısız');
                }
            });
        });
    });

    document.querySelectorAll('.il-card__delete').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (! confirm('Bu görseli silmek istediğine emin misin?')) return;
            var id = this.dataset.id;
            var card = this.closest('.il-card');

            fetch('{{ route('admin.image-library.destroy', ['image' => '__ID__']) }}'.replace('__ID__', id), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            })
            .then(function (res) { return res.json(); })
            .then(function (d) {
                if (d.success) {
                    card.style.opacity = '0';
                    setTimeout(function () { card.remove(); }, 300);
                } else {
                    alert(d.message || 'Silme başarısız');
                }
            })
            .catch(function () { alert('İstek başarısız'); });
        });
    });

    // Thumbnail tıklamasında <a> default davranışı zaten edit sayfasına gider — extra JS yok
})();
</script>
@endpush
