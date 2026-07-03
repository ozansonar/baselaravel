@extends('layouts.app')

@section('title', 'Sıkça Sorulan Sorular | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', \App\Models\Setting::getValue('site_name', config('app.name')) . ' hakkında sıkça sorulan sorular. Sipariş, kargo, iade ve ürünlerimiz hakkında merak ettikleriniz.')
@section('canonical', route('faq'))
@if(\App\Models\Setting::getValue('og_image'))
@section('og_image', url(upload_url(\App\Models\Setting::getValue('og_image'))))
@endif

@push('json-ld')
@if($faqs->isNotEmpty())
@php
    $faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqs->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq->question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq->answer,
            ],
        ])->values()->all(),
        'breadcrumb' => [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Sıkça Sorulan Sorular'],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endpush

@section('content')

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Sıkça Sorulan Sorular</span>
        </nav>
        <h1 class="page-title">Sıkça Sorulan Sorular</h1>
        <p class="page-subtitle">Merak ettiğiniz her şeyin cevabı burada.</p>
    </div>
</header>

{{-- FAQ Accordion --}}
<section class="py-5" aria-labelledby="faq-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="visually-hidden" id="faq-heading">Sıkça Sorulan Sorular Listesi</h2>

                @if($faqs->isNotEmpty())
                <div class="accordion" id="faqAccordion">
                    @foreach($faqs as $i => $faq)
                    <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                        <h3 class="accordion-header">
                            <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }} fw-semibold"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq{{ $faq->id }}"
                                    aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                    aria-controls="faq{{ $faq->id }}">
                                <i class="fa-solid fa-circle-question icon-green me-2"></i>
                                {{ $faq->question }}
                            </button>
                        </h3>
                        <div id="faq{{ $faq->id }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                             data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-brown-light">
                                {{ $faq->answer }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fa-solid fa-question-circle fa-3x text-muted mb-3"></i>
                    <p class="text-brown-light">Henüz soru eklenmemiş.</p>
                </div>
                @endif

                {{-- Contact CTA --}}
                <div class="info-box text-center mt-5 p-5">
                    <i class="fa-solid fa-headset fa-3x icon-green mb-3"></i>
                    <h3 class="mb-3">Cevabınızı bulamadınız mı?</h3>
                    <p class="text-brown-light mb-4">Bize doğrudan ulaşabilirsiniz, en kısa sürede yanıtlayalım.</p>
                    <a href="{{ route('contact') }}" class="btn-custom">
                        <i class="fa-solid fa-paper-plane"></i> Bize Yazın
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
    .accordion-button:not(.collapsed) { background: var(--green-mist); color: var(--green-dark); }
    .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(74, 124, 67, 0.25); }
</style>
@endpush
