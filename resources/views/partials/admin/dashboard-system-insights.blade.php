{{-- Sistem & İçgörü mini kartlar — dashboard.blade.php tarafından include edilir.
     Beklenen: $adminInsights (AdminInsightsService::summary()) --}}
@php
    $ins = $adminInsights ?? null;
@endphp

@if ($ins)
    @php
        $healthStatus = $ins['health']['status'];
        $healthCls = match ($healthStatus) {
            'ok'       => 'text-success',
            'warning'  => 'text-warning',
            'critical' => 'text-danger',
            default    => 'text-muted',
        };
        $healthIcon = match ($healthStatus) {
            'ok'       => 'bi-check-circle-fill',
            'warning'  => 'bi-exclamation-triangle-fill',
            'critical' => 'bi-x-octagon-fill',
            default    => 'bi-question-circle',
        };

        $backupAge = $ins['backup']['age_hours'];
        $backupCls = $backupAge === null
            ? 'text-danger'
            : ($backupAge > 36 ? 'text-warning' : 'text-success');

        $unread = $ins['notifications']['unread'];
    @endphp

    <div class="row g-3 mb-4" data-aos="fade-up">
        {{-- Sistem Sağlık --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('admin.system-health.index') }}" class="text-decoration-none">
                <div class="card-dark h-100">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi {{ $healthIcon }} {{ $healthCls }}" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-clr-secondary small">Sistem Sağlık</div>
                                <div class="fw-bold {{ $healthCls }}">{{ $ins['health']['label'] }}</div>
                                <small class="text-clr-secondary">Detaylı kontrol →</small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Okunmamış Bildirim --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('admin.notifications.index') }}" class="text-decoration-none">
                <div class="card-dark h-100">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-bell-fill {{ $unread > 0 ? 'text-warning' : 'text-clr-secondary' }}" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-clr-secondary small">Bildirim</div>
                                <div class="fw-bold">
                                    {{ $unread }} okunmamış
                                </div>
                                <small class="text-clr-secondary">
                                    {{ $ins['notifications']['last_critical'] ? Str::limit($ins['notifications']['last_critical'], 28) : 'Tümünü gör →' }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Son Yedek --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('admin.backups.index') }}" class="text-decoration-none">
                <div class="card-dark h-100">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-hdd-fill {{ $backupCls }}" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-clr-secondary small">Son Yedek</div>
                                <div class="fw-bold {{ $backupCls }}">
                                    @if ($ins['backup']['exists'])
                                        {{ $backupAge !== null ? $backupAge . ' saat önce' : '—' }}
                                    @else
                                        Yedek yok
                                    @endif
                                </div>
                                <small class="text-clr-secondary">Yedek yönetimi →</small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Aktivite (24sa) --}}
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('admin.audit-logs.index') }}" class="text-decoration-none">
                <div class="card-dark h-100">
                    <div class="card-body-custom">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-activity text-info" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-clr-secondary small">Aktivite (24sa)</div>
                                <div class="fw-bold">{{ number_format($ins['activity']['count_24h']) }} olay</div>
                                <small class="text-clr-secondary">
                                    {{ $ins['activity']['last_at']?->diffForHumans() ?? 'Henüz kayıt yok' }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Hashtag Top --}}
        @if (! empty($ins['hashtag_top']))
            <div class="col-md-6 col-xl-6">
                <a href="{{ route('admin.hashtag-analytics.index') }}" class="text-decoration-none">
                    <div class="card-dark h-100">
                        <div class="card-body-custom">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-graph-up-arrow text-info" style="font-size: 2rem;"></i>
                                <div>
                                    <div class="text-clr-secondary small">En Yüksek Engagement Hashtag</div>
                                    <div class="fw-bold">
                                        #{{ $ins['hashtag_top']['tag'] }}
                                        <span class="badge bg-success ms-1">
                                            {{ number_format($ins['hashtag_top']['avg_engagement_rate'], 2) }}%
                                        </span>
                                    </div>
                                    <small class="text-clr-secondary">Hashtag raporu →</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endif

        {{-- En İyi Saat --}}
        @if (! empty($ins['best_slot']))
            <div class="col-md-6 col-xl-6">
                <a href="{{ route('admin.best-time-analytics.index') }}" class="text-decoration-none">
                    <div class="card-dark h-100">
                        <div class="card-body-custom">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                                <div>
                                    <div class="text-clr-secondary small">Önerilen En İyi Slot</div>
                                    <div class="fw-bold">
                                        {{ $ins['best_slot']['day_label'] }}
                                        — {{ str_pad((string)$ins['best_slot']['hour'], 2, '0', STR_PAD_LEFT) }}:00
                                    </div>
                                    <small class="text-clr-secondary">Detaylı analiz →</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endif
    </div>
@endif
