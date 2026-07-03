@extends('layouts.admin')

@section('title', 'AI Görsel Log Detayı - #' . $log->id)

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.ai-image-logs.index') }}" class="breadcrumb-link">AI Görsel Logları</a>
            </li>
            <li class="breadcrumb-item active text-teal">Log #{{ $log->id }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-image me-2"></i>AI Görsel Log Detayı</h1>
            <p class="page-subtitle">
                <span class="badge {{ \App\Services\AiImageLogService::sourceColor($log->source) }} me-2">
                    {{ \App\Services\AiImageLogService::sourceLabel($log->source) }}
                </span>
                {{ $log->created_at->translatedFormat('d F Y H:i:s') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ai-image-logs.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Geri Dön
            </a>
            <button type="button" class="btn-glass ail-delete-trigger"
                    data-log-id="{{ $log->id }}"
                    onclick="confirmDeleteAiImageLog(this)">
                <i class="bi bi-trash me-1"></i> Sil
            </button>
        </div>
    </div>

    <div class="row g-4">
        {{-- Sol: Görsel + Log Bilgileri --}}
        <div class="col-xl-5" data-aos="fade-up">
            {{-- Üretilen görsel --}}
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-image me-1"></i> Üretilen Görsel</h6>

                    @if($log->isCompleted() && $log->image_path)
                        <a href="{{ upload_url($log->image_path, 'lg') }}" target="_blank" class="d-block ail-preview-link">
                            <img src="{{ upload_url($log->image_path, 'lg') }}"
                                 alt="AI görsel #{{ $log->id }}"
                                 loading="lazy"
                                 class="img-fluid rounded ail-preview-image">
                        </a>
                        <div class="d-flex justify-content-between align-items-center mt-3 ail-preview-meta">
                            <code class="small">{{ $log->image_path }}</code>
                            <a href="{{ upload_url($log->image_path, 'lg') }}" target="_blank" class="btn-glass btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Tam Boyut
                            </a>
                        </div>
                    @elseif($log->isFailed())
                        <div class="ail-preview-empty ail-preview-empty--fail">
                            <i class="bi bi-x-octagon"></i>
                            <span>Görsel üretilemedi</span>
                        </div>
                    @else
                        <div class="ail-preview-empty">
                            <i class="bi bi-hourglass-split"></i>
                            <span>Beklemede</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Log bilgileri --}}
            <div class="card-dark">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-info-circle me-1"></i> Log Bilgileri</h6>

                    <div class="ml-detail-list">
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-hash me-1"></i> Log ID</span>
                            <span class="ml-detail-value">#{{ $log->id }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-flag me-1"></i> Durum</span>
                            <span class="ml-detail-value">
                                @if($log->isCompleted())
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Başarılı</span>
                                @elseif($log->isFailed())
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Başarısız</span>
                                @else
                                    <span class="badge bg-secondary"><i class="bi bi-hourglass-split me-1"></i>Beklemede</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-tags me-1"></i> Kaynak</span>
                            <span class="ml-detail-value">
                                <span class="badge {{ \App\Services\AiImageLogService::sourceColor($log->source) }}">
                                    {{ \App\Services\AiImageLogService::sourceLabel($log->source) }}
                                </span>
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-cpu me-1"></i> Kullanılan Model</span>
                            <span class="ml-detail-value">{{ $log->model ?? '-' }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-aspect-ratio me-1"></i> Aspect Ratio</span>
                            <span class="ml-detail-value">{{ $log->aspect_ratio }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-rulers me-1"></i> Görsel Ölçüsü</span>
                            <span class="ml-detail-value">
                                @if($log->width && $log->height)
                                    {{ $log->width }} × {{ $log->height }} px
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-cash-stack me-1"></i> Maliyet (USD)</span>
                            <span class="ml-detail-value">
                                @if($log->cost_usd !== null)
                                    ${{ number_format((float) $log->cost_usd, 6) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-stopwatch me-1"></i> API Süresi</span>
                            <span class="ml-detail-value">
                                @if($log->generation_time_ms)
                                    {{ number_format($log->generation_time_ms) }} ms
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-arrow-repeat me-1"></i> Deneme Sayısı</span>
                            <span class="ml-detail-value">{{ $log->attempt_count ?? '-' }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-cloud me-1"></i> Provider</span>
                            <span class="ml-detail-value">{{ $log->provider ?? '-' }}</span>
                        </div>
                        @if($log->blog_post_id)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-journal-text me-1"></i> Blog Post ID</span>
                                <span class="ml-detail-value">
                                    <a href="{{ route('admin.blog-posts.edit', $log->blog_post_id) }}" class="text-teal">
                                        #{{ $log->blog_post_id }}
                                    </a>
                                </span>
                            </div>
                        @endif
                        @if($log->instagram_post_id)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-instagram me-1"></i> Instagram Post ID</span>
                                <span class="ml-detail-value">#{{ $log->instagram_post_id }}</span>
                            </div>
                        @endif
                        @if($log->promptTemplate)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-bookmark me-1"></i> Prompt Şablonu</span>
                                <span class="ml-detail-value">{{ $log->promptTemplate->name }}</span>
                            </div>
                        @endif
                        @if($log->author)
                            <div class="ml-detail-item">
                                <span class="ml-detail-label"><i class="bi bi-person me-1"></i> Üreten</span>
                                <span class="ml-detail-value">{{ $log->author->name }}</span>
                            </div>
                        @endif
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-calendar-plus me-1"></i> Oluşturulma</span>
                            <span class="ml-detail-value">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</span>
                        </div>
                    </div>

                    @if($log->error_message)
                        <div class="mt-3 p-3 rounded ail-error-box">
                            <small class="text-danger fw-semibold d-block mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Hata Mesajı</small>
                            <span class="text-danger">{{ $log->error_message }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sağ: Prompt + Request/Response JSON --}}
        <div class="col-xl-7" data-aos="fade-up" data-aos-delay="100">
            {{-- Prompt --}}
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-chat-left-text me-1"></i> Gönderilen Prompt</h6>
                    <pre class="mb-0 p-3 rounded ml-metadata-pre ail-prompt-pre">{{ $log->final_prompt }}</pre>
                </div>
            </div>

            {{-- Request Payload --}}
            @if(! empty($log->request_payload))
                <div class="card-dark mb-4">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-teal mb-0"><i class="bi bi-arrow-up-circle me-1"></i> Request Payload</h6>
                            <button type="button" class="btn-glass btn-sm" onclick="ailToggle('reqWrap', 'reqBtn')" id="reqBtn">
                                <i class="bi bi-eye me-1"></i> Göster
                            </button>
                        </div>
                        <div id="reqWrap" class="d-none">
                            <pre class="mb-0 p-3 rounded ml-metadata-pre ail-payload-pre">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Response Payload --}}
            @if(! empty($log->response_payload))
                <div class="card-dark">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-teal mb-0"><i class="bi bi-arrow-down-circle me-1"></i> Response Payload</h6>
                            <button type="button" class="btn-glass btn-sm" onclick="ailToggle('resWrap', 'resBtn')" id="resBtn">
                                <i class="bi bi-eye me-1"></i> Göster
                            </button>
                        </div>
                        <div id="resWrap" class="d-none">
                            <pre class="mb-0 p-3 rounded ml-metadata-pre ail-payload-pre">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>
            @endif
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
.ml-metadata-pre{font-size:12px;background:var(--bg-input);color:var(--text-muted);border:1px solid var(--border-color)}
.ail-preview-link{display:block}
.ail-preview-image{width:100%;height:auto;display:block}
.ail-preview-meta code{color:var(--text-muted);word-break:break-all}
.ail-preview-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 16px;background:var(--bg-input);border:1px dashed var(--border-color);border-radius:8px;color:var(--text-muted);gap:10px}
.ail-preview-empty i{font-size:48px}
.ail-preview-empty--fail{color:#ef4444;border-color:rgba(239,68,68,.4)}
.ail-error-box{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)}
.ail-prompt-pre{white-space:pre-wrap;max-height:400px;overflow:auto}
.ail-payload-pre{white-space:pre-wrap;max-height:500px;overflow:auto;word-break:break-all}
</style>
@endpush

@push('scripts')
<script>
function ailToggle(wrapId, btnId) {
    var wrap = document.getElementById(wrapId);
    var btn  = document.getElementById(btnId);
    if (! wrap || ! btn) return;
    if (wrap.classList.contains('d-none')) {
        wrap.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Gizle';
    } else {
        wrap.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Göster';
    }
}

function confirmDeleteAiImageLog(btn) {
    var id = btn.getAttribute('data-log-id');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    var doSubmit = function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/ai-image-logs/' + id;
        form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">' +
                         '<input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
        form.submit();
    };

    if (! window.AdminModal || typeof AdminModal.confirm !== 'function') {
        if (window.confirm('Log #' + id + ' silinsin mi? Bu işlem geri alınamaz.')) {
            doSubmit();
        }
        return;
    }

    AdminModal.confirm({
        title: 'AI Görsel Log Silinsin Mi?',
        message: '<strong>Log #' + id + '</strong> kaydını ve ilişkili görseli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
        type: 'danger',
        confirmText: 'Evet, Sil',
        confirmIcon: 'bi bi-trash3',
    }).then(function (confirmed) {
        if (confirmed) {
            doSubmit();
        }
    });
}
</script>
@endpush
