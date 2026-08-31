@extends('layouts.app')

@section('title', __('site.notifications.title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.notifications.desc'))
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
                    <span class="section__eyebrow"><i class="fa-solid fa-bell"></i> {{ __('site.auth.account') }}</span>
                    <h1 class="section__title">{{ __('site.notifications.title') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.notifications.lead') }}</p>

                    <div class="field-card">
                        <form action="{{ route('account.notifications.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            {{-- Bülten: kaynağı abone tablosu --}}
                            <div class="form-check form-switch pref-switch">
                                <input type="hidden" name="newsletter" value="0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="prefNewsletter" name="newsletter" value="1"
                                       @checked($newsletter) data-fv-ignore>
                                <label class="form-check-label" for="prefNewsletter">
                                    <span class="pref-switch__title">{{ __('site.notifications.newsletter') }}</span>
                                    <span class="pref-switch__hint">{{ __('site.notifications.newsletter_hint') }}</span>
                                </label>
                            </div>

                            @foreach(\App\Enums\NotificationPreference::cases() as $type)
                                <div class="form-check form-switch pref-switch">
                                    <input type="hidden" name="preferences[{{ $type->value }}]" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="pref{{ $type->value }}" name="preferences[{{ $type->value }}]" value="1"
                                           @checked($preferences[$type->value]) data-fv-ignore>
                                    <label class="form-check-label" for="pref{{ $type->value }}">
                                        <span class="pref-switch__title">{{ $type->label() }}</span>
                                        <span class="pref-switch__hint">{{ $type->description() }}</span>
                                    </label>
                                </div>
                            @endforeach

                            <hr class="divider my-4">

                            <p class="text-muted small">{{ __('site.notifications.always_on') }}</p>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> {{ __('site.actions.save') }}
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
