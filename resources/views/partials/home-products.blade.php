{{-- Products Section --}}
@if($featuredProducts->isNotEmpty())
<section class="products-section" id="products" aria-labelledby="products-title">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fa-solid fa-star"></i> Doğal Ürünlerimiz</span>
            <h2 class="section-title" id="products-title">Çiftlikten Sofraya</h2>
            <p class="section-subtitle">Tamamen doğal yöntemlerle üretilen, katkı maddesi içermeyen organik ürünlerimizi keşfedin</p>
        </div>

        <div class="row g-4">
            @foreach($featuredProducts as $product)
            <div class="col-md-6 col-lg-4 animate-on-scroll">
                <article class="pcard">
                    <div class="pcard__image">
                        @if($product->is_featured)
                        <span class="pcard__badge">Öne Çıkan</span>
                        @endif
                        @if($product->is_new)
                        <span class="pcard__badge pcard__badge--new">Yeni</span>
                        @endif
                        @if($product->cover_image)
                        <x-responsive-image :path="$product->cover_image" :alt="$product->name" size="md" />
                        @else
                        <i class="fa-solid fa-box-open pcard__icon"></i>
                        @endif
                    </div>
                    <div class="pcard__content">
                        <div class="pcard__category">{{ $product->category?->name }}</div>
                        <h3 class="pcard__title">
                            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
                        </h3>
                        @if($product->short_description)
                        <p class="pcard__desc">{{ Str::limit($product->short_description, 100) }}</p>
                        @endif
                        @if($product->reviews_count > 0)
                        <div class="pcard__rating">
                            @php $avg = round($product->reviews_avg * 2) / 2; @endphp
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= floor($avg))
                                    <i class="fa-solid fa-star"></i>
                                @elseif($i - 0.5 <= $avg)
                                    <i class="fa-solid fa-star-half-stroke"></i>
                                @else
                                    <i class="fa-regular fa-star"></i>
                                @endif
                            @endfor
                            <span>{{ number_format($product->reviews_avg, 1) }} ({{ $product->reviews_count }})</span>
                        </div>
                        @endif
                        <div class="pcard__footer">
                            <div class="pcard__price">
                                @if($product->hasDiscount())
                                <span class="pcard__price-old">{{ number_format($product->discounted_price, 0) }} ₺</span>
                                @endif
                                {{ number_format($product->price, 0) }} ₺
                                <span>/ {{ $product->unit }}</span>
                            </div>
                            <a href="{{ route('products.show', $product->slug) }}" class="pcard__btn"
                               aria-label="{{ $product->name }} detayını gör">
                                <i class="fa-solid fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
            @endforeach
        </div>

        <div class="text-center mt-5">
            <a href="{{ route('products.all') }}" class="btn-custom btn-lg">
                <i class="fa-solid fa-th-large me-2"></i>Tüm Ürünleri Gör
            </a>
        </div>
    </div>
</section>
@endif
