@extends('layouts.app')

@section('title', __('site.faq.title'))
@section('meta_description', __('site.faq.meta_desc'))
@section('canonical', url()->current())

@section('content')

    {{-- ══════════ PAGE HERO ══════════ --}}
    <section class="page-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ __('site.faq.title') }}</li>
                </ol>
            </nav>
            <h1 class="page-hero__title">{{ __('site.faq.title') }}</h1>
            <p class="page-hero__lead">{{ __('site.faq.subtitle') }}</p>
        </div>
    </section>

    {{-- ══════════ FAQ ══════════ --}}
    <section class="section--tight faq" aria-labelledby="faq-heading">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="visually-hidden" id="faq-heading">{{ __('site.faq.list') }}</h2>

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
                            <p class="mb-0">{{ __('site.faq.empty') }}</p>
                        </div>
                    @endif

                    {{-- Contact CTA --}}
                    <div class="cta text-center mt-5">
                        <h2 class="mb-3">{{ __('site.faq.have_question') }}</h2>
                        <p class="cta__lead mb-4 mx-auto mw-readable">{{ __('site.faq.cta_lead') }}</p>
                        <a href="{{ route('contact') }}" class="btn btn-light btn-lg"><i class="fa-solid fa-paper-plane"></i> {{ __('site.contact.form_title') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
