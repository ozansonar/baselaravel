@extends('layouts.admin')

@section('title', 'İçerik Detay')
@section('page_title', 'İçerik Detay')
@section('page_description', $post->title)

@php
    $statusBadge = 'pending';
    $statusLabel = 'Taslak';
    if ($post->trashed()) {
        $statusBadge = 'inactive';
        $statusLabel = 'Silinmiş';
    } elseif ($post->status === \App\Enums\ContentStatus::Published && $post->published_at?->lte(now())) {
        $statusBadge = 'active';
        $statusLabel = 'Yayında';
    } elseif ($post->status === \App\Enums\ContentStatus::Published) {
        $statusBadge = 'info';
        $statusLabel = 'Zamanlanmış';
    } elseif ($post->status === \App\Enums\ContentStatus::Archived) {
        $statusBadge = 'inactive';
        $statusLabel = 'Arşivlendi';
    }

    $frontUrl = $post->status === \App\Enums\ContentStatus::Published && $post->published_at?->lte(now())
        ? route('blog.show', [$post->category->slug, $post->slug])
        : null;
@endphp

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.blog-posts.index') }}" class="breadcrumb-link">İçerikler</a>
            </li>
            <li class="breadcrumb-item active text-teal">Detay</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">İçerik Detay</h1>
                <p class="page-subtitle mb-0">{{ Str::limit($post->title, 60) }}</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($frontUrl)
                <a href="{{ $frontUrl }}" target="_blank" class="btn-glass" title="Sitede Görüntüle">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Sitede Görüntüle
                </a>
            @endif
            <a href="{{ route('admin.blog-posts.edit', $post) }}" class="btn-teal">
                <i class="bi bi-pencil me-1"></i>Düzenle
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Sol: İçerik Detayları --}}
        <div class="col-lg-8">
            {{-- Görsel --}}
            @if($post->image)
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom p-0">
                        <img src="{{ upload_url($post->image, 'lg') }}" alt="{{ $post->title }}" class="img-fluid w-100" loading="lazy">
                    </div>
                </div>
            @endif

            {{-- Temel Bilgiler --}}
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body-custom">
                    <div class="usr-detail-section">
                        <h6 class="usr-detail-section-title">Başlık</h6>
                        <p class="mb-0 text-white">{{ $post->title }}</p>
                    </div>

                    @if($post->excerpt)
                        <div class="usr-detail-section">
                            <h6 class="usr-detail-section-title">Özet</h6>
                            <p class="mb-0 text-muted">{{ $post->excerpt }}</p>
                        </div>
                    @endif

                    <div class="usr-detail-section">
                        <h6 class="usr-detail-section-title">İçerik</h6>
                        <div class="cl-body-content">
                            {!! $post->body !!}
                        </div>
                    </div>
                </div>
            </div>

            {{-- SEO Bilgileri --}}
            @if($post->meta_title || $post->meta_description)
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-body-custom">
                        <div class="usr-detail-section">
                            <h6 class="usr-detail-section-title"><i class="bi bi-search me-1"></i>SEO Bilgileri</h6>
                            <div class="usr-detail-info-list">
                                @if($post->meta_title)
                                    <div class="usr-detail-info-item">
                                        <i class="bi bi-type-h1"></i>
                                        <div>
                                            <span class="usr-detail-info-label">Meta Başlık</span>
                                            <span class="usr-detail-info-value">{{ $post->meta_title }}</span>
                                        </div>
                                    </div>
                                @endif
                                @if($post->meta_description)
                                    <div class="usr-detail-info-item">
                                        <i class="bi bi-text-paragraph"></i>
                                        <div>
                                            <span class="usr-detail-info-label">Meta Açıklama</span>
                                            <span class="usr-detail-info-value">{{ $post->meta_description }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sağ: Bilgi Paneli --}}
        <div class="col-lg-4">
            {{-- Durum & Tarih --}}
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body-custom">
                    <div class="usr-detail-section">
                        <h6 class="usr-detail-section-title"><i class="bi bi-info-circle me-1"></i>Genel Bilgiler</h6>
                        <div class="usr-detail-info-list">
                            <div class="usr-detail-info-item">
                                <i class="bi bi-circle-fill"></i>
                                <div>
                                    <span class="usr-detail-info-label">Durum</span>
                                    <span class="usr-status-badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                                </div>
                            </div>
                            <div class="usr-detail-info-item">
                                <i class="bi bi-folder"></i>
                                <div>
                                    <span class="usr-detail-info-label">Kategori</span>
                                    <span class="usr-detail-info-value">{{ $post->category?->name ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="usr-detail-info-item">
                                <i class="bi bi-person"></i>
                                <div>
                                    <span class="usr-detail-info-label">Yazar</span>
                                    <span class="usr-detail-info-value">{{ $post->author?->name ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="usr-detail-info-item">
                                <i class="bi bi-link-45deg"></i>
                                <div>
                                    <span class="usr-detail-info-label">Slug</span>
                                    <span class="usr-detail-info-value">{{ $post->slug }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- İstatistikler --}}
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card-body-custom">
                    <div class="usr-detail-section">
                        <h6 class="usr-detail-section-title"><i class="bi bi-bar-chart me-1"></i>İstatistikler</h6>
                        <div class="usr-detail-info-list">
                            <div class="usr-detail-info-item">
                                <i class="bi bi-eye"></i>
                                <div>
                                    <span class="usr-detail-info-label">Görüntülenme</span>
                                    <span class="usr-detail-info-value">{{ number_format($post->views, 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <div class="usr-detail-info-item">
                                <i class="bi bi-chat-dots"></i>
                                <div>
                                    <span class="usr-detail-info-label">Yorum Sayısı</span>
                                    <span class="usr-detail-info-value">{{ $post->comments->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tarihler --}}
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="card-body-custom">
                    <div class="usr-detail-section">
                        <h6 class="usr-detail-section-title"><i class="bi bi-calendar3 me-1"></i>Tarihler</h6>
                        <div class="usr-detail-info-list">
                            <div class="usr-detail-info-item">
                                <i class="bi bi-calendar-plus"></i>
                                <div>
                                    <span class="usr-detail-info-label">Oluşturulma</span>
                                    <span class="usr-detail-info-value">{{ $post->created_at->format('d.m.Y H:i') }}</span>
                                </div>
                            </div>
                            <div class="usr-detail-info-item">
                                <i class="bi bi-calendar-check"></i>
                                <div>
                                    <span class="usr-detail-info-label">Son Güncelleme</span>
                                    <span class="usr-detail-info-value">{{ $post->updated_at->format('d.m.Y H:i') }}</span>
                                </div>
                            </div>
                            @if($post->published_at)
                                <div class="usr-detail-info-item">
                                    <i class="bi bi-calendar-event"></i>
                                    <div>
                                        <span class="usr-detail-info-label">Yayın Tarihi</span>
                                        <span class="usr-detail-info-value">{{ $post->published_at->format('d.m.Y H:i') }}</span>
                                    </div>
                                </div>
                            @endif
                            @if($post->deleted_at)
                                <div class="usr-detail-info-item">
                                    <i class="bi bi-calendar-x"></i>
                                    <div>
                                        <span class="usr-detail-info-label">Silinme Tarihi</span>
                                        <span class="usr-detail-info-value">{{ $post->deleted_at->format('d.m.Y H:i') }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Front Link --}}
            @if($frontUrl)
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="card-body-custom">
                        <div class="usr-detail-section mb-0">
                            <h6 class="usr-detail-section-title"><i class="bi bi-globe me-1"></i>Front Link</h6>
                            <a href="{{ $frontUrl }}" target="_blank" class="btn-teal w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-box-arrow-up-right"></i> Sitede Görüntüle
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- İşlemler --}}
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="500">
                <div class="card-body-custom">
                    <div class="usr-detail-section mb-0">
                        <h6 class="usr-detail-section-title"><i class="bi bi-gear me-1"></i>İşlemler</h6>
                        <div class="d-flex flex-column gap-2">
                            <a href="{{ route('admin.blog-posts.edit', $post) }}" class="btn-teal d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-pencil"></i> Düzenle
                            </a>
                            @if(!$post->trashed())
                                <button class="btn-glass d-flex align-items-center justify-content-center gap-2 cl-btn-danger-text" onclick="openDeleteModal('{{ e($post->title) }}', {{ $post->id }})">
                                    <i class="bi bi-trash"></i> Sil
                                </button>
                            @else
                                <form method="POST" action="{{ route('admin.blog-posts.restore', $post->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-glass w-100 d-flex align-items-center justify-content-center gap-2">
                                        <i class="bi bi-arrow-counterclockwise"></i> Geri Yükle
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- DELETE MODAL --}}
    @if(!$post->trashed())
        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="status-modal-icon danger">
                            <i class="bi bi-trash"></i>
                        </div>
                        <h5 class="cl-modal-heading">Silme Onayı</h5>
                        <p class="cl-modal-body-text"><strong id="deleteItemName"></strong> içeriğini silmek istediğinizden emin misiniz?</p>
                        <p class="cl-modal-warning"><i class="bi bi-exclamation-circle me-1"></i>Bu işlem geri alınabilir.</p>
                        <form method="POST" id="deleteForm">
                            @csrf
                            @method('DELETE')
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-teal btn-danger-gradient">
                                    <i class="bi bi-trash"></i> Evet, Sil
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if(!$post->trashed())
@push('scripts')
<script>
function openDeleteModal(name, id) {
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteForm').action = '{{ url("admin/blog-posts") }}/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endpush
@endif
