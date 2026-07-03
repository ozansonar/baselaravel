@extends('layouts.admin')

@section('title', 'Google Merchant Feed')
@section('page_title', 'Google Merchant Feed')
@section('page_description', 'Google Alışveriş için XML feed — günde 1-2 kez Google çeker, ürünlerin Google Shopping\'de görünür')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Google Merchant Feed</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-cart3 me-2"></i>Google Merchant Feed</h1>
            <p class="page-subtitle">
                Ürünlerin Google Alışveriş'te görünmesi için XML feed.
                Bu URL'i Google Merchant Center'a "Programlanmış Getirme" olarak ekle.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ $feedUrl }}" target="_blank" rel="noopener" class="btn-glass">
                <i class="bi bi-eye me-1"></i> XML'i Önizle
            </a>
            <form method="POST" action="{{ route('admin.google-merchant-feed.rebuild') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn-teal">
                    <i class="bi bi-arrow-repeat me-1"></i> Cache Temizle &amp; Yeniden Oluştur
                </button>
            </form>
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

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Aktif Ürün</span>
                    <h3 class="usr-stat-value" data-count="{{ $totalCount }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Feed'e Dahil</span>
                    <h3 class="usr-stat-value" data-count="{{ $validCount }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Eksik Alanlı Ürün</span>
                    <h3 class="usr-stat-value" data-count="{{ $invalidCount }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Son Oluşturulma</span>
                    <h3 class="usr-stat-value">
                        @if($generatedAt)
                            {{ $generatedAt->diffForHumans(['short' => true]) }}
                        @else
                            —
                        @endif
                    </h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: FEED URL CARD --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-link-45deg"></i></div>
            <div>
                <h6 class="mb-0">Feed URL'in</h6>
                <small class="text-muted">Bu URL'i Google Merchant Center'a "Programlanmış Getirme" olarak ekle</small>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="gmf-url-box">
                <code id="feedUrlCode">{{ $feedUrl }}</code>
                <button type="button" class="usr-action-btn topic-convert-btn" id="copyFeedUrlBtn">
                    <i class="bi bi-clipboard"></i> Kopyala
                </button>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <small>
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Cache:</strong> 6 saat. Google bu URL'i günde 1-2 kez çekiyor.
                    Feed her ürün değişikliğinde otomatik güncellenir, panik yapmana gerek yok.
                </small>
            </div>
        </div>
    </div>

    {{-- SECTION 3: HOW TO ADD TO MERCHANT CENTER --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header-custom d-flex align-items-center justify-content-between gap-3" role="button" data-bs-toggle="collapse" data-bs-target="#mcGuideCollapse">
            <div class="d-flex align-items-center gap-3">
                <div class="form-section-icon bg-icon-blue"><i class="bi bi-question-circle-fill"></i></div>
                <div>
                    <h6 class="mb-0">Bu URL'i Google Merchant Center'a Nasıl Eklerim?</h6>
                    <small class="text-muted">5 dakikalık kurulum — detayları görmek için tıkla</small>
                </div>
            </div>
            <i class="bi bi-chevron-down topic-help-chevron"></i>
        </div>
        <div class="collapse" id="mcGuideCollapse">
            <div class="card-body-custom">
                <ol class="gint-steps">
                    <li>
                        <a href="https://merchants.google.com/" target="_blank" rel="noopener">merchants.google.com</a> adresine gir
                        — <strong>Orhan Babanın Çiftliği</strong> hesabını seç
                    </li>
                    <li>
                        Sol menüden <strong>"Ürünler" → "Veri kaynakları"</strong> (veya "Data sources")
                    </li>
                    <li>
                        <strong>"+ Veri kaynağı ekle"</strong> butonuna tıkla
                    </li>
                    <li>
                        Açılan ekranda <strong>"Programlanmış Getirme"</strong> seçeneğini seç
                        <br><small>(Genellikle Google Sheets ile aynı satırda, alternatif olarak görünür. "API" değil!)</small>
                    </li>
                    <li>
                        <strong>Veri kaynağı adı:</strong> <code>Orhan Babanın Çiftliği XML Feed</code>
                        <br>
                        <strong>Dosya URL'si:</strong> <code>{{ $feedUrl }}</code>
                        <br>
                        <strong>Çekme sıklığı:</strong> <code>Günde 1 kez</code> (gece saatlerinde)
                    </li>
                    <li>
                        <strong>"Veri kaynağı oluştur"</strong> butonuna bas
                    </li>
                    <li>
                        Google ilk taramayı yapar (yaklaşık 24-72 saat ürünler incelenir)
                        — sonra Google Alışveriş sonuçlarında ürünlerin görünür
                    </li>
                </ol>
            </div>
        </div>
    </div>

    {{-- SECTION 4: HEALTH CHECK / ISSUES --}}
    @if(! empty($issues))
        <div class="card-dark mb-4 topic-pool-alert topic-pool-alert--warning" data-aos="fade-up">
            <div class="card-body-custom">
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <div class="topic-pool-alert__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 topic-pool-alert__title">{{ count($issues) }} ürün eksik alana sahip — feed'e dahil edilmedi</h6>
                        <p class="mb-0 small text-muted">
                            Bu ürünler Google'a gönderilmeden önce eksiklerini tamamlaman gerek.
                            Google Merchant Center bu ürünleri reddederdi, bu yüzden feed'den otomatik çıkardık.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="cl-table">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th>Eksiklikler</th>
                                <th class="cl-th-actions">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($issues as $issue)
                                <tr>
                                    <td>
                                        <div class="cl-content-info">
                                            <span class="cl-content-title">{{ $issue['name'] }}</span>
                                            <span class="cl-content-meta">ID: {{ $issue['id'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($issue['errors'] as $err)
                                                <span class="usr-status-badge inactive">
                                                    <i class="bi bi-x-circle me-1"></i>{{ $err }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div class="usr-actions">
                                            <a href="{{ route('admin.products.edit', $issue['id']) }}" class="usr-action-btn" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- SECTION 5: PRODUCTS IN FEED --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-header-custom">
            <h6 class="mb-0"><i class="bi bi-collection me-2"></i>Feed'deki Ürünler</h6>
            <small class="text-muted">Google'a gönderilen ürünlerin tam listesi</small>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Görsel</th>
                            <th>Ürün</th>
                            <th class="d-none d-md-table-cell">Kategori</th>
                            <th class="text-end">Fiyat</th>
                            <th>Stok</th>
                            <th>Feed Durumu</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $errors = $issues ? collect($issues)->firstWhere('id', $product->id) : null;
                                $inFeed = empty($errors);
                                $imagePath = $product->image ?? $product->images->first()?->image;
                            @endphp
                            <tr>
                                <td>
                                    @if($imagePath)
                                        <div class="cl-content-thumb">
                                            <img src="{{ upload_url($imagePath, 'thumb') }}" alt="{{ $product->name }}" loading="lazy">
                                        </div>
                                    @else
                                        <div class="cl-content-thumb draft"><i class="bi bi-image"></i></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $product->name }}</span>
                                        <span class="cl-content-meta">/urun/{{ $product->slug }}</span>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    @if($product->category)
                                        <span class="cl-category-badge">{{ $product->category->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($product->hasDiscount())
                                        <small class="text-muted text-decoration-line-through d-block">{{ number_format((float) $product->discounted_price, 2, ',', '.') }} ₺</small>
                                        <strong class="text-success">{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
                                    @else
                                        <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
                                    @endif
                                </td>
                                <td>
                                    @if($product->inStock())
                                        <span class="usr-status-badge active"><i class="bi bi-check-circle me-1"></i>{{ $product->stock }}</span>
                                    @else
                                        <span class="usr-status-badge inactive"><i class="bi bi-x-circle me-1"></i>Stokta yok</span>
                                    @endif
                                </td>
                                <td>
                                    @if($inFeed)
                                        <span class="usr-status-badge active"><i class="bi bi-check-circle"></i> Feed'de</span>
                                    @else
                                        <span class="usr-status-badge pending"><i class="bi bi-exclamation-circle"></i> Eksik</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="usr-actions">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="usr-action-btn" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ url('/urun/' . $product->slug) }}" target="_blank" class="usr-action-btn success" title="Sitede Görüntüle">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-cart3 d-block fs-1 mb-2"></i>
                                    <p class="mb-1">Feed'de gösterilecek aktif ürün yok.</p>
                                    <small>Önce <a href="{{ route('admin.products.index') }}">Ürünler</a> sayfasından aktif ürün ekle.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const copyBtn = document.getElementById('copyFeedUrlBtn');
    const codeEl  = document.getElementById('feedUrlCode');

    copyBtn?.addEventListener('click', async () => {
        const url = codeEl?.textContent?.trim() ?? '';
        if (!url) return;

        try {
            await navigator.clipboard.writeText(url);
            const orig = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="bi bi-check2"></i> Kopyalandı!';
            copyBtn.classList.add('text-success');
            setTimeout(() => {
                copyBtn.innerHTML = orig;
                copyBtn.classList.remove('text-success');
            }, 2000);
        } catch (e) {
            if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({ title: 'Kopyalanamadı', message: 'Tarayıcı izin vermedi: ' + e.message, type: 'danger' });
            }
        }
    });
});
</script>
@endpush
