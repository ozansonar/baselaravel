@extends('layouts.app')

@section('title', __('site.newsletter.unsubscribe_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="section">
        <div class="container">
            <div class="empty-state mw-readable mx-auto">
                <div class="empty-state__icon">
                    <i class="fa-solid {{ $success ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
                </div>
                <h1 class="section__title mt-2">
                    {{ $success ? __('site.newsletter.unsubscribed') : __('site.newsletter.not_found') }}
                </h1>
                <p class="section__lead mx-auto mb-4">
                    @if($success)
                        {{ __('site.newsletter.unsubscribed_lead', ['email' => $email]) }}
                    @else
                        {{ __('site.newsletter.not_found_lead') }}
                    @endif
                </p>
                <a href="{{ localized_route('home') }}" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-house"></i> {{ __('site.nav.home') }}
                </a>
            </div>
        </div>
    </section>
@endsection
