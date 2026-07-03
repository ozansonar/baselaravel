@extends('layouts.admin')

@section('title', 'AI Log Detayı - #' . $log->id)

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.ai-logs.index') }}" class="breadcrumb-link">AI İçerik Logları</a>
            </li>
            <li class="breadcrumb-item active text-teal">Log #{{ $log->id }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-robot me-2"></i>AI Log Detayı</h1>
            <p class="page-subtitle">{{ $log->product_name ?? 'Bilinmeyen Ürün' }} &middot; {{ $log->created_at->translatedFormat('d F Y H:i:s') }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($log->blogPost)
                <a href="{{ route('admin.blog-posts.edit', $log->blog_post_id) }}" class="btn-teal">
                    <i class="bi bi-journal-text me-1"></i> Blog Yazısını Düzenle
                </a>
            @endif
            <a href="{{ route('admin.ai-logs.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Geri Dön
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Sol: Log Bilgileri --}}
        <div class="col-xl-4" data-aos="fade-up">
            <div class="card-dark h-100">
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
                                <span class="ord-status-badge {{ $log->status->cssClass() }}">
                                    <i class="bi {{ $log->status->icon() }}"></i> {{ $log->status->label() }}
                                </span>
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-tags me-1"></i> Kaynak</span>
                            <span class="ml-detail-value">
                                @if($log->source)
                                    <span class="ord-status-badge {{ $log->source->cssClass() }}">
                                        <i class="bi {{ $log->source->icon() }}"></i> {{ $log->source->label() }}
                                    </span>
                                @else
                                    <span>-</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-file-text me-1"></i> Ürün / Başlık</span>
                            <span class="ml-detail-value fw-semibold">{{ $log->product_name ?? '-' }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-box-seam me-1"></i> Seçilen Ürün</span>
                            <span class="ml-detail-value fw-semibold">
                                @if($log->selected_product)
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ $log->selected_product }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-geo-alt me-1"></i> Hedef Şehir</span>
                            <span class="ml-detail-value fw-semibold">
                                @if($log->city_name)
                                    <i class="bi bi-geo-alt-fill text-teal me-1"></i>{{ $log->city_name }}
                                @elseif($log->source && $log->source->value === 'blog_scheduled')
                                    <span class="text-muted"><i class="bi bi-globe me-1"></i>Genel yazı (şehirsiz)</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-stopwatch me-1"></i> API Süresi</span>
                            <span class="ml-detail-value">{{ $log->duration_formatted }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-speedometer me-1"></i> Token Kullanımı</span>
                            <span class="ml-detail-value">{{ $log->token_count ? number_format($log->token_count) : '-' }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-cpu me-1"></i> Kullanılan Model</span>
                            <span class="ml-detail-value">{{ $log->used_model ?? '-' }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-arrow-repeat me-1"></i> Toplam Deneme</span>
                            <span class="ml-detail-value">{{ $log->attempt_count ?? '-' }}</span>
                        </div>
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-calendar-plus me-1"></i> Oluşturulma</span>
                            <span class="ml-detail-value">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</span>
                        </div>
                        @if($log->blogPost)
                        <div class="ml-detail-item">
                            <span class="ml-detail-label"><i class="bi bi-journal-text me-1"></i> Blog Yazısı</span>
                            <span class="ml-detail-value">
                                <a href="{{ route('admin.blog-posts.edit', $log->blog_post_id) }}" class="text-teal">
                                    {{ Str::limit($log->blogPost->title, 60) }}
                                </a>
                            </span>
                        </div>
                        @endif
                    </div>

                    @if($log->error_message)
                        <div class="mt-3 p-3 rounded" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">
                            <small class="text-danger fw-semibold d-block mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Hata Mesajı</small>
                            <span class="text-danger">{{ $log->error_message }}</span>
                        </div>
                    @endif

                    @if($log->error_details)
                        <div class="mt-3">
                            <small class="text-clr-muted d-block mb-2"><i class="bi bi-bug me-1"></i> Hata Detayı (Stack Trace)</small>
                            <pre class="mb-0 p-2 rounded ml-metadata-pre">{{ Str::limit($log->error_details, 2000) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sağ: İçerik Detayları --}}
        <div class="col-xl-8" data-aos="fade-up" data-aos-delay="100">
            {{-- Parsed Response --}}
            @if($log->parsed_response)
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <h6 class="text-teal mb-3"><i class="bi bi-file-earmark-richtext me-1"></i> Oluşturulan İçerik</h6>

                    @if(isset($log->parsed_response['title']))
                    <div class="mb-3">
                        <small class="text-clr-muted d-block mb-1">Başlık</small>
                        <span class="fw-semibold" style="color:var(--text-primary)">{{ $log->parsed_response['title'] }}</span>
                    </div>
                    @endif

                    @if(isset($log->parsed_response['description']))
                    <div class="mb-3">
                        <small class="text-clr-muted d-block mb-1">Açıklama (Meta Description)</small>
                        <span style="color:var(--text-secondary)">{{ $log->parsed_response['description'] }}</span>
                    </div>
                    @endif

                    @if(isset($log->parsed_response['content']))
                    <div class="mb-0">
                        <small class="text-clr-muted d-block mb-2">İçerik (HTML)</small>
                        <div class="p-3 rounded" style="background:var(--bg-input);border:1px solid var(--border-color);max-height:400px;overflow-y:auto;color:var(--text-primary)">
                            {!! $log->parsed_response['content'] !!}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- AI Kapak Görseli Teşhisi --}}
            @if(isset($log->parsed_response['_cover_image']) && is_array($log->parsed_response['_cover_image']))
                @php($cover = $log->parsed_response['_cover_image'])
                <div class="card-dark mb-4">
                    <div class="card-body-custom">
                        <h6 class="mb-3" style="color:{{ ($cover['success'] ?? false) ? '#22c55e' : '#f59e0b' }}">
                            <i class="bi bi-image me-1"></i> AI Kapak Görseli
                            @if($cover['success'] ?? false)
                                <span class="badge bg-success ms-2">Başarılı</span>
                            @else
                                <span class="badge bg-warning ms-2">Üretilemedi — Placeholder kullanıldı</span>
                            @endif
                        </h6>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-clr-muted d-block mb-1">Deneme Sayısı</small>
                                <span class="fw-semibold" style="color:var(--text-primary)">{{ $cover['attempt_count'] ?? '-' }} / 3</span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-clr-muted d-block mb-1">Vision Doğrulama</small>
                                <span class="fw-semibold" style="color:var(--text-primary)">
                                    {{ ($cover['verify_passed'] ?? false) ? 'Geçti' : 'Geçmedi / Atlandı' }}
                                </span>
                            </div>
                            @if(! empty($cover['image_path']))
                            <div class="col-12">
                                <small class="text-clr-muted d-block mb-1">Kayıtlı Görsel Yolu</small>
                                <code style="color:var(--text-primary);word-break:break-all">{{ $cover['image_path'] }}</code>
                            </div>
                            @endif
                            <div class="col-12">
                                <small class="text-clr-muted d-block mb-1">Sebep / Açıklama</small>
                                <span style="color:var(--text-secondary)">{{ $cover['reason'] ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Raw API Response --}}
            @if($log->raw_response)
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-teal mb-0"><i class="bi bi-code-square me-1"></i> Ham API Yanıtı</h6>
                        <button type="button" class="btn-glass btn-sm" onclick="toggleRawResponse()" id="toggleRawBtn">
                            <i class="bi bi-eye me-1"></i> Göster
                        </button>
                    </div>
                    <div id="rawResponseWrap" class="d-none">
                        <pre class="mb-0 p-3 rounded ml-metadata-pre" style="max-height:400px;overflow:auto;white-space:pre-wrap;word-break:break-all">{{ Str::limit($log->raw_response, 5000) }}</pre>
                    </div>
                </div>
            </div>
            @endif

            {{-- Prompt --}}
            @if($log->prompt)
            <div class="card-dark">
                <div class="card-body-custom">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="text-teal mb-0"><i class="bi bi-chat-left-text me-1"></i> Gönderilen Prompt</h6>
                        <button type="button" class="btn-glass btn-sm" onclick="togglePrompt()" id="togglePromptBtn">
                            <i class="bi bi-eye me-1"></i> Göster
                        </button>
                    </div>
                    <div id="promptWrap" class="d-none">
                        <pre class="mb-0 p-3 rounded ml-metadata-pre" style="max-height:400px;overflow:auto;white-space:pre-wrap">{{ $log->prompt }}</pre>
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
</style>
@endpush

@push('scripts')
<script>
function toggleRawResponse() {
    var wrap = document.getElementById('rawResponseWrap');
    var btn = document.getElementById('toggleRawBtn');
    if (wrap.classList.contains('d-none')) {
        wrap.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Gizle';
    } else {
        wrap.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Göster';
    }
}

function togglePrompt() {
    var wrap = document.getElementById('promptWrap');
    var btn = document.getElementById('togglePromptBtn');
    if (wrap.classList.contains('d-none')) {
        wrap.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Gizle';
    } else {
        wrap.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Göster';
    }
}
</script>
@endpush
