@extends('layouts.admin')

@section('title', 'Mail Detayı - ' . $mailLog->subject)

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.mail-logs.index') }}" class="breadcrumb-link">Mail Logları</a>
            </li>
            <li class="breadcrumb-item active text-teal">Mail Detayı</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-envelope-open me-2"></i>Mail Detayı</h1>
            <p class="page-subtitle">{{ Str::limit($mailLog->subject, 80) }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($mailLog->status === \App\Enums\MailLogStatus::Pending)
                {{-- Bekleyen mail listede olduğu gibi buradan da beklemeden gönderilebilir. --}}
                <button type="button" class="btn-teal ml-send-now"
                        data-url="{{ route('admin.mail-logs.send-now', $mailLog) }}"
                        data-recipient="{{ $mailLog->to }}"
                        data-subject="{{ $mailLog->subject }}">
                    <i class="bi bi-send-fill me-1"></i> Şimdi Gönder
                </button>
            @elseif($mailLog->body)
                <button type="button" class="btn-teal" id="resendBtn">
                    <i class="bi bi-arrow-repeat me-1"></i> Yeniden Gönder
                </button>
            @endif
            <a href="{{ route('admin.mail-logs.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Geri Dön
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Sol: Mail Bilgileri --}}
        <div class="col-xl-4" data-aos="fade-up">
            <div class="card-dark h-100">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-info-circle me-1"></i> Mail Bilgileri</h6>

                    <div class="ml-detail-list">
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-person me-1"></i> Alıcı</span>
                            <span class="ml-detail-value">{{ $mailLog->to }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-send me-1"></i> Gönderen</span>
                            <span class="ml-detail-value">{{ $mailLog->from ?? '-' }}</span>
                        </div>
                        @if($mailLog->cc)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-people me-1"></i> CC</span>
                                <span class="ml-detail-value">{{ $mailLog->cc }}</span>
                            </div>
                        @endif
                        @if($mailLog->bcc)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-people-fill me-1"></i> BCC</span>
                                <span class="ml-detail-value">{{ $mailLog->bcc }}</span>
                            </div>
                        @endif
                        @if($mailLog->reply_to)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-reply me-1"></i> Reply-To</span>
                                <span class="ml-detail-value">{{ $mailLog->reply_to }}</span>
                            </div>
                        @endif
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-chat-left-text me-1"></i> Konu</span>
                            <span class="ml-detail-value fw-semibold">{{ $mailLog->subject }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-code-square me-1"></i> Mailable</span>
                            <span class="ml-detail-value">{{ $mailLog->short_mailable }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-flag me-1"></i> Durum</span>
                            <span class="ml-detail-value">
                                <span class="ord-status-badge {{ $mailLog->status->cssClass() }}"><i class="bi {{ $mailLog->status->icon() }}"></i> {{ $mailLog->status->label() }}</span>
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-calendar-plus me-1"></i> Oluşturulma</span>
                            <span class="ml-detail-value">{{ $mailLog->created_at->translatedFormat('d M Y H:i:s') }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-calendar-check me-1"></i> Gönderilme</span>
                            <span class="ml-detail-value">{{ $mailLog->sent_at?->translatedFormat('d M Y H:i:s') ?? '-' }}</span>
                        </div>
                        @if($mailLog->ip_address)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-globe me-1"></i> IP Adresi</span>
                                <span class="ml-detail-value">{{ $mailLog->ip_address }}</span>
                            </div>
                        @endif
                        @if($mailLog->user)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-person-check me-1"></i> Tetikleyen</span>
                                <span class="ml-detail-value">{{ $mailLog->user->full_name }}</span>
                            </div>
                        @endif
                    </div>

                    @if($mailLog->error_message)
                        <div class="mt-3 p-3 rounded" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">
                            <small class="text-danger fw-semibold d-block mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Hata Mesajı</small>
                            <span class="text-danger">{{ $mailLog->error_message }}</span>
                        </div>
                    @endif

                    @if($mailLog->metadata && count($mailLog->metadata) > 0)
                        <div class="mt-3">
                            <small class="text-clr-muted d-block mb-2"><i class="bi bi-braces me-1"></i> Metadata</small>
                            <pre class="mb-0 p-2 rounded ml-metadata-pre">{{ json_encode($mailLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sağ: E-posta İçeriği --}}
        <div class="col-xl-8" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark h-100">
                <div class="card-body-custom">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-teal mb-0"><i class="bi bi-file-earmark-richtext me-1"></i> E-posta İçeriği</h6>
                        @if($mailLog->body)
                            <button type="button" class="btn-glass btn-sm" id="bodyFullscreenBtn" title="Tam Ekran">
                                <i class="bi bi-arrows-fullscreen"></i>
                            </button>
                        @endif
                    </div>
                    @if($mailLog->body)
                        <div class="ml-body-wrapper">
                            <iframe id="bodyFrame" class="ml-body-frame" loading="lazy"></iframe>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x d-block fs-1 mb-2"></i>
                            <p class="mb-0">E-posta içeriği kaydedilmemiş.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
/* Mail detail list */
.ml-detail-list{display:flex;flex-direction:column;gap:0}
.ml-detail-item{display:flex;flex-direction:column;gap:2px;padding:10px 0;border-bottom:1px solid color-mix(in srgb, var(--border-color) 40%, transparent)}
.ml-detail-item:last-child{border-bottom:none}
.ml-detail-label{font-size:12px;color:var(--text-muted);display:flex;align-items:center}
.ml-detail-value{font-size:14px;color:var(--text-primary);word-break:break-all}

/* Metadata pre */
.ml-metadata-pre{font-size:12px;background:var(--bg-input);color:var(--text-muted);border:1px solid var(--border-color);max-height:150px;overflow:auto}

/* Body iframe wrapper */
.ml-body-wrapper{border-radius:8px;overflow:hidden;border:1px solid var(--border-color);background:#fff}
.ml-body-frame{width:100%;border:none;display:block;background:#fff}

/* Fullscreen */
.ml-body-wrapper.ml-fullscreen{position:fixed;inset:0;z-index:9999;border-radius:0;border:none}
.ml-body-wrapper.ml-fullscreen .ml-body-frame{min-height:100vh}
</style>
@endpush

@push('scripts')
{{-- "Şimdi Gönder" düğmesinin davranışı listeyle ortak. --}}
<script src="{{ versioned_asset('assets/admin/js/mail-logs.js') }}"></script>
<script>
(function() {
    'use strict';

    var frame = document.getElementById('bodyFrame');

    // Load body content immediately
    if (frame) {
        fetch('{{ route("admin.mail-logs.body", $mailLog) }}', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var doc = frame.contentDocument || frame.contentWindow.document;
            doc.open();
            doc.write(data.body || '<p style="padding:20px;color:#666;">İçerik bulunamadı.</p>');
            doc.close();

            // Auto-resize iframe to fit content
            function resizeFrame() {
                try {
                    var body = frame.contentDocument.body;
                    var html = frame.contentDocument.documentElement;
                    var height = Math.max(body.scrollHeight, body.offsetHeight, html.scrollHeight, html.offsetHeight);
                    frame.style.height = height + 'px';
                } catch(e) {}
            }
            frame.addEventListener('load', resizeFrame);
            setTimeout(resizeFrame, 200);
            setTimeout(resizeFrame, 1000);
        });

        // Fullscreen toggle
        var fullscreenBtn = document.getElementById('bodyFullscreenBtn');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function() {
                var wrapper = document.querySelector('.ml-body-wrapper');
                wrapper.classList.toggle('ml-fullscreen');
                var icon = fullscreenBtn.querySelector('i');
                if (wrapper.classList.contains('ml-fullscreen')) {
                    icon.className = 'bi bi-fullscreen-exit';
                } else {
                    icon.className = 'bi bi-arrows-fullscreen';
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    var wrapper = document.querySelector('.ml-body-wrapper');
                    if (wrapper && wrapper.classList.contains('ml-fullscreen')) {
                        wrapper.classList.remove('ml-fullscreen');
                        fullscreenBtn.querySelector('i').className = 'bi bi-arrows-fullscreen';
                    }
                }
            });
        }
    }

    // Resend with AdminModal.confirm
    var resendBtn = document.getElementById('resendBtn');
    if (resendBtn) {
        resendBtn.addEventListener('click', function() {
            AdminModal.confirm({
                title: 'Yeniden Gönder',
                message: 'Bu e-postayı aynı alıcıya yeniden göndermek istediğinize emin misiniz?',
                type: 'warning',
                confirmText: 'Evet, Gönder',
                confirmIcon: 'bi bi-arrow-repeat'
            }).then(function(confirmed) {
                if (!confirmed) return;
                resendBtn.disabled = true;
                resendBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Gönderiliyor...';
                fetch('{{ route("admin.mail-logs.resend", $mailLog) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    AdminModal.status({
                        title: data.success ? 'Başarılı' : 'Hata',
                        message: data.message,
                        type: data.success ? 'success' : 'danger'
                    });
                })
                .catch(function() {
                    AdminModal.status({ title: 'Hata', message: 'E-posta yeniden gönderilemedi.', type: 'danger' });
                })
                .finally(function() {
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Yeniden Gönder';
                });
            });
        });
    }
})();
</script>
@endpush
