@extends('layouts.app')

@section('title', \App\Models\Setting::getValue('site_name', config('app.name')) . ' | Çorum Doğal Köy Ürünleri')
@section('meta_description', \App\Models\Setting::getValue('site_name', config('app.name')) . ' - Çorum Büyük Palabıyık Köyü\'nden doğal köy ürünleri: taze süt, köy peyniri, tereyağı, yumurta, bal. Çorum\'dan sofranıza, hızlı kargo.')
@section('canonical', url('/'))
@if(\App\Models\Setting::getValue('og_image'))
@section('og_image', url(upload_url(\App\Models\Setting::getValue('og_image'))))
@endif

@section('content')

{{-- Hero Section --}}
@include('partials.home-hero')

{{-- Stats Section --}}
@include('partials.home-stats')

{{-- About Section --}}
@include('partials.home-about')

{{-- CTA Section --}}
<section class="cta-section" aria-labelledby="cta-title">
    <div class="cta-section__content">
        <h2 class="cta-section__title" id="cta-title">Doğallığı Tatmaya Hazır mısınız?</h2>
        <p class="cta-section__text">Çiftliğimizden sofranıza, en taze ve doğal ürünleri keşfedin. Hemen sipariş verin, kapınıza gelsin!</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="{{ route('contact') }}" class="cta-section__btn cta-section__btn--outline">
                <i class="fa-solid fa-phone me-2"></i>Bize Ulaşın
            </a>
        </div>
    </div>
</section>

{{-- Blog Section --}}
@include('partials.home-blog')

@endsection

@push('styles')
<style>
    /* Hero Section */
    .hero-section { min-height: 100vh; background: linear-gradient(180deg, var(--green-mist) 0%, var(--cream) 100%); position: relative; overflow: hidden; display: flex; align-items: center; padding-top: 140px; }
    .hero-bg-elements { position: absolute; width: 100%; height: 100%; top: 0; left: 0; pointer-events: none; }
    .floating-leaf { position: absolute; font-size: 2rem; color: var(--green-light); opacity: 0.4; animation: heroFloat 6s ease-in-out infinite; }
    .floating-leaf:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
    .floating-leaf:nth-child(2) { top: 25%; right: 15%; animation-delay: 1s; font-size: 1.5rem; }
    .floating-leaf:nth-child(3) { top: 60%; left: 5%; animation-delay: 2s; }
    .floating-leaf:nth-child(4) { top: 70%; right: 8%; animation-delay: 3s; font-size: 2.5rem; }
    .floating-leaf:nth-child(5) { top: 40%; left: 20%; animation-delay: 4s; font-size: 1.2rem; }
    @keyframes heroFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    .hero-content { position: relative; z-index: 10; }
    .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: white; padding: 8px 20px; border-radius: 30px; font-size: 0.9rem; color: var(--green-primary); font-weight: 500; box-shadow: var(--shadow-soft); margin-bottom: 25px; animation: fadeInUp 0.8s ease forwards; }
    .hero-badge i { color: var(--gold); }
    .hero-title { font-size: 4rem; font-weight: 700; color: var(--green-dark); line-height: 1.1; margin-bottom: 20px; animation: fadeInUp 0.8s ease 0.2s forwards; opacity: 0; }
    .hero-title span { color: var(--green-primary); position: relative; }
    .hero-title span::after { content: ''; position: absolute; bottom: 5px; left: 0; width: 100%; height: 12px; background: var(--green-pale); z-index: -1; border-radius: 5px; }
    .hero-description { font-size: 1.2rem; color: var(--brown-light); max-width: 500px; line-height: 1.8; margin-bottom: 35px; animation: fadeInUp 0.8s ease 0.4s forwards; opacity: 0; }
    .hero-buttons { display: flex; gap: 15px; flex-wrap: wrap; animation: fadeInUp 0.8s ease 0.6s forwards; opacity: 0; }
    .btn-outline-custom { border: 2px solid var(--green-primary); padding: 12px 28px; border-radius: 30px; color: var(--green-primary); font-weight: 600; background: transparent; transition: all 0.4s ease; }
    .btn-outline-custom:hover { background: var(--green-primary); color: white; transform: translateY(-3px); }

    /* Farm Scene */
    .farm-scene { position: relative; height: 500px; animation: scaleIn 1s ease 0.5s forwards; opacity: 0; }
    .farm-illustration { position: relative; width: 100%; height: 100%; overflow: hidden; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }

    /* Barn */
    .barn-svg { position: absolute; bottom: 50px; left: 50%; transform: translateX(-50%); width: 280px; z-index: 5; }

    /* Cows */
    .cow-container { position: absolute; bottom: 30px; z-index: 10; animation: cowWalkBounce 22s linear infinite; }
    .cow-svg { width: 140px; height: auto; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.12)); }
    @keyframes cowWalkBounce { 0% { left: -160px; transform: scaleX(1); } 45% { left: calc(100% - 160px); transform: scaleX(1); } 50% { left: calc(100% - 160px); transform: scaleX(-1); } 95% { left: -160px; transform: scaleX(-1); } 100% { left: -160px; transform: scaleX(1); } }
    .cow-container-2 { position: absolute; bottom: 40px; z-index: 8; animation: cowWalkBounce2 30s linear infinite; animation-delay: -12s; }
    .cow-container-2 .cow-svg { width: 105px; }
    @keyframes cowWalkBounce2 { 0% { right: -130px; transform: scaleX(-1); } 45% { right: calc(100% - 130px); transform: scaleX(-1); } 50% { right: calc(100% - 130px); transform: scaleX(1); } 95% { right: -130px; transform: scaleX(1); } 100% { right: -130px; transform: scaleX(-1); } }

    /* Chickens */
    .chicken-container { position: absolute; bottom: 45px; z-index: 9; }
    .chicken-1 { right: 18%; animation: chickenHop 3s ease-in-out infinite; }
    .chicken-2 { right: 28%; animation: chickenHop 3s ease-in-out infinite 0.5s; }
    .chicken-3 { right: 8%; animation: chickenHop 3s ease-in-out infinite 1s; }
    @keyframes chickenHop { 0%, 100% { transform: translateY(0) rotate(0deg); } 15% { transform: translateY(-15px) rotate(-5deg); } 30% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(0) rotate(5deg); } 65% { transform: translateY(-10px) rotate(0deg); } 80% { transform: translateY(0) rotate(-3deg); } }
    .chicken-svg { width: 45px; filter: drop-shadow(0 3px 5px rgba(0,0,0,0.1)); animation: chickenPeck 1s ease-in-out infinite; }
    .chicken-2 .chicken-svg { animation-delay: 0.3s; width: 40px; }
    .chicken-3 .chicken-svg { animation-delay: 0.7s; width: 38px; }
    @keyframes chickenPeck { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(10deg); } }

    /* Butterflies */
    .butterfly { position: absolute; font-size: 1.5rem; z-index: 15; animation: butterflyFly 12s ease-in-out infinite; }
    .butterfly-1 { top: 30%; left: 20%; animation-delay: 0s; color: #ff6b9d; }
    .butterfly-2 { top: 40%; left: 60%; animation-delay: 4s; color: #9b59b6; font-size: 1.2rem; }
    @keyframes butterflyFly { 0% { transform: translate(0, 0) rotate(0deg); } 25% { transform: translate(50px, -30px) rotate(10deg); } 50% { transform: translate(100px, 10px) rotate(-5deg); } 75% { transform: translate(30px, -20px) rotate(15deg); } 100% { transform: translate(0, 0) rotate(0deg); } }

    /* Birds */
    .bird { position: absolute; font-size: 1.2rem; color: var(--brown); z-index: 20; }
    .bird-1 { animation: birdFly1 8s linear infinite; top: 15%; }
    .bird-2 { animation: birdFly2 10s linear infinite; top: 22%; animation-delay: 3s; font-size: 1rem; }
    .bird-3 { animation: birdFly1 12s linear infinite; top: 18%; animation-delay: 6s; font-size: 0.9rem; }
    @keyframes birdFly1 { 0% { left: -30px; transform: translateY(0); } 25% { transform: translateY(-15px); } 50% { transform: translateY(5px); } 75% { transform: translateY(-10px); } 100% { left: 110%; transform: translateY(0); } }
    @keyframes birdFly2 { 0% { right: -30px; transform: translateY(0) scaleX(-1); } 25% { transform: translateY(-10px) scaleX(-1); } 50% { transform: translateY(8px) scaleX(-1); } 75% { transform: translateY(-5px) scaleX(-1); } 100% { right: 110%; transform: translateY(0) scaleX(-1); } }

    /* Grass */
    .grass-row { position: absolute; bottom: 0; left: 0; width: 100%; display: flex; gap: 4px; z-index: 6; }
    .grass-blade { width: 6px; height: 35px; background: linear-gradient(to top, var(--green-primary), var(--green-light)); border-radius: 50% 50% 0 0; transform-origin: bottom center; animation: grassWave 2s ease-in-out infinite; }
    .grass-blade:nth-child(even) { animation-delay: 0.5s; height: 28px; background: linear-gradient(to top, var(--green-dark), var(--green-primary)); }
    .grass-blade:nth-child(3n) { height: 32px; animation-delay: 0.25s; }
    @keyframes grassWave { 0%, 100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }

    /* Flowers */
    .flower { position: absolute; bottom: 25px; z-index: 7; animation: flowerSway 3s ease-in-out infinite; }
    .flower-1 { left: 15%; animation-delay: 0s; }
    .flower-2 { left: 35%; animation-delay: 0.5s; }
    .flower-3 { left: 75%; animation-delay: 1s; }
    .flower-4 { left: 55%; animation-delay: 1.5s; }
    @keyframes flowerSway { 0%, 100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }
    .flower svg { width: 25px; height: 25px; }

    /* Sun */
    .sun { position: absolute; top: 20px; right: 40px; width: 80px; height: 80px; background: linear-gradient(135deg, #ffd54f, #ffb300); border-radius: 50%; box-shadow: 0 0 60px rgba(255, 193, 7, 0.6), 0 0 100px rgba(255, 193, 7, 0.3); animation: sunPulse 4s ease-in-out infinite; z-index: 1; }
    .sun::before { content: ''; position: absolute; top: 50%; left: 50%; width: 120px; height: 120px; background: radial-gradient(circle, rgba(255,213,79,0.3) 0%, transparent 70%); transform: translate(-50%, -50%); animation: sunRays 3s ease-in-out infinite; }
    @keyframes sunPulse { 0%, 100% { transform: scale(1); box-shadow: 0 0 60px rgba(255, 193, 7, 0.6); } 50% { transform: scale(1.05); box-shadow: 0 0 80px rgba(255, 193, 7, 0.8); } }
    @keyframes sunRays { 0%, 100% { transform: translate(-50%, -50%) rotate(0deg) scale(1); } 50% { transform: translate(-50%, -50%) rotate(180deg) scale(1.2); } }

    /* Clouds */
    .cloud { position: absolute; background: white; border-radius: 50px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); z-index: 2; }
    .cloud::before, .cloud::after { content: ''; position: absolute; background: white; border-radius: 50%; }
    .cloud-1 { width: 100px; height: 40px; top: 50px; left: 5%; animation: cloudFloat1 15s ease-in-out infinite; }
    .cloud-1::before { width: 50px; height: 50px; top: -25px; left: 15px; }
    .cloud-1::after { width: 40px; height: 40px; top: -20px; right: 15px; }
    .cloud-2 { width: 80px; height: 30px; top: 90px; left: 25%; animation: cloudFloat2 18s ease-in-out infinite; }
    .cloud-2::before { width: 40px; height: 40px; top: -20px; left: 10px; }
    .cloud-2::after { width: 30px; height: 30px; top: -15px; right: 10px; }
    .cloud-3 { width: 70px; height: 25px; top: 30px; left: 45%; animation: cloudFloat1 20s ease-in-out infinite reverse; }
    .cloud-3::before { width: 35px; height: 35px; top: -18px; left: 8px; }
    .cloud-3::after { width: 25px; height: 25px; top: -12px; right: 8px; }
    @keyframes cloudFloat1 { 0%, 100% { transform: translateX(0) translateY(0); } 50% { transform: translateX(30px) translateY(-10px); } }
    @keyframes cloudFloat2 { 0%, 100% { transform: translateX(0) translateY(0); } 50% { transform: translateX(-20px) translateY(8px); } }

    /* Dust Particles */
    .dust-particle { position: absolute; width: 4px; height: 4px; background: rgba(212, 168, 75, 0.4); border-radius: 50%; animation: dustFloat 8s ease-in-out infinite; }
    .dust-1 { bottom: 60px; left: 20%; animation-delay: 0s; }
    .dust-2 { bottom: 80px; left: 40%; animation-delay: 2s; }
    .dust-3 { bottom: 70px; left: 60%; animation-delay: 4s; }
    .dust-4 { bottom: 90px; left: 80%; animation-delay: 6s; }
    @keyframes dustFloat { 0%, 100% { transform: translateY(0) translateX(0); opacity: 0; } 10% { opacity: 0.6; } 50% { transform: translateY(-40px) translateX(20px); opacity: 0.3; } 90% { opacity: 0; } }

    /* Stats Section */
    .stats-section { padding: 80px 0; background: white; position: relative; }
    .stats-section::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--green-pale), var(--green-primary), var(--green-pale)); }
    .stats-card { text-align: center; padding: 30px; border-radius: 20px; background: var(--green-mist); transition: all 0.4s ease; }
    .stats-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-hover); background: white; }
    .stats-card__icon { width: 70px; height: 70px; background: linear-gradient(135deg, var(--green-primary), var(--green-light)); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.8rem; color: white; transform: rotate(-5deg); transition: all 0.4s ease; }
    .stats-card:hover .stats-card__icon { transform: rotate(0deg) scale(1.1); }
    .stats-card__number { font-size: 2.5rem; font-weight: 700; color: var(--green-dark); font-family: 'Playfair Display', serif; }
    .stats-card__label { color: var(--brown-light); font-size: 1rem; }
    /* Categories Section */
    .categories-section { padding: 100px 0; background: var(--cream); }
    .catcard { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 35px 20px; background: white; border-radius: 25px; text-decoration: none; box-shadow: var(--shadow-soft); transition: all 0.5s ease; height: 100%; position: relative; overflow: hidden; }
    .catcard::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--green-primary), var(--green-light)); transform: scaleX(0); transition: transform 0.4s ease; }
    .catcard:hover { transform: translateY(-12px); box-shadow: var(--shadow-hover); }
    .catcard:hover::before { transform: scaleX(1); }
    .catcard__icon-wrap { width: 90px; height: 90px; background: linear-gradient(135deg, var(--green-mist), var(--green-pale)); border-radius: 25px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--green-primary); margin-bottom: 20px; transition: all 0.5s ease; }
    .catcard:hover .catcard__icon-wrap { background: linear-gradient(135deg, var(--green-primary), var(--green-light)); color: white; transform: rotate(-5deg) scale(1.1); border-radius: 20px; }
    .catcard__img { width: 60px; height: 60px; object-fit: cover; border-radius: 15px; }
    .catcard__name { font-size: 1.05rem; font-weight: 700; color: var(--green-dark); margin-bottom: 5px; transition: color 0.3s ease; }
    .catcard:hover .catcard__name { color: var(--green-primary); }
    .catcard__count { font-size: 0.8rem; color: var(--brown-light); margin-bottom: 15px; }
    .catcard__arrow { width: 36px; height: 36px; border-radius: 50%; background: var(--green-mist); display: flex; align-items: center; justify-content: center; color: var(--green-primary); font-size: 0.85rem; transition: all 0.4s ease; }
    .catcard:hover .catcard__arrow { background: var(--green-primary); color: white; transform: translateX(5px); }
    /* Products Section */
    .products-section { padding: 100px 0; background: var(--cream); position: relative; }
    .pcard { background: white; border-radius: 25px; overflow: hidden; box-shadow: var(--shadow-soft); transition: all 0.5s ease; height: 100%; }
    .pcard:hover { transform: translateY(-15px); box-shadow: var(--shadow-hover); }
    .pcard__image { height: 220px; background: var(--green-mist); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
    .pcard__image::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent); transform: rotate(45deg); transition: all 0.6s ease; opacity: 0; }
    .pcard:hover .pcard__image::before { animation: shine 0.6s ease; }
    @keyframes shine { from { transform: translateX(-100%) rotate(45deg); opacity: 1; } to { transform: translateX(100%) rotate(45deg); opacity: 1; } }
    .pcard__icon { font-size: 5rem; color: var(--green-primary); transition: all 0.4s ease; }
    .pcard:hover .pcard__icon { transform: scale(1.15) rotate(5deg); }
    .pcard__image img { width: 100%; height: 100%; object-fit: cover; }
    .pcard__badge { position: absolute; top: 15px; right: 15px; background: var(--gold); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; z-index: 1; }
    .pcard__badge--new { background: linear-gradient(135deg, #e74c3c, #ff6b6b); top: auto; bottom: 15px; }
    .pcard__content { padding: 25px; }
    .pcard__category { color: var(--green-light); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
    .pcard__title { font-size: 1.4rem; color: var(--green-dark); margin-bottom: 10px; }
    .pcard__title a { color: inherit; text-decoration: none; transition: color 0.3s ease; }
    .pcard__title a:hover { color: var(--green-primary); }
    .pcard__desc { color: var(--brown-light); font-size: 0.95rem; line-height: 1.6; margin-bottom: 20px; }
    .pcard__rating { display: flex; align-items: center; gap: 4px; margin-bottom: 15px; color: var(--gold, #d4a84b); font-size: 0.9rem; }
    .pcard__rating span { color: var(--brown-light); font-size: 0.85rem; margin-left: 4px; }
    .pcard__footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid var(--green-mist); }
    .pcard__price { font-size: 1.5rem; font-weight: 700; color: var(--green-primary); }
    .pcard__price span { font-size: 0.9rem; color: var(--brown-light); font-weight: 400; }
    .pcard__price-old { font-size: 0.9rem; color: var(--brown-light); font-weight: 400; text-decoration: line-through; margin-right: 5px; }
    .pcard__btn { width: 50px; height: 50px; border-radius: 50%; border: none; background: linear-gradient(135deg, var(--green-primary), var(--green-light)); color: white; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(74, 124, 67, 0.3); text-decoration: none; }
    .pcard__btn:hover { transform: scale(1.1) rotate(90deg); color: white; }
    /* About Section */
    .about-section { padding: 100px 0; background: white; position: relative; overflow: hidden; }
    .about-image-container { position: relative; padding: 20px; }
    .about-image-main { width: 100%; height: 450px; background: linear-gradient(135deg, var(--green-mist), var(--green-pale)); border-radius: 30px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
    .about-farm-svg { width: 80%; max-width: 350px; }
    .about-floating-card { position: absolute; background: white; padding: 20px; border-radius: 20px; box-shadow: var(--shadow-soft); animation: float 5s ease-in-out infinite; }
    .about-floating-card--card-1 { top: 10%; right: -10%; display: flex; align-items: center; gap: 15px; }
    .about-floating-card--card-2 { bottom: 10%; left: -5%; animation-delay: 2s; display: flex; align-items: center; gap: 15px; }
    .about-floating-card__icon { width: 50px; height: 50px; background: var(--green-pale); border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--green-primary); flex-shrink: 0; }
    .about-floating-card__text { font-weight: 600; color: var(--green-dark); }
    .about-floating-card__subtext { font-size: 0.85rem; color: var(--brown-light); }
    .about-content { padding-left: 40px; }
    .about-content__title { font-size: 2.8rem; color: var(--green-dark); margin-bottom: 20px; line-height: 1.2; }
    .about-content__text { color: var(--brown-light); line-height: 1.9; margin-bottom: 20px; }
    .about-features { margin: 30px 0; }
    .about-features__item { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
    .about-features__icon { width: 40px; height: 40px; background: var(--green-mist); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--green-primary); flex-shrink: 0; }
    .about-features__text { font-weight: 500; color: var(--green-dark); }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
    /* Testimonials Section */
    .testimonials-section { padding: 100px 0; background: linear-gradient(180deg, var(--green-mist) 0%, var(--cream) 100%); }
    .testimonial-card { background: white; padding: 40px; border-radius: 25px; box-shadow: var(--shadow-soft); position: relative; transition: all 0.4s ease; height: 100%; }
    .testimonial-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-hover); }
    .testimonial-card__quote { position: absolute; top: 20px; right: 30px; font-size: 4rem; color: var(--green-pale); font-family: Georgia, serif; line-height: 1; }
    .testimonial-card__text { font-size: 1.1rem; color: var(--brown); line-height: 1.8; margin-bottom: 25px; font-style: italic; }
    .testimonial-card__author { display: flex; align-items: center; gap: 15px; }
    .testimonial-card__avatar { width: 60px; height: 60px; background: linear-gradient(135deg, var(--green-primary), var(--green-light)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: 600; flex-shrink: 0; }
    .testimonial-card__name { font-weight: 600; color: var(--green-dark); }
    .testimonial-card__role { font-size: 0.9rem; color: var(--brown-light); }
    .testimonial-card__rating { color: var(--gold); margin-top: 5px; }

    /* Google Reviews Section */
    .google-reviews-section { padding: 100px 0; background: white; }
    .google-reviews-summary { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; }
    .google-reviews-stars { color: var(--gold); font-size: 1.3rem; }
    .google-reviews-avg { font-size: 1.4rem; font-weight: 700; color: var(--green-dark); }
    .google-reviews-count { font-size: 0.95rem; color: var(--brown-light); }
    .google-review-card { background: var(--cream); padding: 30px; border-radius: 20px; transition: all 0.4s ease; height: 100%; display: flex; flex-direction: column; }
    .google-review-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); }
    .google-review-card__header { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
    .google-review-card__avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--green-primary), var(--green-light)); color: white; font-weight: 600; font-size: 1.1rem; flex-shrink: 0; }
    .google-review-card__avatar img { width: 100%; height: 100%; object-fit: cover; }
    .google-review-card__author { flex: 1; min-width: 0; }
    .google-review-card__name { font-weight: 600; color: var(--green-dark); font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .google-review-card__time { font-size: 0.8rem; color: var(--brown-light); }
    .google-review-card__google-icon { color: #4285F4; font-size: 1.3rem; flex-shrink: 0; }
    .google-review-card__rating { color: var(--gold); margin-bottom: 12px; font-size: 0.95rem; }
    .google-review-card__rating .fa-star-empty { color: #ddd; }
    .google-review-card__text { color: var(--brown); font-size: 0.95rem; line-height: 1.7; flex: 1; }
    .google-reviews-link { display: inline-flex; align-items: center; gap: 5px; color: var(--green-primary); font-weight: 600; text-decoration: none; padding: 12px 30px; border: 2px solid var(--green-primary); border-radius: 30px; transition: all 0.3s ease; }
    .google-reviews-link:hover { background: var(--green-primary); color: white; transform: translateY(-2px); }

    /* YouTube Section */
    .youtube-section { padding: 100px 0; background: var(--cream); }
    .youtube-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: var(--shadow-soft); transition: all 0.4s ease; height: 100%; cursor: pointer; }
    .youtube-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); }
    .youtube-card__thumb { position: relative; aspect-ratio: 16/9; overflow: hidden; }
    .youtube-card__img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
    .youtube-card:hover .youtube-card__img { transform: scale(1.05); }
    .youtube-card__play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; background: rgba(255,0,0,0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; transition: all 0.3s ease; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .youtube-card:hover .youtube-card__play { background: #ff0000; transform: translate(-50%, -50%) scale(1.1); }
    .youtube-card__duration { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 500; }
    .youtube-card__body { padding: 20px; }
    .youtube-card__title { font-size: 1.05rem; color: var(--green-dark); margin-bottom: 10px; line-height: 1.5; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .youtube-card__meta { display: flex; gap: 15px; font-size: 0.85rem; color: var(--brown-light); flex-wrap: wrap; }
    .youtube-card__meta i { color: var(--green-primary); margin-right: 4px; }
    .youtube-channel-link { display: inline-flex; align-items: center; gap: 5px; color: #ff0000; font-weight: 600; text-decoration: none; padding: 12px 30px; border: 2px solid #ff0000; border-radius: 30px; transition: all 0.3s ease; }
    .youtube-channel-link:hover { background: #ff0000; color: white; transform: translateY(-2px); }

    /* YouTube Modal */
    .youtube-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; align-items: center; justify-content: center; }
    .youtube-modal.active { display: flex; }
    .youtube-modal__backdrop { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); }
    .youtube-modal__content { position: relative; width: 90%; max-width: 900px; z-index: 1; }
    .youtube-modal__close { position: absolute; top: -45px; right: 0; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 5px 10px; transition: opacity 0.3s; }
    .youtube-modal__close:hover { opacity: 0.7; }
    .youtube-modal__player { position: relative; padding-bottom: 56.25%; height: 0; border-radius: 12px; overflow: hidden; }
    .youtube-modal__player iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

    /* Blog Section */
    .blog-section { padding: 100px 0; background: var(--cream); }
    .blog-card { background: white; border-radius: 25px; overflow: hidden; box-shadow: var(--shadow-soft); transition: all 0.5s ease; height: 100%; }
    .blog-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-hover); }
    .blog-card__image { height: 200px; background: var(--green-mist); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
    .blog-card__image i { font-size: 4rem; color: var(--green-primary); opacity: 0.5; }
    .blog-card__image img { width: 100%; height: 100%; object-fit: cover; }
    .blog-card__category { position: absolute; top: 15px; left: 15px; background: var(--green-primary); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
    .blog-card__content { padding: 25px; }
    .blog-card__meta { display: flex; gap: 15px; margin-bottom: 12px; font-size: 0.85rem; color: var(--brown-light); }
    .blog-card__meta i { color: var(--green-primary); margin-right: 5px; }
    .blog-card__title { font-size: 1.3rem; color: var(--green-dark); margin-bottom: 12px; line-height: 1.4; transition: color 0.3s ease; }
    .blog-card:hover .blog-card__title { color: var(--green-primary); }
    .blog-card__excerpt { color: var(--brown-light); font-size: 0.95rem; line-height: 1.7; margin-bottom: 20px; }
    .blog-card__link { display: inline-flex; align-items: center; gap: 8px; color: var(--green-primary); font-weight: 600; text-decoration: none; transition: gap 0.3s ease; }
    .blog-card__link:hover { gap: 12px; color: var(--green-dark); }

    /* CTA Section */
    .cta-section { padding: 100px 0; background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-primary) 100%); position: relative; overflow: hidden; }
    .cta-section::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
    .cta-section__content { position: relative; z-index: 1; text-align: center; }
    .cta-section__title { font-size: 3rem; color: white; margin-bottom: 20px; }
    .cta-section__text { color: rgba(255,255,255,0.85); font-size: 1.2rem; max-width: 600px; margin: 0 auto 40px; }
    .cta-section__btn { display: inline-flex; align-items: center; padding: 18px 35px; border-radius: 30px; font-weight: 600; font-size: 1rem; text-decoration: none; transition: all 0.4s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    .cta-section__btn--primary { background: var(--gold); color: white; border: 2px solid var(--gold); }
    .cta-section__btn--primary:hover { transform: translateY(-3px); background: #e6b84d; color: white; box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
    .cta-section__btn--outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.5); }
    .cta-section__btn--outline:hover { transform: translateY(-3px); background: rgba(255,255,255,0.15); color: white; border-color: white; box-shadow: 0 15px 40px rgba(0,0,0,0.3); }

    @media (max-width: 991px) {
        .hero-section { padding-top: 140px; }
        .hero-title { font-size: 3rem; }
        .farm-scene { height: 350px; }
        .about-content { padding-left: 0; margin-top: 40px; }
    }
    @media (max-width: 767px) {
        .hero-title { font-size: 2.2rem; }
        .hero-description { font-size: 1rem; }
        .farm-scene { height: 280px; }
        .barn-svg { width: 180px; }
        .cow-svg { width: 90px !important; }
        .stats-section { padding: 40px 0; }
        .stats-card__number { font-size: 1.8rem; }
        .categories-section { padding: 60px 0; }
        .catcard { padding: 25px 15px; }
        .catcard__icon-wrap { width: 70px; height: 70px; font-size: 2rem; }
        .about-section { padding: 60px 0; }
        .about-content__title { font-size: 2rem; }
        .about-floating-card { display: none; }
        .products-section { padding: 60px 0; }
        .blog-section { padding: 60px 0; }
        .testimonials-section { padding: 60px 0; }
        .testimonial-card { padding: 25px; }
        .google-reviews-section { padding: 60px 0; }
        .google-review-card { padding: 20px; }
        .youtube-section { padding: 60px 0; }
        .youtube-card__body { padding: 15px; }
        .youtube-card__play { width: 50px; height: 50px; font-size: 1.1rem; }
        .cta-section { padding: 60px 0; }
        .cta-section__title { font-size: 2rem; }
        .cta-section__text { font-size: 1rem; }
        .cta-section__btn { padding: 14px 25px; font-size: 0.9rem; }
    }
</style>
@endpush
