{{-- Testimonials Section --}}
@if($testimonials->isNotEmpty())
<section class="testimonials-section" id="testimonials" aria-labelledby="testimonials-title">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fa-solid fa-comments"></i> Müşteri Yorumları</span>
            <h2 class="section-title" id="testimonials-title">Ne Diyorlar?</h2>
            <p class="section-subtitle">Binlerce mutlu müşterimizden bazılarının görüşleri</p>
        </div>

        <div class="row g-4">
            @foreach($testimonials as $testimonial)
            <div class="col-lg-4 animate-on-scroll">
                <div class="testimonial-card">
                    <div class="testimonial-card__quote">"</div>
                    <p class="testimonial-card__text">{{ $testimonial->body }}</p>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar">{{ $testimonial->initials }}</div>
                        <div>
                            <div class="testimonial-card__name">{{ $testimonial->name }}</div>
                            <div class="testimonial-card__role">{{ $testimonial->location }}</div>
                            <div class="testimonial-card__rating">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($testimonial->rating))
                                    <i class="fa-solid fa-star"></i>
                                    @elseif($i - $testimonial->rating <= 0.5 && $i - $testimonial->rating > 0)
                                    <i class="fa-solid fa-star-half-stroke"></i>
                                    @else
                                    <i class="fa-regular fa-star"></i>
                                    @endif
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
