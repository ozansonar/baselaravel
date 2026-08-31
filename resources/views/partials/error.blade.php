{{-- Reusable error content — vars: $code, $title, $message --}}
<section class="section">
    <div class="container">
        <div class="empty-state">
            <div class="display-1 fw-bold text-brand mb-2">{{ $code }}</div>
            <h1 class="section__title">{{ $title }}</h1>
            <p class="section__lead mx-auto mb-4">{{ $message }}</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ localized_route('home') }}" class="btn btn-primary btn-lg"><i class="fa-solid fa-house"></i> {{ __('site.nav.home') }}</a>
                <a href="{{ localized_route('blog.index') }}" class="btn btn-light btn-lg">{{ __('site.blog.title') }}</a>
            </div>
        </div>
    </div>
</section>
