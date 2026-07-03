@extends('layouts.admin')

@section('title', 'Google Entegrasyonu')
@section('page_title', 'Google Entegrasyonu')
@section('page_description', 'Service Account JSON yükle, GSC ve Indexing API ayarlarını yönet')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Google Entegrasyonu</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-google me-2"></i>Google Entegrasyonu</h1>
            <p class="page-subtitle">
                Service Account JSON yükle, Search Console ve Indexing API ayarlarını yönet.
                Tüm değişiklikler arka planda çalışır — site hızı etkilenmez.
            </p>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success mb-4" data-aos="fade-up">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- SECTION 1: STATUS --}}
    <div class="card-dark mb-4 topic-pool-alert {{ $configured ? 'topic-pool-alert--healthy' : 'topic-pool-alert--warning' }}" data-aos="fade-up">
        <div class="card-body-custom">
            <div class="d-flex align-items-start gap-3 flex-wrap">
                <div class="topic-pool-alert__icon">
                    @if($configured)
                        <i class="bi bi-shield-check"></i>
                    @else
                        <i class="bi bi-shield-exclamation"></i>
                    @endif
                </div>
                <div class="flex-grow-1">
                    @if($activeMode === 'oauth')
                        <h6 class="mb-1 topic-pool-alert__title">Bağlandı (OAuth 2.0 — kullanıcı hesabı)</h6>
                        <p class="mb-2 small text-muted">
                            Bağlı hesap: <code>{{ $oauthToken?->google_email ?? '—' }}</code><br>
                            Token son yenilenme: <strong>{{ $oauthToken?->last_refreshed_at?->diffForHumans() ?? '—' }}</strong>
                            @if($lastSuccess)
                                · Son başarılı API çağrısı: <strong>{{ $lastSuccess->created_at->diffForHumans() }}</strong>
                            @endif
                        </p>
                    @elseif($activeMode === 'service_account')
                        <h6 class="mb-1 topic-pool-alert__title">Bağlandı (Service Account JSON — eski yöntem)</h6>
                        <p class="mb-2 small text-muted">
                            Service Account: <code>{{ $serviceAccountInfo['client_email'] ?? '—' }}</code><br>
                            Proje: <code>{{ $serviceAccountInfo['project_id'] ?? '—' }}</code><br>
                            JSON yüklendi: <strong>{{ $jsonUploadedAt?->translatedFormat('d M Y H:i') ?? '—' }}</strong>
                            @if($lastSuccess)
                                · Son başarılı API çağrısı: <strong>{{ $lastSuccess->created_at->diffForHumans() }}</strong>
                            @endif
                        </p>
                    @else
                        <h6 class="mb-1 topic-pool-alert__title">Bağlı değil</h6>
                        <p class="mb-0 small text-muted">
                            Aşağıdaki <strong>"Google ile Bağlan"</strong> kartından OAuth 2.0 ile bağlan (önerilen).
                            Alternatif olarak Service Account JSON yüklemesi de yapılabilir.
                        </p>
                    @endif
                </div>
                @if($configured)
                    <button type="button" id="testConnectionBtn" class="usr-action-btn topic-convert-btn">
                        <i class="bi bi-wifi"></i> Bağlantıyı Test Et
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- SECTION 1B: OAUTH 2.0 (PREFERRED) --}}
    <div class="card-dark mb-4 gint-oauth-card" data-aos="fade-up" data-aos-delay="50">
        <div class="card-header-custom d-flex align-items-center gap-3 flex-wrap">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-google"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-0">
                    Google ile Bağlan
                    <span class="badge bg-success ms-2">Önerilen</span>
                </h6>
                <small class="text-muted">
                    OAuth 2.0 user-flow — kendi Google hesabınla giriş yap, GSC'de zaten owner olduğun
                    için ek e-posta eklemeye gerek yok.
                </small>
            </div>
        </div>
        <div class="card-body-custom">
            @if(! $oauthConfigured)
                <div class="alert alert-warning mb-3 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>OAuth Client ID veya Secret tanımlı değil.</strong>
                    Aşağıdaki formdan doldur, sonra "Google ile Bağlan" butonu aktif olacak.
                    Bu bilgileri Google Cloud Console → APIs &amp; Services → Credentials sayfasından
                    "OAuth 2.0 Client ID" oluşturarak elde edersin
                    (detaylı adımlar için <code>docs/google-oauth-kurulum-rehberi.md</code>).
                </div>
            @endif

            <form method="POST" action="{{ route('admin.google-integration.oauth-credentials.update') }}" class="mb-4">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="googleOAuthClientId" class="form-label small text-muted">
                            <i class="bi bi-key me-1"></i> OAuth Client ID
                        </label>
                        <input type="text" id="googleOAuthClientId" name="google_oauth_client_id"
                               class="stg-input"
                               value="{{ $oauthClientId }}"
                               placeholder="123456789-abc.apps.googleusercontent.com"
                               autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label for="googleOAuthClientSecret" class="form-label small text-muted">
                            <i class="bi bi-shield-lock me-1"></i> OAuth Client Secret
                        </label>
                        <input type="password" id="googleOAuthClientSecret" name="google_oauth_client_secret"
                               class="stg-input"
                               placeholder="{{ $oauthSecretSet ? '•••••••• (kayıtlı)' : 'GOCSPX-...' }}"
                               autocomplete="new-password">
                        <small class="text-muted d-block mt-1">
                            Boş bırakırsan mevcut değer korunur.
                        </small>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn-glass">
                        <i class="bi bi-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>

            <hr class="my-3 gint-divider">


            @if($oauthToken)
                <div class="gint-oauth-connected">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="gint-oauth-avatar"><i class="bi bi-person-check"></i></div>
                        <div class="flex-grow-1">
                            <strong>{{ $oauthToken->google_email }}</strong>
                            <div class="small text-muted">
                                Bağlandı: {{ $oauthToken->created_at->diffForHumans() }} ·
                                Token yenileme: {{ $oauthToken->last_refreshed_at?->diffForHumans() ?? 'henüz yenilenmedi' }}
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.google-integration.oauth.disconnect') }}"
                              id="gintOAuthDisconnectForm" class="d-inline">
                            @csrf
                            <button type="button" class="btn-glass" id="gintOAuthDisconnectBtn">
                                <i class="bi bi-power me-1"></i> Bağlantıyı Kes
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div class="small text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Tıklayınca Google'ın izin ekranına yönlendirileceksin. <strong>"İzin Ver"</strong>
                        dedikten sonra otomatik buraya geri döner ve bağlantı kurulur.
                    </div>
                    <a href="{{ route('admin.google-integration.oauth.start') }}"
                       class="btn-teal {{ $oauthConfigured ? '' : 'gint-btn-disabled' }}"
                       @if(! $oauthConfigured) onclick="event.preventDefault();" @endif>
                        <i class="bi bi-google me-1"></i> Google ile Bağlan
                    </a>
                </div>
            @endif

            <div class="gint-oauth-redirect-uri mt-3 small text-muted">
                <strong>Authorized redirect URI</strong> (Google Cloud Console'da OAuth client'a eklenmesi gereken):
                <br>
                <code class="user-select-all">{{ $oauthRedirectUri }}</code>
            </div>
        </div>
    </div>

    {{-- SECTION 2: UPLOAD FORM (LEGACY — alternative path) --}}
    <div class="card-dark mb-4 {{ $activeMode === 'oauth' ? 'gint-legacy-card--dimmed' : '' }}" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-blue"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-0">
                    Service Account JSON Yükle
                    <span class="badge bg-secondary ms-2">Alternatif</span>
                </h6>
                <small class="text-muted">
                    Eski yöntem — service account email'i Search Console'a eklemeyi gerektirir.
                    OAuth bağlıyken bu yol kullanılmaz.
                </small>
            </div>
        </div>
        <div class="card-body-custom">
            <form method="POST" action="{{ route('admin.google-integration.upload') }}" enctype="multipart/form-data" id="gjsonUploadForm">
                @csrf
                <div class="gint-upload-zone" id="gintDropzone">
                    <input type="file" name="service_account_file" id="serviceAccountFile" accept=".json,application/json" class="gint-upload-input" required>
                    <label for="serviceAccountFile" class="gint-upload-label">
                        <i class="bi bi-file-earmark-code gint-upload-icon"></i>
                        <span class="gint-upload-text">
                            <strong>JSON dosyasını seçmek için tıkla</strong>
                            <small>veya buraya sürükle bırak</small>
                        </span>
                        <span class="gint-upload-meta">.json · max 64 KB</span>
                    </label>
                    <div class="gint-upload-selected" id="gintSelectedFile" style="display:none">
                        <i class="bi bi-file-earmark-check"></i>
                        <span class="gint-selected-name"></span>
                        <button type="button" class="gint-clear-btn" id="gintClearBtn"><i class="bi bi-x"></i></button>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                    <small class="text-muted">
                        <i class="bi bi-shield-lock me-1"></i>
                        Dosya <code>storage/app/google/</code> altına chmod 600 ile kaydedilir. Web'den erişilemez, git'e commit edilmez.
                    </small>
                    <div class="d-flex gap-2">
                        @if($configured)
                            <form method="POST" action="{{ route('admin.google-integration.delete') }}" class="d-inline" id="gintDeleteForm">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="usr-action-btn danger topic-convert-btn" id="gintDeleteBtn">
                                    <i class="bi bi-trash"></i> Mevcut JSON'u Sil
                                </button>
                            </form>
                        @endif
                        <button type="submit" class="btn-teal" id="gintUploadBtn" disabled>
                            <i class="bi bi-upload me-1"></i> {{ $configured ? 'Yeni JSON ile Değiştir' : 'JSON Yükle' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 3: FEATURE FLAGS --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-toggles"></i></div>
            <div>
                <h6 class="mb-0">Entegrasyon Ayarları</h6>
                <small class="text-muted">Hangi servislerin aktif olacağını seç</small>
            </div>
        </div>
        <div class="card-body-custom">
            <form method="POST" action="{{ route('admin.google-integration.update-settings') }}">
                @csrf
                @method('PUT')

                <div class="gint-toggle-row">
                    <div class="gint-toggle-info">
                        <h6 class="mb-1"><i class="bi bi-search me-1 text-info"></i>Search Console (Veri Çekme)</h6>
                        <small class="text-muted">Cron 6 saatte bir top queries + per-page metrikleri çeker. Pasif edilirse veri toplama durur.</small>
                    </div>
                    <label class="gint-switch">
                        <input type="hidden" name="google_gsc_enabled" value="0">
                        <input type="checkbox" name="google_gsc_enabled" value="1" {{ $gscEnabled ? 'checked' : '' }}>
                        <span class="gint-switch-slider"></span>
                    </label>
                </div>

                <div class="gint-toggle-row">
                    <div class="gint-toggle-info">
                        <h6 class="mb-1"><i class="bi bi-arrow-up-right-square me-1 text-warning"></i>Indexing API (Otomatik İndeksleme)</h6>
                        <small class="text-muted">Yeni ürün/blog/şehir sayfası eklendiğinde Google'a otomatik "tara" emri gönderir. Önerilir.</small>
                    </div>
                    <label class="gint-switch">
                        <input type="hidden" name="google_indexing_enabled" value="0">
                        <input type="checkbox" name="google_indexing_enabled" value="1" {{ $indexingEnabled ? 'checked' : '' }}>
                        <span class="gint-switch-slider"></span>
                    </label>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-check2 me-1"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION 4: PROPERTY INFO --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-purple"><i class="bi bi-globe"></i></div>
            <div>
                <h6 class="mb-0">Property URL</h6>
                <small class="text-muted">Search Console'da kayıtlı olan site URL'si</small>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <code class="flex-grow-1">{{ $propertyUrl }}</code>
                <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer" class="usr-action-btn topic-convert-btn">
                    <i class="bi bi-box-arrow-up-right"></i> Search Console'da Aç
                </a>
            </div>
            <small class="text-muted d-block mt-2">
                Bu URL'yi değiştirmek için <code>.env</code> dosyasındaki <code>GSC_PROPERTY_URL</code> değişkenini düzenle.
            </small>
        </div>
    </div>

    {{-- SECTION 5: STEP-BY-STEP GUIDE --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="250">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-3" role="button" data-bs-toggle="collapse" data-bs-target="#guideCollapse">
            <div class="d-flex align-items-center gap-3">
                <div class="form-section-icon bg-icon-blue"><i class="bi bi-question-circle-fill"></i></div>
                <div>
                    <h6 class="mb-0">JSON Anahtarını Nasıl Alırım?</h6>
                    <small class="text-muted">7 adımlık rehber — detayları görmek için tıkla</small>
                </div>
            </div>
            <i class="bi bi-chevron-down topic-help-chevron"></i>
        </div>
        <div class="collapse" id="guideCollapse">
            <div class="card-body-custom">
                <ol class="gint-steps">
                    <li>
                        <strong>Google Cloud Console:</strong>
                        <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> aç
                        → <em>Yeni Proje</em> oluştur (örn: <code>orhanbabaninciftligi-seo</code>)
                    </li>
                    <li>
                        <strong>API'leri etkinleştir:</strong> <em>APIs & Services → Enabled APIs</em> →
                        <em>+ ENABLE APIS</em> → <code>Search Console API</code> ve <code>Indexing API</code>
                    </li>
                    <li>
                        <strong>Service Account oluştur:</strong> <em>APIs & Services → Credentials</em>
                        → <em>+ CREATE CREDENTIALS → Service account</em>
                        <br><small>Adı: <code>gsc-integration</code></small>
                    </li>
                    <li>
                        <strong>JSON anahtarı indir:</strong> Service account'a tıkla →
                        <em>KEYS sekmesi → ADD KEY → Create new key → JSON</em>
                        <br><small>JSON dosyası bilgisayarına otomatik iner.</small>
                    </li>
                    <li>
                        <strong>Search Console'da yetki ver:</strong>
                        <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a>
                        → <em>Settings → Users and permissions → ADD USER</em>
                        <br><small>Service account email'i (örn: <code>...@projeniz.iam.gserviceaccount.com</code>) → permission: <strong>Owner</strong></small>
                    </li>
                    <li>
                        <strong>JSON'u yukarıdaki forma yükle</strong> ve <em>Yükle</em> butonuna bas.
                    </li>
                    <li>
                        <strong>Bağlantıyı Test Et</strong> butonuna tıkla → "✓ Bağlantı başarılı" mesajını gör →
                        <a href="{{ route('admin.seo-performance.index') }}">SEO Performans</a> sayfasında verileri gör.
                    </li>
                </ol>
                <div class="alert alert-info mt-3 mb-0">
                    <small>
                        <i class="bi bi-info-circle me-1"></i>
                        Detaylı dökümantasyon: <code>docs/google-api-kurulum-rehberi.md</code> dosyasına bak.
                    </small>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fileInput   = document.getElementById('serviceAccountFile');
    const dropzone    = document.getElementById('gintDropzone');
    const selectedBox = document.getElementById('gintSelectedFile');
    const selectedName = document.querySelector('.gint-selected-name');
    const clearBtn    = document.getElementById('gintClearBtn');
    const uploadBtn   = document.getElementById('gintUploadBtn');
    const labelEl     = dropzone?.querySelector('.gint-upload-label');

    const showSelected = (file) => {
        if (!file) return;
        selectedName.textContent = file.name + ' (' + Math.round(file.size / 1024 * 10) / 10 + ' KB)';
        if (labelEl) labelEl.style.display = 'none';
        selectedBox.style.display = 'flex';
        uploadBtn.disabled = false;
    };

    const clearSelected = () => {
        fileInput.value = '';
        selectedBox.style.display = 'none';
        if (labelEl) labelEl.style.display = '';
        uploadBtn.disabled = true;
    };

    fileInput?.addEventListener('change', (e) => {
        const f = e.target.files?.[0];
        if (f) showSelected(f);
    });

    clearBtn?.addEventListener('click', clearSelected);

    if (dropzone) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('gint-drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('gint-drag-over');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const f = e.dataTransfer?.files?.[0];
            if (f) {
                fileInput.files = e.dataTransfer.files;
                showSelected(f);
            }
        });
    }

    // Test bağlantı
    const testBtn = document.getElementById('testConnectionBtn');
    if (testBtn) {
        testBtn.addEventListener('click', async () => {
            testBtn.disabled = true;
            const orig = testBtn.innerHTML;
            testBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Test ediliyor...';

            try {
                const res = await fetch(@json(route('admin.google-integration.test-connection')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();

                if (window.AdminModal && typeof AdminModal.status === 'function') {
                    AdminModal.status({
                        title: data.success ? 'Bağlantı Başarılı' : 'Bağlantı Hatası',
                        message: data.message + (data.email ? '\n\nService Account: ' + data.email : ''),
                        type: data.success ? 'success' : 'danger',
                    });
                }

                if (data.success) setTimeout(() => location.reload(), 1500);
            } catch (e) {
                if (window.AdminModal && typeof AdminModal.status === 'function') {
                    AdminModal.status({ title: 'Hata', message: 'İstek hatası: ' + e.message, type: 'danger' });
                }
            } finally {
                testBtn.disabled = false;
                testBtn.innerHTML = orig;
            }
        });
    }

    // Sil onayı
    const deleteBtn = document.getElementById('gintDeleteBtn');
    const deleteForm = document.getElementById('gintDeleteForm');
    if (deleteBtn && deleteForm) {
        deleteBtn.addEventListener('click', () => {
            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Service Account JSON Silinsin Mi?',
                    message: 'Bu işlem entegrasyonu devre dışı bırakacak. Veri çekimi duracak.',
                    confirmText: 'Evet, Sil',
                    cancelText: 'Vazgeç',
                    type: 'danger',
                    onConfirm: () => deleteForm.submit(),
                });
            } else {
                deleteForm.submit();
            }
        });
    }

    // OAuth disconnect onayı
    const oauthDisconnectBtn = document.getElementById('gintOAuthDisconnectBtn');
    const oauthDisconnectForm = document.getElementById('gintOAuthDisconnectForm');
    if (oauthDisconnectBtn && oauthDisconnectForm) {
        oauthDisconnectBtn.addEventListener('click', () => {
            const submit = () => oauthDisconnectForm.submit();
            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Google Bağlantısı Kesilsin Mi?',
                    message: 'Token Google tarafında da iptal edilecek. Yeniden bağlanmak için Google ile tekrar giriş yapman gerekir.',
                    confirmText: 'Evet, Bağlantıyı Kes',
                    cancelText: 'Vazgeç',
                    type: 'danger',
                    onConfirm: submit,
                });
            } else {
                submit();
            }
        });
    }
});
</script>
@endpush

@push('styles')
<style>
.gint-oauth-card{border:1px solid color-mix(in srgb, var(--text-teal) 35%, transparent)}
.gint-oauth-connected{padding:14px 16px;border-radius:10px;background:color-mix(in srgb, var(--text-teal) 8%, transparent);border:1px solid color-mix(in srgb, var(--text-teal) 25%, transparent)}
.gint-oauth-avatar{width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:color-mix(in srgb, var(--text-teal) 20%, transparent);color:var(--text-teal);font-size:20px}
.gint-oauth-redirect-uri{padding:10px 12px;background:var(--bg-input);border:1px dashed var(--border-color);border-radius:8px}
.gint-oauth-redirect-uri code{word-break:break-all;color:var(--text-primary)}
.gint-legacy-card--dimmed{opacity:.55}
.gint-legacy-card--dimmed:hover{opacity:.85;transition:opacity .2s ease}
.gint-btn-disabled{opacity:.5;cursor:not-allowed;pointer-events:none}
.gint-divider{border:0;border-top:1px solid var(--border-color);opacity:.4}
</style>
@endpush
