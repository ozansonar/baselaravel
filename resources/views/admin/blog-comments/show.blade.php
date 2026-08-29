@extends('layouts.admin')

@section('title', 'Yorum Detay')

@php
    $durum = $comment->trashed() ? 'trashed' : $comment->status->value;
    $rozet = match (true) {
        $comment->trashed()                                       => ['inactive', 'Silinmiş', 'bi-trash'],
        $comment->status === \App\Enums\CommentStatus::Approved    => ['active', 'Onaylı', 'bi-check-circle-fill'],
        $comment->status === \App\Enums\CommentStatus::Rejected    => ['inactive', 'Reddedildi', 'bi-x-circle-fill'],
        default                                                   => ['pending', 'Beklemede', 'bi-hourglass-split'],
    };
@endphp

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.blog-comments.index') }}" class="breadcrumb-link">Yorumlar</a>
            </li>
            <li class="breadcrumb-item active text-teal">{{ Str::limit($comment->name, 30) }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">Yorum Detayı</h1>
            <p class="page-subtitle mb-0">
                @if($comment->post)
                    <i class="bi bi-file-earmark-text me-1"></i>{{ $comment->post->title }}
                @else
                    Yazısı silinmiş bir yorum
                @endif
            </p>
        </div>
        <a href="{{ route('admin.blog-comments.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left"></i> Listeye Dön
        </a>
    </div>

    <div class="row g-4">

        {{-- ==================== SOL: YORUM ==================== --}}
        <div class="col-lg-8">

            {{-- Yorumun kendisi: yazan kişi, durumu ve metni tek kartta.
                 Önce metin ile kişi bilgileri iki ayrı kutuya bölünmüştü;
                 kimin ne yazdığını görmek için gözü sağa sola atmak
                 gerekiyordu. --}}
            <div class="cmt-detail-card" data-aos="fade-up">
                <div class="cmt-detail-head">
                    <span class="cmt-avatar cmt-avatar--lg">{{ mb_strtoupper(mb_substr($comment->name, 0, 1)) }}</span>

                    <div class="cmt-detail-head__body">
                        <h2 class="cmt-detail-name">{{ $comment->name }}</h2>
                        <div class="cmt-detail-meta">
                            <a href="mailto:{{ $comment->email }}" class="cmt-detail-meta__item">
                                <i class="bi bi-envelope"></i>{{ $comment->email }}
                            </a>
                            <span class="cmt-detail-meta__item">
                                <i class="bi bi-clock"></i>{{ $comment->created_at->translatedFormat('d F Y, H:i') }}
                            </span>
                            @if($comment->ip_address)
                                <span class="cmt-detail-meta__item">
                                    <i class="bi bi-hdd-network"></i>{{ $comment->ip_address }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <span class="usr-status-badge {{ $rozet[0] }} cmt-detail-status">
                        <i class="bi {{ $rozet[2] }} me-1"></i>{{ $rozet[1] }}
                    </span>
                </div>

                @if($comment->parent)
                    {{-- Yanıtlanan yorum metnin üstünde: yanıtı bağlamı olmadan
                         okumak çoğu zaman anlamsız. --}}
                    <div class="cmt-quote">
                        <span class="cmt-quote__label"><i class="bi bi-reply-fill"></i> {{ $comment->parent->name }} şunu yazmıştı</span>
                        <p class="cmt-quote__text">{{ Str::limit($comment->parent->body, 300) }}</p>
                    </div>
                @endif

                <div class="cmt-detail-body">
                    {!! nl2br(e($comment->body)) !!}
                </div>

                <div class="cmt-detail-foot">
                    <span class="cmt-detail-foot__item">
                        <i class="bi bi-type"></i>{{ mb_strlen($comment->body) }} karakter
                    </span>
                    @if($comment->parent_id)
                        <span class="cmt-detail-foot__item"><i class="bi bi-reply"></i>Bir yoruma yanıt</span>
                    @endif
                    @if($comment->replies->isNotEmpty())
                        <span class="cmt-detail-foot__item"><i class="bi bi-chat-left-text"></i>{{ $comment->replies->count() }} yanıt aldı</span>
                    @endif
                </div>
            </div>

            {{-- Bu yoruma gelen yanıtlar --}}
            @if($comment->replies->isNotEmpty())
                <div class="card-dark mt-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-blue"><i class="bi bi-chat-left-text"></i></div>
                            <div>
                                <h6 class="mb-0">Yanıtlar</h6>
                                <small class="text-muted">Bu yoruma gelen {{ $comment->replies->count() }} yanıt</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        @foreach($comment->replies as $reply)
                            <div class="cmt-reply {{ $loop->last ? '' : 'cmt-reply--sep' }}">
                                <span class="cmt-avatar">{{ mb_strtoupper(mb_substr($reply->name, 0, 1)) }}</span>
                                <div class="cmt-reply__body">
                                    <div class="cmt-reply__head">
                                        <strong>{{ $reply->name }}</strong>
                                        <span class="usr-status-badge {{ $reply->status === \App\Enums\CommentStatus::Approved ? 'active' : ($reply->status === \App\Enums\CommentStatus::Rejected ? 'inactive' : 'pending') }}">
                                            {{ $reply->status->label() }}
                                        </span>
                                        <span class="cmt-reply__date">{{ $reply->created_at->format('d.m.Y H:i') }}</span>
                                    </div>
                                    <p class="cmt-reply__text">{{ $reply->body }}</p>
                                    <a href="{{ route('admin.blog-comments.show', $reply) }}" class="cmt-reply__link">
                                        Yanıtı aç <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ==================== SAĞ: İŞLEMLER VE BAĞLAM ==================== --}}
        <div class="col-lg-4">

            {{-- İşlemler en üstte: sayfaya gelme sebebi genellikle onaylamak
                 ya da reddetmek. --}}
            <div class="card-dark" data-aos="fade-up">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-purple"><i class="bi bi-lightning-charge"></i></div>
                        <div>
                            <h6 class="mb-0">İşlemler</h6>
                            <small class="text-muted">Yorumun durumunu değiştirin</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom d-flex flex-column gap-2">
                    @if($comment->trashed())
                        <form method="POST" action="{{ route('admin.blog-comments.restore', $comment->id) }}" id="restoreForm-{{ $comment->id }}">
                            @csrf
                            @method('PATCH')
                            {{-- Onay penceresi zorunlu: tıklama anında iş bitiyor. --}}
                            <button type="button" class="btn-teal w-100 justify-content-center"
                                    onclick="confirmCommentAction('restore', {{ $comment->id }}, @js($comment->name))">
                                <i class="bi bi-arrow-counterclockwise"></i> Geri Yükle
                            </button>
                        </form>
                    @else
                        @if($comment->status !== \App\Enums\CommentStatus::Approved)
                            <form method="POST" action="{{ route('admin.blog-comments.approve', $comment) }}" id="approveForm-{{ $comment->id }}">
                                @csrf
                                @method('PATCH')
                                <button type="button" class="btn-teal w-100 justify-content-center"
                                        onclick="confirmCommentAction('approve', {{ $comment->id }}, @js($comment->name))">
                                    <i class="bi bi-check-lg"></i> Onayla ve Yayınla
                                </button>
                            </form>
                        @endif

                        @if($comment->status !== \App\Enums\CommentStatus::Rejected)
                            <form method="POST" action="{{ route('admin.blog-comments.reject', $comment) }}" id="rejectForm-{{ $comment->id }}">
                                @csrf
                                @method('PATCH')
                                <button type="button" class="btn-glass w-100 justify-content-center"
                                        onclick="confirmCommentAction('reject', {{ $comment->id }}, @js($comment->name))">
                                    <i class="bi bi-x-lg"></i> Reddet
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.blog-comments.destroy', $comment) }}" id="deleteForm-{{ $comment->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn-glass danger w-100 justify-content-center"
                                    onclick="openDeleteModal({{ $comment->id }}, @js($comment->name))">
                                <i class="bi bi-trash"></i> Sil
                            </button>
                        </form>
                    @endif

                    @if($comment->status === \App\Enums\CommentStatus::Approved && !$comment->trashed())
                        <p class="cmt-hint mb-0">
                            <i class="bi bi-info-circle me-1"></i>Bu yorum sitede yayında.
                        </p>
                    @elseif(!$comment->trashed())
                        <p class="cmt-hint mb-0">
                            <i class="bi bi-eye-slash me-1"></i>Onaylanana kadar sitede görünmüyor.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Yazı bağlamı --}}
            @if($comment->post)
                <div class="card-dark mt-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-blue"><i class="bi bi-file-earmark-text"></i></div>
                            <div>
                                <h6 class="mb-0">Yorumun Yazısı</h6>
                                <small class="text-muted">{{ $comment->post->comments()->count() }} yorum aldı</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom d-flex flex-column gap-2">
                        <span class="cmt-post-title">{{ $comment->post->title }}</span>
                        <a href="{{ route('admin.blog-comments.index', ['post_id' => $comment->blog_post_id]) }}" class="btn-glass w-100 justify-content-center">
                            <i class="bi bi-chat-dots"></i> Bu Yazının Yorumları
                        </a>
                        <a href="{{ route('admin.blog-posts.edit', $comment->post) }}" class="btn-glass w-100 justify-content-center">
                            <i class="bi bi-pencil"></i> Yazıyı Düzenle
                        </a>
                    </div>
                </div>
            @endif

            {{-- Teknik bilgiler --}}
            <div class="card-dark mt-4" data-aos="fade-up" data-aos-delay="150">
                <div class="card-header-custom">
                    <div class="form-section-header mb-0">
                        <div class="form-section-icon bg-icon-teal"><i class="bi bi-info-circle"></i></div>
                        <div>
                            <h6 class="mb-0">Kayıt Bilgileri</h6>
                            <small class="text-muted">Yorumla birlikte tutulan veriler</small>
                        </div>
                    </div>
                </div>
                <div class="card-body-custom">
                    <dl class="cmt-facts mb-0">
                        <dt>Gönderim</dt>
                        <dd>{{ $comment->created_at->translatedFormat('d F Y, H:i') }}</dd>

                        @if($comment->updated_at && $comment->updated_at->ne($comment->created_at))
                            <dt>Son değişiklik</dt>
                            <dd>{{ $comment->updated_at->translatedFormat('d F Y, H:i') }}</dd>
                        @endif

                        <dt>IP adresi</dt>
                        <dd>{{ $comment->ip_address ?? '—' }}</dd>

                        <dt>Kayıt no</dt>
                        <dd>#{{ $comment->id }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
{{-- Onay pencerelerini ve silme akışını liste sayfasıyla aynı dosya sürüyor;
     iki ekranda iki ayrı onay metni olmasın. --}}
<script src="{{ versioned_asset('assets/admin/js/blog-comments.js') }}"></script>
@endpush
