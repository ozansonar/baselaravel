{{-- Blog Comments — needs $post, $comments, $commentCount from blog/show --}}
<div id="comments">

    <h2 class="section__title h4 mb-4">
        <i class="fa-solid fa-comments text-brand me-2"></i>Yorumlar
        @if($commentCount > 0)
            <span class="text-muted fw-normal">({{ $commentCount }})</span>
        @endif
    </h2>

    {{-- Comment list --}}
    @if($comments->isNotEmpty())
        <div class="mb-5">
            @foreach($comments as $c)
                <div class="comment">
                    <div class="comment__avatar">{{ mb_strtoupper(mb_substr($c->name, 0, 1)) }}</div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="comment__name">{{ $c->name }}</span>
                            <span class="comment__date">{{ $c->created_at->translatedFormat('d M Y') }}</span>
                        </div>
                        <p class="mb-0">{{ $c->body }}</p>

                        {{-- Replies --}}
                        @if($c->approvedReplies->isNotEmpty())
                            @foreach($c->approvedReplies as $reply)
                                <div class="comment comment--reply">
                                    <div class="comment__avatar">{{ mb_strtoupper(mb_substr($reply->name, 0, 1)) }}</div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <span class="comment__name">{{ $reply->name }}</span>
                                            <span class="comment__date">{{ $reply->created_at->translatedFormat('d M Y') }}</span>
                                        </div>
                                        <p class="mb-0">{{ $reply->body }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state mb-4">
            <div class="empty-state__icon"><i class="fa-regular fa-comment-dots"></i></div>
            <p class="mb-0">Henüz yorum yapılmamış. İlk yorumu siz yapın!</p>
        </div>
    @endif

    {{-- Comment form --}}
    <div class="field-card">
        <h3 class="h5 mb-3">Yorum Yap</h3>
        <form id="blogCommentForm" method="POST" action="{{ route('blog-comments.store') }}" novalidate>
            @csrf
            <input type="hidden" name="blog_post_id" value="{{ $post->id }}">

            <div class="row g-3">
                <div class="col-sm-6">
                    <label for="comment-name" class="form-label">Ad Soyad</label>
                    <input type="text" id="comment-name" name="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror" placeholder="Adınız ve soyadınız" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label for="comment-email" class="form-label">E-posta</label>
                    <input type="email" id="comment-email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror" placeholder="E-posta adresiniz" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">E-posta adresiniz yayınlanmayacaktır.</small>
                </div>

                <div class="col-12">
                    <label for="comment-body" class="form-label">Yorumunuz</label>
                    <textarea id="comment-body" name="body" rows="5"
                              class="form-control @error('body') is-invalid @enderror" placeholder="Yorumunuzu buraya yazın..." required>{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <x-recaptcha />
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="blogCommentSubmit">
                        <i class="fa-solid fa-paper-plane"></i> Yorum Gönder
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
(function () {
    var form = document.getElementById('blogCommentForm');
    if (!form) return;

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var btn  = document.getElementById('blogCommentSubmit');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gönderiliyor...';

        var data = {};
        new FormData(form).forEach(function (value, key) {
            if (key !== '_token') { data[key] = value; }
        });
        if (!data['g-recaptcha-response'] && typeof grecaptcha !== 'undefined') {
            data['g-recaptcha-response'] = grecaptcha.getResponse();
        }

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;

            if (res.success) {
                form.reset();
                if (typeof grecaptcha !== 'undefined') { grecaptcha.reset(); }
                if (typeof showResultModal === 'function') {
                    showResultModal('success', res.message);
                }
            } else if (res.errors) {
                var messages = Object.keys(res.errors).map(function (k) { return res.errors[k][0]; });
                if (typeof showResultModal === 'function') {
                    showResultModal('error', messages.join('<br>'));
                }
            } else {
                if (typeof showResultModal === 'function') {
                    showResultModal('error', res.message || 'Bir hata oluştu.');
                }
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (typeof showResultModal === 'function') {
                showResultModal('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
            }
        });
    });
})();
</script>
