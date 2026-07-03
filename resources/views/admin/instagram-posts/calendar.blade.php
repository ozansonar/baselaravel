@extends('layouts.admin')

@section('title', 'Instagram Takvimi')
@section('page_title', 'Instagram Takvimi')
@section('page_description', 'Planlanmış ve yayınlanmış postları takvim görünümünde gör')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.instagram-posts.index') }}" class="breadcrumb-link">Instagram Gönderileri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Takvim</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-calendar-week me-2"></i>Instagram Takvimi</h1>
            <p class="page-subtitle">{{ $monthStart->translatedFormat('F Y') }} — planlanmış ve yayınlanmış gönderiler</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass">
                <i class="bi bi-list-ul me-1"></i> Liste Görünümü
            </a>
            <a href="{{ route('admin.instagram-posts.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg me-1"></i> Yeni Gönderi
            </a>
        </div>
    </div>

    {{-- Month navigation --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <a href="{{ route('admin.instagram-posts.calendar', ['month' => $prevMonth]) }}" class="btn-glass">
                <i class="bi bi-chevron-left me-1"></i> {{ \Carbon\Carbon::createFromFormat('Y-m', $prevMonth)->translatedFormat('F Y') }}
            </a>
            <h4 class="mb-0">{{ $monthStart->translatedFormat('F Y') }}</h4>
            <a href="{{ route('admin.instagram-posts.calendar', ['month' => $nextMonth]) }}" class="btn-glass">
                {{ \Carbon\Carbon::createFromFormat('Y-m', $nextMonth)->translatedFormat('F Y') }} <i class="bi bi-chevron-right ms-1"></i>
            </a>
        </div>
    </div>

    {{-- Stats summary --}}
    @php
        $scheduledCount = $posts->where('status', \App\Enums\InstagramPostStatus::Scheduled)->count();
        $publishedCount = $posts->where('status', \App\Enums\InstagramPostStatus::Published)->count();
        $failedCount    = $posts->where('status', \App\Enums\InstagramPostStatus::Failed)->count();
    @endphp
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-calendar-event-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bu Ay Planlanmış</span>
                    <h3 class="usr-stat-value">{{ $scheduledCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bu Ay Yayınlanan</span>
                    <h3 class="usr-stat-value">{{ $publishedCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarısız</span>
                    <h3 class="usr-stat-value">{{ $failedCount }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Calendar grid --}}
    <div class="card-dark">
        <div class="card-body-custom">
            @php
                // Pazartesi ile başlayan haftalık grid: ay başının haftanın hangi günü olduğunu bul
                // Carbon: Monday=1, Sunday=7
                $firstDayWeek = $monthStart->dayOfWeekIso; // 1-7
                $daysBefore   = $firstDayWeek - 1;         // ayın başlangıcından önce kaç boş gün
                $totalDays    = $monthEnd->day;
                $daysAfter    = (7 - (($daysBefore + $totalDays) % 7)) % 7;

                $today = now()->startOfDay();
            @endphp

            <div class="ig-calendar-weekdays">
                @foreach(['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $day)
                    <div class="ig-calendar-weekday">{{ $day }}</div>
                @endforeach
            </div>

            <div class="ig-calendar-grid">
                {{-- Önceki ayın günleri (boş) --}}
                @for($i = 0; $i < $daysBefore; $i++)
                    <div class="ig-calendar-day ig-calendar-empty"></div>
                @endfor

                {{-- Bu ayın günleri --}}
                @for($d = 1; $d <= $totalDays; $d++)
                    @php
                        $date = $monthStart->copy()->day($d);
                        $key  = $date->format('Y-m-d');
                        $dayPosts = $byDate->get($key, collect());
                        $isToday  = $date->isSameDay($today);
                        $isPast   = $date->lt($today);
                    @endphp
                    <div class="ig-calendar-day {{ $isToday ? 'ig-day-today' : '' }} {{ $isPast ? 'ig-day-past' : '' }} {{ $dayPosts->isNotEmpty() ? 'ig-day-has-posts' : '' }}">
                        <div class="ig-day-number">{{ $d }}</div>
                        @if($dayPosts->isNotEmpty())
                            <div class="ig-day-posts">
                                @foreach($dayPosts->take(3) as $post)
                                    @php
                                        $detailRoute = $post->status === \App\Enums\InstagramPostStatus::Published
                                            ? route('admin.instagram-posts.show', $post->id)
                                            : route('admin.instagram-posts.edit', $post->id);
                                    @endphp
                                    <a href="{{ $detailRoute }}"
                                       class="ig-day-post ig-day-post--{{ $post->status->value }}"
                                       title="{{ \Illuminate\Support\Str::limit((string) $post->caption, 80) }}">
                                        @if($post->image_path)
                                            <img src="{{ upload_url($post->image_path, 'thumb') }}" alt="">
                                        @elseif($post->media_type)
                                            <i class="bi {{ $post->media_type->icon() }}"></i>
                                        @endif
                                        <span class="ig-day-post-time">
                                            @if($post->status === \App\Enums\InstagramPostStatus::Published && $post->published_at)
                                                <i class="bi bi-check-circle"></i> {{ $post->published_at->format('H:i') }}
                                            @elseif($post->scheduled_at)
                                                <i class="bi {{ $post->status->icon() }}"></i> {{ $post->scheduled_at->format('H:i') }}
                                            @endif
                                        </span>
                                    </a>
                                @endforeach
                                @if($dayPosts->count() > 3)
                                    <div class="ig-day-more">+{{ $dayPosts->count() - 3 }} daha</div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endfor

                {{-- Sonraki ayın günleri (boş) --}}
                @for($i = 0; $i < $daysAfter; $i++)
                    <div class="ig-calendar-day ig-calendar-empty"></div>
                @endfor
            </div>
        </div>
    </div>

@endsection
