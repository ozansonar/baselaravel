@extends('layouts.admin')

@section('title', 'AI İçerik Üret')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">AI İçerik Üret</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-stars me-2"></i>AI İçerik Üret</h1>
            <p class="page-subtitle">Seçtiğin kategori için Gemini AI ile anlık blog içeriği oluştur</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ai-prompt-settings.edit') }}" class="btn-glass">
                <i class="bi bi-sliders2 me-1"></i> Ayarlar
            </a>
            <a href="{{ route('admin.ai-logs.index') }}" class="btn-glass">
                <i class="bi bi-clock-history me-1"></i> Loglar
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            @if(session('ai_log_id'))
                <a href="{{ route('admin.ai-logs.show', session('ai_log_id')) }}" class="text-teal ms-2">
                    <i class="bi bi-arrow-right-circle me-1"></i>Log detayını gör
                </a>
            @endif
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Lütfen aşağıdaki hataları düzeltin:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- Sol: Üretim Formu --}}
        <div class="col-xl-8" data-aos="fade-up">
            <form action="{{ route('admin.ai-generate.store') }}" method="POST" id="aiGenerateForm">
                @csrf

                <div class="card-dark mb-4">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-pencil-square me-2 text-teal"></i>Üretim Parametreleri</h6>
                    </div>
                    <div class="card-body-custom">
                        {{-- Kategori --}}
                        <div class="stg-field mb-3">
                            <label class="stg-label" for="category_id">
                                Blog Kategorisi <span class="text-danger">*</span>
                            </label>
                            <select name="category_id"
                                    id="category_id"
                                    class="stg-input @error('category_id') is-invalid @enderror"
                                    required>
                                <option value="">Kategori seçin...</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }} ({{ $category->slug }})
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint">Üretilen yazı bu kategoriye atanacak.</p>
                        </div>

                        {{-- Konu (opsiyonel) --}}
                        <div class="stg-field mb-3">
                            <label class="stg-label" for="topic">
                                Konu / Ürün <span class="text-muted">(opsiyonel)</span>
                            </label>
                            <input type="text"
                                   name="topic"
                                   id="topic"
                                   class="stg-input @error('topic') is-invalid @enderror"
                                   value="{{ old('topic') }}"
                                   maxlength="255"
                                   placeholder="Örn: Doğal Köy Yoğurdu, SEO İpuçları, vb.">
                            @error('topic')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint">
                                <i class="bi bi-info-circle me-1"></i>
                                Boş bırakırsan ürün listesinden rastgele seçer. Özel bir konu yazarsan AI o konu hakkında içerik üretir.
                            </p>
                        </div>

                        {{-- Hedef Şehir (opsiyonel) --}}
                        <div class="stg-field mb-0">
                            <label class="stg-label" for="city">
                                Hedef Şehir <span class="text-muted">(opsiyonel)</span>
                            </label>
                            <select name="city"
                                    id="city"
                                    class="stg-input @error('city') is-invalid @enderror">
                                <option value="">— Genel yazı (şehirsiz) —</option>
                                @foreach(($setting->target_cities ?? []) as $cityName)
                                    <option value="{{ $cityName }}" {{ old('city') === $cityName ? 'selected' : '' }}>
                                        {{ $cityName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="stg-hint">
                                <i class="bi bi-geo-alt me-1"></i>
                                Şehir seçersen yazı o şehre yönelik anahtar kelimelerle zenginleştirilir
                                (örn: "İstanbul köy ürünleri", "İstanbul'a kargo"). Manuel üretim
                                cron rotasyonunu <strong>etkilemez</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Info banner --}}
                <div class="card-dark mb-4">
                    <div class="card-body-custom">
                        <div class="aig-info-box">
                            <i class="bi bi-info-circle-fill text-teal"></i>
                            <div>
                                <strong>Dikkat:</strong> Üretim işlemi 30-60 saniye sürebilir.
                                Butona bastıktan sonra lütfen bekle, sayfayı yenileme.
                                İşlem tamamlandığında blog yazısı düzenleme sayfasına yönlendirileceksin.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action bar --}}
                <div class="card-dark">
                    <div class="card-body-custom">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('admin.dashboard') }}" class="btn-glass">
                                <i class="bi bi-x-lg me-1"></i> İptal
                            </a>
                            <button type="submit" class="btn-teal" id="generateBtn">
                                <i class="bi bi-stars me-1"></i> <span id="btnLabel">İçeriği Üret</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Sağ: Bilgi & Ürün Listesi Önizleme --}}
        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            {{-- Nasıl Çalışır --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-lightbulb me-2 text-teal"></i>Nasıl Çalışır?</h6>
                </div>
                <div class="card-body-custom">
                    <ol class="aig-steps">
                        <li>Kategori seç (ve isteğe bağlı konu yaz)</li>
                        <li>"İçeriği Üret" butonuna tıkla</li>
                        <li>Gemini AI içeriği oluşturur (~30-60sn)</li>
                        <li>Blog yazısı otomatik kategoriye atanır</li>
                        <li>Düzenleme sayfasında ince ayar yapabilirsin</li>
                    </ol>
                </div>
            </div>

            {{-- Mevcut Ürün Listesi --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-box-seam me-2 text-teal"></i>Ürün Listesi</h6>
                    <a href="{{ route('admin.ai-prompt-settings.edit') }}" class="btn-glass btn-sm" title="Düzenle">
                        <i class="bi bi-pencil"></i>
                    </a>
                </div>
                <div class="card-body-custom">
                    <p class="stg-hint mb-3">Konu yazmazsan aşağıdakilerden rastgele biri seçilir:</p>
                    @if(! empty($setting->products))
                        <div class="aig-product-chips">
                            @foreach($setting->products as $product)
                                <span class="aig-product-chip" data-product="{{ $product }}">{{ $product }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0"><i class="bi bi-exclamation-circle me-1"></i> Ürün listesi boş.</p>
                    @endif
                </div>
            </div>

            {{-- Kurallar Özet --}}
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-shield-check me-2 text-teal"></i>Uygulanan Kurallar</h6>
                </div>
                <div class="card-body-custom">
                    <div class="aig-info-row">
                        <span class="text-muted">Maksimum kelime:</span>
                        <span class="fw-semibold">{{ $setting->max_words }}</span>
                    </div>
                    <div class="aig-info-row">
                        <span class="text-muted">Toplam ürün:</span>
                        <span class="fw-semibold">{{ count($setting->products ?? []) }}</span>
                    </div>
                    <div class="aig-info-row">
                        <span class="text-muted">Ayar durumu:</span>
                        <span class="fw-semibold">
                            @if($setting->is_active)
                                <i class="bi bi-check-circle-fill text-success"></i> Aktif
                            @else
                                <i class="bi bi-pause-circle-fill text-warning"></i> Pasif (manuel üretim halen çalışır)
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Loading Overlay --}}
    <div id="aigLoading" class="aig-loading-overlay d-none">
        <div class="aig-loading-box">
            <div class="aig-spinner">
                <i class="bi bi-stars"></i>
            </div>
            <h5 class="mb-2">AI içerik üretiyor...</h5>
            <p class="text-muted mb-0">Bu işlem 30-60 saniye sürebilir.<br>Lütfen sayfayı kapatma.</p>
        </div>
    </div>

@endsection

@push('styles')
<style>
.aig-info-box{display:flex;gap:12px;align-items:flex-start;padding:12px 16px;background:rgba(20,184,166,.08);border:1px solid rgba(20,184,166,.2);border-radius:10px;color:var(--text-primary);font-size:14px;line-height:1.6}
.aig-info-box i{font-size:20px;margin-top:2px;flex-shrink:0}
.aig-steps{margin:0;padding-left:20px;color:var(--text-primary);font-size:14px;line-height:1.9}
.aig-steps li{margin-bottom:4px}
.aig-product-chips{display:flex;flex-wrap:wrap;gap:6px}
.aig-product-chip{display:inline-flex;align-items:center;padding:6px 12px;border-radius:16px;background:var(--bg-input);border:1px solid var(--border-color);font-size:12px;color:var(--text-primary);cursor:pointer;transition:.2s;user-select:none}
.aig-product-chip:hover{background:rgba(20,184,166,.12);border-color:rgba(20,184,166,.4);color:var(--text-primary)}
.aig-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid color-mix(in srgb, var(--border-color) 40%, transparent);font-size:13px;gap:8px}
.aig-info-row:last-child{border-bottom:none}

/* Loading overlay */
.aig-loading-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:9999;display:flex;align-items:center;justify-content:center}
.aig-loading-box{background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:40px 60px;text-align:center;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.aig-spinner{width:80px;height:80px;margin:0 auto 20px;border-radius:50%;background:linear-gradient(135deg,#14b8a6,#0d9488);display:flex;align-items:center;justify-content:center;animation:aigPulse 1.5s ease-in-out infinite}
.aig-spinner i{font-size:38px;color:#fff;animation:aigSpin 2s linear infinite}
@keyframes aigPulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(20,184,166,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 20px rgba(20,184,166,0)}}
@keyframes aigSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
</style>
@endpush

@push('scripts')
<script>
(function() {
    const form = document.getElementById('aiGenerateForm');
    const btn = document.getElementById('generateBtn');
    const btnLabel = document.getElementById('btnLabel');
    const overlay = document.getElementById('aigLoading');
    const topicInput = document.getElementById('topic');

    // Click chip to fill topic
    document.querySelectorAll('.aig-product-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            topicInput.value = this.dataset.product || '';
            topicInput.focus();
        });
    });

    // Show loading + disable button on submit
    form?.addEventListener('submit', function(e) {
        if (!form.checkValidity()) return;
        btn.disabled = true;
        btnLabel.textContent = 'Üretiliyor...';
        overlay.classList.remove('d-none');
    });
})();
</script>
@endpush
