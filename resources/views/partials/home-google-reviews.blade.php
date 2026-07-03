{{-- Google Reviews Section --}}
@if($googleReviews->isNotEmpty())
<section class="google-reviews-section" id="googleReviews" aria-labelledby="google-reviews-title">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fa-brands fa-google"></i> Google Yorumları</span>
            <h2 class="section-title" id="google-reviews-title">Google'da Ne Diyorlar?</h2>
            @if($googleStats['count'] > 0)
            <div class="google-reviews-summary">
                <div class="google-reviews-stars">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= floor($googleStats['average']))
                        <i class="fa-solid fa-star"></i>
                        @elseif($i - $googleStats['average'] <= 0.5 && $i - $googleStats['average'] > 0)
                        <i class="fa-solid fa-star-half-stroke"></i>
                        @else
                        <i class="fa-regular fa-star"></i>
                        @endif
                    @endfor
                </div>
                <span class="google-reviews-avg">{{ $googleStats['average'] }}</span>
                <span class="google-reviews-count">/ {{ $googleStats['count'] }} yorum</span>
            </div>
            @endif
        </div>

        <div class="row g-4">
            @foreach($googleReviews as $review)
            <div class="col-lg-4 col-md-6 animate-on-scroll">
                <div class="google-review-card">
                    <div class="google-review-card__header">
                        <div class="google-review-card__avatar">
                            @if($review->author_photo_url)
                            <img src="{{ $review->author_photo_url }}" alt="{{ $review->author_name }}" class="img-fluid" loading="lazy" decoding="async" referrerpolicy="no-referrer" width="44" height="44">
                            @else
                            {{ $review->initials }}
                            @endif
                        </div>
                        <div class="google-review-card__author">
                            <div class="google-review-card__name">{{ $review->author_name }}</div>
                            <div class="google-review-card__time">{{ $review->relative_time }}</div>
                        </div>
                        <div class="google-review-card__google-icon">
                            <i class="fa-brands fa-google"></i>
                        </div>
                    </div>
                    <div class="google-review-card__rating">
                        @for($i = 1; $i <= 5; $i++)
                        <i class="fa-solid fa-star{{ $i <= $review->rating ? '' : ' fa-star-empty' }}"></i>
                        @endfor
                    </div>
                    @if($review->text)
                    <p class="google-review-card__text">{{ Str::limit($review->text, 200) }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-4">
            <a href="https://maps.app.goo.gl/4G7HesbJEaN2jdeW9" target="_blank" rel="noopener noreferrer" class="google-reviews-link">
                <i class="fa-brands fa-google me-2"></i>Google Maps'te Tüm Yorumları Gör
                <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
@endif
