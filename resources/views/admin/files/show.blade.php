@extends('layouts.admin')

@section('title', $file->original_name . ' — Dosya Detayı')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.files.index') }}" class="breadcrumb-link">Dosya Yöneticisi</a>
            </li>
            <li class="breadcrumb-item active text-teal">{{ \Illuminate\Support\Str::limit($file->original_name, 40) }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">
                <i class="bi {{ $file->iconClass() }} {{ $file->iconColorClass() }} me-2"></i>{{ \Illuminate\Support\Str::limit($file->original_name, 60) }}
            </h1>
            <p class="page-subtitle">
                <span class="badge bg-secondary-subtle text-secondary-emphasis me-2">{{ $file->categoryLabel() }}</span>
                <span class="text-muted">{{ strtoupper($file->extension) }}</span> ·
                <span class="text-muted">{{ $file->humanSize() }}</span> ·
                <span class="text-muted">{{ $file->created_at->translatedFormat('d M Y H:i') }}</span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.files.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Geri Dön
            </a>
            <a href="{{ $file->publicUrl() }}" target="_blank" rel="noopener" class="btn-glass">
                <i class="bi bi-box-arrow-up-right me-1"></i> Yeni Sekmede Aç
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- ==================== SOL: PREVIEW + META ==================== --}}
        <div class="col-xl-5" data-aos="fade-up">
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-eye me-1"></i> Önizleme</h6>

                    @if($file->isImage())
                        <a href="{{ $file->publicUrl() }}" target="_blank" rel="noopener" class="d-block fmgr-preview-link">
                            <img src="{{ $file->publicUrl() }}"
                                 alt="{{ $file->alt_text ?? $file->original_name }}"
                                 loading="lazy"
                                 class="img-fluid rounded fmgr-preview-image">
                        </a>
                    @else
                        <div class="fmgr-show-icon-wrap">
                            <i class="bi {{ $file->iconClass() }} {{ $file->iconColorClass() }}"></i>
                            <span class="fmgr-show-icon-ext">.{{ $file->extension }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card-dark">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-info-circle me-1"></i> Dosya Bilgileri</h6>

                    <div class="ml-detail-list">
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-file-earmark me-1"></i> Orijinal Ad</span>
                            <span class="ml-detail-value fw-semibold">{{ $file->original_name }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-tag me-1"></i> Kategori</span>
                            <span class="ml-detail-value">{{ $file->categoryLabel() }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-filetype-pdf me-1"></i> Uzantı / MIME</span>
                            <span class="ml-detail-value">.{{ $file->extension }} <small class="text-muted">({{ $file->mime_type }})</small></span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-hdd me-1"></i> Orijinal Boyut</span>
                            <span class="ml-detail-value">{{ $file->humanSize() }}</span>
                        </div>
                        @if($file->isImage() && $file->webp_size)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-arrow-down-circle me-1"></i> WebP Boyut</span>
                                <span class="ml-detail-value">
                                    {{ $file->webpSizeHuman() }}
                                    @if($file->webpSavingsPercent() !== null && $file->webpSavingsPercent() > 0)
                                        <small class="badge bg-success-subtle text-success-emphasis ms-1">
                                            %{{ $file->webpSavingsPercent() }} tasarruf
                                        </small>
                                    @endif
                                </span>
                            </div>
                        @endif
                        @if($file->isImage() && $file->width && $file->height)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-rulers me-1"></i> Çözünürlük</span>
                                <span class="ml-detail-value">{{ $file->width }} × {{ $file->height }} px</span>
                            </div>
                        @endif
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-download me-1"></i> İndirme Sayısı</span>
                            <span class="ml-detail-value">{{ number_format($file->download_count) }}</span>
                        </div>
                        @if($file->uploader)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-person me-1"></i> Yükleyen</span>
                                <span class="ml-detail-value">{{ $file->uploader->name ?: '-' }}</span>
                            </div>
                        @endif
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-calendar-plus me-1"></i> Yükleme Tarihi</span>
                            <span class="ml-detail-value">{{ $file->created_at->translatedFormat('d M Y H:i:s') }}</span>
                        </div>
                        @if($file->hash)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-fingerprint me-1"></i> SHA256 Hash</span>
                                <span class="ml-detail-value fmgr-hash">{{ \Illuminate\Support\Str::limit($file->hash, 24, '...') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== SAĞ: URL KOPYALAMA + EDIT ==================== --}}
        <div class="col-xl-7" data-aos="fade-up" data-aos-delay="100">
            {{-- URL formatları --}}
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3">
                        <i class="bi bi-clipboard me-1"></i> URL & Link Kopyala
                    </h6>

                    @if($file->isImage())
                        <div class="alert alert-info small mb-3 py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>WebP</strong> = küçük + hızlı (modern tarayıcılar) ·
                            <strong>Orijinal</strong> = JPG/PNG (geniş uyum, e-mail/eski tarayıcı)
                        </div>
                    @endif

                    {{-- ─── WebP / Stored versiyon ─── --}}
                    @if($file->isImage())
                        <div class="fmgr-url-section">
                            <h6 class="fmgr-url-section-title">
                                <i class="bi bi-arrow-down-circle text-success me-1"></i>WebP (Önerilen)
                                <small class="text-muted ms-2">{{ $file->webpSizeHuman() }}</small>
                            </h6>
                        </div>
                    @endif

                    {{-- Full URL (site adresi dahil) --}}
                    <div class="stg-field mb-3">
                        <label class="stg-label">
                            <i class="bi bi-globe2 me-1"></i> Full URL (site adresi dahil)
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">Önerilen</span>
                        </label>
                        <div class="fmgr-copy-row">
                            <input type="text" class="stg-input fmgr-copy-input" id="fmgrUrlFull" readonly
                                   value="{{ $file->fullUrl() }}">
                            <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrUrlFull">
                                <i class="bi bi-clipboard me-1"></i>Kopyala
                            </button>
                        </div>
                        <small class="stg-hint">Sosyal medya, e-mail, dış paylaşım için.</small>
                    </div>

                    {{-- Sade URL (relative) --}}
                    <div class="stg-field mb-3">
                        <label class="stg-label">
                            <i class="bi bi-link-45deg me-1"></i> Sade URL (site içi)
                        </label>
                        <div class="fmgr-copy-row">
                            <input type="text" class="stg-input fmgr-copy-input" id="fmgrUrlPlain" readonly
                                   value="{{ $file->publicUrl() }}">
                            <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrUrlPlain">
                                <i class="bi bi-clipboard me-1"></i>Kopyala
                            </button>
                        </div>
                        <small class="stg-hint">Site içi link (relative path).</small>
                    </div>

                    {{-- HTML Link --}}
                    <div class="stg-field mb-3">
                        <label class="stg-label">
                            <i class="bi bi-code-slash me-1"></i> HTML Link (download)
                        </label>
                        <div class="fmgr-copy-row">
                            <input type="text" class="stg-input fmgr-copy-input" id="fmgrUrlHtml" readonly
                                   value='<a href="{{ $file->fullUrl() }}" target="_blank" rel="noopener">{{ $file->original_name }}</a>'>
                            <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrUrlHtml">
                                <i class="bi bi-clipboard me-1"></i>Kopyala
                            </button>
                        </div>
                        <small class="stg-hint">Blog/sayfa HTML editörüne yapıştırılabilir.</small>
                    </div>

                    @if($file->isImage())
                        {{-- Markdown --}}
                        <div class="stg-field mb-3">
                            <label class="stg-label">
                                <i class="bi bi-markdown me-1"></i> Markdown Görsel
                            </label>
                            <div class="fmgr-copy-row">
                                <input type="text" class="stg-input fmgr-copy-input" id="fmgrUrlMarkdown" readonly
                                       value="![{{ $file->alt_text ?: $file->original_name }}]({{ $file->fullUrl() }})">
                                <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrUrlMarkdown">
                                    <i class="bi bi-clipboard me-1"></i>Kopyala
                                </button>
                            </div>
                            <small class="stg-hint">Markdown editörü için.</small>
                        </div>

                        {{-- HTML img --}}
                        <div class="stg-field mb-3">
                            <label class="stg-label">
                                <i class="bi bi-image me-1"></i> HTML &lt;img&gt;
                            </label>
                            <div class="fmgr-copy-row">
                                <input type="text" class="stg-input fmgr-copy-input" id="fmgrUrlImgTag" readonly
                                       value='<img src="{{ $file->fullUrl() }}" alt="{{ $file->alt_text ?: $file->original_name }}" loading="lazy">'>
                                <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrUrlImgTag">
                                    <i class="bi bi-clipboard me-1"></i>Kopyala
                                </button>
                            </div>
                            <small class="stg-hint">img tag'i — `loading="lazy"` ile birlikte.</small>
                        </div>

                        {{-- ─── Orijinal versiyon (JPG/PNG) ─── --}}
                        @if($file->original_path)
                            <hr class="my-3 fmgr-divider">

                            <div class="fmgr-url-section">
                                <h6 class="fmgr-url-section-title">
                                    <i class="bi bi-file-earmark-image text-warning me-1"></i>Orijinal ({{ strtoupper(pathinfo($file->original_path, PATHINFO_EXTENSION)) }})
                                    <small class="text-muted ms-2">{{ $file->humanSize() }}</small>
                                </h6>
                            </div>

                            {{-- Orijinal Full URL --}}
                            <div class="stg-field mb-3">
                                <label class="stg-label">
                                    <i class="bi bi-globe2 me-1"></i> Orijinal Full URL
                                </label>
                                <div class="fmgr-copy-row">
                                    <input type="text" class="stg-input fmgr-copy-input" id="fmgrOriginalFull" readonly
                                           value="{{ $file->originalFullUrl() }}">
                                    <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrOriginalFull">
                                        <i class="bi bi-clipboard me-1"></i>Kopyala
                                    </button>
                                </div>
                                <small class="stg-hint">Orijinal JPG/PNG — geniş tarayıcı uyumu için.</small>
                            </div>

                            {{-- Orijinal Sade URL --}}
                            <div class="stg-field mb-3">
                                <label class="stg-label">
                                    <i class="bi bi-link-45deg me-1"></i> Orijinal Sade URL
                                </label>
                                <div class="fmgr-copy-row">
                                    <input type="text" class="stg-input fmgr-copy-input" id="fmgrOriginalPlain" readonly
                                           value="{{ $file->originalUrl() }}">
                                    <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrOriginalPlain">
                                        <i class="bi bi-clipboard me-1"></i>Kopyala
                                    </button>
                                </div>
                            </div>

                            {{-- Orijinal HTML img --}}
                            <div class="stg-field">
                                <label class="stg-label">
                                    <i class="bi bi-image me-1"></i> Orijinal HTML &lt;img&gt;
                                </label>
                                <div class="fmgr-copy-row">
                                    <input type="text" class="stg-input fmgr-copy-input" id="fmgrOriginalImgTag" readonly
                                           value='<img src="{{ $file->originalFullUrl() }}" alt="{{ $file->alt_text ?: $file->original_name }}" loading="lazy">'>
                                    <button type="button" class="btn-teal btn-sm" data-fmgr-copy-target="fmgrOriginalImgTag">
                                        <i class="bi bi-clipboard me-1"></i>Kopyala
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Edit form --}}
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3">
                        <i class="bi bi-pencil me-1"></i> Bilgileri Düzenle
                    </h6>

                    <form method="POST" action="{{ route('admin.files.update', $file) }}">
                        @csrf
                        @method('PATCH')

                        <div class="stg-field mb-3">
                            <label for="fmgrEditTitle" class="stg-label">
                                <i class="bi bi-card-text me-1"></i>Başlık (admin için açıklama)
                            </label>
                            <input type="text" id="fmgrEditTitle" name="title" class="stg-input"
                                   maxlength="191"
                                   value="{{ old('title', $file->title) }}"
                                   placeholder="Örn: Sertifika - Organik Üretim 2026">
                        </div>

                        @if($file->isImage())
                            <div class="stg-field mb-3">
                                <label for="fmgrEditAlt" class="stg-label">
                                    <i class="bi bi-universal-access me-1"></i>Alt Metin (SEO + erişilebilirlik)
                                </label>
                                <textarea id="fmgrEditAlt" name="alt_text" class="stg-input" rows="3"
                                          maxlength="500"
                                          placeholder="Görselin ne anlattığını açıklayan kısa metin">{{ old('alt_text', $file->alt_text) }}</textarea>
                                <small class="stg-hint">
                                    Markdown ve HTML link kopyalandığında bu metin alt= attribute'ında kullanılır.
                                </small>
                            </div>
                        @endif

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn-glass fmgr-delete-btn"
                                    data-file-id="{{ $file->id }}"
                                    data-file-name="{{ $file->original_name }}"
                                    onclick="fmgrConfirmDelete(this)">
                                <i class="bi bi-trash me-1"></i>Dosyayı Sil
                            </button>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-save me-1"></i>Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
.ml-detail-list{display:flex;flex-direction:column;gap:0}
.ml-detail-item{display:flex;flex-direction:column;gap:2px;padding:10px 0;border-bottom:1px solid color-mix(in srgb, var(--border-color) 40%, transparent)}
.ml-detail-item:last-child{border-bottom:none}
.ml-detail-label{font-size:12px;color:var(--text-muted);display:flex;align-items:center}
.ml-detail-value{font-size:14px;color:var(--text-primary);word-break:break-all}

.fmgr-preview-link{display:block}
.fmgr-preview-image{width:100%;height:auto;display:block}

.fmgr-show-icon-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 16px;background:var(--bg-input);border:1px dashed var(--border-color);border-radius:12px;gap:12px}
.fmgr-show-icon-wrap i{font-size:96px}
.fmgr-show-icon-ext{font-size:18px;font-weight:600;color:var(--text-muted);text-transform:lowercase;letter-spacing:0.05em}

.fmgr-hash{font-family:'Courier New',monospace;font-size:12px}

.fmgr-copy-row{display:flex;gap:8px;align-items:stretch}
.fmgr-copy-row .fmgr-copy-input{flex-grow:1;font-family:'Courier New',monospace;font-size:12px;background:var(--bg-base)}
.fmgr-copy-row .btn-teal{flex-shrink:0;white-space:nowrap}

.fmgr-url-section{margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid color-mix(in srgb, var(--border-color) 40%, transparent)}
.fmgr-url-section-title{margin:0;font-size:14px;font-weight:600;color:var(--text-primary);display:flex;align-items:center}

.fmgr-divider{border:0;border-top:2px dashed var(--border-color);opacity:.5}
</style>
@endpush

@push('scripts')
<script>
'use strict';

// Tüm "Kopyala" butonları için tek handler
document.querySelectorAll('[data-fmgr-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-fmgr-copy-target');
        var input = document.getElementById(targetId);
        if (!input) return;

        var notify = function (success) {
            if (success) {
                if (typeof showToast === 'function') {
                    showToast('Kopyalandı', 'success');
                }
                var origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Kopyalandı';
                btn.disabled = true;
                setTimeout(function () {
                    btn.innerHTML = origHtml;
                    btn.disabled = false;
                }, 1500);
            } else if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({
                    title: 'Kopyalanamadı',
                    message: 'Tarayıcı izin vermedi. Metni elle seçip Ctrl+C ile kopyalayın.',
                    type: 'warning',
                });
            }
        };

        var text = input.value;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { notify(true); }, function () { fallbackCopy(); });
        } else {
            fallbackCopy();
        }

        function fallbackCopy() {
            try {
                input.select();
                var ok = document.execCommand('copy');
                notify(ok);
            } catch (e) {
                notify(false);
            }
        }
    });
});

function fmgrConfirmDelete(btn) {
    var id = btn.getAttribute('data-file-id');
    var name = btn.getAttribute('data-file-name');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    var doSubmit = function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/files/' + id;
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
        form.submit();
    };

    if (window.AdminModal && typeof AdminModal.confirm === 'function') {
        AdminModal.confirm({
            title: 'Dosya Silinsin Mi?',
            message: '<strong>' + name + '</strong> kalıcı olarak silinecek (dosya + DB kaydı). Geri alınamaz.',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
        }).then(function (confirmed) {
            if (confirmed) doSubmit();
        });
    } else if (window.confirm(name + ' silinsin mi?')) {
        doSubmit();
    }
}
</script>
@endpush
