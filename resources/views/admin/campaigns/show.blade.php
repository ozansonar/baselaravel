@extends('layouts.admin')

@section('title', $campaign->name)
@section('page_title', $campaign->name)

@php
    use App\Enums\CampaignStatus;
    use App\Services\CampaignDispatcher;

    $isDraft    = $campaign->isEditable();
    $isSending  = $campaign->status === CampaignStatus::Sending;
    $isPaused   = $campaign->status === CampaignStatus::Paused;
    $isFinished = in_array($campaign->status, [CampaignStatus::Sent, CampaignStatus::Cancelled], true);
@endphp

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}" class="breadcrumb-link">Mail Kampanyaları</a></li>
            <li class="breadcrumb-item active text-teal">{{ $campaign->name }}</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">
                @if($isSending)<span class="live-dot" aria-hidden="true"></span>@endif
                {{ $campaign->name }}
            </h1>
            <p class="page-subtitle">
                <span class="menu-manage-tag menu-manage-tag--{{ $campaign->status->badgeClass() }}">{{ $campaign->status->label() }}</span>
                <span class="ms-2"><i class="bi {{ $campaign->audience->icon() }} me-1"></i>{{ $campaign->audience->label() }}</span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($isDraft)
                @can('update', $campaign)
                    <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn-glass"><i class="bi bi-pencil"></i> Düzenle</a>
                @endcan
            @endif

            @can('send', $campaign)
                @if($isSending)
                    <form method="POST" action="{{ route('admin.campaigns.pause', $campaign) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn-glass"><i class="bi bi-pause-fill"></i> Duraklat</button>
                    </form>
                @elseif($isPaused)
                    <form method="POST" action="{{ route('admin.campaigns.resume', $campaign) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn-teal"><i class="bi bi-play-fill"></i> Sürdür</button>
                    </form>
                @endif

                @unless($isFinished)
                    <button type="button" class="btn-glass text-neon-red" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="bi bi-x-circle"></i> İptal Et
                    </button>
                @endunless
            @endcan
        </div>
    </div>

    {{-- ═══════════ 1. ONAY KUTUSU (yalnızca taslakta) ═══════════ --}}
    @if($isDraft)
        @can('send', $campaign)
            <div class="card-dark mb-4 campaign-approve" data-aos="fade-up">
                <div class="card-header-custom">
                    <h6><i class="bi bi-shield-check me-2 text-teal"></i>Gönderim Onayı</h6>
                </div>
                <div class="card-body-custom">
                    @if($preview['error'])
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ $preview['error'] }}
                            @can('update', $campaign)
                                <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="alert-link ms-1">Alıcı seçimini düzenleyin</a>.
                            @endcan
                        </div>
                    @else
                        <div class="row g-4 align-items-center">
                            <div class="col-lg-7">
                                <p class="mb-2">
                                    Bu kampanya <strong class="text-teal fs-5">{{ number_format($preview['count']) }} kişiye</strong>
                                    gönderilecek.
                                </p>
                                <p class="text-clr-secondary small mb-3">
                                    Onayladığınızda gönderim hemen başlamaz: kampanya sıraya alınır ve cron her
                                    {{ CampaignDispatcher::RUN_INTERVAL_MINUTES }} dakikada bir çalışarak
                                    saatlik limiti aşmadan gönderir.
                                    @if($campaign->throttled)
                                        Turda en fazla <strong>{{ $perRunQuota }}</strong>, saatte en fazla
                                        <strong>{{ $hourlyLimit }}</strong> mail.
                                    @else
                                        Bu kampanyada yayma kapalı: saatlik limit ({{ $hourlyLimit }}) dolana kadar
                                        aralıksız gönderilir.
                                    @endif
                                </p>

                                <details class="campaign-sample">
                                    <summary class="text-teal">Alıcılardan örnek göster</summary>
                                    <ul class="list-unstyled mt-2 mb-0">
                                        @foreach($preview['sample'] as $row)
                                            <li class="text-clr-secondary small">
                                                {{ \App\Support\PersonName::full($row['first_name'] ?? null, $row['last_name'] ?? null) ?? '—' }}
                                                &lt;{{ $row['email'] }}&gt;
                                            </li>
                                        @endforeach
                                        @if($preview['count'] > count($preview['sample']))
                                            <li class="text-clr-secondary small">
                                                … ve {{ number_format($preview['count'] - count($preview['sample'])) }} kişi daha
                                            </li>
                                        @endif
                                    </ul>
                                </details>
                            </div>
                            <div class="col-lg-5">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn-teal btn-lg" data-bs-toggle="modal" data-bs-target="#approveModal">
                                        <i class="bi bi-send-fill"></i> Onayla ve Gönderime Al
                                    </button>
                                    <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                        <i class="bi bi-clock"></i> İleri Bir Tarihe Zamanla
                                    </button>
                                </div>
                                <p class="text-clr-secondary small mt-2 mb-0 text-center">
                                    Göndermeden önce kendinize test maili atmanız önerilir.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-info" data-aos="fade-up">
                <i class="bi bi-info-circle me-1"></i>
                Bu kampanya taslak durumunda. Gönderim başlatma yetkisi olan bir yöneticinin onaylaması gerekiyor.
            </div>
        @endcan
    @endif

    {{-- ═══════════ 2. DURUM SAYAÇLARI ═══════════ --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-people-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Alıcı</span>
                    <h3 class="usr-stat-value">{{ number_format($campaign->total_recipients ?: $preview['count']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-envelope-check-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Gönderildi</span>
                    <h3 class="usr-stat-value">{{ number_format($campaign->sent_count) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Sırada Bekleyen</span>
                    <h3 class="usr-stat-value">{{ number_format($pendingCount) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Gönderilemedi</span>
                    <h3 class="usr-stat-value">{{ number_format($campaign->failed_count) }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ 3. İLERLEME + CRON ═══════════ --}}
    @if($campaign->total_recipients > 0)
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-body-custom">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-clr-secondary">Gönderim ilerlemesi</span>
                    <strong>%{{ $campaign->progress() }}</strong>
                </div>
                <div class="progress mb-3" style="height: 10px;">
                    <div class="progress-bar bg-teal" role="progressbar" style="width: {{ $campaign->progress() }}%"
                         aria-valuenow="{{ $campaign->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="row g-3 campaign-meta">
                    <div class="col-md-3 col-6">
                        <div class="contact-info__label">Sıradaki cron</div>
                        <div class="fw-semibold">
                            {{ $nextRunAt->format('H:i') }}
                            <small class="text-clr-secondary" id="cronCountdown" data-seconds="{{ $nextRunIn }}">
                                ({{ $nextRunIn }} sn)
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="contact-info__label">Bu saatin kotası</div>
                        <div class="fw-semibold">{{ $sentLastHour }} / {{ $hourlyLimit }}
                            <small class="text-clr-secondary">({{ $remaining }} kaldı)</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="contact-info__label">Tur başına</div>
                        <div class="fw-semibold">
                            {{ $campaign->throttled ? $perRunQuota . ' mail' : 'limit dolana kadar' }}
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="contact-info__label">Tahmini bitiş</div>
                        <div class="fw-semibold">
                            {{ $estimate ? $estimate->format('d.m.Y H:i') : '—' }}
                        </div>
                    </div>
                </div>

                @if(!empty($breakdown))
                    <hr class="my-3">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(App\Enums\CampaignRecipientStatus::cases() as $case)
                            @if(($breakdown[$case->value] ?? 0) > 0)
                                <span class="menu-manage-tag menu-manage-tag--{{ $case->badgeClass() }}">
                                    {{ $case->label() }}: {{ number_format($breakdown[$case->value]) }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-4">
        {{-- ═══════════ 4. ÖNİZLEME ═══════════ --}}
        <div class="col-xl-8">
            <div class="card-dark mb-4" data-aos="fade-up">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6><i class="bi bi-envelope me-2 text-teal"></i>Mail Önizleme</h6>
                    <span class="text-clr-secondary small">
                        <strong>Konu:</strong> {{ $campaign->subject }}
                    </span>
                </div>
                <div class="card-body-custom">
                    {{-- Sandboxed: admin-authored HTML, rendered only to be looked at. --}}
                    <iframe srcdoc="{{ $campaign->body }}" sandbox
                            style="width:100%; min-height:420px; border:0; background:#fff; border-radius:8px;"
                            title="Mail önizleme"></iframe>
                </div>
            </div>

            @if($failures->isNotEmpty())
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-exclamation-triangle me-2 text-neon-red"></i>Gönderilemeyenler</h6>
                    </div>
                    <div class="card-body-custom p-0">
                        <div class="table-responsive">
                            <table class="cl-table">
                                <thead><tr><th>E-posta</th><th>Deneme</th><th>Hata</th></tr></thead>
                                <tbody>
                                    @foreach($failures as $failure)
                                        <tr>
                                            <td>{{ $failure->email }}</td>
                                            <td>{{ $failure->attempts }}</td>
                                            <td><small class="text-clr-secondary">{{ \Illuminate\Support\Str::limit($failure->error, 90) }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════════ 5. YAN PANEL ═══════════ --}}
        <div class="col-xl-4">
            @can('update', $campaign)
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-send-check me-2 text-teal"></i>Test Gönderimi</h6>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="{{ route('admin.campaigns.test', $campaign) }}" data-validate novalidate>
                            @csrf
                            <div class="stg-field mb-2">
                                <input type="text" class="stg-input" name="test_email"
                                       data-validation-engine="validate[required,custom[email]]"
                                       value="{{ auth()->user()->email }}" placeholder="test@ornek.com">
                            </div>
                            <button type="submit" class="btn-glass w-100">
                                <i class="bi bi-send"></i> Test Maili Gönder
                            </button>
                            <small class="stg-hint d-block mt-2">
                                Listeye gitmez, konusu <code>[TEST]</code> ile işaretlenir.
                            </small>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card-header-custom"><h6><i class="bi bi-info-circle me-2 text-teal"></i>Kampanya Bilgisi</h6></div>
                <div class="card-body-custom">
                    <div class="contact-info__label">Gönderen</div>
                    <div class="mb-3">{{ $campaign->senderName() }} &lt;{{ $campaign->senderAddress() }}&gt;</div>

                    @if($campaign->reply_to)
                        <div class="contact-info__label">Yanıt Adresi</div>
                        <div class="mb-3">{{ $campaign->reply_to }}</div>
                    @endif

                    <div class="contact-info__label">Gönderim Şekli</div>
                    <div class="mb-3">{{ $campaign->throttled ? 'Saate yayarak' : 'Limit dolana kadar aralıksız' }}</div>

                    @if($campaign->locale)
                        <div class="contact-info__label">Dil</div>
                        <div class="mb-3">{{ strtoupper($campaign->locale) }}</div>
                    @endif

                    @foreach([
                        'Zamanlanan'  => $campaign->scheduled_at,
                        'Başlangıç'   => $campaign->started_at,
                        'Tamamlanma'  => $campaign->completed_at,
                    ] as $label => $date)
                        @if($date)
                            <div class="contact-info__label">{{ $label }}</div>
                            <div class="mb-3">{{ $date->format('d.m.Y H:i') }}</div>
                        @endif
                    @endforeach

                    @if($campaign->author)
                        <div class="contact-info__label">Oluşturan</div>
                        <div>{{ $campaign->author->full_name }}</div>
                    @endif
                </div>
            </div>

            @if($campaign->attachments->isNotEmpty())
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
                    <div class="card-header-custom"><h6><i class="bi bi-paperclip me-2 text-teal"></i>Ekler</h6></div>
                    <div class="card-body-custom">
                        @foreach($campaign->attachments as $attachment)
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-truncate"><i class="bi bi-file-earmark me-2"></i>{{ $attachment->original_name }}</span>
                                <small class="text-clr-secondary flex-shrink-0">{{ $attachment->humanSize() }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════ MODALLER ═══════════ --}}
    @can('send', $campaign)
        @if($isDraft && !$preview['error'])
            <div class="modal fade modal-custom" id="approveModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-theme">
                    <div class="modal-content modal-content-theme">
                        <form method="POST" action="{{ route('admin.campaigns.send', $campaign) }}">
                            @csrf
                            <div class="modal-header">
                                <h6 class="modal-title"><i class="bi bi-send-fill me-2 text-teal"></i>Gönderimi Onayla</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>{{ number_format($preview['count']) }} kişiye</strong>
                                   "<em>{{ $campaign->subject }}</em>" konulu mail gönderilecek.</p>
                                <p class="text-clr-secondary small mb-0">
                                    Gönderim {{ $nextRunAt->format('H:i') }} itibarıyla başlar ve saatlik limite göre
                                    yayılır. Başladıktan sonra içerik değiştirilemez; yalnızca duraklatabilir veya
                                    iptal edebilirsiniz. Gönderilmiş mailler geri alınamaz.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Onaylıyorum, Gönder</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade modal-custom" id="scheduleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-theme">
                    <div class="modal-content modal-content-theme">
                        <form method="POST" action="{{ route('admin.campaigns.send', $campaign) }}" data-validate novalidate>
                            @csrf
                            <div class="modal-header">
                                <h6 class="modal-title"><i class="bi bi-clock me-2 text-teal"></i>Gönderimi Zamanla</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body">
                                <div class="stg-field">
                                    <label class="stg-label" for="scheduled_at">Gönderim zamanı</label>
                                    <input type="datetime-local" class="stg-input" id="scheduled_at" name="scheduled_at" data-validation-engine="validate[required]">
                                    <small class="stg-hint">
                                        Belirtilen saatten sonraki ilk cron turunda başlar. Alıcı listesi o an
                                        dondurulur, yani o tarihe kadar listeye eklenenler de dahil olur.
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal">Zamanla</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @unless($isFinished)
            <div class="modal fade modal-custom" id="cancelModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-theme">
                    <div class="modal-content modal-content-theme">
                        <div class="modal-body text-center p-4">
                            <div class="delete-modal-icon"><i class="bi bi-x-circle"></i></div>
                            <h5 class="mt-3">Kampanyayı iptal et</h5>
                            <p class="text-clr-secondary mb-4">
                                Sırada bekleyen {{ number_format($pendingCount) }} alıcıya gönderilmeyecek.
                                Gönderilmiş {{ number_format($campaign->sent_count) }} mail geri alınamaz.
                            </p>
                            <form method="POST" action="{{ route('admin.campaigns.cancel', $campaign) }}">
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
        @endunless
    @endcan
@endsection

@push('styles')
<style>
    .campaign-approve { border: 1px solid rgba(46, 230, 168, .35); }
    .campaign-sample summary { cursor: pointer; }
    .campaign-meta .contact-info__label { font-size: .75rem; }

    .live-dot {
        display: inline-block; width: 10px; height: 10px; border-radius: 50%;
        background: var(--neon-green, #2ee6a8); margin-right: 6px; vertical-align: middle;
        animation: live-pulse 1.6s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(46, 230, 168, .5); }
        50%      { opacity: .55; box-shadow: 0 0 0 6px rgba(46, 230, 168, 0); }
    }
    @media (prefers-reduced-motion: reduce) { .live-dot { animation: none; } }
</style>
@endpush

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/campaign-status.js') }}"></script>
@endpush
