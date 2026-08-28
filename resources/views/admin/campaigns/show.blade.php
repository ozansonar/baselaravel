@extends('layouts.admin')

@section('title', $campaign->name)
@section('page_title', $campaign->name)

@php
    use App\Enums\CampaignStatus;
    use App\Services\CampaignDispatcher;

    $isDraft    = $campaign->isEditable();
    $isScheduled = $campaign->status === CampaignStatus::Scheduled;
    $isSending  = $campaign->status === CampaignStatus::Sending;
    $isPaused   = $campaign->status === CampaignStatus::Paused;
    $isFinished = in_array($campaign->status, [CampaignStatus::Sent, CampaignStatus::Cancelled], true);

    // Gönderim dışında bırakılan adres sayısı: onay kutusu ile alıcı kartı
    // aynı sayıyı gösteriyor, iki yerde ayrı ayrı hesaplanmasın.
    $cikarilan = $breakdown[App\Enums\CampaignRecipientStatus::Skipped->value] ?? 0;
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
                    @if($isScheduled)
                        <h6><i class="bi bi-calendar-check me-2 text-teal"></i>Gönderim Zamanlandı</h6>
                    @else
                        <h6><i class="bi bi-shield-check me-2 text-teal"></i>Gönderim Onayı</h6>
                    @endif
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
                                @if($isScheduled)
                                    <p class="mb-2">
                                        Bu kampanya <strong class="text-teal fs-5">{{ number_format($preview['count']) }} kişiye</strong>
                                        <strong class="text-teal">{{ $campaign->scheduled_at?->format('d.m.Y H:i') }}</strong>
                                        itibarıyla gönderilecek.
                                    </p>
                                    <p class="text-clr-secondary small mb-3">
                                        @if($campaign->scheduled_at?->isFuture())
                                            Planlanan saate <strong>{{ $campaign->scheduled_at->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</strong> var.
                                            O saatten sonraki ilk cron turunda gönderim başlar;
                                        @else
                                            Planlanan saat geçti, gönderim ilk cron turunda başlayacak;
                                        @endif
                                        cron her {{ CampaignDispatcher::RUN_INTERVAL_MINUTES }} dakikada bir çalışır ve
                                        @if($campaign->throttled)
                                            turda en fazla <strong>{{ $perRunQuota }}</strong>, saatte en fazla
                                            <strong>{{ $hourlyLimit }}</strong> mail gönderir.
                                        @else
                                            saatlik limit ({{ $hourlyLimit }}) dolana kadar aralıksız gönderir.
                                        @endif
                                        Gönderim başlayana kadar listeyi düzenleyebilirsiniz.
                                    </p>
                                @else
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
                                @endif

                                @if($recipientsReady)
                                    {{-- Liste kayıtla birlikte kuruluyor: örnek göstermenin anlamı
                                         yok, tam listenin kendisi aşağıda süzgeçlenip düzenlenebiliyor. --}}
                                    <p class="stg-hint mb-0">
                                        <i class="bi bi-people me-1 text-teal"></i>
                                        @if($cikarilan > 0)
                                            <strong>{{ number_format($cikarilan) }} adres</strong> gönderim dışında bırakıldı.
                                        @endif
                                        <a href="#alicilar" class="alert-link">Alıcı listesini aşağıdan düzenleyebilirsiniz</a>:
                                        arayın, süzün, göndermek istemediğinizi çıkarın.
                                    </p>
                                @else
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
                                @endif
                            </div>
                            <div class="col-lg-5">
                                <div class="d-grid gap-2">
                                    @if($isScheduled)
                                        {{-- Zamanlanmış kampanyada asıl eylem tarihi değiştirmek; hemen
                                             göndermek isteyen de plandan vazgeçebilmeli. --}}
                                        <button type="button" class="btn-teal btn-lg cmp-plan" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                            <i class="bi bi-calendar-event"></i>
                                            <span class="cmp-plan__text">
                                                <span class="cmp-plan__date">{{ $campaign->scheduled_at?->format('d.m.Y H:i') }}</span>
                                                <span class="cmp-plan__hint">Zamanı değiştir</span>
                                            </span>
                                        </button>
                                        <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#approveModal">
                                            <i class="bi bi-send-fill"></i> Planı İptal Et, Hemen Gönder
                                        </button>
                                    @else
                                        <button type="button" class="btn-teal btn-lg" data-bs-toggle="modal" data-bs-target="#approveModal">
                                            <i class="bi bi-send-fill"></i> Onayla ve Gönderime Al
                                        </button>
                                        <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                            <i class="bi bi-clock"></i> İleri Bir Tarihe Zamanla
                                        </button>
                                    @endif
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
    {{-- Taslakta gösterilmiyor: liste onaydan önce hazırlanabildiği için
         alıcı sayısı dolu olsa da henüz süren bir gönderim yok. --}}
    @if(! $isDraft && $campaign->total_recipients > 0)
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
        {{-- ═══════════ 4. ALICI YÖNETİMİ ═══════════ --}}
        <div class="col-xl-8">
            {{-- ═══════════ ALICI LİSTESİ ═══════════ --}}
            @if($recipientsReady || $campaign->total_recipients > 0)
                <div class="card-dark mb-4" id="alicilar" data-aos="fade-up">
                    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6><i class="bi bi-people me-2 text-teal"></i>Alıcılar</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="cmp-badge">{{ number_format($recipients->total()) }}</span>
                            @if($isDraft)
                                @can('update', $campaign)
                                    {{-- Liste onaydan önce donduğu için kaynak listeye sonradan
                                         eklenenler kendiliğinden girmiyor; yenileme onu sağlıyor.
                                         Çıkarılan adresler de geri geldiği için onay isteniyor. --}}
                                    <form method="POST" action="{{ route('admin.campaigns.recipients.prepare', $campaign) }}"
                                          id="recipientRefreshForm" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="refresh" value="1">
                                        <button type="button" class="btn-glass btn-sm js-refresh-list"
                                                data-excluded="{{ $cikarilan }}">
                                            <i class="bi bi-arrow-repeat"></i> Listeyi yenile
                                        </button>
                                    </form>
                                @endcan
                            @endif
                            @if(($breakdown[App\Enums\CampaignRecipientStatus::Failed->value] ?? 0) > 0)
                                @can('update', $campaign)
                                    <form method="POST" action="{{ route('admin.campaigns.recipients.retry', $campaign) }}"
                                          id="retryAllForm" class="d-inline">
                                        @csrf
                                        <button type="button" class="btn-glass btn-sm js-retry-all">
                                            <i class="bi bi-arrow-clockwise"></i> Başarısızları yeniden dene
                                        </button>
                                    </form>
                                @endcan
                            @endif
                            {{-- Süzgeç dosyaya da taşınıyor: "başarısızları ver" diyen
                                 biri dosyada tüm listeyi bulmamalı. --}}
                            <a href="{{ route('admin.campaigns.recipients.export', [$campaign, 'rstatus' => $recipientFilter['status'] ?: null, 'rsearch' => $recipientFilter['search'] ?: null]) }}"
                               class="btn-glass btn-sm" title="Görünen listeyi CSV indir">
                                <i class="bi bi-download"></i> CSV
                            </a>
                            <x-export-menu export="campaign-recipients"
                                           :params="['campaign' => $campaign->id]"
                                           :total="$recipients->total()" />
                        </div>
                    </div>

                    <div class="card-body-custom">
                        {{-- Süzgeç GET: seçilen durum adres çubuğunda kalsın,
                             sayfa yenilendiğinde ya da paylaşıldığında kaybolmasın. --}}
                        <form method="GET" action="{{ route('admin.campaigns.show', $campaign) }}"
                              class="cmp-recipients__filter" data-validate novalidate>
                            <div class="cmp-chip-row">
                                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                                   class="cmp-chip {{ $recipientFilter['status'] === '' ? 'cmp-chip--aktif' : '' }}">
                                    Tümü
                                    <span class="cmp-chip__count">{{ number_format(array_sum($breakdown)) }}</span>
                                </a>
                                @foreach($statuses as $case)
                                    <a href="{{ route('admin.campaigns.show', [$campaign, 'rstatus' => $case->value]) }}"
                                       class="cmp-chip cmp-chip--{{ $case->badgeClass() }} {{ $recipientFilter['status'] === $case->value ? 'cmp-chip--aktif' : '' }}">
                                        {{ $case->label() }}
                                        <span class="cmp-chip__count">{{ number_format($breakdown[$case->value] ?? 0) }}</span>
                                    </a>
                                @endforeach
                            </div>

                            <div class="cmp-recipients__search">
                                @if($recipientFilter['status'] !== '')
                                    <input type="hidden" name="rstatus" value="{{ $recipientFilter['status'] }}">
                                @endif
                                <label class="visually-hidden" for="rsearch">Alıcı ara</label>
                                <input type="text" class="stg-input stg-input--sm" id="rsearch" name="rsearch"
                                       value="{{ $recipientFilter['search'] }}"
                                       data-validation-engine="validate[maxSize[191]]"
                                       placeholder="E-posta veya ad ara...">
                                <button type="submit" class="btn-glass btn-sm">
                                    <i class="bi bi-search"></i>
                                </button>
                                @if($recipientFilter['search'] !== '')
                                    <a href="{{ route('admin.campaigns.show', [$campaign, 'rstatus' => $recipientFilter['status'] ?: null]) }}"
                                       class="btn-glass btn-sm" title="Aramayı temizle">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>

                    @can('update', $campaign)
                        {{-- Toplu işlem formu tablonun dışında: satır içindeki tekil
                             işlem formlarıyla iç içe geçemez, kutular form niteliğiyle
                             bağlanıyor. --}}
                        <form method="POST" action="{{ route('admin.campaigns.recipients.bulk', $campaign) }}"
                              id="recipientBulkForm">
                            @csrf
                            <input type="hidden" name="action" id="recipientBulkAction" value="exclude">
                        </form>

                        <div class="cmp-bulk d-none" id="recipientBulkBar">
                            <span class="cmp-bulk__count">
                                <strong id="recipientBulkCount">0</strong> alıcı seçildi
                            </span>
                            <div class="cmp-bulk__actions">
                                <button type="button" class="btn-glass btn-sm js-bulk" data-action="exclude">
                                    <i class="bi bi-person-dash"></i> Gönderimden çıkar
                                </button>
                                <button type="button" class="btn-glass btn-sm js-bulk" data-action="restore">
                                    <i class="bi bi-arrow-counterclockwise"></i> Sıraya al
                                </button>
                                <button type="button" class="btn-glass btn-sm js-bulk" data-action="retry">
                                    <i class="bi bi-arrow-clockwise"></i> Yeniden dene
                                </button>
                            </div>
                            <button type="button" class="cmp-bulk__clear" id="recipientBulkClear">Seçimi bırak</button>
                        </div>
                    @endcan

                    <div class="card-body-custom p-0">
                        <div class="table-responsive">
                            <table class="cl-table">
                                <thead>
                                    <tr>
                                        @can('update', $campaign)
                                            <th class="sub-check-col">
                                                <input type="checkbox" id="recipientSelectAll"
                                                       aria-label="Tümünü seç" data-fv-ignore>
                                            </th>
                                        @endcan
                                        <th class="cmp-recipients__num">#</th>
                                        <th>Alıcı</th>
                                        <th>Durum</th>
                                        <th class="d-none d-lg-table-cell">Gönderim</th>
                                        <th class="d-none d-xl-table-cell">Not</th>
                                        <th class="cl-th-actions">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recipients as $index => $alici)
                                        <tr>
                                            @can('update', $campaign)
                                                <td class="sub-check-col">
                                                    <input type="checkbox" form="recipientBulkForm"
                                                           name="recipient_ids[]" value="{{ $alici->id }}"
                                                           class="js-recipient-row" data-fv-ignore
                                                           aria-label="{{ $alici->email }} seç">
                                                </td>
                                            @endcan
                                            <td class="cmp-recipients__num">
                                                {{ $recipients->firstItem() + $index }}
                                            </td>
                                            <td data-label="Alıcı">
                                                <div class="cmp-recipient">
                                                    <span class="cmp-recipient__mail">{{ $alici->email }}</span>
                                                    @if($alici->full_name)
                                                        <span class="cmp-recipient__name">{{ $alici->full_name }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td data-label="Durum">
                                                <span class="menu-manage-tag menu-manage-tag--{{ $alici->status->badgeClass() }}">
                                                    {{ $alici->status->label() }}
                                                </span>
                                            </td>
                                            <td data-label="Gönderim" class="d-none d-lg-table-cell">
                                                @if($alici->sent_at)
                                                    <div class="sub-date">
                                                        <span>{{ $alici->sent_at->translatedFormat('d M, H:i') }}</span>
                                                        <small>{{ $alici->sent_at->diffForHumans() }}</small>
                                                    </div>
                                                @elseif($alici->attempts > 0)
                                                    <small class="text-clr-secondary">{{ $alici->attempts }} deneme</small>
                                                @else
                                                    <span class="text-clr-secondary">—</span>
                                                @endif
                                            </td>
                                            <td data-label="Not" class="d-none d-xl-table-cell">
                                                @if($alici->error)
                                                    <small class="text-neon-red" title="{{ $alici->error }}">
                                                        {{ \Illuminate\Support\Str::limit($alici->error, 60) }}
                                                    </small>
                                                @else
                                                    <span class="text-clr-secondary">—</span>
                                                @endif
                                            </td>
                                            <td data-label="İşlem">
                                                @can('update', $campaign)
                                                    <div class="usr-actions">
                                                        @if($alici->status === App\Enums\CampaignRecipientStatus::Skipped)
                                                            <form method="POST" action="{{ route('admin.campaigns.recipients.restore', [$campaign, $alici]) }}">
                                                                @csrf
                                                                <button type="submit" class="usr-action-btn success" title="Sıraya geri al">
                                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                                </button>
                                                            </form>
                                                        @elseif($alici->status !== App\Enums\CampaignRecipientStatus::Sent)
                                                            <form method="POST" action="{{ route('admin.campaigns.recipients.exclude', [$campaign, $alici]) }}">
                                                                @csrf
                                                                <button type="submit" class="usr-action-btn danger" title="Gönderimden çıkar">
                                                                    <i class="bi bi-person-dash"></i>
                                                                </button>
                                                            </form>
                                                        @else
                                                            {{-- Gönderilmiş adres geri alınamaz: mail yola çıktı. --}}
                                                            <span class="text-clr-secondary" title="Mail gönderildi">—</span>
                                                        @endif
                                                    </div>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->user()->can('update', $campaign) ? 7 : 6 }}" class="text-center py-5">
                                                <i class="bi bi-people d-block mb-2 fs-2 text-muted"></i>
                                                <span class="text-muted">
                                                    @if($recipientFilter['search'] !== '' || $recipientFilter['status'] !== '')
                                                        Bu süzgeçle eşleşen alıcı yok.
                                                    @else
                                                        Bu listede alıcı yok.
                                                    @endif
                                                </span>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($recipients->hasPages())
                        <div class="card-body-custom">
                            {{ $recipients->links() }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- ═══════════ SIRADAKİ TUR ═══════════ --}}
            @if($nextBatch->isNotEmpty())
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6><i class="bi bi-hourglass-split me-2 text-teal"></i>Önümüzdeki Turda Gidecekler</h6>
                        <span class="cmp-badge">{{ $nextBatch->count() }} adres</span>
                    </div>
                    <div class="card-body-custom">
                        <p class="stg-hint mb-3">
                            Zamanlanmış görev
                            @if($nextRunAt)
                                <strong>{{ $nextRunAt->format('H:i') }}</strong>
                            @endif
                            çalıştığında sırayla bu adreslere gidecek. Listeden çıkarırsanız
                            bu turda da sonrakilerde de gönderilmez.
                        </p>
                        <ol class="cmp-next">
                            @foreach($nextBatch as $aday)
                                <li class="cmp-next__row">
                                    <span class="cmp-next__mail">{{ $aday->email }}</span>
                                    @if($aday->full_name)
                                        <span class="cmp-next__name">{{ $aday->full_name }}</span>
                                    @endif
                                    @can('update', $campaign)
                                        <form method="POST" class="cmp-next__form"
                                              action="{{ route('admin.campaigns.recipients.exclude', [$campaign, $aday]) }}">
                                            @csrf
                                            <button type="submit" class="usr-action-btn danger" title="Gönderimden çıkar">
                                                <i class="bi bi-person-dash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            @endif

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

            {{-- ═══════════ MAİL ÖNİZLEME ═══════════ --}}
            {{-- Katlı geliyor: sayfanın en uzun parçası bu, ama gövdeye bir kez
                 bakılıp geçiliyor. Kapalıyken alıcı listesi ile ekler tek ekrana
                 sığıyor. Açma işi <details>'e bırakıldı, JS'e gerek yok. --}}
            <details class="card-dark cmp-fold mb-4" data-aos="fade-up">
                {{-- Başlık satırı summary'nin içinde ayrı bir kutu: summary'nin
                     kendisine display verilirse (card-header-custom da flex veriyor)
                     tarayıcı onu özet saymayı bırakıyor, kart tıklamayla açılmıyor. --}}
                <summary class="cmp-fold__head">
                    <span class="card-header-custom cmp-fold__row">
                        <span class="cmp-fold__title">
                            <i class="bi bi-envelope me-2 text-teal"></i>Mail Önizleme
                        </span>
                        <span class="text-clr-secondary small">
                            <strong>Konu:</strong> {{ $campaign->subject }}
                        </span>
                        <i class="bi bi-chevron-down cmp-fold__ok" aria-hidden="true"></i>
                    </span>
                </summary>
                <div class="card-body-custom">
                    {{-- Sandboxed: admin-authored HTML, rendered only to be looked at.

                         Gövde ham hâliyle değil, mailin gördüğü hâliyle gösteriliyor:
                         görseller kapsayıcı genişliğine sığdırılıyor ve içerik mailin
                         600 pikselik sütununa alınıyor. Önizleme ile gelen mailin
                         farklı görünmesi, tasarımın bozuk olduğunu ancak gönderimden
                         sonra fark ettiriyordu. --}}
                    <iframe srcdoc="{{ $bodyPreview }}" sandbox
                            class="cmp-preview" title="Mail önizleme"></iframe>
                </div>
            </details>
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
                    <div class="detail-list">
                        {{-- Gönderen adı ile adresi ayrı satırlarda: tek satıra
                             sıkışınca ikisi de okunmuyordu. --}}
                        <div class="detail-item">
                            <span class="detail-label"><i class="bi bi-send"></i> Gönderen</span>
                            <span class="detail-value">
                                {{ $campaign->senderName() }}
                                <span class="detail-hint">{{ $campaign->senderAddress() }}</span>
                            </span>
                        </div>

                        @if($campaign->reply_to)
                            <div class="detail-item">
                                <span class="detail-label"><i class="bi bi-reply"></i> Yanıt Adresi</span>
                                <span class="detail-value">{{ $campaign->reply_to }}</span>
                            </div>
                        @endif

                        <div class="detail-item">
                            <span class="detail-label"><i class="bi bi-speedometer2"></i> Gönderim Şekli</span>
                            <span class="detail-value">
                                <span class="menu-manage-tag menu-manage-tag--{{ $campaign->throttled ? 'blue' : 'orange' }}">
                                    <i class="bi {{ $campaign->throttled ? 'bi-hourglass-split' : 'bi-lightning-charge-fill' }}"></i>
                                    {{ $campaign->throttled ? 'Saate yayarak' : 'Aralıksız' }}
                                </span>
                                <span class="detail-hint">
                                    {{ $campaign->throttled
                                        ? 'Saatlik sınır kadar gönderilir, kalanı sonraki saate kalır.'
                                        : 'Saatlik sınır dolana kadar ara vermeden gönderilir.' }}
                                </span>
                            </span>
                        </div>

                        @if($campaign->locale)
                            <div class="detail-item">
                                <span class="detail-label"><i class="bi bi-translate"></i> Dil</span>
                                <span class="detail-value">{{ strtoupper($campaign->locale) }}</span>
                            </div>
                        @endif

                        @foreach([
                            'Zamanlanan' => ['date' => $campaign->scheduled_at, 'icon' => 'bi-calendar-event'],
                            'Başlangıç'  => ['date' => $campaign->started_at,   'icon' => 'bi-play-circle'],
                            'Tamamlanma' => ['date' => $campaign->completed_at, 'icon' => 'bi-check-circle'],
                        ] as $label => $row)
                            @if($row['date'])
                                <div class="detail-item">
                                    <span class="detail-label"><i class="bi {{ $row['icon'] }}"></i> {{ $label }}</span>
                                    <span class="detail-value">
                                        {{ $row['date']->translatedFormat('d F Y, H:i') }}
                                        <span class="detail-hint">{{ $row['date']->diffForHumans() }}</span>
                                    </span>
                                </div>
                            @endif
                        @endforeach

                        @if($campaign->author)
                            <div class="detail-item">
                                <span class="detail-label"><i class="bi bi-person-badge"></i> Oluşturan</span>
                                <span class="detail-value">{{ $campaign->author->full_name }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($campaign->attachments->isNotEmpty())
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h6><i class="bi bi-paperclip me-2 text-teal"></i>Ekler</h6>
                        <span class="cmp-badge">{{ $campaign->attachments->count() }}</span>
                    </div>
                    <div class="card-body-custom">
                        <ol class="cmp-files">
                            @foreach($campaign->attachments as $attachment)
                                {{-- Sıra numarası mailde göründüğü sırayla aynı; ek
                                     konuşulurken "üçüncü dosya" demek mümkün olsun. --}}
                                <li class="cmp-file">
                                    <a href="{{ upload_url($attachment->path) }}" class="cmp-file__link"
                                       target="_blank" rel="noopener"
                                       title="{{ $attachment->original_name }} — yeni sekmede aç">
                                        <i class="bi bi-file-earmark-arrow-down cmp-file__icon"></i>
                                        <span class="cmp-file__name">{{ $attachment->original_name }}</span>
                                    </a>
                                    <span class="cmp-file__size">{{ $attachment->humanSize() }}</span>
                                </li>
                            @endforeach
                        </ol>
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
                                <h6 class="modal-title">
                                    <i class="bi bi-send-fill me-2 text-teal"></i>
                                    {{ $isScheduled ? 'Planı İptal Et, Hemen Gönder' : 'Gönderimi Onayla' }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body">
                                @if($isScheduled)
                                    <p>
                                        <strong>{{ $campaign->scheduled_at?->format('d.m.Y H:i') }}</strong> planı iptal edilecek ve
                                        <strong>{{ number_format($preview['count']) }} kişiye</strong>
                                        "<em>{{ $campaign->subject }}</em>" konulu mail hemen sıraya alınacak.
                                    </p>
                                @else
                                    <p><strong>{{ number_format($preview['count']) }} kişiye</strong>
                                       "<em>{{ $campaign->subject }}</em>" konulu mail gönderilecek.</p>
                                @endif
                                <p class="text-clr-secondary small mb-0">
                                    Gönderim {{ $nextRunAt->format('H:i') }} itibarıyla başlar ve saatlik limite göre
                                    yayılır. Başladıktan sonra içerik değiştirilemez; yalnızca duraklatabilir veya
                                    iptal edebilirsiniz. Gönderilmiş mailler geri alınamaz.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal">
                                    <i class="bi bi-check-lg"></i>
                                    {{ $isScheduled ? 'Evet, Hemen Gönder' : 'Onaylıyorum, Gönder' }}
                                </button>
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
                                <h6 class="modal-title">
                                    <i class="bi bi-clock me-2 text-teal"></i>
                                    {{ $isScheduled ? 'Gönderim Zamanını Değiştir' : 'Gönderimi Zamanla' }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body">
                                @if($isScheduled)
                                    <p class="text-clr-secondary small">
                                        Şu anki plan: <strong class="text-teal">{{ $campaign->scheduled_at?->format('d.m.Y H:i') }}</strong>.
                                        Yeni bir saat seçtiğinizde bu planın yerini alır.
                                    </p>
                                @endif
                                <div class="stg-field">
                                    <label class="stg-label" for="scheduled_at">Gönderim zamanı</label>
                                    {{-- Mevcut plan alana dolu geliyor: kullanıcı saati değiştirirken
                                         tarihi baştan yazmak zorunda kalmasın. --}}
                                    <input type="datetime-local" class="stg-input" id="scheduled_at" name="scheduled_at"
                                           value="{{ $campaign->scheduled_at?->format('Y-m-d\TH:i') }}"
                                           data-validation-engine="validate[required]">
                                    <small class="stg-hint">
                                        Belirtilen saatten sonraki ilk cron turunda başlar. Gönderim aşağıdaki
                                        listeye yapılır; o tarihe kadar eklenen yeni adresleri de katmak için
                                        listeyi yenileyin.
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal">
                                    {{ $isScheduled ? 'Zamanı Güncelle' : 'Zamanla' }}
                                </button>
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

    /* Planlı gönderim düğmesi: tarih birinci satır, eylem ikinci satır —
       ikisi tek satıra sığmıyor ve sarınca dağınık duruyordu. */
    .cmp-plan__text { display: inline-flex; flex-direction: column; line-height: 1.25; }
    .cmp-plan__date { font-weight: 600; }
    .cmp-plan__hint { font-size: .75rem; opacity: .85; font-weight: 400; }
    .campaign-sample summary { cursor: pointer; }

    /* Katlanır kart: başlık satırının kendisi açma düğmesi.
       summary'ye display verilmiyor — verilince tarayıcı onu özet saymıyor. */
    .cmp-fold__head { cursor: pointer; list-style: none; }
    .cmp-fold__head::-webkit-details-marker { display: none; }
    .cmp-fold__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .cmp-fold__title { font-size: .95rem; font-weight: 600; }
    .cmp-fold:not([open]) .cmp-fold__row { border-bottom: 0; }
    .cmp-fold__ok { margin-left: auto; transition: transform .2s ease; }
    .cmp-fold[open] .cmp-fold__ok { transform: rotate(180deg); }
    @media (prefers-reduced-motion: reduce) { .cmp-fold__ok { transition: none; } }
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
    <script src="{{ versioned_asset('assets/admin/js/campaign-recipients.js') }}"></script>
@endpush
