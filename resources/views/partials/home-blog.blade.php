{{-- Blog Section --}}
@if($blogPosts->isNotEmpty())
<section class="blog-section" id="blog" aria-labelledby="blog-title">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fa-solid fa-newspaper"></i> Çiftlik Günlüğü</span>
            <h2 class="section-title" id="blog-title">Blog Yazılarımız</h2>
            <p class="section-subtitle">Çiftlik hayatı, sağlıklı beslenme ve doğal ürünler hakkında güncel yazılarımız</p>
        </div>

        <div class="row g-4">
            @foreach($blogPosts as $post)
            <div class="col-md-6 col-lg-4 animate-on-scroll">
                <article class="blog-card">
                    <div class="blog-card__image">
                        @if($post->image)
                        <x-responsive-image :path="$post->image" :alt="$post->title" size="md" />
                        @else
                        <i class="{{ $post->category?->icon ?? 'fa-solid fa-pen-nib' }}"></i>
                        @endif
                        <span class="blog-card__category">{{ $post->category?->name }}</span>
                    </div>
                    <div class="blog-card__content">
                        <div class="blog-card__meta">
                            <time datetime="{{ $post->published_at->format('Y-m-d') }}"><i class="fa-solid fa-calendar-alt"></i> {{ $post->published_at->translatedFormat('d F Y') }}</time>
                            <span><i class="fa-solid fa-user"></i> {{ $post->author?->name ?: 'Orhan Gazi SONAR' }}</span>
                        </div>
                        <h3 class="blog-card__title">{{ $post->title }}</h3>
                        <p class="blog-card__excerpt">{{ Str::limit($post->excerpt, 120) }}</p>
                        <a href="{{ route('blog.show', [$post->category->slug, $post->slug]) }}" class="blog-card__link">
                            Devamını Oku <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-5">
            <a href="{{ route('blog.index') }}" class="btn-custom btn-lg">
                <i class="fa-solid fa-book-open me-2"></i>Tüm Yazıları Gör
            </a>
        </div>
    </div>
</section>
@endif
