@extends('layouts.admin')

@section('title', 'Yorum Detay')

@section('content')
      <!-- Page Header -->
      <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h1 class="page-title">Yorum Detay</h1>
          <p class="page-subtitle">{{ $comment->post?->title }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="{{ route('admin.blog-comments.index') }}" class="btn btn-outline-teal">
            <i class="bi bi-arrow-left"></i> Geri Dön
          </a>
        </div>
      </div>

      <div class="row g-4">
        <!-- Comment Detail -->
        <div class="col-lg-8">
          <div class="admin-card">
            <div class="admin-card-header">
              <h5 class="admin-card-title"><i class="bi bi-chat-dots"></i> Yorum İçeriği</h5>
            </div>
            <div class="admin-card-body">
              <p class="mb-0 comment-body">{{ $comment->body }}</p>
            </div>
          </div>

          @if($comment->parent)
          <div class="admin-card mt-4">
            <div class="admin-card-header">
              <h5 class="admin-card-title"><i class="bi bi-reply"></i> Yanıtlanan Yorum</h5>
            </div>
            <div class="admin-card-body">
              <div class="d-flex gap-3 align-items-start">
                <div>
                  <strong>{{ $comment->parent->name }}</strong>
                  <small class="text-muted ms-2">{{ $comment->parent->created_at->format('d.m.Y H:i') }}</small>
                  <p class="mt-2 mb-0 text-muted">{{ $comment->parent->body }}</p>
                </div>
              </div>
            </div>
          </div>
          @endif

          @if($comment->replies->isNotEmpty())
          <div class="admin-card mt-4">
            <div class="admin-card-header">
              <h5 class="admin-card-title"><i class="bi bi-chat-left-text"></i> Yanıtlar ({{ $comment->replies->count() }})</h5>
            </div>
            <div class="admin-card-body">
              @foreach($comment->replies as $reply)
              <div class="d-flex gap-3 align-items-start {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                <div>
                  <strong>{{ $reply->name }}</strong>
                  <small class="text-muted ms-2">{{ $reply->created_at->format('d.m.Y H:i') }}</small>
                  <span class="badge bg-{{ $reply->status->color() }} ms-2">{{ $reply->status->label() }}</span>
                  <p class="mt-2 mb-0 text-muted">{{ $reply->body }}</p>
                </div>
              </div>
              @endforeach
            </div>
          </div>
          @endif
        </div>

        <!-- Comment Info -->
        <div class="col-lg-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <h5 class="admin-card-title"><i class="bi bi-person"></i> Yorum Bilgileri</h5>
            </div>
            <div class="admin-card-body">
              <div class="mb-3">
                <small class="text-muted d-block">Ad Soyad</small>
                <strong>{{ $comment->name }}</strong>
              </div>
              <div class="mb-3">
                <small class="text-muted d-block">E-posta</small>
                <span>{{ $comment->email }}</span>
              </div>
              <div class="mb-3">
                <small class="text-muted d-block">IP Adresi</small>
                <span>{{ $comment->ip_address ?? '-' }}</span>
              </div>
              <div class="mb-3">
                <small class="text-muted d-block">Durum</small>
                <span class="badge bg-{{ $comment->status->color() }}">{{ $comment->status->label() }}</span>
              </div>
              <div class="mb-3">
                <small class="text-muted d-block">Tarih</small>
                <span>{{ $comment->created_at->format('d.m.Y H:i') }}</span>
              </div>
              @if($comment->post)
              <div class="mb-3">
                <small class="text-muted d-block">Yazı</small>
                <a href="{{ route('admin.blog-posts.edit', $comment->post) }}" class="text-decoration-none">
                  {{ $comment->post->title }}
                </a>
              </div>
              @endif
            </div>
          </div>

          <!-- Actions -->
          <div class="admin-card mt-4">
            <div class="admin-card-header">
              <h5 class="admin-card-title"><i class="bi bi-gear"></i> İşlemler</h5>
            </div>
            <div class="admin-card-body d-flex flex-column gap-2">
              @if($comment->status !== \App\Enums\CommentStatus::Approved)
              <form method="POST" action="{{ route('admin.blog-comments.approve', $comment) }}">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-success w-100">
                      <i class="bi bi-check-lg"></i> Onayla
                  </button>
              </form>
              @endif
              @if($comment->status !== \App\Enums\CommentStatus::Rejected)
              <form method="POST" action="{{ route('admin.blog-comments.reject', $comment) }}">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-warning w-100">
                      <i class="bi bi-x-lg"></i> Reddet
                  </button>
              </form>
              @endif
              <form method="POST" action="{{ route('admin.blog-comments.destroy', $comment) }}" id="deleteCommentForm">
                  @csrf
                  @method('DELETE')
                  <button type="button" class="btn btn-outline-danger w-100" onclick="AdminModal.confirm({ title: 'Silme Onayı', message: 'Bu yorumu silmek istediğinize emin misiniz?', type: 'danger', confirmText: 'Evet, Sil', confirmIcon: 'bi bi-trash3' }).then(function(c){ if(c) document.getElementById('deleteCommentForm').submit(); })">
                      <i class="bi bi-trash"></i> Sil
                  </button>
              </form>
            </div>
          </div>
        </div>
      </div>
@endsection
