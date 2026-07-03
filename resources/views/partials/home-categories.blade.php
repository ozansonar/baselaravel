{{-- Categories Section --}}
@if($categories->isNotEmpty())
@php
    $categoryIcons = [
        'sut-urunleri' => 'fa-solid fa-cheese',
        'yumurta'       => 'fa-solid fa-egg',
        'et-urunleri'   => 'fa-solid fa-drumstick-bite',
        'bal-recel'     => 'fa-solid fa-jar',
        'sebze-meyve'   => 'fa-solid fa-apple-whole',
        'kuru-gida'     => 'fa-solid fa-wheat-awn',
    ];
@endphp
<section class="categories-section" id="categories" aria-labelledby="categories-title">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fa-solid fa-th-large"></i> Kategoriler</span>
            <h2 class="section-title" id="categories-title">Ürün Kategorilerimiz</h2>
            <p class="section-subtitle">Çiftliğimizden sofranıza, doğal ve taze ürünler</p>
        </div>

        <div class="row g-4">
            @foreach($categories->where('parent_id', null) as $category)
            <div class="col-6 col-md-4 col-lg-2 animate-on-scroll">
                <a href="{{ route('products.index', $category->slug) }}" class="catcard">
                    <div class="catcard__icon-wrap">
                        @if($category->image)
                        <x-responsive-image :path="$category->image" :alt="$category->name" size="thumb" class="catcard__img" />
                        @else
                        <i class="{{ $categoryIcons[$category->slug] ?? 'fa-solid fa-seedling' }}"></i>
                        @endif
                    </div>
                    <h5 class="catcard__name">{{ $category->name }}</h5>
                    <span class="catcard__count">{{ $category->products_count }} Ürün</span>
                    <span class="catcard__arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
