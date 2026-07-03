{{-- Blog Comments Section --}}
<div class="blog-comments-section" id="comments">
    <h3 class="blog-comments-title">
        <i class="fa-solid fa-comments"></i>
        Yorumlar
        @if($commentCount > 0)
        <span class="blog-comments-count">{{ $commentCount }}</span>
        @endif
    </h3>

    {{-- Comment List --}}
    @if($comments->isNotEmpty())
    <div class="blog-comments-list">
        @foreach($comments as $comment)
        <div class="blog-comment">
            <div class="blog-comment-avatar">
                {{ $comment->initials() }}
            </div>
            <div class="blog-comment-content">
                <div class="blog-comment-header">
                    <strong class="blog-comment-author">{{ $comment->name }}</strong>
                    <time class="blog-comment-date" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->translatedFormat('d F Y, H:i') }}</time>
                </div>
                <p class="blog-comment-body">{{ $comment->body }}</p>
                <button type="button" class="blog-comment-reply-btn" data-comment-id="{{ $comment->id }}">
                    <i class="fa-solid fa-reply"></i> Yanıtla
                </button>

                {{-- Replies --}}
                @if($comment->approvedReplies->isNotEmpty())
                <div class="blog-comment-replies">
                    @foreach($comment->approvedReplies as $reply)
                    <div class="blog-comment blog-comment-reply">
                        <div class="blog-comment-avatar blog-comment-avatar-sm">
                            {{ $reply->initials() }}
                        </div>
                        <div class="blog-comment-content">
                            <div class="blog-comment-header">
                                <strong class="blog-comment-author">{{ $reply->name }}</strong>
                                <time class="blog-comment-date" datetime="{{ $reply->created_at->toIso8601String() }}">{{ $reply->created_at->translatedFormat('d F Y, H:i') }}</time>
                            </div>
                            <p class="blog-comment-body">{{ $reply->body }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Reply Form (hidden by default) --}}
                <div class="blog-comment-reply-form d-none" id="reply-form-{{ $comment->id }}">
                    <form class="blog-comment-form" data-parent-id="{{ $comment->id }}" novalidate>
                        @csrf
                        <input type="hidden" name="blog_post_id" value="{{ $post->id }}">
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <input type="text" name="name" class="blog-comment-input validate[required,minSize[2],maxSize[100]]" placeholder="Ad Soyad *">
                            </div>
                            <div class="col-sm-6">
                                <input type="email" name="email" class="blog-comment-input validate[required,custom[email]]" placeholder="E-posta *">
                            </div>
                            <div class="col-12">
                                <textarea name="body" class="blog-comment-input blog-comment-textarea validate[required,minSize[3],maxSize[2000]]" rows="3" placeholder="Yanıtınız *"></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn-custom btn-sm">
                                    <i class="fa-solid fa-paper-plane"></i> Yanıtla
                                </button>
                                <button type="button" class="btn-comment-cancel" onclick="cancelReply({{ $comment->id }})">
                                    İptal
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="blog-comments-empty">
        <i class="fa-regular fa-comment-dots"></i>
        <p>Henüz yorum yapılmamış. İlk yorumu siz yapın!</p>
    </div>
    @endif

    {{-- Comment Form --}}
    <div class="blog-comment-form-section" id="comment-form">
        <h4 class="blog-comment-form-title">Yorum Yap</h4>
        <form class="blog-comment-form" id="mainCommentForm" novalidate>
            @csrf
            <input type="hidden" name="blog_post_id" value="{{ $post->id }}">
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="blog-comment-label" for="comment-name">Ad Soyad</label>
                    <input type="text" name="name" id="comment-name" class="blog-comment-input validate[required,minSize[2],maxSize[100]]" placeholder="Adınız ve soyadınız">
                </div>
                <div class="col-sm-6">
                    <label class="blog-comment-label" for="comment-email">E-posta</label>
                    <input type="email" name="email" id="comment-email" class="blog-comment-input validate[required,custom[email]]" placeholder="E-posta adresiniz">
                    <small class="blog-comment-hint">E-posta adresiniz yayınlanmayacaktır.</small>
                </div>
                <div class="col-12">
                    <label class="blog-comment-label" for="comment-body">Yorumunuz</label>
                    <textarea name="body" id="comment-body" class="blog-comment-input blog-comment-textarea validate[required,minSize[3],maxSize[2000]]" rows="5" placeholder="Yorumunuzu buraya yazın..."></textarea>
                </div>
                <div class="col-12">
                    <x-recaptcha />
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-custom" id="commentSubmitBtn">
                        <i class="fa-solid fa-paper-plane"></i> Yorum Gönder
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
