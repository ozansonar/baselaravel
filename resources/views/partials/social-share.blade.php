@props(['url', 'title', 'description' => '', 'image' => ''])

@php
    $shareUrl = urlencode($url);
    $shareTitle = urlencode($title);
    $shareText = urlencode($title . ' — ' . $description);
    $shareImage = $image ? urlencode($image) : '';
@endphp

<div class="social-share">
    <span class="social-share__label"><i class="fa-solid fa-share-nodes"></i> {{ __('site.actions.share') }}</span>
    <div class="social-share__buttons">
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
           class="social-share__btn social-share__btn--facebook"
           target="_blank" rel="noopener noreferrer"
           aria-label="{{ __('site.misc.share_on', ['network' => 'Facebook']) }}"
           data-share-window="600x400">
            <i class="fa-brands fa-facebook-f"></i>
        </a>

        <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}"
           class="social-share__btn social-share__btn--x"
           target="_blank" rel="noopener noreferrer"
           aria-label="{{ __('site.misc.share_on', ['network' => 'X']) }}"
           data-share-window="600x400">
            <i class="fa-brands fa-x-twitter"></i>
        </a>

        <a href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}"
           class="social-share__btn social-share__btn--whatsapp"
           target="_blank" rel="noopener noreferrer"
           aria-label="{{ __('site.misc.share_on', ['network' => 'WhatsApp']) }}">
            <i class="fa-brands fa-whatsapp"></i>
        </a>

        <a href="https://t.me/share/url?url={{ $shareUrl }}&text={{ $shareTitle }}"
           class="social-share__btn social-share__btn--telegram"
           target="_blank" rel="noopener noreferrer"
           aria-label="{{ __('site.misc.share_on', ['network' => 'Telegram']) }}"
           data-share-window="600x400">
            <i class="fa-brands fa-telegram"></i>
        </a>

        @if($shareImage)
        <a href="https://pinterest.com/pin/create/button/?url={{ $shareUrl }}&media={{ $shareImage }}&description={{ $shareTitle }}"
           class="social-share__btn social-share__btn--pinterest"
           target="_blank" rel="noopener noreferrer"
           aria-label="{{ __('site.misc.share_on', ['network' => 'Pinterest']) }}"
           data-share-window="600x700">
            <i class="fa-brands fa-pinterest-p"></i>
        </a>
        @endif

        <button type="button"
                class="social-share__btn social-share__btn--copy js-copy-link"
                data-url="{{ $url }}"
                aria-label="{{ __('site.misc.copy_link_aria') }}">
            <i class="fa-solid fa-link"></i>
        </button>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
document.querySelectorAll('.js-copy-link').forEach(function (btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
        var url = btn.dataset.url;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                var icon = btn.querySelector('i');
                icon.className = 'fa-solid fa-check';
                setTimeout(function () { icon.className = 'fa-solid fa-link'; }, 2000);
            });
        } else {
            var input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            var icon = btn.querySelector('i');
            icon.className = 'fa-solid fa-check';
            setTimeout(function () { icon.className = 'fa-solid fa-link'; }, 2000);
        }
    });
});
</script>

@once
@push('scripts')
    <script src="{{ versioned_asset('js/share-window.js') }}" nonce="{{ csp_nonce() }}"></script>
@endpush
@endonce
