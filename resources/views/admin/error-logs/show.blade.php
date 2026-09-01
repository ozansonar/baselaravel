@extends('layouts.admin')

@section('title', 'Hata Detayı')
@section('page_title', 'Hata Detayı')
@section('page_description', $log->shortException())

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.error-logs.index') }}" class="breadcrumb-link">Hata Kayıtları</a>
            </li>
            <li class="breadcrumb-item active text-teal">{{ $log->shortException() }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">
                {{ $log->shortException() }}
                @if($log->isResolved())
                    <span class="usr-status-badge active ms-2"><i class="bi bi-check2-circle me-1"></i>Çözüldü</span>
                @else
                    <span class="usr-status-badge suspended ms-2"><i class="bi bi-exclamation-octagon me-1"></i>Açık</span>
                @endif
            </h1>
            <p class="page-subtitle">{{ $log->exception }}</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('admin.error-logs.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left"></i> Listeye Dön
            </a>

            @can('update', $log)
                @if($log->isResolved())
                    <form method="POST" action="{{ route('admin.error-logs.reopen', $log->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-glass">
                            <i class="bi bi-arrow-counterclockwise"></i> Yeniden Aç
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.error-logs.resolve', $log->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-teal">
                            <i class="bi bi-check2"></i> Çözüldü İşaretle
                        </button>
                    </form>
                @endif
            @endcan

            @can('delete', $log)
                <form method="POST" action="{{ route('admin.error-logs.destroy', $log->id) }}" id="elDeleteForm">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn-glass"
                            data-confirm-submit="elDeleteForm"
                            data-confirm-title="Hata kaydını sil"
                            data-confirm-message="Bu kayıt silinecek. Aynı hata yeniden oluşursa listede yeniden görünür."
                            data-confirm-text="Evet, Sil"
                            data-confirm-icon="bi bi-trash3">
                        <i class="bi bi-trash"></i> Sil
                    </button>
                </form>
            @endcan
        </div>
    </div>

    {{-- ==================== SECTION 1: ÖZET KUTULARI ==================== --}}
    <div class="al-meta-grid mb-4" data-aos="fade-up">
        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-arrow-repeat"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">Tekrar</span>
                <span class="al-meta-value">{{ number_format($log->occurrences, 0, ',', '.') }} kez</span>
                <span class="al-meta-hint">{{ $log->id }} numaralı kayıt</span>
            </div>
        </div>

        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-clock-history"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">Son Görülme</span>
                <span class="al-meta-value">{{ $log->last_seen_at?->translatedFormat('d M Y H:i') }}</span>
                <span class="al-meta-hint">{{ $log->last_seen_at?->diffForHumans() }}</span>
            </div>
        </div>

        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-calendar-event"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">İlk Görülme</span>
                <span class="al-meta-value">{{ $log->first_seen_at?->translatedFormat('d M Y H:i') }}</span>
                <span class="al-meta-hint">{{ $log->first_seen_at?->diffForHumans() }}</span>
            </div>
        </div>

        <div class="al-meta-card">
            <div class="al-meta-icon"><i class="bi bi-code-slash"></i></div>
            <div class="al-meta-body">
                <span class="al-meta-label">Kaynak</span>
                <span class="al-meta-value">{{ $log->isVendor() ? 'Paket (vendor)' : 'Proje kodu' }}</span>
                <span class="al-meta-hint">
                    {{ $log->isVendor()
                        ? 'Düzeltilecek yer çoğu zaman burayı çağıran kendi kodumuz'
                        : 'Doğrudan projenin kendi kodu' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Çözülmüş kaydın kim tarafından ne zaman kapatıldığı --}}
    @if($log->isResolved())
        <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="50">
            <i class="bi bi-check2-circle"></i>
            <div>
                <strong>Bu hata çözüldü olarak işaretlendi.</strong>
                {{ $log->resolved_at?->translatedFormat('d F Y H:i') }}
                @if($log->resolver)
                    — {{ $log->resolver->full_name }}
                @endif
                . Aynı hata yeniden oluşursa işaret kendiliğinden kalkar ve kayıt yeniden açık listesine döner.
            </div>
        </div>
    @endif

    <div class="row g-4">
        {{-- ==================== SECTION 2: MESAJ VE YIĞIN İZİ ==================== --}}
        <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-chat-square-text me-2 text-teal"></i>Hata Mesajı</h6>
                </div>
                <div class="card-body-custom">
                    <pre class="qs-exception mb-3">{{ $log->message !== null && $log->message !== '' ? $log->message : '(mesaj yok)' }}</pre>

                    <div class="al-detail-list">
                        <div class="al-detail-row">
                            <span class="al-detail-label">Konum</span>
                            <span class="al-detail-value"><span class="al-ip">{{ $log->location() }}</span></span>
                        </div>
                        <div class="al-detail-row">
                            <span class="al-detail-label">Hata sınıfı</span>
                            <span class="al-detail-value"><span class="al-ip">{{ $log->exception }}</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-list-ol me-2 text-teal"></i>Yığın İzi</h6>
                </div>
                <div class="card-body-custom">
                    {{-- İz, hatanın hangi çağrı zinciriyle oluştuğunu gösterir.
                         En üstteki satır patladığı yer, aşağı indikçe onu
                         çağıran kod. Varsa önceki hatalar da altta. --}}
                    <pre class="qs-exception">{{ $log->trace ?: 'Yığın izi kaydedilmemiş.' }}</pre>
                </div>
            </div>
        </div>

        {{-- ==================== SECTION 3: İSTEK BİLGİSİ ==================== --}}
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="150">
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-globe2 me-2 text-teal"></i>Son İstek</h6>
                </div>
                <div class="card-body-custom">
                    {{-- Kayıt tek satır olduğu için buradaki bilgiler hatanın
                         **en son** görüldüğü isteğe ait; önceki tekrarların
                         adresi saklanmıyor. --}}
                    @if($log->url === null && $log->method === null)
                        <p class="text-muted mb-0">
                            <i class="bi bi-terminal me-1"></i>
                            Bu hata bir tarayıcı isteğinden değil, konsoldan ya da zamanlanmış bir görevden geldi.
                        </p>
                    @else
                        <div class="al-detail-list">
                            <div class="al-detail-row">
                                <span class="al-detail-label">Adres</span>
                                <span class="al-detail-value"><span class="al-ip">{{ $log->url ?: '—' }}</span></span>
                            </div>
                            <div class="al-detail-row">
                                <span class="al-detail-label">Yöntem</span>
                                <span class="al-detail-value">{{ $log->method ?: '—' }}</span>
                            </div>
                            <div class="al-detail-row">
                                <span class="al-detail-label">IP</span>
                                <span class="al-detail-value"><span class="al-ip">{{ $log->ip_address ?: '—' }}</span></span>
                            </div>
                            <div class="al-detail-row">
                                <span class="al-detail-label">Kullanıcı</span>
                                <span class="al-detail-value">
                                    {{ $log->user?->full_name ?? 'Giriş yapılmamış' }}
                                </span>
                            </div>
                            <div class="al-detail-row">
                                <span class="al-detail-label">Tarayıcı</span>
                                <span class="al-detail-value">{{ $log->user_agent ?: '—' }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="nt-card">
                <div class="nt-card-header">
                    <div class="nt-card-icon c-orange"><i class="bi bi-recycle"></i></div>
                    <h6>Saklama</h6>
                </div>
                <div class="nt-card-body">
                    <p class="text-muted mb-0">
                        {{ $retentionDays }} gündür tekrar etmeyen kayıtlar her pazar 03:40'ta otomatik siliniyor.
                        Tekrar eden bir hata "eski" sayılmaz — ölçüt son görülme tarihi.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
