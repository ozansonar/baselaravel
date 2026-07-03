@extends('layouts.admin')

@section('title', 'Gönderi Detayı #' . $post->id)
@section('page_title', 'Gönderi Detayı')
@section('page_description', 'Yayınlanmış Instagram + Facebook gönderisinin tüm detayları.')

@push('styles')
<style>
.ig-show-img-main{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.06);object-fit:cover}
.ig-show-img-main.is-video{background:#000;max-height:600px}
.ig-show-img-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(120px, 1fr));gap:8px;margin-top:10px}
.ig-show-img-grid img{width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,.06)}
.ig-show-meta-row{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)}
.ig-show-meta-row:last-child{border-bottom:none}
.ig-show-meta-label{color:var(--text-muted, #64748b);font-size:13px;font-weight:500;flex-shrink:0}
.ig-show-meta-value{color:var(--text-primary, #f1f5f9);font-size:13px;font-weight:600;text-align:right;word-break:break-all}
.ig-show-caption-block{background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.05);border-radius:8px;padding:14px 16px;font-size:14px;line-height:1.6;white-space:pre-wrap;word-break:break-word}
.ig-show-hashtag{display:inline-block;background:rgba(94,234,212,.10);color:#5eead4;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;margin:2px 4px 2px 0}
.ig-show-status-block{padding:12px 16px;border-radius:8px;font-size:13px;line-height:1.55}
.ig-show-status-block.is-success{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#22c55e}
.ig-show-status-block.is-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.30);color:#fecaca}
.ig-show-status-block.is-neutral{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-muted, #94a3b8)}
.ig-show-status-title{font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.ig-show-metrics-grid{display:grid;grid-template-columns:repeat(2, 1fr);gap:10px;margin-bottom:8px}
.ig-show-metric-cell{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);border-radius:8px;padding:10px 12px;text-align:center}
.ig-show-metric-icon{font-size:18px;margin-bottom:4px}
.ig-show-metric-icon.is-likes{color:#ef4444}
.ig-show-metric-icon.is-comments{color:#3b82f6}
.ig-show-metric-icon.is-reach{color:#22c55e}
.ig-show-metric-icon.is-impressions{color:#fbbf24}
.ig-show-metric-icon.is-saved{color:#a855f7}
.ig-show-metric-value{font-size:20px;font-weight:700;color:var(--text-primary, #f1f5f9);line-height:1.1}
.ig-show-metric-label{font-size:11px;color:var(--text-muted, #94a3b8);text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.ig-show-metric-empty{font-size:12px;color:var(--text-muted, #94a3b8);text-align:center;padding:8px}
</style>
@endpush

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.instagram-posts.index') }}" class="breadcrumb-link"><i class="bi bi-instagram me-1"></i>Instagram Gönderileri</a>
            </li>
            <li class="breadcrumb-item active text-teal">#{{ $post->id }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title">Gönderi Detayı <span class="text-muted">#{{ $post->id }}</span></h1>
            <p class="page-subtitle">
                <span class="usr-status-badge {{ $post->status->badgeClass() }}">
                    <i class="bi {{ $post->status->icon() }} me-1"></i>{{ $post->status->label() }}
                </span>
                @if($post->published_at)
                    <span class="ms-2 text-muted">— {{ $post->published_at->format('d.m.Y H:i') }} tarihinde yayınlandı</span>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($post->permalink)
            <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="btn-glass">
                <i class="bi bi-instagram me-1"></i> Instagram'da Gör
            </a>
            @endif
            @if($post->fb_permalink)
            <a href="{{ $post->fb_permalink }}" target="_blank" rel="noopener" class="btn-glass">
                <i class="bi bi-facebook me-1"></i> Facebook'ta Gör
            </a>
            @endif
            <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Listeye Dön
            </a>
        </div>
    </div>

    <div class="row g-4">

        {{-- ════════ SOL: Görsel(ler) + Caption ════════ --}}
        <div class="col-lg-7">

            {{-- Medya --}}
            @php
                $mediaType = $post->media_type ?? \App\Enums\InstagramMediaType::Image;
                $hasVideo = ! empty($post->video_path);
                $hasImage = ! empty($post->image_path);
            @endphp
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi {{ $mediaType->icon() }}"></i></div>
                        <div>
                            <h6 class="mb-0">Medya — {{ $mediaType->label() }}</h6>
                            <small class="text-muted">
                                @if($mediaType === \App\Enums\InstagramMediaType::Image)
                                    @php $imgCount = 1 + $post->additionalImages->count(); @endphp
                                    @if($imgCount === 1)
                                        Tek görsel
                                    @else
                                        Carousel — {{ $imgCount }} görsel
                                    @endif
                                @elseif($mediaType === \App\Enums\InstagramMediaType::Reels)
                                    Reels video
                                    @if($post->video_duration_seconds)
                                        ({{ $post->video_duration_seconds }} sn)
                                    @endif
                                @else
                                    Story
                                    @if($hasVideo) (video) @elseif($hasImage) (görsel) @endif
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @if($hasVideo)
                        <video controls preload="metadata" class="ig-show-img-main is-video">
                            <source src="{{ upload_url($post->video_path) }}" type="video/mp4">
                            Tarayıcınız video oynatmayı desteklemiyor.
                        </video>
                    @elseif($hasImage)
                        <img src="{{ upload_url($post->image_path, 'lg') }}" alt="Ana görsel" class="ig-show-img-main">
                    @else
                        <div class="text-muted text-center py-4">
                            <i class="bi bi-question-circle me-1"></i> Medya dosyası bulunamadı.
                        </div>
                    @endif

                    @if($mediaType === \App\Enums\InstagramMediaType::Image && $post->additionalImages->isNotEmpty())
                    <div class="ig-show-img-grid">
                        @foreach($post->additionalImages as $img)
                            <img src="{{ upload_url($img->image_path, 'sm') }}" alt="Ek görsel {{ $loop->iteration }}">
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Caption --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-chat-text"></i></div>
                        <div>
                            <h6 class="mb-0">İçerik (Caption)</h6>
                            <small class="text-muted">{{ mb_strlen((string) $post->caption) }} karakter</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @if(!empty($post->caption))
                        <div class="ig-show-caption-block">{{ $post->caption }}</div>
                    @else
                        <span class="text-muted">Caption yok.</span>
                    @endif

                    @if(is_array($post->hashtags) && count($post->hashtags) > 0)
                    <div class="mt-3">
                        <small class="text-muted d-block mb-2"><i class="bi bi-hash me-1"></i>{{ count($post->hashtags) }} hashtag</small>
                        @foreach($post->hashtags as $tag)
                            <span class="ig-show-hashtag">#{{ ltrim($tag, '#') }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- API Geçmişi --}}
            @if($post->logs->isNotEmpty())
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-red"><i class="bi bi-journal-text"></i></div>
                        <div>
                            <h6 class="mb-0">API Geçmişi</h6>
                            <small class="text-muted">{{ $post->logs->count() }} kayıt — son {{ min($post->logs->count(), 20) }} gösteriliyor</small>
                        </div>
                    </div>
                    <a href="{{ route('admin.instagram-logs.index', ['post_id' => $post->id]) }}" class="btn-glass btn-glass-sm">
                        <i class="bi bi-arrow-up-right-square me-1"></i> Tümünü Gör
                    </a>
                </div>
                <div class="card-body-custom">
                    <div class="small">
                        @foreach($post->logs->take(20) as $log)
                        <div class="d-flex justify-content-between align-items-start py-2 border-bottom border-secondary">
                            <div class="flex-grow-1">
                                <strong><code>{{ $log->action }}</code></strong>
                                <div class="text-muted">
                                    {{ $log->created_at->format('d.m.Y H:i:s') }}
                                    @if($log->api_duration_ms !== null)
                                        — <span class="text-muted">{{ number_format($log->api_duration_ms, 0, ',', '.') }} ms</span>
                                    @endif
                                </div>
                                @if($log->error_message)
                                <div class="text-danger small mt-1">{{ \Illuminate\Support\Str::limit($log->error_message, 200) }}</div>
                                @endif
                            </div>
                            <span class="usr-status-badge {{ $log->status === 'success' ? 'active' : 'inactive' }} ms-2">
                                {{ $log->status }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

        </div>

        {{-- ════════ SAĞ: Bilgiler ════════ --}}
        <div class="col-lg-5">

            {{-- Genel Bilgi --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-info-circle"></i></div>
                        <div>
                            <h6 class="mb-0">Genel Bilgi</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Post ID</span>
                        <span class="ig-show-meta-value">#{{ $post->id }}</span>
                    </div>
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Oluşturan</span>
                        <span class="ig-show-meta-value">{{ $post->author?->name ?? '—' }}</span>
                    </div>
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Oluşturulma</span>
                        <span class="ig-show-meta-value">{{ $post->created_at?->format('d.m.Y H:i') ?? '—' }}</span>
                    </div>
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Son Güncelleme</span>
                        <span class="ig-show-meta-value">{{ $post->updated_at?->format('d.m.Y H:i') ?? '—' }}</span>
                    </div>
                    @if($post->retry_count > 0)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Toplam Deneme</span>
                        <span class="ig-show-meta-value">{{ $post->retry_count }} kez</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Engagement Metrics --}}
            @if($post->status === \App\Enums\InstagramPostStatus::Published)
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-orange"><i class="bi bi-graph-up"></i></div>
                        <div>
                            <h6 class="mb-0">Etkileşim İstatistikleri</h6>
                            <small class="text-muted">
                                @if($post->metrics_fetched_at)
                                    Son güncelleme: {{ $post->metrics_fetched_at->diffForHumans() }}
                                @else
                                    Henüz veri çekilmedi (cron 04:30'da çalışır)
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @if($post->like_count !== null || $post->reach !== null)
                    <div class="ig-show-metrics-grid">
                        <div class="ig-show-metric-cell">
                            <div class="ig-show-metric-icon is-likes"><i class="bi bi-heart-fill"></i></div>
                            <div class="ig-show-metric-value">{{ number_format((int) ($post->like_count ?? 0), 0, ',', '.') }}</div>
                            <div class="ig-show-metric-label">Beğeni</div>
                        </div>
                        <div class="ig-show-metric-cell">
                            <div class="ig-show-metric-icon is-comments"><i class="bi bi-chat-fill"></i></div>
                            <div class="ig-show-metric-value">{{ number_format((int) ($post->comments_count ?? 0), 0, ',', '.') }}</div>
                            <div class="ig-show-metric-label">Yorum</div>
                        </div>
                        @if($post->reach !== null)
                        <div class="ig-show-metric-cell">
                            <div class="ig-show-metric-icon is-reach"><i class="bi bi-eye-fill"></i></div>
                            <div class="ig-show-metric-value">{{ number_format($post->reach, 0, ',', '.') }}</div>
                            <div class="ig-show-metric-label">Erişim</div>
                        </div>
                        @endif
                        @if($post->impressions !== null)
                        <div class="ig-show-metric-cell">
                            <div class="ig-show-metric-icon is-impressions"><i class="bi bi-bar-chart-fill"></i></div>
                            <div class="ig-show-metric-value">{{ number_format($post->impressions, 0, ',', '.') }}</div>
                            <div class="ig-show-metric-label">Gösterim</div>
                        </div>
                        @endif
                        @if($post->saved_count !== null)
                        <div class="ig-show-metric-cell">
                            <div class="ig-show-metric-icon is-saved"><i class="bi bi-bookmark-fill"></i></div>
                            <div class="ig-show-metric-value">{{ number_format($post->saved_count, 0, ',', '.') }}</div>
                            <div class="ig-show-metric-label">Kaydetme</div>
                        </div>
                        @endif
                    </div>
                    @if($post->reach === null)
                        <div class="ig-show-metric-empty">
                            <i class="bi bi-info-circle me-1"></i>
                            Erişim/gösterim/kaydetme metrikleri sadece Instagram Business veya Creator hesaplarında erişilebilir.
                        </div>
                    @endif
                    @else
                        <div class="ig-show-metric-empty">
                            <i class="bi bi-hourglass-split me-1"></i>
                            Henüz veri çekilmedi. Sync günlük 04:30'da otomatik çalışır.
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Instagram Yayın --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-pink"><i class="bi bi-instagram"></i></div>
                        <div>
                            <h6 class="mb-0">Instagram Yayını</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @if($post->status === \App\Enums\InstagramPostStatus::Published)
                        <div class="ig-show-status-block is-success mb-3">
                            <div class="ig-show-status-title">
                                <i class="bi bi-check-circle-fill"></i> Başarıyla Yayınlandı
                            </div>
                            Yayınlanma: <strong>{{ $post->published_at?->format('d.m.Y H:i:s') ?? '—' }}</strong>
                        </div>
                    @elseif($post->status === \App\Enums\InstagramPostStatus::Failed)
                        <div class="ig-show-status-block is-error mb-3">
                            <div class="ig-show-status-title">
                                <i class="bi bi-x-circle-fill"></i> Yayınlanamadı
                            </div>
                            @if($post->error_message)
                                {{ $post->error_message }}
                            @else
                                Sebep belirsiz — API geçmişine bakın.
                            @endif
                        </div>
                    @else
                        <div class="ig-show-status-block is-neutral mb-3">
                            <div class="ig-show-status-title">
                                <i class="bi {{ $post->status->icon() }}"></i> {{ $post->status->label() }}
                            </div>
                            @if($post->scheduled_at)
                                Planlanan: <strong>{{ $post->scheduled_at->format('d.m.Y H:i') }}</strong>
                            @endif
                        </div>
                    @endif

                    @if($post->scheduled_at)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Planlanan Tarih</span>
                        <span class="ig-show-meta-value">{{ $post->scheduled_at->format('d.m.Y H:i') }}</span>
                    </div>
                    @endif
                    @if($post->published_at)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Gerçek Yayın</span>
                        <span class="ig-show-meta-value">{{ $post->published_at->format('d.m.Y H:i:s') }}</span>
                    </div>
                    @endif
                    @if($post->ig_media_id)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Media ID</span>
                        <span class="ig-show-meta-value"><code>{{ $post->ig_media_id }}</code></span>
                    </div>
                    @endif
                    @if($post->ig_post_id)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Post ID</span>
                        <span class="ig-show-meta-value"><code>{{ $post->ig_post_id }}</code></span>
                    </div>
                    @endif
                    @if($post->permalink)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Permalink</span>
                        <span class="ig-show-meta-value">
                            <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="text-teal">
                                Aç <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Facebook Yayın --}}
            @if($post->publish_to_facebook)
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-blue"><i class="bi bi-facebook"></i></div>
                        <div>
                            <h6 class="mb-0">Facebook Yayını</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    @if($post->fb_published_at)
                        <div class="ig-show-status-block is-success mb-3">
                            <div class="ig-show-status-title">
                                <i class="bi bi-check-circle-fill"></i> Facebook'ta Yayınlandı
                            </div>
                            Yayınlanma: <strong>{{ $post->fb_published_at->format('d.m.Y H:i:s') }}</strong>
                        </div>
                    @elseif($post->fb_error_message)
                        <div class="ig-show-status-block is-error mb-3">
                            <div class="ig-show-status-title">
                                <i class="bi bi-x-circle-fill"></i> Facebook'ta Yayınlanamadı
                            </div>
                            {{ $post->fb_error_message }}
                        </div>
                    @else
                        <div class="ig-show-status-block is-neutral mb-3">
                            <i class="bi bi-clock me-1"></i> Henüz yayınlanmadı.
                        </div>
                    @endif

                    @if($post->fb_published_at)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Yayın Tarihi</span>
                        <span class="ig-show-meta-value">{{ $post->fb_published_at->format('d.m.Y H:i:s') }}</span>
                    </div>
                    @endif
                    @if($post->fb_post_id)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Post ID</span>
                        <span class="ig-show-meta-value"><code>{{ $post->fb_post_id }}</code></span>
                    </div>
                    @endif
                    @if($post->fb_permalink)
                    <div class="ig-show-meta-row">
                        <span class="ig-show-meta-label">Permalink</span>
                        <span class="ig-show-meta-value">
                            <a href="{{ $post->fb_permalink }}" target="_blank" rel="noopener" class="text-teal">
                                Aç <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

        </div>
    </div>

@endsection
