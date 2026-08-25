@extends('layouts.app')

@section('title', __('site.auth.account') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.account.dashboard_desc'))
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="row g-4">

                {{-- Sidebar --}}
                <div class="col-lg-3">
                    @include('account.partials.sidebar', ['user' => $user])
                </div>

                {{-- Content --}}
                <div class="col-lg-9">
                    <span class="section__eyebrow"><i class="fa-solid fa-house-user"></i> {{ __('site.auth.account') }}</span>
                    <h1 class="section__title">{{ __('site.account.welcome', ['name' => $user->first_name]) }}</h1>
                    <p class="section__lead mb-4">{{ __('site.account.dashboard_lead') }}</p>

                    <div class="field-card">
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="contact-info__label">{{ __('site.account.full_name') }}</div>
                                <div class="contact-info__value">{{ $user->full_name }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">{{ __('site.contact.email') }}</div>
                                <div class="contact-info__value">{{ $user->email }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">{{ __('site.contact.phone') }}</div>
                                <div class="contact-info__value">{{ $user->phone ?: '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">{{ __('site.account.member_since') }}</div>
                                <div class="contact-info__value">{{ $user->created_at->translatedFormat('d M Y') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">{{ __('site.account.role') }}</div>
                                <div class="contact-info__value">
                                    @forelse($user->roles as $role)
                                        {{ $role->name }}@if(!$loop->last), @endif
                                    @empty
                                        {{ __('site.account.member') }}
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <hr class="divider my-4">

                        <a href="{{ route('account.profile') }}" class="btn btn-primary">
                            <i class="fa-solid fa-user-pen"></i> {{ __('site.account.edit_profile') }}
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
