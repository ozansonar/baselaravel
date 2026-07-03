@extends('layouts.admin')

@section('title', 'Instagram Gönderileri')
@section('page_title', 'Instagram Gönderileri')
@section('page_description', 'Zamanlanmış ve yayınlanmış Instagram gönderilerini yönetin.')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Instagram Gönderileri</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title">Instagram Gönderileri</h1>
            <p class="page-subtitle">Günlük paylaşımları planlayın, cron otomatik yayınlasın.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.instagram-posts.calendar') }}" class="btn-glass">
                <i class="bi bi-calendar-week me-1"></i> Takvim Görünümü
            </a>
            @if(Route::has('admin.instagram-posts.bulk.form'))
            <a href="{{ route('admin.instagram-posts.bulk.form') }}" class="btn-glass">
                <i class="bi bi-upload me-1"></i> Toplu Plan
            </a>
            @endif
            <a href="{{ route('admin.instagram-posts.vertex-plan') }}" class="btn-glass">
                <i class="bi bi-stars me-1"></i> Vertex ile Planla
            </a>
            <a href="{{ route('admin.instagram-posts.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg me-1"></i> Yeni Gönderi
            </a>
        </div>
    </div>

    {{-- STAT CARDS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-instagram"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam</span>
                    <h3 class="usr-stat-value">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-calendar-event-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Planlanmış</span>
                    <h3 class="usr-stat-value">{{ $stats['scheduled'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Yayınlanan</span>
                    <h3 class="usr-stat-value">{{ $stats['published'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarısız</span>
                    <h3 class="usr-stat-value">{{ $stats['failed'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════ CRON DURUM WIDGET ════════ --}}
    @php
        $cronState = $cronSummary['healthy'] ? 'healthy' : ($cronSummary['warning'] ? 'warning' : 'error');
        $cronStateLabel = match($cronState) {
            'healthy' => 'Sağlıklı',
            'warning' => 'Uyarı',
            'error'   => 'Çalışmıyor',
        };
        $cronStateIcon = match($cronState) {
            'healthy' => 'bi-heart-pulse-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'error'   => 'bi-x-octagon-fill',
        };
    @endphp
    <div class="ig-cron-widget ig-cron-widget--{{ $cronState }} mb-4"
         data-next-run-seconds="{{ $cronSummary['next_run_in_seconds'] }}">
        <div class="ig-cron-widget__header">
            <div class="ig-cron-widget__title">
                <i class="bi {{ $cronStateIcon }}"></i>
                <div>
                    <strong>Yayın Cron'u — {{ $cronStateLabel }}</strong>
                    <small>Komut: <code>instagram:publish-scheduled</code> · {{ $cronSummary['interval_label'] }}</small>
                </div>
            </div>
            <button type="button" class="ig-cron-widget__toggle btn-glass btn-glass-sm" data-bs-toggle="collapse" data-bs-target="#igCronDetails" aria-expanded="false">
                <i class="bi bi-chevron-down"></i>
                <span>Detaylar</span>
            </button>
        </div>

        <div class="ig-cron-widget__grid">
            {{-- Sıradaki çalışma — canlı geri sayım --}}
            <div class="ig-cron-stat">
                <span class="ig-cron-stat__label"><i class="bi bi-clock-history me-1"></i> Sıradaki Çalışma</span>
                <span class="ig-cron-stat__value">
                    <span data-cron-countdown>—</span>
                </span>
                <span class="ig-cron-stat__sub">{{ $cronSummary['next_run']->format('H:i') }}</span>
            </div>

            {{-- Son çalışma --}}
            <div class="ig-cron-stat">
                <span class="ig-cron-stat__label"><i class="bi bi-arrow-counterclockwise me-1"></i> Son Çalışma</span>
                <span class="ig-cron-stat__value">
                    @if($cronSummary['last_run'])
                        {{ $cronSummary['last_run']->format('H:i:s') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </span>
                <span class="ig-cron-stat__sub">
                    {{ $cronSummary['last_run_human'] ?? 'Hiç çalışmamış' }}
                </span>
            </div>

            {{-- Bu turda yayınlanacaklar --}}
            <div class="ig-cron-stat {{ $cronSummary['scheduled_due'] > 0 ? 'ig-cron-stat--accent' : '' }}">
                <span class="ig-cron-stat__label"><i class="bi bi-send-check me-1"></i> Sıradaki Tur'da</span>
                <span class="ig-cron-stat__value">{{ $cronSummary['scheduled_due'] }}</span>
                <span class="ig-cron-stat__sub">
                    {{ $cronSummary['scheduled_total'] }} planlı toplam
                </span>
            </div>

            {{-- Yeniden denenecek (failed) --}}
            <div class="ig-cron-stat {{ $cronSummary['failed_active'] > 0 ? 'ig-cron-stat--danger' : '' }}">
                <span class="ig-cron-stat__label"><i class="bi bi-arrow-repeat me-1"></i> Yeniden Denenecek</span>
                <span class="ig-cron-stat__value">{{ $cronSummary['failed_active'] }}</span>
                <span class="ig-cron-stat__sub">
                    @if($cronSummary['failed_active'] > 0)
                        Cron sıradaki turda dener
                    @else
                        Hiç bekleyen yok
                    @endif
                </span>
            </div>

            {{-- Son yayın --}}
            <div class="ig-cron-stat">
                <span class="ig-cron-stat__label"><i class="bi bi-instagram me-1"></i> Son Yayın</span>
                <span class="ig-cron-stat__value">
                    @if($cronSummary['last_published_at'])
                        {{ $cronSummary['last_published_at']->format('H:i') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </span>
                <span class="ig-cron-stat__sub">
                    {{ $cronSummary['last_published_at']?->diffForHumans() ?? 'Henüz yayın yok' }}
                </span>
            </div>
        </div>

        {{-- Detay collapsible — diğer IG cron'ları + sağlık açıklaması --}}
        <div class="collapse" id="igCronDetails">
            <div class="ig-cron-widget__details">
                <div class="ig-cron-health">
                    <strong><i class="bi bi-info-circle me-1"></i> Sağlık Durumu</strong>
                    @if($cronState === 'healthy')
                        <p class="mb-0 small">Cron son 15 dakika içinde çalışmış — sistem sağlıklı.</p>
                    @elseif($cronState === 'warning')
                        <p class="mb-0 small text-warning">Cron 15+ dakikadır çalışmamış. Birkaç tur kaçmış olabilir; 60 dakikayı aşarsa müdahale gerekir.</p>
                    @else
                        <p class="mb-0 small text-danger">
                            Cron 60+ dakikadır çalışmamış (veya hiç çalışmamış). Linux crontab'ı kontrol et:
                            <code>* * * * * cd /path && php artisan schedule:run</code>
                        </p>
                    @endif
                </div>

                <div class="ig-cron-other-list">
                    <strong class="d-block mb-2"><i class="bi bi-list-check me-1"></i> Diğer Instagram Cron'ları</strong>
                    <table class="cl-table mb-0">
                        <thead>
                            <tr>
                                <th>Cron</th>
                                <th>Komut</th>
                                <th>Çalışma Zamanı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="ig-cron-other-row--main">
                                <td><strong>Yayın</strong> (bu widget)</td>
                                <td><code>instagram:publish-scheduled</code></td>
                                <td>{{ $cronSummary['interval_label'] }}</td>
                            </tr>
                            @foreach($cronSummary['other_crons'] as $cron)
                                <tr>
                                    <td>{{ $cron['name'] }}</td>
                                    <td><code>{{ $cron['command'] }}</code></td>
                                    <td>{{ $cron['schedule'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- STATUS TABS --}}
    @php $currentStatus = request('status', ''); @endphp
    <div class="cl-status-tabs mb-4">
        <a href="{{ route('admin.instagram-posts.index') }}" class="cl-status-tab {{ $currentStatus === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $stats['total'] }}</span>
        </a>
        <a href="{{ route('admin.instagram-posts.index', ['status' => 'draft']) }}" class="cl-status-tab {{ $currentStatus === 'draft' ? 'active' : '' }}">
            <i class="bi bi-file-earmark"></i>
            <span>Taslak</span>
            <span class="cl-tab-count">{{ $statusCounts['draft'] }}</span>
        </a>
        <a href="{{ route('admin.instagram-posts.index', ['status' => 'scheduled']) }}" class="cl-status-tab {{ $currentStatus === 'scheduled' ? 'active' : '' }}">
            <i class="bi bi-calendar-event text-neon-blue"></i>
            <span>Planlanmış</span>
            <span class="cl-tab-count">{{ $statusCounts['scheduled'] }}</span>
        </a>
        <a href="{{ route('admin.instagram-posts.index', ['status' => 'published']) }}" class="cl-status-tab {{ $currentStatus === 'published' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Yayınlandı</span>
            <span class="cl-tab-count">{{ $statusCounts['published'] }}</span>
        </a>
        <a href="{{ route('admin.instagram-posts.index', ['status' => 'failed']) }}" class="cl-status-tab {{ $currentStatus === 'failed' ? 'active' : '' }}">
            <i class="bi bi-exclamation-triangle text-neon-red"></i>
            <span>Başarısız</span>
            <span class="cl-tab-count">{{ $statusCounts['failed'] }}</span>
        </a>
        <a href="{{ route('admin.instagram-posts.index', ['status' => 'trashed']) }}" class="cl-status-tab {{ $currentStatus === 'trashed' ? 'active' : '' }}">
            <i class="bi bi-trash text-neon-red"></i>
            <span>Silinmiş</span>
            <span class="cl-tab-count">{{ $statusCounts['trashed'] }}</span>
        </a>
    </div>

    {{-- BULK ACTION TOOLBAR (gizli, seçim yapılınca açılır) --}}
    <form method="POST" action="{{ route('admin.instagram-posts.bulk-action') }}" id="igBulkForm">
        @csrf
        <div class="ig-bulk-toolbar" id="igBulkToolbar" style="display:none">
            <span class="ig-bulk-info"><strong id="igBulkCount">0</strong> gönderi seçildi</span>
            <div class="ig-bulk-actions">
                <button type="submit" name="bulk_action" value="mark_draft" class="usr-action-btn topic-convert-btn ig-bulk-btn" data-confirm="Seçilen gönderiler taslağa alınsın mı? (Yayınlananlar atlanır)">
                    <i class="bi bi-file-earmark"></i> Taslağa Al
                </button>
                <button type="submit" name="bulk_action" value="delete" class="usr-action-btn topic-convert-btn ig-bulk-btn ig-bulk-danger" data-confirm="Seçilen gönderiler silinsin mi?">
                    <i class="bi bi-trash"></i> Sil
                </button>
                @if($currentStatus === 'trashed')
                <button type="submit" name="bulk_action" value="restore" class="usr-action-btn topic-convert-btn ig-bulk-btn" data-confirm="Seçilen gönderiler geri yüklensin mi?">
                    <i class="bi bi-arrow-counterclockwise"></i> Geri Yükle
                </button>
                @endif
                <button type="button" class="usr-action-btn topic-convert-btn" id="igBulkClear">
                    <i class="bi bi-x"></i> Seçimi Temizle
                </button>
            </div>
        </div>

    {{-- TABLE --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="igSelectAll" class="form-check-input" title="Tümünü seç">
                            </th>
                            <th style="width: 80px;">Görsel</th>
                            <th class="ig-col-type">Tür</th>
                            <th>Caption</th>
                            <th>Planlanan / Yayınlanan</th>
                            <th>Durum</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="{{ $post->id }}" class="form-check-input ig-row-check">
                            </td>
                            <td data-label="Görsel">
                                @php
                                    // Galeri payload — tüm görseller + video JSON formatında
                                    $galleryItems = [];
                                    if ($post->image_path) {
                                        $galleryItems[] = [
                                            'url'  => upload_url($post->image_path, 'lg'),
                                            'type' => 'image',
                                            'alt'  => 'Ana görsel',
                                        ];
                                    }
                                    foreach ($post->additionalImages as $idx => $img) {
                                        $galleryItems[] = [
                                            'url'  => upload_url($img->image_path, 'lg'),
                                            'type' => 'image',
                                            'alt'  => 'Ek görsel #' . ($idx + 1),
                                        ];
                                    }
                                    if ($post->video_path) {
                                        $galleryItems[] = [
                                            'url'    => upload_url($post->video_path),
                                            'type'   => 'video',
                                            'poster' => $post->image_path ? upload_url($post->image_path, 'lg') : null,
                                            'alt'    => 'Video',
                                        ];
                                    }
                                    $imgCount    = 1 + $post->additionalImages->count();
                                    $hasMedia    = $galleryItems !== [];
                                @endphp
                                <div class="ig-thumb-wrap {{ $hasMedia ? 'ig-thumb-clickable' : '' }}"
                                     @if($hasMedia)
                                         data-ig-gallery
                                         data-ig-gallery-title="{{ \Illuminate\Support\Str::limit((string) $post->caption, 60) ?: 'Gönderi #' . $post->id }}"
                                         data-ig-images='@json($galleryItems)'
                                         role="button"
                                         tabindex="0"
                                         title="Galeri olarak aç"
                                     @endif>
                                    @if($post->image_path)
                                        <img src="{{ upload_url($post->image_path, 'thumb') }}"
                                             alt="Görsel"
                                             class="rounded cl-thumb-60"
                                             loading="lazy">
                                    @elseif($post->video_path)
                                        <video src="{{ upload_url($post->video_path) }}"
                                               class="rounded cl-thumb-60"
                                               preload="metadata"
                                               muted></video>
                                    @else
                                        <span class="ig-thumb-placeholder rounded cl-thumb-60">
                                            <i class="bi {{ $post->media_type?->icon() ?? 'bi-image' }}"></i>
                                        </span>
                                    @endif
                                    @if($imgCount > 1 && ($post->media_type?->supportsCarousel() ?? true))
                                        <span class="ig-carousel-badge" title="Carousel — {{ $imgCount }} görsel">
                                            <i class="bi bi-images"></i> {{ $imgCount }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Tür">
                                @php
                                    $type = $post->media_type ?? \App\Enums\InstagramMediaType::Image;
                                    $typeBadgeClass = match($type) {
                                        \App\Enums\InstagramMediaType::Reels => 'ig-type-badge ig-type-badge--reels',
                                        \App\Enums\InstagramMediaType::Story => 'ig-type-badge ig-type-badge--story',
                                        default => 'ig-type-badge ig-type-badge--feed',
                                    };
                                @endphp
                                <span class="{{ $typeBadgeClass }}">
                                    <i class="bi {{ $type->icon() }}"></i>
                                    {{ $type->label() }}
                                </span>
                                @if($type === \App\Enums\InstagramMediaType::Image)
                                    @php $imgCount = 1 + $post->additionalImages()->count(); @endphp
                                    @if($imgCount > 1)
                                        <small class="d-block text-muted mt-1"><i class="bi bi-images me-1"></i>{{ $imgCount }} görsel</small>
                                    @endif
                                @elseif($type === \App\Enums\InstagramMediaType::Reels && $post->video_duration_seconds)
                                    <small class="d-block text-muted mt-1"><i class="bi bi-clock me-1"></i>{{ $post->video_duration_seconds }} sn</small>
                                @elseif($type === \App\Enums\InstagramMediaType::Story && $post->video_path)
                                    <small class="d-block text-muted mt-1"><i class="bi bi-camera-video me-1"></i>video</small>
                                @endif
                            </td>
                            <td data-label="Caption">
                                <div class="cl-content-info">
                                    <span class="cl-content-title">
                                        {{ \Illuminate\Support\Str::limit((string) $post->caption, 80) }}
                                    </span>
                                    @if(is_array($post->hashtags) && count($post->hashtags) > 0)
                                    <span class="cl-content-meta text-teal">
                                        {{ collect($post->hashtags)->take(5)->map(fn($t) => '#'.ltrim($t, '#'))->implode(' ') }}
                                        @if(count($post->hashtags) > 5)
                                        +{{ count($post->hashtags) - 5 }}
                                        @endif
                                    </span>
                                    @endif
                                    @if($post->status === \App\Enums\InstagramPostStatus::Published && $post->like_count !== null)
                                    <span class="cl-content-meta" title="Beğeni / Yorum / Erişim">
                                        <i class="bi bi-heart-fill text-danger"></i> {{ number_format($post->like_count, 0, ',', '.') }}
                                        <i class="bi bi-chat-fill ms-2 text-info"></i> {{ number_format((int) ($post->comments_count ?? 0), 0, ',', '.') }}
                                        @if($post->reach !== null)
                                            <i class="bi bi-eye-fill ms-2 text-success"></i> {{ number_format($post->reach, 0, ',', '.') }}
                                        @endif
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Tarih">
                                @if($post->status === \App\Enums\InstagramPostStatus::Published && $post->published_at)
                                <div class="cl-content-info">
                                    <span class="usr-meta">{{ $post->published_at->format('d.m.Y') }}</span>
                                    <span class="cl-content-meta">{{ $post->published_at->format('H:i') }}</span>
                                </div>
                                @elseif($post->scheduled_at)
                                <div class="cl-content-info">
                                    <span class="usr-meta">{{ $post->scheduled_at->format('d.m.Y') }}</span>
                                    <span class="cl-content-meta">{{ $post->scheduled_at->format('H:i') }}</span>
                                </div>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Durum">
                                <span class="usr-status-badge {{ $post->trashed() ? 'inactive' : $post->status->badgeClass() }}">
                                    @if($post->trashed())
                                        <i class="bi bi-trash me-1"></i>Silinmiş
                                    @else
                                        <i class="bi {{ $post->status->icon() }} me-1"></i>{{ $post->status->label() }}
                                    @endif
                                </span>
                                @if($post->publish_to_facebook)
                                    <span class="ig-fb-badge" title="Facebook'a da paylaşılacak/paylaşıldı">
                                        <i class="bi bi-facebook"></i>
                                        @if($post->fb_published_at)
                                            <i class="bi bi-check-circle-fill ig-fb-success"></i>
                                        @elseif($post->fb_error_message)
                                            <i class="bi bi-x-circle-fill ig-fb-error" title="{{ $post->fb_error_message }}"></i>
                                        @endif
                                    </span>
                                @endif
                                @if($post->error_message && $post->status === \App\Enums\InstagramPostStatus::Failed)
                                <div class="small text-danger mt-1" title="{{ $post->error_message }}">
                                    <i class="bi bi-exclamation-circle me-1"></i>{{ \Illuminate\Support\Str::limit($post->error_message, 60) }}
                                </div>
                                @endif
                                @if($post->status === \App\Enums\InstagramPostStatus::Failed)
                                    @if($post->isRetryExhausted())
                                    <div class="small text-danger mt-1">
                                        <i class="bi bi-x-octagon me-1"></i>Kalıcı hata ({{ $post->retry_count }} deneme — manuel müdahale gerekli)
                                    </div>
                                    @elseif($post->retry_count > 0)
                                    <div class="small text-warning mt-1">
                                        <i class="bi bi-arrow-repeat me-1"></i>Cron yeniden deneyecek ({{ $post->retry_count }}/{{ \App\Models\InstagramPost::MAX_RETRY_COUNT }})
                                    </div>
                                    @endif
                                @endif
                            </td>
                            <td data-label="İşlem">
                                <div class="usr-actions">
                                    @if($post->trashed())
                                    <form method="POST" action="{{ route('admin.instagram-posts.restore', $post->id) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="usr-action-btn success" title="Geri Yükle">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                    @else
                                        @if($post->permalink)
                                        <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="usr-action-btn" title="Instagram'da Gör">
                                            <i class="bi bi-instagram"></i>
                                        </a>
                                        @endif
                                        @if($post->status !== \App\Enums\InstagramPostStatus::Published)
                                        <form method="POST" action="{{ route('admin.instagram-posts.publish-now', $post->id) }}" class="d-inline ig-confirm-publish">
                                            @csrf
                                            <button type="submit" class="usr-action-btn" title="Şimdi Paylaş">
                                                <i class="bi bi-send-fill"></i>
                                            </button>
                                        </form>
                                        @endif
                                        @if($post->status === \App\Enums\InstagramPostStatus::Published)
                                            <a href="{{ route('admin.instagram-posts.show', $post->id) }}" class="usr-action-btn" title="Detayları Görüntüle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('admin.instagram-posts.edit', $post->id) }}" class="usr-action-btn" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.instagram-posts.destroy', $post->id) }}" class="d-inline ig-confirm-delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="usr-action-btn danger" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-instagram d-block fs-1 mb-2"></i>
                                Henüz Instagram gönderisi eklenmemiş.
                                <div class="mt-2">
                                    <a href="{{ route('admin.instagram-posts.create') }}" class="btn-teal">
                                        <i class="bi bi-plus-lg me-1"></i> İlk Gönderiyi Oluştur
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </form>

    {{-- PAGINATION --}}
    @include('partials.admin.pagination', ['paginator' => $posts, 'itemLabel' => 'gönderi'])

    {{-- Galeri Lightbox Modal --}}
    <div class="modal fade ig-gallery-modal" id="igGalleryModal" tabindex="-1" aria-hidden="true" aria-labelledby="igGalleryTitle">
        <div class="modal-dialog modal-xl modal-dialog-centered ig-gallery-dialog">
            <div class="modal-content ig-gallery-content">
                <div class="ig-gallery-header">
                    <h5 class="ig-gallery-title" id="igGalleryTitle">Görsel Galerisi</h5>
                    <button type="button" class="ig-gallery-close" data-bs-dismiss="modal" aria-label="Kapat">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="ig-gallery-stage" id="igGalleryStage">
                    {{-- JS render eder: <img> veya <video> --}}
                </div>
                <button type="button" class="ig-gallery-nav ig-gallery-prev" id="igGalleryPrev" aria-label="Önceki">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="ig-gallery-nav ig-gallery-next" id="igGalleryNext" aria-label="Sonraki">
                    <i class="bi bi-chevron-right"></i>
                </button>
                <div class="ig-gallery-footer">
                    <span class="ig-gallery-counter" id="igGalleryCounter">1 / 1</span>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/instagram-posts-index.js') }}"></script>
@endpush
