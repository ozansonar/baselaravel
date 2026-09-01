@extends('layouts.admin')

@section('title', 'Duyuru: ' . $notification->title)
@section('page_title', $notification->title)

@section('content')
    @php
        use App\Enums\PushNotificationStatus;

        $processed = $notification->sent_count + $notification->failed_count + $notification->skipped_count;
        $remaining = max(0, $notification->total_devices - $processed);
    @endphp

    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.push-notifications.index') }}" class="breadcrumb-link">Push Duyuruları</a></li>
            <li class="breadcrumb-item active text-teal">{{ \Illuminate\Support\Str::limit($notification->title, 40) }}</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">{{ $notification->title }}</h1>
            <p class="page-subtitle">
                <span class="menu-manage-tag menu-manage-tag--{{ $notification->status->badgeClass() }}">
                    {{ $notification->status->label() }}
                </span>
                <span class="ms-2">{{ $notification->audienceLabel() }}</span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.push-notifications.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left"></i> Listeye Dön
            </a>
            @if($notification->status->isCancellable())
                @can('cancel', $notification)
                    <button type="button" class="btn-danger-solid"
                            data-action="push-iptal" data-label="{{ $notification->title }}">
                        <i class="bi bi-x-circle"></i> Gönderimi İptal Et
                    </button>
                @endcan
            @endif
        </div>
    </div>

    @if($notification->status === PushNotificationStatus::Queued && ! $configured)
        <div class="alert alert-warning d-flex align-items-start gap-2" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Bildirim taşıyıcısı yapılandırılmamış.</strong>
                Bu duyuru sırada bekliyor ama gönderim denendiğinde hiçbir cihaza ulaşmayacak.
            </div>
        </div>
    @endif

    @if($notification->last_error)
        <div class="alert alert-danger d-flex align-items-start gap-2" data-aos="fade-up">
            <i class="bi bi-x-octagon-fill mt-1"></i>
            <div>{{ $notification->last_error }}</div>
        </div>
    @endif

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-phone-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Hedef Cihaz</span>
                    <h3 class="usr-stat-value" data-count="{{ $notification->total_devices }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-send-check-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ulaşan</span>
                    <h3 class="usr-stat-value" data-count="{{ $notification->sent_count }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Başarısız</span>
                    <h3 class="usr-stat-value" data-count="{{ $notification->failed_count }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Sırada</span>
                    <h3 class="usr-stat-value" data-count="{{ $remaining }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-dark mb-4" data-aos="fade-up">
                <div class="card-header-custom">
                    <h6><i class="bi bi-bell me-2 text-teal"></i>Gönderilen Duyuru</h6>
                </div>
                <div class="card-body-custom">
                    <div class="cmp-row">
                        <span class="cmp-row__icon cmp-row__icon--{{ $notification->audience->color() }}">
                            <i class="bi {{ $notification->audience->icon() }}"></i>
                        </span>
                        <span class="cmp-row__text">
                            <span class="cmp-row__name">{{ $notification->title }}</span>
                            <span class="cmp-row__subject">{{ $notification->body }}</span>
                        </span>
                    </div>

                    @if($notification->link)
                        <div class="rdr-meta mt-3">
                            <div class="rdr-meta__row">
                                <span>Bağlantı</span>
                                <strong>{{ $notification->link }}</strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($notification->total_devices > 0)
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="60">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-graph-up me-2 text-teal"></i>İlerleme</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="cmp-bar">
                            <div class="cmp-bar__head">
                                <span class="cmp-bar__count">
                                    {{ number_format($processed) }} / {{ number_format($notification->total_devices) }} cihaz denendi
                                </span>
                                <span class="cmp-bar__pct">%{{ $notification->progress() }}</span>
                            </div>
                            <div class="progress cmp-progress">
                                <div class="progress-bar bg-teal cmp-progress__bar" role="progressbar"
                                     style="--cmp-progress: {{ $notification->progress() }}%"
                                     aria-valuenow="{{ $notification->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            @if($notification->skipped_count > 0)
                                <span class="cmp-bar__fail">
                                    <i class="bi bi-slash-circle"></i>{{ number_format($notification->skipped_count) }} cihaz atlandı — taşıyıcı yapılandırılmamıştı
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card-header-custom">
                    <h6><i class="bi bi-info-circle me-2 text-teal"></i>Künye</h6>
                </div>
                <div class="card-body-custom">
                    <div class="rdr-meta">
                        <div class="rdr-meta__row">
                            <span>Hedef</span>
                            <strong>{{ $notification->audienceLabel() }}</strong>
                        </div>
                        <div class="rdr-meta__row">
                            <span>Gönderen</span>
                            <strong>{{ $notification->sender?->full_name ?? 'Silinmiş hesap' }}</strong>
                        </div>
                        <div class="rdr-meta__row">
                            <span>Sıraya alındı</span>
                            <strong>{{ $notification->created_at?->format('d.m.Y H:i') }}</strong>
                        </div>
                        <div class="rdr-meta__row">
                            <span>Gönderim başladı</span>
                            <strong>{{ $notification->started_at?->format('d.m.Y H:i') ?? '—' }}</strong>
                        </div>
                        <div class="rdr-meta__row">
                            <span>Tamamlandı</span>
                            <strong>{{ $notification->completed_at?->format('d.m.Y H:i') ?? '—' }}</strong>
                        </div>
                    </div>

                    @if($notification->status->isPending())
                        <small class="stg-hint mt-3 d-block">
                            Gönderim {{ $interval }} dakikada bir çalışan görevle sürüyor.
                            Sayılar sayfa yenilendikçe güncellenir.
                        </small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- İptal onayı --}}
    <div class="modal fade modal-custom" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-body text-center p-4">
                    <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <h5 class="mt-3">Gönderimi iptal et</h5>
                    <p class="text-clr-secondary mb-4"><span id="cancelPushName"></span> duyurusu cihazlara gönderilmeyecek.</p>
                    <form method="POST" action="{{ route('admin.push-notifications.cancel', $notification) }}">
                        @csrf
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-danger-solid">İptal Et</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/push-notifications.js') }}"></script>
@endpush
