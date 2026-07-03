@extends('layouts.admin')

@section('title', 'Toplu Plan #' . $bulkImport->id)
@section('page_title', 'Toplu Plan Durumu')
@section('page_description', 'CSV ile yüklenen postların işleme durumu.')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.instagram-posts.index') }}" class="breadcrumb-link"><i class="bi bi-instagram me-1"></i>Instagram Gönderileri</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.instagram-posts.bulk.form') }}" class="breadcrumb-link">Toplu Plan</a>
            </li>
            <li class="breadcrumb-item active text-teal">#{{ $bulkImport->id }}</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title">Toplu Plan #{{ $bulkImport->id }}</h1>
            <p class="page-subtitle">
                {{ $bulkImport->created_at->format('d.m.Y H:i') }} — {{ $bulkImport->author?->name ?? 'Bilinmeyen' }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass">
                <i class="bi bi-list-ul me-1"></i> Tüm Gönderiler
            </a>
            <a href="{{ route('admin.instagram-posts.bulk.form') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Yeni Toplu Plan
            </a>
        </div>
    </div>

    {{-- Status banner --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <div class="bulk-progress-wrap" data-bulk-status-url="{{ route('admin.instagram-posts.bulk.status', $bulkImport->id) }}">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <span class="bulk-status bulk-status-{{ $bulkImport->status }}" id="bulkStatusBadge">
                            {{ match($bulkImport->status) {
                                \App\Models\BulkImport::STATUS_PENDING        => 'Bekliyor',
                                \App\Models\BulkImport::STATUS_PROCESSING     => 'İşleniyor',
                                \App\Models\BulkImport::STATUS_COMPLETED      => 'Tamamlandı',
                                \App\Models\BulkImport::STATUS_PARTIAL_FAILED => 'Kısmen Başarılı',
                                \App\Models\BulkImport::STATUS_FAILED         => 'Başarısız',
                                default                                       => $bulkImport->status,
                            } }}
                        </span>
                        <small class="text-muted ms-2">
                            <span id="bulkProcessedCount">{{ $bulkImport->processed_rows }}</span> /
                            <span id="bulkTotalCount">{{ $bulkImport->total_rows }}</span> satır işlendi
                        </small>
                    </div>
                    <div>
                        <span class="text-success me-3"><i class="bi bi-check-circle me-1"></i><span id="bulkSuccessCount">{{ $bulkImport->success_count }}</span> başarılı</span>
                        <span class="text-danger"><i class="bi bi-x-circle me-1"></i><span id="bulkFailCount">{{ $bulkImport->fail_count }}</span> hata</span>
                    </div>
                </div>

                <div class="bulk-progress">
                    <div class="bulk-progress-bar" id="bulkProgressBar" data-pct="{{ $bulkImport->total_rows > 0 ? floor(($bulkImport->processed_rows / $bulkImport->total_rows) * 100) : 0 }}"></div>
                </div>
                <div class="text-center small mt-1">
                    <strong id="bulkProgressPct">{{ $bulkImport->total_rows > 0 ? floor(($bulkImport->processed_rows / $bulkImport->total_rows) * 100) : 0 }}%</strong>
                </div>

                <div class="bulk-finished-msg d-none" id="bulkFinishedMsg">
                    <hr class="my-3">
                    <div class="text-center">
                        <i class="bi bi-check-circle-fill text-success display-6"></i>
                        <h5 class="mt-2 mb-2">İşleme tamamlandı</h5>
                        <a href="{{ route('admin.instagram-posts.index') }}" class="btn-teal">
                            <i class="bi bi-list-ul me-1"></i> Tüm Gönderileri Gör
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Form-level seçilen template (varsa) bilgisi --}}
    @if($bulkImport->promptTemplate)
        <div class="card-dark mb-4">
            <div class="card-body-custom d-flex align-items-center gap-3">
                <i class="bi bi-stars text-purple" style="font-size:1.5rem;"></i>
                <div class="flex-grow-1">
                    <strong>Form-level AI Prompt Template:</strong>
                    <span class="text-teal">{{ $bulkImport->promptTemplate->name }}</span>
                    @if($bulkImport->promptTemplate->reference_image_path)
                        <span class="badge bg-info ms-2"><i class="bi bi-image-fill me-1"></i> Referans görsel ekli</span>
                    @endif
                    <small class="d-block text-muted mt-1">
                        Excel'de <code>prompt_template</code> kolonu doldurulmuş satırlar için override geçerli; diğerleri bu template'i kullandı.
                    </small>
                </div>
            </div>
        </div>
    @endif

    {{-- Üretilen post listesi --}}
    @if($posts->isNotEmpty())
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-teal"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <h6 class="mb-0">Üretilen Postlar ({{ $posts->count() }})</h6>
                        <small class="text-muted">AI ile üretilen görseller için "🔍 AI Detay" — gönderilen prompt + API yanıtını gösterir</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="cl-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px;">Görsel</th>
                                <th>Caption</th>
                                <th>Tip</th>
                                <th>Planlanan</th>
                                <th>Durum</th>
                                <th class="text-end" style="width:200px;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($posts as $post)
                                <tr>
                                    <td>
                                        @if($post->image_path)
                                            <img src="{{ upload_url($post->image_path, 'thumb') }}" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;" loading="lazy">
                                        @elseif($post->video_path)
                                            <span class="d-inline-flex align-items-center justify-content-center rounded" style="width:48px;height:48px;background:rgba(255,255,255,0.06);">
                                                <i class="bi bi-camera-reels"></i>
                                            </span>
                                        @else
                                            <span class="d-inline-flex align-items-center justify-content-center rounded text-muted" style="width:48px;height:48px;background:rgba(255,255,255,0.04);">
                                                <i class="bi bi-image"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small" style="max-width:380px;">
                                            {{ \Illuminate\Support\Str::limit((string) $post->caption, 100) ?: '—' }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="usr-status-badge">
                                            <i class="bi {{ $post->media_type?->icon() ?? 'bi-image' }} me-1"></i>
                                            {{ $post->media_type?->label() ?? 'Image' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $post->scheduled_at?->format('d.m.Y H:i') ?? '—' }}</small>
                                    </td>
                                    <td>
                                        <span class="usr-status-badge {{ $post->status?->badgeClass() ?? '' }}">
                                            <i class="bi {{ $post->status?->icon() ?? 'bi-circle' }} me-1"></i>
                                            {{ $post->status?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($post->aiGeneratedImage)
                                            <button type="button" class="btn-glass btn-glass-sm"
                                                    data-ai-image-detail="{{ $post->aiGeneratedImage->id }}"
                                                    title="Gönderilen prompt + API yanıtı">
                                                <i class="bi bi-search me-1"></i> AI Detay
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.instagram-posts.edit', $post->id) }}" class="btn-glass btn-glass-sm" title="Postu düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Hata listesi --}}
    <div class="card-dark mb-4 bulk-errors-card {{ ($bulkImport->errors === null || count($bulkImport->errors) === 0) ? 'd-none' : '' }}" id="bulkErrorsCard">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-red"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <h6 class="mb-0">Hata Listesi</h6>
                    <small class="text-muted"><span id="bulkErrorsCount">{{ count($bulkImport->errors ?? []) }}</span> satır işlenemedi</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <ul class="bulk-errors-list mb-0" id="bulkErrorsList">
                @foreach($bulkImport->errors ?? [] as $err)
                <li>
                    <strong>Satır {{ $err['row'] ?? '?' }}:</strong>
                    {{ $err['message'] ?? 'Bilinmeyen hata' }}
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- AI Detay Modal (shared partial) --}}
    @include('admin._partials.ai-image-detail-modal')

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/instagram-bulk.js') }}"></script>
@endpush
