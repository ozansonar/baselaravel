@extends('layouts.admin')

@section('title', 'Görsel Kütüphanesi')
@section('page_title', 'Görsel Kütüphanesi')
@section('page_description', 'Blog ve sosyal medya için ürün-bazlı görsel kütüphanesi (sıfır AI maliyeti)')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Görsel Kütüphanesi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Görsel Kütüphanesi</h1>
            <p class="page-subtitle">
                Otomatik blog cron'u ve sosyal medya görselleri buradan rastgele seçer.
                Yüklediğin görseller bir ürüne (veya "Genel" havuza) bağlanır,
                <strong>AI çağrısı yapılmaz</strong>.
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

    {{-- ==================== STAT CARDS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-images"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Görsel</span>
                    <h3 class="usr-stat-value" data-count="{{ $totals['images'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ürün Sayısı</span>
                    <h3 class="usr-stat-value" data-count="{{ $totals['products'] }}">0</h3>
                    <small class="text-clr-muted">+ Genel havuz</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-collection"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Genel Havuz</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['Genel'] ?? 0 }}">0</h3>
                    <small class="text-clr-muted">Fallback için</small>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aylık AI Maliyeti</span>
                    <h3 class="usr-stat-value">0 ₺</h3>
                    <small class="text-clr-muted">Kütüphaneden seçim</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== INFO ALERT (yeni kullanıcı için açıklama) ==================== --}}
    @if($totals['images'] === 0)
        <div class="card-dark mb-4 mlib-info-alert" data-aos="fade-up">
            <div class="card-body-custom">
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <i class="bi bi-info-circle-fill text-teal mlib-info-alert__icon"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2">Görsel kütüphanesi boş</h6>
                        <p class="mb-2 small">
                            Otomatik blog cron'u her çalıştığında bu kütüphaneden ürünle
                            eşleşen rastgele bir görsel seçer. Boşken cron <strong>placeholder</strong>
                            (genel köy görseli) kullanır.
                        </p>
                        <p class="mb-0 small text-muted">
                            <strong>İlk adım:</strong> Aşağıdaki bir ürüne tıkla, görsel yükle.
                            Önerilen format: kare 1:1, 1500×1500 px.
                            Detaylı prompt'lar için <code>docs/media-library-modulu.md</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ==================== PRODUCT GRID ==================== --}}
    <div class="row g-4">
        @foreach($products as $product)
            @php
                $count = $stats[$product] ?? 0;
                $slug = \Illuminate\Support\Str::slug($product);
                $productPreviews = $previews[$product] ?? collect();
            @endphp
            <div class="col-xxl-4 col-lg-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                <a href="{{ route('admin.media-library.show', $slug) }}"
                   class="mlib-product-card {{ $count === 0 ? 'mlib-product-card--empty' : '' }}">
                    <div class="mlib-product-card__header">
                        <div class="mlib-product-card__icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="mlib-product-card__title-wrap">
                            <h5 class="mlib-product-card__title">{{ $product }}</h5>
                            <span class="mlib-product-card__count">
                                @if($count === 0)
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    Henüz görsel yok
                                @else
                                    <i class="bi bi-images me-1"></i>{{ $count }} görsel
                                @endif
                            </span>
                        </div>
                        <i class="bi bi-arrow-right mlib-product-card__arrow"></i>
                    </div>

                    @if($productPreviews->isNotEmpty())
                        <div class="mlib-product-card__previews">
                            @foreach($productPreviews as $preview)
                                <div class="mlib-product-card__thumb">
                                    <img src="{{ upload_url($preview->image_path, 'thumb') }}"
                                         alt="{{ $product }} görsel önizleme"
                                         loading="lazy"
                                         class="img-fluid">
                                </div>
                            @endforeach
                            @for($i = $productPreviews->count(); $i < 4; $i++)
                                <div class="mlib-product-card__thumb mlib-product-card__thumb--empty">
                                    <i class="bi bi-image"></i>
                                </div>
                            @endfor
                        </div>
                    @else
                        <div class="mlib-product-card__empty-state">
                            <i class="bi bi-cloud-upload"></i>
                            <span>Görsel yüklemek için tıkla</span>
                        </div>
                    @endif
                </a>
            </div>
        @endforeach

        {{-- "Genel" havuz kartı — her zaman en sonda --}}
        @php
            $generalCount = $stats['Genel'] ?? 0;
            $generalPreviews = $previews['__general__'] ?? collect();
        @endphp
        <div class="col-xxl-4 col-lg-6" data-aos="fade-up" data-aos-delay="{{ count($products) * 50 }}">
            <a href="{{ route('admin.media-library.show', 'genel') }}"
               class="mlib-product-card mlib-product-card--general {{ $generalCount === 0 ? 'mlib-product-card--empty' : '' }}">
                <div class="mlib-product-card__header">
                    <div class="mlib-product-card__icon mlib-product-card__icon--general">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div class="mlib-product-card__title-wrap">
                        <h5 class="mlib-product-card__title">
                            Genel Havuz
                            <span class="badge bg-info-subtle text-info-emphasis ms-1">Fallback</span>
                        </h5>
                        <span class="mlib-product-card__count">
                            @if($generalCount === 0)
                                <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                Henüz görsel yok
                            @else
                                <i class="bi bi-images me-1"></i>{{ $generalCount }} görsel
                            @endif
                        </span>
                    </div>
                    <i class="bi bi-arrow-right mlib-product-card__arrow"></i>
                </div>

                @if($generalPreviews->isNotEmpty())
                    <div class="mlib-product-card__previews">
                        @foreach($generalPreviews as $preview)
                            <div class="mlib-product-card__thumb">
                                <img src="{{ upload_url($preview->image_path, 'thumb') }}"
                                     alt="Genel havuz görsel önizleme"
                                     loading="lazy"
                                     class="img-fluid">
                            </div>
                        @endforeach
                        @for($i = $generalPreviews->count(); $i < 4; $i++)
                            <div class="mlib-product-card__thumb mlib-product-card__thumb--empty">
                                <i class="bi bi-image"></i>
                            </div>
                        @endfor
                    </div>
                @else
                    <div class="mlib-product-card__empty-state">
                        <i class="bi bi-cloud-upload"></i>
                        <span>Genel görseller (cron fallback için)</span>
                    </div>
                @endif
            </a>
        </div>
    </div>

@endsection

@push('styles')
<style>
.mlib-info-alert{border-left:4px solid var(--text-teal)}
.mlib-info-alert__icon{font-size:32px;flex-shrink:0}

.mlib-product-card{display:flex;flex-direction:column;gap:14px;padding:20px;background:var(--bg-input);border:1px solid var(--border-color);border-radius:14px;color:var(--text-primary);text-decoration:none;transition:all .2s ease;height:100%}
.mlib-product-card:hover{border-color:var(--text-teal);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.18);color:var(--text-primary)}
.mlib-product-card--empty{opacity:.7}
.mlib-product-card--empty:hover{opacity:1}
.mlib-product-card--general{border-color:color-mix(in srgb, var(--text-teal) 35%, transparent)}

.mlib-product-card__header{display:flex;align-items:center;gap:14px}
.mlib-product-card__icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb, var(--text-teal) 15%, transparent);color:var(--text-teal);font-size:22px;flex-shrink:0}
.mlib-product-card__icon--general{background:color-mix(in srgb, #f59e0b 15%, transparent);color:#f59e0b}
.mlib-product-card__title-wrap{flex-grow:1;min-width:0}
.mlib-product-card__title{margin:0 0 4px;font-size:16px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mlib-product-card__count{font-size:13px;color:var(--text-muted);display:inline-flex;align-items:center}
.mlib-product-card__arrow{color:var(--text-muted);font-size:18px;transition:transform .2s ease;flex-shrink:0}
.mlib-product-card:hover .mlib-product-card__arrow{transform:translateX(4px);color:var(--text-teal)}

.mlib-product-card__previews{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
.mlib-product-card__thumb{aspect-ratio:1/1;border-radius:8px;overflow:hidden;background:var(--bg-base);border:1px solid var(--border-color)}
.mlib-product-card__thumb img{width:100%;height:100%;object-fit:cover;display:block}
.mlib-product-card__thumb--empty{display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:18px;opacity:.4}

.mlib-product-card__empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:32px 16px;background:var(--bg-base);border:1px dashed var(--border-color);border-radius:10px;color:var(--text-muted);font-size:13px;text-align:center}
.mlib-product-card__empty-state i{font-size:28px;opacity:.6}
</style>
@endpush
