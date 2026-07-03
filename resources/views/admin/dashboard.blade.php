@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_description', 'Genel bakış ve istatistikler')

@section('content')

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between" data-aos="fade-down" data-aos-duration="400">
        <div>
            <h2>Dashboard</h2>
            <p>Hoş geldiniz, {{ auth()->user()->name }}. Sitenizin genel durumunu buradan takip edin.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.orders.index') }}" class="btn-glass">
                <i class="bi bi-box-seam"></i> Siparişler
            </a>
            <a href="{{ route('admin.products.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Ürün
            </a>
        </div>
    </div>

    {{-- Onboarding Wizard Banner --}}
    @php
        $onboarding = app(\App\Services\OnboardingService::class);
    @endphp
    @if ($onboarding->shouldShowBanner())
        <div class="card-dark mb-4 topic-pool-alert topic-pool-alert--warning" data-aos="fade-down">
            <div class="card-body-custom">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="topic-pool-alert__icon">
                        <i class="bi bi-magic"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 topic-pool-alert__title">
                            Kurulum tamamlanmadı — {{ $onboarding->completedCount() }} / {{ \App\Services\OnboardingService::TOTAL_STEPS }} adım
                        </h6>
                        <small class="text-muted">
                            Sitenizi tam kapasiteyle kullanmak için kalan adımları tamamlayın.
                        </small>
                    </div>
                    <a href="{{ route('admin.onboarding.index') }}" class="btn-teal">
                        <i class="bi bi-arrow-right me-1"></i> Kuruluma Devam Et
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Instagram Token Expiry Banner --}}
    @if(in_array($instagramTokenStatus['level'] ?? 'ok', ['warning', 'critical', 'expired', 'unknown'], true))
        @php
            $tokenLevel = $instagramTokenStatus['level'];
            $bannerClass = $tokenLevel === 'expired' ? 'topic-pool-alert--empty' : ($tokenLevel === 'critical' ? 'topic-pool-alert--critical' : 'topic-pool-alert--warning');
            $bannerIcon = $tokenLevel === 'expired' ? 'bi-x-octagon-fill' : ($tokenLevel === 'critical' ? 'bi-exclamation-triangle-fill' : 'bi-clock-history');
        @endphp
        <div class="card-dark mb-4 topic-pool-alert {{ $bannerClass }}" data-aos="fade-down">
            <div class="card-body-custom">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="topic-pool-alert__icon">
                        <i class="bi {{ $bannerIcon }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 topic-pool-alert__title">
                            <i class="bi bi-instagram me-1"></i> {{ $instagramTokenStatus['message'] }}
                        </h6>
                        @if(! empty($instagramTokenStatus['expires_at']))
                            <small class="text-muted">
                                Bitiş: {{ $instagramTokenStatus['expires_at']->translatedFormat('d M Y H:i') }}
                            </small>
                        @endif
                    </div>
                    <a href="{{ route('admin.settings.index') }}#stg-instagram" class="btn-teal">
                        <i class="bi bi-arrow-clockwise me-1"></i> Token'ı Yenile
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Instagram Özet Widget --}}
    @if(isset($instagramSummary))
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-pink"><i class="bi bi-instagram"></i></div>
                <div>
                    <h6 class="mb-0">Instagram Durum Özeti</h6>
                    <small class="text-muted">Son 24 saat + planlanan / başarısız sayıları</small>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if(Route::has('admin.instagram-logs.index'))
                <a href="{{ route('admin.instagram-logs.index') }}" class="btn-glass">
                    <i class="bi bi-journal-code me-1"></i> API Logları
                </a>
                @endif
                <a href="{{ route('admin.instagram-posts.index') }}" class="btn-glass">
                    <i class="bi bi-list-ul me-1"></i> Tüm Gönderiler
                </a>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="dash-ig-summary">
                <div class="dash-ig-mini dash-ig-mini--success">
                    <span class="dash-ig-mini__label"><i class="bi bi-check-circle"></i> 24 Saat Başarılı</span>
                    <span class="dash-ig-mini__value">{{ $instagramSummary['success_24h'] }}</span>
                    <span class="dash-ig-mini__sub">Yayınlanan API çağrısı</span>
                </div>
                <div class="dash-ig-mini {{ $instagramSummary['failed_24h'] > 0 ? 'dash-ig-mini--danger' : '' }}">
                    <span class="dash-ig-mini__label"><i class="bi bi-x-circle"></i> 24 Saat Başarısız</span>
                    <span class="dash-ig-mini__value">{{ $instagramSummary['failed_24h'] }}</span>
                    <span class="dash-ig-mini__sub">
                        @if($instagramSummary['failed_24h'] > 0 && Route::has('admin.instagram-logs.index'))
                            <a href="{{ route('admin.instagram-logs.index', ['status' => 'failed']) }}" class="text-danger">İncele →</a>
                        @elseif($instagramSummary['failed_24h'] > 0)
                            <span class="text-danger">{{ $instagramSummary['failed_24h'] }} hata</span>
                        @else
                            Sorun yok
                        @endif
                    </span>
                </div>
                <div class="dash-ig-mini {{ $instagramSummary['scheduled'] > 0 ? 'dash-ig-mini--warning' : '' }}">
                    <span class="dash-ig-mini__label"><i class="bi bi-calendar-event"></i> Planlanmış</span>
                    <span class="dash-ig-mini__value">{{ $instagramSummary['scheduled'] }}</span>
                    <span class="dash-ig-mini__sub">Sıradaki yayınlar</span>
                </div>
                <div class="dash-ig-mini {{ $instagramSummary['failed_active'] > 0 ? 'dash-ig-mini--danger' : '' }}">
                    <span class="dash-ig-mini__label"><i class="bi bi-arrow-repeat"></i> Yeniden Denenecek</span>
                    <span class="dash-ig-mini__value">{{ $instagramSummary['failed_active'] }}</span>
                    <span class="dash-ig-mini__sub">
                        @if($instagramSummary['failed_active'] > 0)
                            Cron sıradaki çalışmasında deneyecek
                        @else
                            Hiç bekleyen yok
                        @endif
                    </span>
                </div>
            </div>

            <div class="dash-ig-cron {{ $instagramSummary['cron_healthy'] ? 'dash-ig-cron--healthy' : 'dash-ig-cron--unhealthy' }}">
                <i class="bi {{ $instagramSummary['cron_healthy'] ? 'bi-heart-pulse-fill' : 'bi-exclamation-octagon-fill' }}"></i>
                <div class="flex-grow-1">
                    @if($instagramSummary['cron_healthy'])
                        <strong>Cron sağlıklı çalışıyor.</strong>
                        @if($instagramSummary['cron_last_run'])
                            Son çalışma: {{ $instagramSummary['cron_last_run']->format('d.m.Y H:i') }}
                            ({{ $instagramSummary['cron_last_run']->diffForHumans() }})
                        @endif
                    @elseif($instagramSummary['cron_last_run'])
                        <strong>Cron 26 saatten fazladır çalışmıyor!</strong>
                        Son çalışma: {{ $instagramSummary['cron_last_run']->format('d.m.Y H:i') }} —
                        Linux crontab'ını kontrol et.
                    @else
                        <strong>Cron hiç çalışmamış.</strong>
                        <code>* * * * * cd /path && php artisan schedule:run</code> tanımlı mı kontrol et.
                    @endif
                </div>
                @if($instagramSummary['last_published_at'])
                    <small class="text-muted">
                        Son yayın: {{ $instagramSummary['last_published_at']->diffForHumans() }}
                    </small>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ════════ AI MALİYET WIDGET ════════ --}}
    @if(isset($aiCostSummary) && $aiCostSummary !== null)
    @php $b = $aiCostSummary['budget']; $mom = $aiCostSummary['mom']; @endphp
    <div class="card-dark mb-4 ai-cost-widget ai-cost-widget--{{ $b['state'] }}">
        <div class="card-body-custom">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">
                        <i class="bi bi-cash-coin text-success me-2"></i>
                        AI Maliyet · {{ now()->translatedFormat('F Y') }}
                    </h5>
                    <small class="text-muted">
                        Görsel üretimi (Imagen/Gemini Image) — text üretimi rapor amaçlı görünür
                    </small>
                </div>
                <a href="{{ route('admin.ai-costs.index') }}" class="btn-glass btn-glass-sm">
                    <i class="bi bi-bar-chart-line me-1"></i> Detaylı Rapor
                </a>
            </div>

            <div class="row g-3">
                <div class="col-md-5">
                    <div class="ai-cost-progress-wrap">
                        <div class="d-flex justify-content-between mb-1">
                            <strong>${{ number_format($b['used_image'], 2) }} / ${{ number_format($b['limit'], 2) }}</strong>
                            <span class="badge bg-{{ $b['state'] === 'exceeded' ? 'danger' : ($b['state'] === 'warning' ? 'warning' : 'success') }}-subtle text-{{ $b['state'] === 'exceeded' ? 'danger' : ($b['state'] === 'warning' ? 'warning' : 'success') }}-emphasis">
                                {{ $b['percent'] }}%
                            </span>
                        </div>
                        <div class="ai-cost-progress">
                            <div class="ai-cost-progress-bar ai-cost-progress-bar--{{ $b['state'] }}"
                                 style="width: {{ min(100, $b['percent']) }}%"></div>
                        </div>
                        @if($b['exceeded'])
                            <small class="text-danger d-block mt-1">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                                Bütçe aşıldı! @if($b['block_enabled'])AI görsel üretimi durduruldu.@endif
                            </small>
                        @elseif($b['alert'])
                            <small class="text-warning d-block mt-1">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Bütçe yaklaşıyor (%{{ $b['percent'] }})
                            </small>
                        @else
                            <small class="text-muted d-block mt-1">
                                Kalan: <strong>${{ number_format($b['remaining'], 2) }}</strong>
                                @if($b['used_text'] > 0)
                                    · Text gen: ${{ number_format($b['used_text'], 2) }}
                                @endif
                            </small>
                        @endif
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="ai-cost-mom">
                        <span class="ai-cost-mom__label">Geçen aydan</span>
                        @if($mom['change_pct'] !== null)
                            <strong class="ai-cost-mom__value ai-cost-mom__value--{{ $mom['direction'] }}">
                                @if($mom['direction'] === 'up')<i class="bi bi-arrow-up"></i>
                                @elseif($mom['direction'] === 'down')<i class="bi bi-arrow-down"></i>
                                @else<i class="bi bi-dash"></i>@endif
                                {{ abs((float) $mom['change_pct']) }}%
                            </strong>
                        @else
                            <strong class="ai-cost-mom__value">—</strong>
                        @endif
                        <small>${{ number_format($mom['last_month'], 2) }} → ${{ number_format($mom['this_month'], 2) }}</small>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="ai-cost-spark">
                        <span class="ai-cost-spark__label">Son 7 gün</span>
                        @php $vals = array_column($aiCostSummary['last_7_days'] ?? [], 'total'); $maxVal = max(0.001, max($vals ?: [0])); @endphp
                        <div class="ai-cost-spark__bars">
                            @foreach($aiCostSummary['last_7_days'] ?? [] as $day)
                                @php $h = max(2, (int) round(((float) $day['total'] / $maxVal) * 100)); @endphp
                                <span class="ai-cost-spark__bar"
                                      style="height: {{ $h }}%"
                                      title="{{ \Carbon\Carbon::parse($day['date'])->format('d.m') }}: ${{ number_format($day['total'], 4) }}"></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
            <div class="stat-card teal animate-in">
                <div class="stat-card-header">
                    <div class="stat-icon teal"><i class="bi bi-currency-lira"></i></div>
                </div>
                <div class="stat-value" data-count="{{ (int) $stats['total_revenue'] }}">0</div>
                <div class="stat-label">Toplam Gelir (₺)</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card purple animate-in anim-delay-1">
                <div class="stat-card-header">
                    <div class="stat-icon purple"><i class="bi bi-box-seam-fill"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['total_orders'] }}">0</div>
                <div class="stat-label">Toplam Sipariş</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card blue animate-in anim-delay-2">
                <div class="stat-card-header">
                    <div class="stat-icon blue"><i class="bi bi-grid-fill"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['total_products'] }}">0</div>
                <div class="stat-label">Toplam Ürün</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card orange animate-in anim-delay-3">
                <div class="stat-card-header">
                    <div class="stat-icon orange"><i class="bi bi-envelope-fill"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['unread_messages'] }}">0</div>
                <div class="stat-label">Okunmamış Mesaj</div>
            </div>
        </div>
    </div>


    {{-- Sistem & İçgörü mini kartlar (sağlık, bildirim, yedek, aktivite, hashtag, en iyi saat) --}}
    @include('partials.admin.dashboard-system-insights')

    <!-- ==================== SECTION 2: ORDER STATUS + ACTIVITY ==================== -->
    <div class="row g-3 mb-4">
        <!-- Order Status Summary -->
        <div class="col-xl-8" data-aos="fade-up" data-aos-delay="50">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-clipboard-data me-2 text-teal"></i>Sipariş Durumu</h6>
                    <a href="{{ route('admin.orders.index') }}" class="btn-glass btn-sm">Tümünü Gör</a>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-6 col-md">
                            <div class="text-center">
                                <div class="fs-3 fw-bold text-warning" data-count="{{ $orderStatusCounts['pending'] }}">0</div>
                                <small class="text-clr-secondary"><i class="bi bi-clock me-1"></i>Beklemede</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-center">
                                <div class="fs-3 fw-bold text-info" data-count="{{ $orderStatusCounts['processing'] }}">0</div>
                                <small class="text-clr-secondary"><i class="bi bi-gear me-1"></i>Hazırlanıyor</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-center">
                                <div class="fs-3 fw-bold text-primary" data-count="{{ $orderStatusCounts['shipped'] }}">0</div>
                                <small class="text-clr-secondary"><i class="bi bi-truck me-1"></i>Kargoda</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-center">
                                <div class="fs-3 fw-bold text-neon-green" data-count="{{ $orderStatusCounts['delivered'] }}">0</div>
                                <small class="text-clr-secondary"><i class="bi bi-check-circle me-1"></i>Teslim</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-center">
                                <div class="fs-3 fw-bold text-neon-red" data-count="{{ $orderStatusCounts['cancelled'] }}">0</div>
                                <small class="text-clr-secondary"><i class="bi bi-x-circle me-1"></i>İptal</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-clock-history me-2 text-neon-green"></i>Son Aktiviteler</h6>
                </div>
                <div class="card-body-custom pt-2">
                    @forelse($recentMessages->take(4) as $msg)
                        <div class="activity-item">
                            <div class="activity-icon bg-icon-{{ $msg->is_read ? 'green' : 'orange' }}-strong">
                                <i class="bi bi-{{ $msg->is_read ? 'envelope-open' : 'envelope-fill' }}"></i>
                            </div>
                            <div class="activity-content">
                                <h6>{{ $msg->name }}</h6>
                                <p>{{ $msg->created_at->diffForHumans() }} — {{ Str::limit($msg->subject, 30) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-clr-secondary mb-0 text-center py-3">Henüz mesaj yok.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2.5: GOOGLE SEARCH CONSOLE ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-12" data-aos="fade-up" data-aos-delay="50">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-google me-2 text-teal"></i>Google'da Sizi Bulanlar (son 28 gün, top 10)</h6>
                    <a href="{{ route('admin.seo-performance.index') }}" class="btn-glass btn-sm">Detayları Gör</a>
                </div>
                <div class="card-body-custom card-body-flush">
                    @if($topQueries->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-google d-block fs-1 mb-2"></i>
                            <p class="mb-1">Henüz arama verisi yok.</p>
                            <small>GSC verileri 2-3 gün gecikmeyle gelir. Setup için <a href="{{ route('admin.seo-performance.index') }}">SEO Performans</a> sayfasını ziyaret et.</small>
                        </div>
                    @else
                        <table class="table-dark-custom">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Arama Kelimesi</th>
                                    <th class="text-end">Tıklama</th>
                                    <th class="text-end d-none d-md-table-cell">Gösterim</th>
                                    <th class="text-end d-none d-lg-table-cell">CTR</th>
                                    <th class="text-end">Pozisyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topQueries as $i => $q)
                                    <tr>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td><strong class="text-light">{{ $q->query }}</strong></td>
                                        <td class="text-end">{{ number_format($q->clicks, 0, ',', '.') }}</td>
                                        <td class="text-end d-none d-md-table-cell text-muted">{{ number_format($q->impressions, 0, ',', '.') }}</td>
                                        <td class="text-end d-none d-lg-table-cell">
                                            @php $ctr = (float) $q->ctr; @endphp
                                            <span class="{{ $ctr >= 5 ? 'text-success' : ($ctr >= 2 ? 'text-warning' : 'text-danger') }}">
                                                %{{ number_format($ctr, 1, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @php $pos = (float) $q->position; @endphp
                                            <span class="usr-status-badge {{ $pos <= 3 ? 'active' : ($pos <= 10 ? 'pending' : 'inactive') }}">
                                                {{ number_format($pos, 1, ',', '.') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 3: RECENT ORDERS TABLE ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-12" data-aos="fade-up" data-aos-delay="50">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-table me-2 text-teal"></i>Son Siparişler</h6>
                    <a href="{{ route('admin.orders.index') }}" class="btn-glass btn-sm">Tümünü Gör</a>
                </div>
                <div class="card-body-custom card-body-flush">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>Sipariş ID</th>
                                <th>Müşteri</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td class="text-teal-semibold">#{{ $order->order_number }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-10">
                                            <div class="user-avatar-sm avatar-gradient-teal">
                                                {{ strtoupper(mb_substr($order->shipping_name, 0, 1)) }}{{ strtoupper(mb_substr(explode(' ', $order->shipping_name)[1] ?? '', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-600-primary">{{ $order->shipping_name }}</div>
                                                <div class="text-sm-muted">{{ $order->shipping_phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-600-primary">{{ number_format((float) $order->total, 2) }} ₺</td>
                                    <td>
                                        @php
                                            $badgeClass = match($order->status) {
                                                \App\Enums\OrderStatus::Pending => 'pending',
                                                \App\Enums\OrderStatus::Processing => 'info',
                                                \App\Enums\OrderStatus::Shipped => 'info',
                                                \App\Enums\OrderStatus::Delivered => 'active',
                                                \App\Enums\OrderStatus::Cancelled => 'inactive',
                                            };
                                        @endphp
                                        <span class="status-badge {{ $badgeClass }}">{{ $order->status->label() }}</span>
                                    </td>
                                    <td>{{ $order->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="btn-icon" title="Görüntüle"><i class="bi bi-eye"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-clr-secondary py-4">
                                        <i class="bi bi-inbox d-block fs-1 mb-2"></i>
                                        Henüz sipariş yok.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 4: PRODUCTS + CATEGORIES + REVIEWS ==================== -->
    <div class="row g-3 mb-4">
        <!-- Featured Products -->
        <div class="col-xl-5" data-aos="fade-up" data-aos-delay="50">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-star-fill me-2 text-warning"></i>Öne Çıkan Ürünler</h6>
                    <a href="{{ route('admin.products.index') }}" class="btn-glass btn-sm">Tümünü Gör</a>
                </div>
                <div class="card-body-custom card-body-flush">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th>Fiyat</th>
                                <th>Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($featuredProducts as $product)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-10">
                                            @if($product->image)
                                                <img src="{{ upload_url($product->image, 'thumb') }}"
                                                     alt="{{ $product->name }}"
                                                     class="rounded"
                                                     width="36" height="36"
                                                     loading="lazy">
                                            @else
                                                <div class="user-avatar-sm avatar-gradient-teal">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <div class="fw-600-primary">{{ Str::limit($product->name, 25) }}</div>
                                                <div class="text-sm-muted">{{ $product->category?->name ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-600-primary">
                                        <span class="text-neon-green">{{ number_format($product->price, 2) }} ₺</span>
                                    </td>
                                    <td>
                                        <span class="status-badge {{ $product->stock > 10 ? 'active' : ($product->stock > 0 ? 'pending' : 'danger') }}">
                                            {{ $product->stock }} {{ $product->unit }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-clr-secondary py-4">Henüz öne çıkan ürün yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Categories -->
        <div class="col-xl-3" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-folder-fill me-2 text-neon-blue"></i>Kategoriler</h6>
                    <a href="{{ route('admin.categories.index') }}" class="btn-glass btn-sm">Yönet</a>
                </div>
                <div class="card-body-custom">
                    @forelse($categories as $category)
                        <div class="activity-item">
                            <div class="activity-icon bg-icon-teal-strong">
                                <i class="bi bi-folder2"></i>
                            </div>
                            <div class="activity-content">
                                <h6>{{ $category->name }}</h6>
                                <p>{{ $category->products_count ?? 0 }} ürün</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-clr-secondary mb-0 text-center py-3">Henüz kategori yok.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Reviews -->
        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="150">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-chat-square-text me-2 text-neon-purple"></i>Son Değerlendirmeler</h6>
                    <a href="{{ route('admin.reviews.index') }}" class="btn-glass btn-sm">Tümünü Gör</a>
                </div>
                <div class="card-body-custom">
                    @forelse($recentReviews as $review)
                        <div class="activity-item">
                            <div class="activity-icon bg-icon-{{ $review->status->value === 'approved' ? 'green' : ($review->status->value === 'pending' ? 'orange' : 'red') }}-strong">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div class="activity-content">
                                <h6>
                                    {{ $review->name }}
                                    <span class="text-warning ms-1">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }} small"></i>
                                        @endfor
                                    </span>
                                </h6>
                                <p>{{ $review->created_at->diffForHumans() }} — {{ Str::limit($review->product?->name, 20) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-clr-secondary mb-0 text-center py-3">Henüz değerlendirme yok.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/dashboard.js') }}"></script>
@endpush
