@extends('layouts.admin')

@section('title', $product->name . ' — Ürün Detayı')
@section('page_title', 'Ürün Detayı')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.products.index') }}" class="breadcrumb-link">Ürünler</a>
            </li>
            <li class="breadcrumb-item active text-teal">{{ Str::limit($product->name, 30) }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">{{ $product->name }}</h1>
            <p class="page-subtitle mb-0">
                <span class="badge bg-{{ $product->status->color() }}">{{ $product->status->label() }}</span>
                @if($product->is_featured)
                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill me-1"></i>Öne Çıkan</span>
                @endif
                @if($product->is_new)
                    <span class="badge bg-info ms-1">Yeni</span>
                @endif
                @if(!$product->allow_reviews)
                    <span class="badge bg-secondary ms-1"><i class="bi bi-chat-square-x me-1"></i>Yorum Kapalı</span>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.products.index') }}" class="btn-glass">
                <i class="bi bi-arrow-left me-1"></i> Ürünler
            </a>
            <a href="{{ route('products.show', $product->slug) }}" class="btn-glass" target="_blank">
                <i class="bi bi-eye me-1"></i> Sitede Gör
            </a>
            <a href="{{ route('admin.products.edit', $product) }}" class="btn-teal">
                <i class="bi bi-pencil me-1"></i> Düzenle
            </a>
        </div>
    </div>

    {{-- KPI Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-currency-lira"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Fiyat</span>
                    <h3 class="usr-stat-value">{{ number_format($product->price, 2) }} ₺</h3>
                    @if($product->discounted_price)
                        <small class="text-clr-muted text-decoration-line-through">{{ number_format($product->discounted_price, 2) }} ₺</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon {{ $product->inStock() ? 'usr-stat-icon-blue' : 'usr-stat-icon-red' }}">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Stok</span>
                    <h3 class="usr-stat-value">{{ $product->stock }} {{ $product->unit ?? 'adet' }}</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-star-half"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Değerlendirme</span>
                    <h3 class="usr-stat-value">{{ $reviewStats['average'] }} <small class="text-clr-muted" style="font-size:0.5em;">({{ $reviewStats['total'] }})</small></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal"><i class="bi bi-images"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Görseller</span>
                    <h3 class="usr-stat-value">{{ $product->images->count() }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ══════════ SOL KOLON ══════════ --}}
        <div class="col-lg-5" data-aos="fade-up">

            {{-- Görsel Galeri --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-images text-teal me-2"></i>Görseller</h6>
                    <a href="{{ route('admin.products.edit', $product) }}#section-images" class="btn-glass btn-sm">
                        <i class="bi bi-pencil me-1"></i> Düzenle
                    </a>
                </div>
                <div class="card-body-custom">
                    @if($product->cover_image)
                        <img src="{{ upload_url($product->cover_image, 'lg') }}"
                             alt="{{ $product->name }}"
                             class="img-fluid rounded mb-3 w-100 prd-detail-main-img"
                             id="mainProductImg"
                             data-path="{{ $product->cover_image }}"
                             loading="lazy">
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-image text-clr-muted" style="font-size: 3rem; opacity:0.3;"></i>
                            <p class="text-clr-muted mt-2 mb-0">Görsel eklenmemiş</p>
                        </div>
                    @endif

                    @php
                        $allImages = collect();
                        if ($product->image) {
                            $allImages->push((object)['image' => $product->image, 'label' => 'Ana']);
                        }
                        foreach ($product->images as $img) {
                            $allImages->push($img);
                        }
                    @endphp

                    @if($allImages->count() > 0)
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            @foreach($allImages as $idx => $img)
                                <img src="{{ upload_url($img->image, 'thumb') }}"
                                     alt="{{ $product->name }} — {{ $idx + 1 }}"
                                     class="prd-detail-thumb rounded {{ $idx === 0 ? 'prd-detail-thumb-active' : '' }}"
                                     data-full="{{ upload_url($img->image, 'lg') }}"
                                     data-path="{{ $img->image }}"
                                     loading="lazy"
                                     width="60" height="60">
                            @endforeach
                        </div>
                    @endif

                    {{-- AI Enhance butonu --}}
                    @if($product->cover_image || $allImages->count() > 0)
                        <button type="button" class="btn-teal btn-sm w-100" id="openEnhanceBtn">
                            <i class="bi bi-magic me-1"></i> Görseli AI ile İyileştir
                        </button>
                    @endif
                </div>
            </div>

            {{-- Ürün Bilgileri --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6 class="mb-0"><i class="bi bi-info-circle text-teal me-2"></i>Ürün Bilgileri</h6>
                </div>
                <div class="card-body-custom p-0">
                    <table class="cl-table mb-0">
                        <tbody>
                            <tr>
                                <td class="text-clr-muted"><i class="bi bi-tag me-1"></i>Kategori</td>
                                <td class="text-clr-primary">{{ $product->category?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-clr-muted"><i class="bi bi-upc-scan me-1"></i>SKU</td>
                                <td class="text-clr-primary">{{ $product->sku ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-clr-muted"><i class="bi bi-link-45deg me-1"></i>Slug</td>
                                <td class="text-clr-primary"><code>{{ $product->slug }}</code></td>
                            </tr>
                            <tr>
                                <td class="text-clr-muted"><i class="bi bi-sort-numeric-up me-1"></i>Sıralama</td>
                                <td class="text-clr-primary">{{ $product->sort_order }}</td>
                            </tr>
                            <tr>
                                <td class="text-clr-muted"><i class="bi bi-calendar-plus me-1"></i>Oluşturulma</td>
                                <td class="text-clr-primary">{{ $product->created_at->translatedFormat('d M Y, H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="text-clr-muted"><i class="bi bi-clock-history me-1"></i>Son Güncelleme</td>
                                <td class="text-clr-primary">{{ $product->updated_at->translatedFormat('d M Y, H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- SEO --}}
            <div class="card-dark">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-search text-teal me-2"></i>SEO</h6>
                    <a href="{{ route('admin.products.edit', $product) }}#section-seo" class="btn-glass btn-sm">
                        <i class="bi bi-pencil me-1"></i> Düzenle
                    </a>
                </div>
                <div class="card-body-custom">
                    @if($product->meta_title || $product->meta_description)
                        @if($product->meta_title)
                            <div class="mb-3">
                                <small class="text-clr-muted d-block mb-1"><i class="bi bi-type me-1"></i>Meta Title</small>
                                <span class="text-clr-primary">{{ $product->meta_title }}</span>
                                <small class="text-clr-muted ms-1">({{ mb_strlen($product->meta_title) }} karakter)</small>
                            </div>
                        @endif
                        @if($product->meta_description)
                            <div>
                                <small class="text-clr-muted d-block mb-1"><i class="bi bi-card-text me-1"></i>Meta Description</small>
                                <span class="text-clr-secondary">{{ $product->meta_description }}</span>
                                <small class="text-clr-muted ms-1">({{ mb_strlen($product->meta_description) }} karakter)</small>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-search text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                            <p class="text-clr-muted mt-2 mb-0">SEO bilgileri henüz eklenmemiş</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════ SAĞ KOLON ══════════ --}}
        <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">

            {{-- Açıklama --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-file-text text-teal me-2"></i>Açıklama</h6>
                    <a href="{{ route('admin.products.edit', $product) }}#section-basic" class="btn-glass btn-sm">
                        <i class="bi bi-pencil me-1"></i> Düzenle
                    </a>
                </div>
                <div class="card-body-custom">
                    @if($product->short_description || $product->description)
                        @if($product->short_description)
                            <div class="p-3 mb-3 rounded" style="background:rgba(13,202,240,0.05); border-left:3px solid rgba(13,202,240,0.3);">
                                <small class="text-clr-muted d-block mb-1"><i class="bi bi-quote me-1"></i>Kısa Açıklama</small>
                                <p class="text-clr-secondary mb-0" style="line-height:1.7;">{{ $product->short_description }}</p>
                            </div>
                        @endif
                        @if($product->description)
                            <div class="text-clr-secondary prd-detail-desc" style="line-height:1.8;">
                                {!! $product->description !!}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-file-text text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                            <p class="text-clr-muted mt-2 mb-0">Açıklama henüz eklenmemiş</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Özellikler + Besin Değerleri + Kargo: Yan yana veya alt alta --}}
            <div class="row g-4 mb-4">
                {{-- Özellikler --}}
                <div class="{{ $product->nutritions->isNotEmpty() ? 'col-md-6' : 'col-12' }}">
                    <div class="card-dark h-100">
                        <div class="card-header-custom d-flex align-items-center justify-content-between">
                            <h6 class="mb-0"><i class="bi bi-list-check text-teal me-2"></i>Özellikler</h6>
                            <a href="{{ route('admin.products.edit', $product) }}#section-features" class="btn-glass btn-sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                        <div class="card-body-custom {{ $product->features->isNotEmpty() ? 'p-0' : '' }}">
                            @if($product->features->isNotEmpty())
                                <table class="cl-table mb-0">
                                    <tbody>
                                        @foreach($product->features as $feature)
                                            <tr>
                                                <td class="text-clr-primary">
                                                    <i class="{{ $feature->icon }} me-1 text-teal"></i>
                                                    {{ $feature->text }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="text-center py-4">
                                    <i class="bi bi-list-check text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                                    <p class="text-clr-muted mt-2 mb-0">Özellik eklenmemiş</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Besin Değerleri --}}
                <div class="{{ $product->features->isNotEmpty() ? 'col-md-6' : 'col-12' }}">
                    <div class="card-dark h-100">
                        <div class="card-header-custom d-flex align-items-center justify-content-between">
                            <h6 class="mb-0"><i class="bi bi-heart-pulse text-teal me-2"></i>Besin Değerleri</h6>
                            <a href="{{ route('admin.products.edit', $product) }}#section-nutrition" class="btn-glass btn-sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                        <div class="card-body-custom {{ $product->nutritions->isNotEmpty() ? 'p-0' : '' }}">
                            @if($product->nutritions->isNotEmpty())
                                <table class="cl-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Besin</th>
                                            <th>Porsiyon</th>
                                            <th>Günlük %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($product->nutritions as $n)
                                            <tr>
                                                <td class="text-clr-primary">{{ $n->name }}</td>
                                                <td class="text-clr-secondary">{{ $n->per_value }}</td>
                                                <td class="text-clr-muted">{{ $n->daily_value }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="text-center py-4">
                                    <i class="bi bi-heart-pulse text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                                    <p class="text-clr-muted mt-2 mb-0">Besin değeri eklenmemiş</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kargo --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-truck text-teal me-2"></i>Kargo / Teslimat</h6>
                    <a href="{{ route('admin.products.edit', $product) }}#section-shipping" class="btn-glass btn-sm">
                        <i class="bi bi-pencil"></i>
                    </a>
                </div>
                <div class="card-body-custom {{ $product->shippingItems->isNotEmpty() ? 'p-0' : '' }}">
                    @if($product->shippingItems->isNotEmpty())
                        <table class="cl-table mb-0">
                            <tbody>
                                @foreach($product->shippingItems as $item)
                                    <tr>
                                        <td class="text-clr-muted" style="width:30%;">{{ $item->type }}</td>
                                        <td class="text-clr-primary">{{ $item->content }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-truck text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                            <p class="text-clr-muted mt-2 mb-0">Kargo bilgisi eklenmemiş</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ══════════ DEĞERLENDİRMELER ══════════ --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-chat-square-text text-teal me-2"></i>Değerlendirmeler</h6>
                    <a href="{{ route('admin.reviews.index', ['search' => $product->name]) }}" class="btn-glass btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> Tümünü Gör
                    </a>
                </div>
                <div class="card-body-custom">
                    {{-- Özet istatistik --}}
                    @if($reviewStats['total'] > 0)
                        <div class="d-flex align-items-center gap-4 mb-4 flex-wrap">
                            <div class="text-center">
                                <div class="text-warning" style="font-size:2.5rem; font-weight:700; line-height:1;">
                                    {{ $reviewStats['average'] }}
                                </div>
                                <div class="text-warning mb-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= round($reviewStats['average']) ? '-fill' : '' }}"></i>
                                    @endfor
                                </div>
                                <small class="text-clr-muted">{{ $reviewStats['total'] }} değerlendirme</small>
                            </div>
                            <div class="flex-grow-1">
                                @for($star = 5; $star >= 1; $star--)
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <small class="text-clr-muted" style="width:20px;">{{ $star }}</small>
                                        <i class="bi bi-star-fill text-warning" style="font-size:0.7rem;"></i>
                                        <div class="flex-grow-1" style="height:6px; background:rgba(255,255,255,0.06); border-radius:3px; overflow:hidden;">
                                            <div style="width:{{ $reviewStats['distribution'][$star] ?? 0 }}%; height:100%; background:var(--teal, #14b8a6); border-radius:3px;"></div>
                                        </div>
                                        <small class="text-clr-muted" style="width:35px; text-align:right;">%{{ $reviewStats['distribution'][$star] ?? 0 }}</small>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @endif

                    {{-- AI Yorum Üretim Formu (inline) --}}
                    <div class="p-3 mb-4 rounded" style="background:rgba(13,202,240,0.06); border:1px solid rgba(13,202,240,0.12);">
                        <div class="d-flex align-items-end gap-3 flex-wrap">
                            <div style="min-width:100px; max-width:140px;">
                                <label class="form-label text-clr-muted mb-1" style="font-size:0.8rem;">
                                    <i class="bi bi-hash me-1"></i>Adet
                                </label>
                                <input type="number" class="form-control form-control-theme form-control-sm"
                                       id="inlineReviewCount" value="5" min="1" max="20">
                            </div>
                            <button type="button" class="btn-teal btn-sm" id="inlineGenBtn">
                                <i class="bi bi-robot me-1"></i>
                                <span id="inlineGenBtnText">AI Yorum Üret</span>
                            </button>
                            <small class="text-clr-muted d-none d-md-inline">Üretilen yorumlar "Beklemede" olarak oluşturulur</small>
                        </div>
                    </div>

                    {{-- Yorum Listesi --}}
                    @if($product->reviews->isEmpty())
                        <div class="text-center py-4">
                            <i class="bi bi-chat-square-dots text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                            <p class="text-clr-muted mt-2 mb-0">Henüz değerlendirme yok — yukarıdan AI ile üretebilirsiniz</p>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-3" id="reviewsList">
                            @foreach($product->reviews as $review)
                                <div class="p-3 review-item" id="review-{{ $review->id }}" style="background:rgba(255,255,255,0.03); border-radius:10px; border:1px solid rgba(255,255,255,0.06);">
                                    <div class="d-flex align-items-start justify-content-between mb-2 flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="prd-detail-avatar">
                                                {{ mb_substr($review->name, 0, 1, 'UTF-8') }}
                                            </div>
                                            <div>
                                                <strong class="text-clr-primary" style="font-size:0.9rem;">{{ $review->name }}</strong>
                                                <span class="review-status-label badge bg-{{ $review->status->color() }} ms-1" style="font-size:0.65rem;">{{ $review->status->label() }}</span>
                                                <small class="text-clr-muted d-block">{{ $review->created_at->diffForHumans() }}</small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="text-warning" style="font-size:0.8rem;">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                                                @endfor
                                            </div>
                                            <div class="d-flex gap-1 ms-1">
                                                @if($review->status !== \App\Enums\ReviewStatus::Approved)
                                                    <button class="usr-action-btn js-review-action"
                                                            data-action="approve"
                                                            data-id="{{ $review->id }}"
                                                            data-url="{{ route('admin.reviews.approve', $review) }}"
                                                            title="Onayla">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                @endif
                                                @if($review->status !== \App\Enums\ReviewStatus::Rejected)
                                                    <button class="usr-action-btn js-review-action"
                                                            data-action="reject"
                                                            data-id="{{ $review->id }}"
                                                            data-url="{{ route('admin.reviews.reject', $review) }}"
                                                            title="Reddet">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                @endif
                                                <button class="usr-action-btn danger js-review-delete"
                                                        data-id="{{ $review->id }}"
                                                        data-url="{{ route('admin.reviews.destroy', $review) }}"
                                                        title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mb-0 text-clr-secondary" style="font-size:0.9rem; line-height:1.6;">
                                        {{ $review->comment }}
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        @if($product->reviews_count > 10)
                            <div class="text-center mt-3">
                                <a href="{{ route('admin.reviews.index', ['search' => $product->name]) }}" class="btn-glass btn-sm">
                                    Tüm {{ $product->reviews_count }} yorumu gör <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        @endif
                    @endif
                </div>
            {{-- ══════════ SSS (FAQ) ══════════ --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="bi bi-question-circle text-teal me-2"></i>Sıkça Sorulan Sorular</h6>
                    <span class="text-clr-muted" id="faqCount">{{ $productFaqs->count() }} soru</span>
                </div>
                <div class="card-body-custom">
                    {{-- AI SSS Üretim Formu --}}
                    <div class="p-3 mb-4 rounded" style="background:rgba(13,202,240,0.06); border:1px solid rgba(13,202,240,0.12);">
                        <div class="d-flex align-items-end gap-3 flex-wrap">
                            <div style="min-width:100px; max-width:140px;">
                                <label class="form-label text-clr-muted mb-1" style="font-size:0.8rem;">
                                    <i class="bi bi-hash me-1"></i>Adet
                                </label>
                                <input type="number" class="form-control form-control-theme form-control-sm"
                                       id="faqCountInput" value="6" min="3" max="10">
                            </div>
                            <button type="button" class="btn-teal btn-sm" id="generateFaqBtn">
                                <i class="bi bi-robot me-1"></i>
                                <span id="generateFaqBtnText">AI SSS Üret</span>
                            </button>
                            <small class="text-clr-muted d-none d-md-inline">Schema.org FAQPage markup ile Google rich result'ta görünür</small>
                        </div>
                    </div>

                    {{-- SSS Listesi --}}
                    <div id="faqList">
                        @forelse($productFaqs as $faq)
                            <div class="p-3 mb-2 rounded" id="faq-{{ $faq->id }}" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06);">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong class="text-clr-primary" style="font-size:0.9rem;">
                                        <i class="bi bi-question-circle text-teal me-1"></i>{{ $faq->question }}
                                    </strong>
                                    <button class="usr-action-btn danger js-faq-delete" data-id="{{ $faq->id }}" title="Sil">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <p class="mb-0 text-clr-secondary" style="font-size:0.85rem; line-height:1.6; padding-left:24px;">{{ $faq->answer }}</p>
                            </div>
                        @empty
                            <div class="text-center py-4" id="faqEmpty">
                                <i class="bi bi-question-circle text-clr-muted" style="font-size:1.5rem; opacity:0.3;"></i>
                                <p class="text-clr-muted mt-2 mb-0">Henüz SSS eklenmemiş — yukarıdan AI ile üretebilirsiniz</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            </div>
        </div>
    </div>

    {{-- AI Görsel İyileştirme Modal --}}
    <div class="modal fade" id="enhanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-header modal-header-theme">
                    <h5 class="modal-title">
                        <i class="bi bi-magic text-teal me-2"></i>AI ile Görsel İyileştir
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body modal-body-theme">
                    <div class="mb-3">
                        <label class="form-label text-clr-secondary">Kaynak Görsel</label>
                        <div class="d-flex align-items-center gap-3">
                            <img id="enhancePreview" src="" alt="Kaynak" class="rounded" width="80" height="80" style="object-fit:cover;">
                            <small class="text-clr-muted">Seçili görselden yeni versiyonlar üretilir ve galeriye eklenir.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-clr-secondary">İyileştirme Stili</label>
                        <div class="d-flex flex-column gap-2" style="max-height:380px; overflow-y:auto; padding-right:4px;">

                            <small class="text-clr-muted text-uppercase fw-bold mt-1"><i class="bi bi-camera-reels me-1"></i>Profesyonel</small>

                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="studio" checked class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-camera me-1"></i>Stüdyo Çekim</strong>
                                    <small class="d-block text-clr-muted">Beyaz arka plan, profesyonel ışık, e-ticaret formatı</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="clean" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-eraser me-1"></i>Arka Plan Temizle</strong>
                                    <small class="d-block text-clr-muted">Ürünü saf beyaz arka plana koy, ürüne dokunma</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="social" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-instagram me-1"></i>Sosyal Medya</strong>
                                    <small class="d-block text-clr-muted">Instagram uyumlu, canlı renkler, göz alıcı</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="default" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-stars me-1"></i>Genel İyileştirme</strong>
                                    <small class="d-block text-clr-muted">Işık, renk, keskinlik düzeltmesi</small>
                                </div>
                            </label>

                            <small class="text-clr-muted text-uppercase fw-bold mt-2"><i class="bi bi-flower1 me-1"></i>Köy & Doğal Ürün</small>

                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="natural" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-tree me-1"></i>Doğal Ortam</strong>
                                    <small class="d-block text-clr-muted">Ahşap masa, çiftlik ortamı, rustik sıcak tonlar</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="breakfast" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-cup-hot me-1"></i>Kahvaltı Sofrası</strong>
                                    <small class="d-block text-clr-muted">Serpme kahvaltı düzeninde, çay, simit, peynir çeşitleriyle</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="harvest" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-flower2 me-1"></i>Hasat / Toplama</strong>
                                    <small class="d-block text-clr-muted">Çorum köy arazisi, tarla, hasır sepet, otantik hasat anı</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="wooden" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-columns-gap me-1"></i>Ahşap Sunum</strong>
                                    <small class="d-block text-clr-muted">Kesme tahtası, taze otlar, bıçak, vintage food photography</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="basket" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-basket me-1"></i>Sepet / Pazar Tezgahı</strong>
                                    <small class="d-block text-clr-muted">Hasır sepet, ahşap kasa, diğer köy ürünleriyle bolluk hissi</small>
                                </div>
                            </label>
                            <label class="prd-enhance-style-option">
                                <input type="radio" name="enhance_style" value="family" class="form-check-input me-2">
                                <div>
                                    <strong><i class="bi bi-people me-1"></i>Aile Sofrası</strong>
                                    <small class="d-block text-clr-muted">Sıcak ev mutfağı, aile yemeği, samimi güven veren ortam</small>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-theme">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="button" class="btn-teal" id="enhanceSubmitBtn">
                        <i class="bi bi-magic me-1"></i>
                        <span id="enhanceSubmitText">İyileştir</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var statusMap = { approve: { label: 'Onaylı', cls: 'success' }, reject: { label: 'Reddedildi', cls: 'danger' } };

    // Gallery thumbnail click
    var mainImg = document.getElementById('mainProductImg');
    document.querySelectorAll('.prd-detail-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            if (mainImg) {
                mainImg.src = thumb.dataset.full;
            }
            document.querySelectorAll('.prd-detail-thumb').forEach(function (t) {
                t.classList.remove('prd-detail-thumb-active');
            });
            thumb.classList.add('prd-detail-thumb-active');
        });
    });

    // AI Review inline generate (AJAX)
    var aiBtn = document.getElementById('inlineGenBtn');
    var aiBtnText = document.getElementById('inlineGenBtnText');
    var inlineCountInput = document.getElementById('inlineReviewCount');
    var generateUrl = @json(route('admin.ai-reviews.generate'));

    if (aiBtn) {
        aiBtn.addEventListener('click', function () {
            var count = parseInt(inlineCountInput.value, 10);
            if (!count || count < 1 || count > 20) {
                notify('Yorum sayısı 1-20 arası olmalı.', 'danger');
                return;
            }

            aiBtn.disabled = true;
            aiBtnText.textContent = 'Üretiliyor...';

            if (window.AdminModal && AdminModal.loading) {
                AdminModal.loading.show({
                    title: 'AI Yorum Üretiliyor...',
                    message: count + ' adet yorum oluşturuluyor, bu işlem 30-60 saniye sürebilir.'
                });
            }

            fetch(generateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ product_id: {{ $product->id }}, count: count })
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                aiBtn.disabled = false;
                aiBtnText.textContent = 'AI Yorum Üret';
                if (window.AdminModal && AdminModal.loading) AdminModal.loading.hide();

                if (res.data.success) {
                    notify(res.data.message, 'success');
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    notify(res.data.message || 'Üretim başarısız.', 'danger');
                }
            })
            .catch(function () {
                aiBtn.disabled = false;
                aiBtnText.textContent = 'AI Yorum Üret';
                if (window.AdminModal && AdminModal.loading) AdminModal.loading.hide();
                notify('Bağlantı hatası oluştu.', 'danger');
            });
        });
    }

    // Approve / Reject via AJAX
    document.querySelectorAll('.js-review-action').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.dataset.action;
            var url = btn.dataset.url;
            var reviewId = btn.dataset.id;

            var cfg = action === 'approve'
                ? { title: 'Yorumu Onayla', message: 'Bu yorumu onaylamak istediğinize emin misiniz?', type: 'success', confirmText: 'Onayla', confirmIcon: 'bi bi-check-circle-fill' }
                : { title: 'Yorumu Reddet', message: 'Bu yorumu reddetmek istediğinize emin misiniz?', type: 'warning', confirmText: 'Reddet', confirmIcon: 'bi bi-x-circle-fill' };

            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm(cfg).then(function (ok) {
                    if (ok) executeAction(url, 'PATCH', reviewId, action);
                });
            } else {
                executeAction(url, 'PATCH', reviewId, action);
            }
        });
    });

    // Delete via AJAX
    document.querySelectorAll('.js-review-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Yorumu Sil',
                    message: 'Bu yorumu silmek istediğinize emin misiniz?',
                    type: 'danger',
                    confirmText: 'Sil',
                    confirmIcon: 'bi bi-trash3'
                }).then(function (ok) {
                    if (ok) executeAction(btn.dataset.url, 'DELETE', btn.dataset.id, 'delete');
                });
            } else {
                executeAction(btn.dataset.url, 'DELETE', btn.dataset.id, 'delete');
            }
        });
    });

    function executeAction(url, method, reviewId, action) {
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) { return r.ok ? r : Promise.reject(r); })
        .then(function () {
            var row = document.getElementById('review-' + reviewId);
            if (!row) return;

            if (action === 'delete') {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function () { row.remove(); }, 300);
                notify('Yorum silindi.', 'success');
                return;
            }

            var info = statusMap[action];
            var badge = row.querySelector('.review-status-label');
            if (badge && info) {
                badge.className = 'review-status-label badge bg-' + info.cls + ' ms-1';
                badge.style.fontSize = '0.65rem';
                badge.textContent = info.label;
            }

            if (action === 'approve') { var ab = row.querySelector('[data-action="approve"]'); if (ab) ab.remove(); }
            if (action === 'reject')  { var rb = row.querySelector('[data-action="reject"]');  if (rb) rb.remove(); }

            notify('Yorum ' + (action === 'approve' ? 'onaylandı' : 'reddedildi') + '.', 'success');
        })
        .catch(function () { notify('İşlem başarısız oldu.', 'danger'); });
    }

    function notify(message, type) {
        if (window.AdminModal && typeof AdminModal.status === 'function') {
            AdminModal.status({ title: type === 'success' ? 'Başarılı' : 'Hata', message: message, type: type });
        }
    }

    // ── AI FAQ Generate ──
    var faqBtn = document.getElementById('generateFaqBtn');
    var faqBtnText = document.getElementById('generateFaqBtnText');
    var faqCountInput = document.getElementById('faqCountInput');
    var faqList = document.getElementById('faqList');
    var faqEmpty = document.getElementById('faqEmpty');
    var faqCountLabel = document.getElementById('faqCount');
    var faqGenerateUrl = @json(route('admin.products.generate-faq', $product));

    if (faqBtn) {
        faqBtn.addEventListener('click', function () {
            var count = parseInt(faqCountInput.value, 10);
            if (!count || count < 3 || count > 10) { notify('3-10 arası soru sayısı girin.', 'danger'); return; }

            faqBtn.disabled = true;
            faqBtnText.textContent = 'Üretiliyor...';
            if (window.AdminModal && AdminModal.loading) {
                AdminModal.loading.show({ title: 'AI SSS Üretiliyor...', message: count + ' adet soru-cevap oluşturuluyor.' });
            }

            fetch(faqGenerateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ count: count })
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                faqBtn.disabled = false;
                faqBtnText.textContent = 'AI SSS Üret';
                if (window.AdminModal && AdminModal.loading) AdminModal.loading.hide();

                if (res.data.success && res.data.faqs) {
                    notify(res.data.message, 'success');
                    if (faqEmpty) faqEmpty.remove();
                    res.data.faqs.forEach(function (faq) {
                        var div = document.createElement('div');
                        div.className = 'p-3 mb-2 rounded';
                        div.id = 'faq-' + faq.id;
                        div.style.cssText = 'background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06);';
                        div.innerHTML =
                            '<div class="d-flex justify-content-between align-items-start mb-1">' +
                                '<strong class="text-clr-primary" style="font-size:0.9rem;"><i class="bi bi-question-circle text-teal me-1"></i>' + escHtml(faq.question) + '</strong>' +
                                '<button class="usr-action-btn danger js-faq-delete" data-id="' + faq.id + '" title="Sil"><i class="bi bi-trash"></i></button>' +
                            '</div>' +
                            '<p class="mb-0 text-clr-secondary" style="font-size:0.85rem; line-height:1.6; padding-left:24px;">' + escHtml(faq.answer) + '</p>';
                        faqList.appendChild(div);
                        bindFaqDelete(div.querySelector('.js-faq-delete'));
                    });
                    var currentCount = faqList.querySelectorAll('[id^="faq-"]').length;
                    faqCountLabel.textContent = currentCount + ' soru';
                } else {
                    notify(res.data.message || 'Üretim başarısız.', 'danger');
                }
            })
            .catch(function () {
                faqBtn.disabled = false;
                faqBtnText.textContent = 'AI SSS Üret';
                if (window.AdminModal && AdminModal.loading) AdminModal.loading.hide();
                notify('Bağlantı hatası.', 'danger');
            });
        });
    }

    function bindFaqDelete(btn) {
        if (!btn) return;
        btn.addEventListener('click', function () {
            AdminModal.confirm({
                title: 'SSS Sil', message: 'Bu soruyu silmek istediğinize emin misiniz?',
                type: 'danger', confirmText: 'Sil', confirmIcon: 'bi bi-trash3'
            }).then(function (ok) {
                if (!ok) return;
                fetch('/admin/faqs/' + btn.dataset.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) {
                    if (r.ok) {
                        var el = document.getElementById('faq-' + btn.dataset.id);
                        if (el) { el.style.transition = 'opacity 0.3s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 300); }
                        notify('Soru silindi.', 'success');
                        var currentCount = faqList.querySelectorAll('[id^="faq-"]').length;
                        faqCountLabel.textContent = currentCount + ' soru';
                    } else { notify('Silinemedi.', 'danger'); }
                });
            });
        });
    }
    document.querySelectorAll('.js-faq-delete').forEach(bindFaqDelete);

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ── AI Enhance Modal ──
    var enhanceModalEl = document.getElementById('enhanceModal');
    var enhanceModal = enhanceModalEl ? new bootstrap.Modal(enhanceModalEl) : null;
    var enhancePreview = document.getElementById('enhancePreview');
    var enhanceSubmitBtn = document.getElementById('enhanceSubmitBtn');
    var enhanceSubmitText = document.getElementById('enhanceSubmitText');
    var enhanceUrl = @json(route('admin.products.enhance-image', $product));
    var selectedImagePath = '';

    var openEnhanceBtn = document.getElementById('openEnhanceBtn');
    if (openEnhanceBtn && enhanceModal) {
        openEnhanceBtn.addEventListener('click', function () {
            var mainImg = document.getElementById('mainProductImg');
            if (mainImg) {
                enhancePreview.src = mainImg.src;
                selectedImagePath = mainImg.dataset.path || '';
            }
            var activeThumb = document.querySelector('.prd-detail-thumb-active');
            if (activeThumb && activeThumb.dataset.path) {
                selectedImagePath = activeThumb.dataset.path;
                enhancePreview.src = activeThumb.dataset.full || activeThumb.src;
            }
            enhanceModal.show();
        });
    }

    // Thumbnail click also updates enhance source
    document.querySelectorAll('.prd-detail-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            selectedImagePath = thumb.dataset.path || '';
        });
    });

    if (enhanceSubmitBtn) {
        enhanceSubmitBtn.addEventListener('click', function () {
            if (!selectedImagePath) {
                notify('Lütfen bir görsel seçin.', 'danger');
                return;
            }

            var style = document.querySelector('input[name="enhance_style"]:checked');
            if (!style) return;

            enhanceSubmitBtn.disabled = true;
            enhanceSubmitText.textContent = 'İyileştiriliyor... (30-90 sn)';

            fetch(enhanceUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    image_path: selectedImagePath,
                    style: style.value
                })
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                enhanceSubmitBtn.disabled = false;
                enhanceSubmitText.textContent = 'İyileştir';

                if (res.ok && res.data.success) {
                    enhanceModal.hide();
                    notify(res.data.message, 'success');
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    notify(res.data.message || 'İşlem başarısız.', 'danger');
                }
            })
            .catch(function () {
                enhanceSubmitBtn.disabled = false;
                enhanceSubmitText.textContent = 'İyileştir';
                notify('Bağlantı hatası oluştu.', 'danger');
            });
        });
    }

    @if(session('success'))
        notify(@json(session('success')), 'success');
    @endif
    @if(session('error'))
        notify(@json(session('error')), 'danger');
    @endif
})();
</script>
@endpush
