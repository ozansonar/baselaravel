@extends('layouts.app')

@section('title', 'Sıkça Sorulan Sorular')
@section('meta_description', 'Sıkça sorulan sorular ve yanıtları. Merak ettiğiniz konularda hızlı bilgi edinin.')
@section('canonical', url()->current())

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Anasayfa</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sıkça Sorulan Sorular</li>
                </ol>
            </nav>
            <h1 class="page-hero__title">Sıkça Sorulan Sorular</h1>
            <p class="page-hero__lead">Merak ettiğiniz her şeyin cevabı burada.</p>
        </div>
    </section>

    {{-- ══════════ FAQ ══════════ --}}
    <section class="section--tight faq" aria-labelledby="faq-heading">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="visually-hidden" id="faq-heading">Sıkça Sorulan Sorular Listesi</h2>

                    @if($faqs->isNotEmpty())
                        <div class="accordion" id="faqAccordion">
                            @foreach($faqs as $faq)
                                <div class="accordion-item">
                                    <h3 class="accordion-header" id="faq-heading-{{ $loop->index }}">
                                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                                                type="button" data-bs-toggle="collapse"
                                                data-bs-target="#faq-{{ $loop->index }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                                aria-controls="faq-{{ $loop->index }}">
                                            <i class="fa-solid fa-circle-question text-brand me-2"></i>{{ $faq->question }}
                                        </button>
                                    </h3>
                                    <div id="faq-{{ $loop->index }}"
                                         class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                         aria-labelledby="faq-heading-{{ $loop->index }}"
                                         data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">{!! nl2br(e($faq->answer)) !!}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="fa-regular fa-circle-question"></i></div>
                            <p class="mb-0">Henüz soru eklenmemiş.</p>
                        </div>
                    @endif

                    {{-- Contact CTA --}}
                    <div class="cta text-center mt-5">
                        <h2 class="mb-3">Sorunuz mu var?</h2>
                        <p class="cta__lead mb-4 mx-auto mw-readable">Cevabınızı bulamadıysanız bize doğrudan ulaşın, en kısa sürede yanıtlayalım.</p>
                        <a href="{{ route('contact') }}" class="btn btn-light btn-lg"><i class="fa-solid fa-paper-plane"></i> Bize Yazın</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
