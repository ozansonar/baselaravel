{{-- Blog Comments — needs $post, $comments, $commentCount from blog/show --}}
<div id="comments">

    <h2 class="section__title h4 mb-4">
        <i class="fa-solid fa-comments text-brand me-2"></i>{{ __('site.blog.comments') }}
        @if($commentCount > 0)
            <span class="text-muted fw-normal">({{ $commentCount }})</span>
        @endif
    </h2>

    {{-- Comment form --}}
    {{-- data-fv-no-lock: form AJAX ile gidiyor, düğmesini blog-comments.js
         yönetiyor. Kilitlense düğme istek bittikten sonra dönen spinner'da
         takılı kalırdı. --}}
    <div class="field-card mb-5">
        <h3 class="h5 mb-3">{{ __('site.blog.comment_title') }}</h3>
        <form id="blogCommentForm" method="POST" action="{{ route('blog-comments.store') }}"
              data-validate novalidate data-fv-no-lock>
            @csrf
            <input type="hidden" name="blog_post_id" value="{{ $post->id }}" data-fv-ignore>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label for="comment-name" class="form-label">{{ __('site.blog.comment_name') }} <span class="text-brand">*</span></label>
                    {{-- Sunucu bu alanda yalnız uzunluk arıyor (min:2, max:100);
                         custom[letters] eklenseydi sunucunun kabul ettiği bir adı
                         istemci reddeder, kullanıcı düzeltemeyeceği bir hataya
                         bakardı. --}}
                    <input type="text" id="comment-name" name="name" value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror"
                           data-validation-engine="validate[required,custom[letters],minSize[2],maxSize[100]]" data-fv-mask="letters"
                           placeholder="{{ __('site.blog.comment_name_ph') }}" autocomplete="name">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label for="comment-email" class="form-label">{{ __('site.blog.comment_email') }} <span class="text-brand">*</span></label>
                    {{-- type=text: biçim denetimi doğrulama motorunun, tarayıcının
                         kendi balonunun değil. --}}
                    <input type="text" id="comment-email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror"
                           data-validation-engine="validate[required,custom[email],maxSize[191]]"
                           placeholder="{{ __('site.blog.comment_email_ph') }}" autocomplete="email">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">{{ __('site.blog.comment_email_note') }}</small>
                </div>

                <div class="col-12">
                    <label for="comment-body" class="form-label">{{ __('site.blog.comment_body') }} <span class="text-brand">*</span></label>
                    <textarea id="comment-body" name="body" rows="5"
                              class="form-control @error('body') is-invalid @enderror"
                              data-validation-engine="validate[required,minSize[3],maxSize[2000]]"
                              placeholder="{{ __('site.blog.comment_body_ph') }}">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Panelden açılmışsa robot doğrulaması; kapalıysa hiç basılmıyor.
                     Site anahtarı da ayarlardan geliyor. --}}
                <div class="col-12">
                    <x-recaptcha />
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="blogCommentSubmit">
                        <i class="fa-solid fa-paper-plane"></i> {{ __('site.blog.comment_submit') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Comment list --}}
    @if($comments->isNotEmpty())
        <div>
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
        <div class="empty-state">
            <div class="empty-state__icon"><i class="fa-regular fa-comment-dots"></i></div>
            <p class="mb-0">{{ __('site.blog.comment_empty') }}</p>
        </div>
    @endif

</div>

@push('scripts')
{{-- Metinler window.SiteText'ten geliyor (partials/js-lang). reCAPTCHA'nın
     açık olup olmadığı sayfaya özel bir ayar, o yüzden burada kalıyor —
     istemci, kutu işaretlenmeden isteği hiç göndermesin. --}}
<script src="{{ versioned_asset('js/blog-comments.js') }}"
        data-recaptcha-enabled="{{ app(\App\Services\RecaptchaService::class)->isEnabled() ? '1' : '0' }}"
        defer></script>
@endpush
