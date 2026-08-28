@extends('layouts.admin')

@section('title', 'Blog Yorumları')
@section('page_title', 'Blog Yorumları')
@section('page_description', 'Blog yazılarına gelen yorumları yönetin')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.blog-posts.index') }}" class="breadcrumb-link">İçerik Yönetimi</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yorumlar</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Blog Yorumları</h1>
            <p class="page-subtitle">
                @if($pendingCount > 0)
                    {{ $pendingCount }} onay bekleyen yorum var
                @else
                    Tüm blog yorumlarını yönetin
                @endif
            </p>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Yorum</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Onaylı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['approved'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Beklemede</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['pending'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Reddedildi</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['rejected'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: STATUS TABS ==================== -->
    @php
        $currentStatus = request('status', '');
        $totalCount = $stats['total'];
    @endphp

    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="100">
        <a href="{{ route('admin.blog-comments.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ $currentStatus === '' ? 'active' : '' }}">
            <span>Tümü</span>
            <span class="cl-tab-count">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('admin.blog-comments.index', array_merge(request()->except(['status', 'page']), ['status' => 'pending'])) }}"
           class="cl-status-tab {{ $currentStatus === 'pending' ? 'active' : '' }}">
            <i class="bi bi-hourglass-split text-neon-orange"></i>
            <span>Beklemede</span>
            <span class="cl-tab-count">{{ $statusCounts['pending'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.blog-comments.index', array_merge(request()->except(['status', 'page']), ['status' => 'approved'])) }}"
           class="cl-status-tab {{ $currentStatus === 'approved' ? 'active' : '' }}">
            <i class="bi bi-check-circle text-neon-green"></i>
            <span>Onaylı</span>
            <span class="cl-tab-count">{{ $statusCounts['approved'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.blog-comments.index', array_merge(request()->except(['status', 'page']), ['status' => 'rejected'])) }}"
           class="cl-status-tab {{ $currentStatus === 'rejected' ? 'active' : '' }}">
            <i class="bi bi-x-circle text-neon-red"></i>
            <span>Reddedildi</span>
            <span class="cl-tab-count">{{ $statusCounts['rejected'] ?? 0 }}</span>
        </a>
        <a href="{{ route('admin.blog-comments.index', array_merge(request()->except(['status', 'page']), ['status' => 'trashed'])) }}"
           class="cl-status-tab {{ $currentStatus === 'trashed' ? 'active' : '' }}">
            <i class="bi bi-trash text-neon-red"></i>
            <span>Silinmiş</span>
            <span class="cl-tab-count">{{ $statusCounts['trashed'] ?? 0 }}</span>
        </a>
    </div>


    <!-- ==================== SECTION 3: FILTERS & TOOLBAR ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.blog-comments.index') }}" class="cl-toolbar" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                @if(request('post_id'))
                    <input type="hidden" name="post_id" value="{{ request('post_id') }}">
                @endif
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" id="commentSearch" placeholder="Ad, e-posta veya yorum içeriğinde ara..." value="{{ request('search') }}" data-fv-ignore>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.blog-comments.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" id="perPage" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-export-menu export="blog-comments" :total="$comments->total()" />
                    {{-- Sürücü: assets/admin/js/bulk-actions.js --}}
                    <div class="cl-bulk-actions d-none" data-bulk-bar>
                        <span class="cl-bulk-count"><span data-bulk-count>0</span> seçili</span>
                        @if(request('status') === 'trashed')
                            <button type="button" class="usr-action-btn success"
                                    data-bulk-action="bulkRestoreForm"
                                    data-bulk-title="Toplu Geri Yükleme"
                                    data-bulk-message=":count yorum geri yüklenecek. Onaylıyor musunuz?"
                                    data-bulk-confirm="Evet, Geri Yükle"
                                    data-bulk-icon="bi bi-arrow-counterclockwise"
                                    title="Seçilenleri Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                        @else
                            <button type="button" class="usr-action-btn success"
                                    data-bulk-action="bulkApproveForm"
                                    data-bulk-title="Toplu Onay"
                                    data-bulk-message=":count yorum onaylanacak ve sitede görünür olacak."
                                    data-bulk-confirm="Evet, Onayla"
                                    data-bulk-icon="bi bi-check-lg"
                                    title="Seçilenleri Onayla"><i class="bi bi-check-lg"></i></button>
                            <button type="button" class="usr-action-btn danger"
                                    data-bulk-action="bulkDeleteForm"
                                    data-bulk-title="Toplu Silme Onayı"
                                    data-bulk-message=":count yorum silinecek. Silinenler &quot;Silinmiş&quot; sekmesinden geri alınabilir."
                                    data-bulk-type="danger"
                                    data-bulk-confirm="Evet, Sil"
                                    data-bulk-icon="bi bi-trash3"
                                    title="Seçilenleri Sil"><i class="bi bi-trash"></i></button>
                        @endif
                        <button type="button" class="usr-action-btn" data-bulk-clear title="Seçimi Bırak"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- ==================== SECTION 4: TABLE ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="cl-th-checkbox">
                                <input type="checkbox" class="usr-checkbox" data-bulk-all aria-label="Tümünü seç" data-fv-ignore>
                            </th>
                            <th>Kişi</th>
                            <th>Yazı</th>
                            <th class="d-none d-md-table-cell">Yorum</th>
                            <th>Durum</th>
                            <th class="d-none d-lg-table-cell">Tarih</th>
                            <th class="cl-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="commentsTableBody">
                        @forelse($comments as $comment)
                            <tr data-status="{{ $comment->trashed() ? 'trashed' : $comment->status->value }}">
                                <td data-label="Seç"><input type="checkbox" class="usr-checkbox" data-bulk-item value="{{ $comment->id }}" data-fv-ignore></td>
                                <td data-label="Kişi">
                                    <div class="cl-content-info">
                                        <span class="cl-content-title">{{ $comment->name }}</span>
                                        <span class="cl-content-meta"><i class="bi bi-envelope me-1"></i>{{ $comment->email }}</span>
                                        @if($comment->parent_id)
                                            <span class="cl-content-meta text-info"><i class="bi bi-reply me-1"></i>Yanıt</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Yazı">
                                    @if($comment->post)
                                        <a href="{{ route('admin.blog-comments.index', ['post_id' => $comment->blog_post_id]) }}" class="cl-content-meta text-teal">
                                            {{ Str::limit($comment->post->title, 35) }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Yorum" class="d-none d-md-table-cell">
                                    <span class="cl-content-meta">{{ Str::limit($comment->body, 60) }}</span>
                                </td>
                                <td data-label="Durum">
                                    @if($comment->trashed())
                                        <span class="usr-status-badge inactive">Silinmiş</span>
                                    @elseif($comment->status === \App\Enums\CommentStatus::Approved)
                                        <span class="usr-status-badge active">Onaylı</span>
                                    @elseif($comment->status === \App\Enums\CommentStatus::Pending)
                                        <span class="usr-status-badge pending">Beklemede</span>
                                    @elseif($comment->status === \App\Enums\CommentStatus::Rejected)
                                        <span class="usr-status-badge inactive">Reddedildi</span>
                                    @endif
                                </td>
                                <td data-label="Tarih" class="d-none d-lg-table-cell">
                                    <div class="cl-content-info">
                                        <span class="usr-meta">{{ $comment->created_at->format('d.m.Y') }}</span>
                                        <span class="cl-content-meta">{{ $comment->created_at->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td data-label="İşlem">
                                    <div class="usr-actions">
                                        @if($comment->trashed())
                                            <form method="POST" action="{{ route('admin.blog-comments.restore', $comment->id) }}" id="restoreForm-{{ $comment->id }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="button" class="usr-action-btn success" title="Geri Yükle" onclick="confirmCommentAction('restore', {{ $comment->id }}, '{{ addslashes($comment->name) }}')"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.blog-comments.show', $comment) }}" class="usr-action-btn" title="Detay"><i class="bi bi-eye"></i></a>
                                            @if($comment->status !== \App\Enums\CommentStatus::Approved)
                                                <form method="POST" action="{{ route('admin.blog-comments.approve', $comment) }}" id="approveForm-{{ $comment->id }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="button" class="usr-action-btn success" title="Onayla" onclick="confirmCommentAction('approve', {{ $comment->id }}, '{{ addslashes($comment->name) }}')"><i class="bi bi-check-lg"></i></button>
                                                </form>
                                            @endif
                                            @if($comment->status !== \App\Enums\CommentStatus::Rejected)
                                                <form method="POST" action="{{ route('admin.blog-comments.reject', $comment) }}" id="rejectForm-{{ $comment->id }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="button" class="usr-action-btn warning" title="Reddet" onclick="confirmCommentAction('reject', {{ $comment->id }}, '{{ addslashes($comment->name) }}')"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            @endif
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $comment->id }}, '{{ addslashes($comment->name) }}')"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-chat-dots d-block fs-1 mb-2"></i>
                                    Yorum bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.admin.pagination', ['paginator' => $comments, 'itemLabel' => 'yorum'])
    </div>

    {{-- Toplu işlem formları — kimlikleri bulk-actions.js dolduruyor. --}}
    <form method="POST" action="{{ route('admin.blog-comments.bulk-approve', request()->query()) }}" id="bulkApproveForm" class="d-none">
        @csrf
        @method('PATCH')
    </form>
    <form method="POST" action="{{ route('admin.blog-comments.bulk-destroy', request()->query()) }}" id="bulkDeleteForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>
    <form method="POST" action="{{ route('admin.blog-comments.bulk-restore', request()->query()) }}" id="bulkRestoreForm" class="d-none">
        @csrf
        @method('PATCH')
    </form>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/blog-comments.js') }}"></script>
@endpush
