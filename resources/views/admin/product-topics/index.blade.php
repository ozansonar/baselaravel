@extends('layouts.admin')

@section('title', 'Topic Cluster')
@section('page_title', 'Topic Cluster')
@section('page_description', 'Ürün etrafında planlanmış blog konuları üret ve cron havuzunu doldur')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Topic Cluster</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-diagram-3 me-2"></i>Topic Cluster</h1>
            <p class="page-subtitle">
                AI ile her ürün için 7 farklı search intent'te blog başlığı üret. Cron 4 kez/gün havuzdan topic seçip yazıya çevirir.
            </p>
        </div>
    </div>

    {{-- SECTION 1: STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Topic</span>
                    <h3 class="usr-stat-value" data-count="{{ $totalTopics }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-teal">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Havuzdaki Bekleyen</span>
                    <h3 class="usr-stat-value" data-count="{{ $poolSize }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Blog'a Çevrilmiş</span>
                    <h3 class="usr-stat-value" data-count="{{ $convertedTopics }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-calendar-week-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Tahmini Süre</span>
                    <h3 class="usr-stat-value">
                        @if($poolSize === 0)
                            0 gün
                        @else
                            {{ $daysUntilEmpty }} gün
                        @endif
                    </h3>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: POOL STATUS ALERT --}}
    <div class="card-dark mb-4 topic-pool-alert topic-pool-alert--{{ $poolStatus }}" data-aos="fade-up">
        <div class="card-body-custom">
            <div class="d-flex align-items-start gap-3 flex-wrap">
                <div class="topic-pool-alert__icon">
                    @if($poolStatus === 'empty')
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    @elseif($poolStatus === 'critical')
                        <i class="bi bi-exclamation-circle-fill"></i>
                    @elseif($poolStatus === 'warning')
                        <i class="bi bi-info-circle-fill"></i>
                    @else
                        <i class="bi bi-check-circle-fill"></i>
                    @endif
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 topic-pool-alert__title">
                        @if($poolStatus === 'empty')
                            Topic havuzu boş — Cron rastgele ürün adıyla üretiyor
                        @elseif($poolStatus === 'critical')
                            Havuz kritik seviyede — En kısa sürede topic üret
                        @elseif($poolStatus === 'warning')
                            Havuz azalıyor — Yakında yeni topic üretmelisin
                        @else
                            Havuz sağlıklı — Cron için yeterli topic var
                        @endif
                    </h6>
                    <p class="mb-0 small text-muted">
                        @if($poolStatus === 'empty')
                            Şu an havuzda hiç bekleyen topic yok. Cron 4 kez/gün çalışıyor ve topic bulamadığında "rastgele ürün adı" fallback'i devreye giriyor — bu daha düşük kaliteli içerik üretir. Aşağıdaki ürünlerden en az birkaçı için <strong>"AI ile 7 Konu Üret"</strong> butonuna tıkla.
                        @elseif($poolStatus === 'critical')
                            Havuzda <strong>{{ $poolSize }} topic</strong> kaldı. Cron 4 blog/gün ürettiğine göre yaklaşık <strong>{{ $daysUntilEmpty }} gün</strong> sonra havuz boşalır. Şimdiden yeni topic üretmeye başla.
                        @elseif($poolStatus === 'warning')
                            Havuzda <strong>{{ $poolSize }} topic</strong> bekliyor (yaklaşık {{ $daysUntilEmpty }} gün yeter). Hafta içinde 1-2 ürün için yeni topic üretmen iyi olur.
                        @else
                            Havuzda <strong>{{ $poolSize }} topic</strong> bekliyor. Cron 4 blog/gün ürettiğine göre yaklaşık <strong>{{ $daysUntilEmpty }} gün</strong> içerik üretir. Şimdilik yeni topic üretmene gerek yok.
                        @endif
                    </p>
                    @if($productsWithoutTopics > 0)
                        <p class="mb-0 small mt-2">
                            <i class="bi bi-exclamation-diamond text-warning me-1"></i>
                            <strong>{{ $productsWithoutTopics }} ürün</strong> için henüz hiç topic üretilmedi.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 3: HOW IT WORKS (collapsible) --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header-custom" role="button" data-bs-toggle="collapse" data-bs-target="#topicHelpCollapse">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="form-section-icon bg-icon-blue"><i class="bi bi-question-circle-fill"></i></div>
                    <div>
                        <h6 class="mb-0">Bu sayfa ne işe yarar? <span class="text-muted small">(detayları görmek için tıkla)</span></h6>
                    </div>
                </div>
                <i class="bi bi-chevron-down topic-help-chevron"></i>
            </div>
        </div>
        <div class="collapse" id="topicHelpCollapse">
            <div class="card-body-custom">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="text-teal mb-2"><i class="bi bi-1-circle-fill me-1"></i> Mantık</h6>
                        <p class="small text-muted mb-3">
                            Google'a "konu otoritesi" sinyali vermek için her ürün etrafında 7 farklı blog yazısından oluşan bir küme (cluster) oluştururuz. AI her ürün için 7 farklı search intent'te (kullanıcı niyeti) başlık önerir, sonra cron bunları sırayla blog yazısına çevirir.
                        </p>

                        <h6 class="text-teal mb-2"><i class="bi bi-2-circle-fill me-1"></i> Akış</h6>
                        <ol class="small text-muted mb-0 ps-3">
                            <li>Aşağıdaki ürün için <strong>"AI ile 7 Konu Üret"</strong> butonuna tıkla</li>
                            <li>AI 7 farklı başlık önerir (havuza eklenir)</li>
                            <li>Cron her gün 4 kez (09:07, 12:23, 15:41, 18:16) havuzdan topic alır → blog yazısına çevirir</li>
                            <li>İstersen tek tek <strong>"Blog Yap"</strong> butonuyla manuel de çevirebilirsin</li>
                        </ol>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-teal mb-2"><i class="bi bi-3-circle-fill me-1"></i> Ne zaman üretmem gerek?</h6>
                        <ul class="small text-muted mb-3 ps-3">
                            <li><span class="topic-status-dot topic-status-dot--healthy"></span> <strong>Yeşil:</strong> Havuzda 30+ topic → 1+ haftalık içerik var, üretmeye gerek yok</li>
                            <li><span class="topic-status-dot topic-status-dot--warning"></span> <strong>Sarı:</strong> 10-30 topic → Bu hafta yeni üretmeyi planla</li>
                            <li><span class="topic-status-dot topic-status-dot--critical"></span> <strong>Turuncu:</strong> &lt;10 topic → Hemen üret, havuz tükenmek üzere</li>
                            <li><span class="topic-status-dot topic-status-dot--empty"></span> <strong>Kırmızı:</strong> 0 topic → Cron rastgele ürün adı kullanıyor (kaliteli değil)</li>
                        </ul>

                        <h6 class="text-teal mb-2"><i class="bi bi-4-circle-fill me-1"></i> 7 Search Intent</h6>
                        <div class="row g-2 small">
                            <div class="col-md-6"><span class="cl-category-badge"><i class="bi bi-tools me-1"></i>Nasıl Yapılır</span></div>
                            <div class="col-md-6"><span class="cl-category-badge"><i class="bi bi-bar-chart me-1"></i>Market vs Köy</span></div>
                            <div class="col-md-6"><span class="cl-category-badge"><i class="bi bi-snow me-1"></i>Saklama</span></div>
                            <div class="col-md-6"><span class="cl-category-badge"><i class="bi bi-cart me-1"></i>Sipariş</span></div>
                            <div class="col-md-6"><span class="cl-category-badge"><i class="bi bi-egg-fried me-1"></i>Tarif</span></div>
                            <div class="col-md-6"><span class="cl-category-badge"><i class="bi bi-heart-pulse me-1"></i>Sağlık</span></div>
                            <div class="col-md-12"><span class="cl-category-badge"><i class="bi bi-search me-1"></i>Alış Rehberi</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success mb-4" data-aos="fade-up">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif

    {{-- SECTION 4: PRODUCTS LIST --}}
    @forelse($products as $product)
        @php
            $topicCount     = $product->topics_count;
            $convertedCount = $product->topics->whereNotNull('blog_post_id')->count();
            $pendingCount   = $topicCount - $convertedCount;
        @endphp
        <div class="card-dark mb-4 topic-product-card" id="product-{{ $product->id }}" data-aos="fade-up">
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="form-section-icon bg-icon-teal"><i class="bi bi-box-seam-fill"></i></div>
                    <div>
                        <h6 class="mb-0">{{ $product->name }}</h6>
                        <small class="text-muted">
                            {{ $product->category?->name ?? 'Kategori yok' }}
                            @if($topicCount > 0)
                                · <strong class="text-light">{{ $topicCount }}</strong> topic
                                · <span class="text-success">{{ $convertedCount }} blog'a çevrildi</span>
                                @if($pendingCount > 0)
                                    · <span class="text-warning">{{ $pendingCount }} bekliyor</span>
                                @endif
                            @else
                                · <span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Henüz topic yok</span>
                            @endif
                        </small>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button"
                            class="btn-glass topic-generate-btn"
                            data-product-id="{{ $product->id }}"
                            data-product-name="{{ $product->name }}">
                        <i class="bi bi-stars me-1"></i>
                        @if($topicCount === 0)
                            AI ile 7 Konu Üret
                        @else
                            Yeni 7 Konu Daha Üret
                        @endif
                    </button>
                </div>
            </div>

            <div class="card-body-custom">
                @if($product->topics->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-diagram-3 d-block fs-1 mb-2"></i>
                        Bu ürün için henüz konu kümesi üretilmedi.
                        <br>
                        Yukarıdaki <strong>"AI ile 7 Konu Üret"</strong> butonuna tıkla.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Başlık</th>
                                    <th class="d-none d-md-table-cell">Search Intent</th>
                                    <th>Durum</th>
                                    <th class="cl-th-actions">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="topics-tbody" data-product-id="{{ $product->id }}">
                                @foreach($product->topics as $topic)
                                    <tr id="topic-{{ $topic->id }}">
                                        <td data-label="Başlık">
                                            <div class="cl-content-info">
                                                <span class="cl-content-title">{{ $topic->title }}</span>
                                            </div>
                                        </td>
                                        <td data-label="Intent" class="d-none d-md-table-cell">
                                            <span class="cl-category-badge">{{ $topic->search_intent ?? '-' }}</span>
                                        </td>
                                        <td data-label="Durum" class="topic-status-cell">
                                            @if($topic->isConverted() && $topic->blogPost)
                                                <span class="usr-status-badge active">
                                                    <i class="bi bi-check-circle"></i> Blog'a çevrildi
                                                </span>
                                            @else
                                                <span class="usr-status-badge pending">
                                                    <i class="bi bi-hourglass"></i> Bekliyor
                                                </span>
                                            @endif
                                        </td>
                                        <td data-label="İşlem">
                                            <div class="usr-actions topic-actions">
                                                @if($topic->isConverted() && $topic->blogPost)
                                                    <a class="usr-action-btn"
                                                       title="Blog Yazısını Düzenle"
                                                       href="{{ route('admin.blog-posts.edit', $topic->blogPost->id) }}">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    @if($topic->blogPost->category)
                                                    <a class="usr-action-btn success"
                                                       title="Sitede Görüntüle"
                                                       target="_blank"
                                                       href="{{ route('blog.show', [$topic->blogPost->category->slug, $topic->blogPost->slug]) }}">
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                    </a>
                                                    @endif
                                                @else
                                                    <button type="button"
                                                            class="usr-action-btn topic-convert-btn"
                                                            title="Blog Yazısı Oluştur"
                                                            data-topic-id="{{ $topic->id }}"
                                                            data-topic-title="{{ e($topic->title) }}">
                                                        <i class="bi bi-magic"></i> Blog Yap
                                                    </button>
                                                @endif
                                                <form method="POST"
                                                      action="{{ route('admin.product-topics.destroy', $topic) }}"
                                                      class="d-inline topic-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                            class="usr-action-btn danger topic-delete-btn"
                                                            title="Konuyu Sil"
                                                            data-topic-title="{{ e($topic->title) }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card-dark">
            <div class="card-body-custom text-center py-5">
                <i class="bi bi-box-seam d-block fs-1 mb-2 text-muted"></i>
                <p class="mb-0 text-muted">Sistemde aktif ürün yok. Önce ürün ekle.</p>
            </div>
        </div>
    @endforelse
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/product-topics.js') }}"></script>
@endpush
