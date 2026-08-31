@extends('layouts.app')

@section('title', __('site.devices.title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.devices.desc'))
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
                    <span class="section__eyebrow"><i class="fa-solid fa-laptop-mobile"></i> {{ __('site.auth.account') }}</span>
                    <h1 class="section__title">{{ __('site.devices.title') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.devices.lead') }}</p>

                    {{-- Tarayıcı oturumları --}}
                    <div class="field-card mb-4">
                        <h2 class="h5 fw-bold mb-1">{{ __('site.devices.browsers') }}</h2>
                        <p class="text-muted small mb-4">{{ __('site.devices.browsers_hint') }}</p>

                        @if(! $sessionsSupported)
                            <p class="text-muted mb-0">{{ __('site.devices.browsers_unavailable') }}</p>
                        @else
                            <ul class="device-list">
                                @forelse($sessions as $session)
                                    <li class="device-list__item">
                                        <span class="device-list__icon"><i class="fa-solid fa-display"></i></span>

                                        <div class="device-list__body">
                                            <div class="device-list__name">
                                                {{ $session['agent'] ?? __('site.devices.unknown_agent') }}
                                                @if($session['current'])
                                                    <span class="badge text-bg-success ms-1">{{ __('site.devices.this_device') }}</span>
                                                @endif
                                            </div>
                                            <div class="device-list__meta">
                                                <span><i class="fa-solid fa-location-dot"></i> {{ $session['ip'] ?? '—' }}</span>
                                                <span><i class="fa-regular fa-clock"></i> {{ __('site.devices.last_active', ['time' => $session['last_active']->diffForHumans()]) }}</span>
                                            </div>
                                        </div>

                                        @unless($session['current'])
                                            <form action="{{ route('account.devices.sessions.destroy', $session['id']) }}" method="POST"
                                                  class="device-list__action"
                                                  data-confirm="{{ __('site.devices.confirm_session') }}"
                                                  data-confirm-title="{{ __('site.devices.revoke') }}"
                                                  data-confirm-btn="{{ __('site.devices.revoke') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fa-solid fa-xmark"></i> {{ __('site.devices.revoke') }}
                                                </button>
                                            </form>
                                        @endunless
                                    </li>
                                @empty
                                    <li class="device-list__empty">{{ __('site.devices.no_sessions') }}</li>
                                @endforelse
                            </ul>
                        @endif
                    </div>

                    {{-- Uygulama jetonları --}}
                    <div class="field-card mb-4">
                        <h2 class="h5 fw-bold mb-1">{{ __('site.devices.apps') }}</h2>
                        <p class="text-muted small mb-4">{{ __('site.devices.apps_hint') }}</p>

                        <ul class="device-list">
                            @forelse($tokens as $token)
                                <li class="device-list__item">
                                    <span class="device-list__icon"><i class="fa-solid fa-mobile-screen-button"></i></span>

                                    <div class="device-list__body">
                                        <div class="device-list__name">{{ $token->name }}</div>
                                        <div class="device-list__meta">
                                            <span><i class="fa-regular fa-clock"></i>
                                                {{ $token->last_used_at
                                                    ? __('site.devices.last_active', ['time' => $token->last_used_at->diffForHumans()])
                                                    : __('site.devices.never_used') }}</span>
                                            <span><i class="fa-regular fa-calendar-plus"></i>
                                                {{ __('site.devices.created', ['date' => $token->created_at?->translatedFormat('d M Y') ?? '—']) }}</span>
                                            @if($token->expires_at)
                                                <span><i class="fa-regular fa-hourglass-half"></i>
                                                    {{ __('site.devices.expires', ['date' => $token->expires_at->translatedFormat('d M Y')]) }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <form action="{{ route('account.devices.tokens.destroy', $token->id) }}" method="POST"
                                          class="device-list__action"
                                          data-confirm="{{ __('site.devices.confirm_token') }}"
                                          data-confirm-title="{{ __('site.devices.revoke') }}"
                                          data-confirm-btn="{{ __('site.devices.revoke') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fa-solid fa-xmark"></i> {{ __('site.devices.revoke') }}
                                        </button>
                                    </form>
                                </li>
                            @empty
                                <li class="device-list__empty">{{ __('site.devices.no_tokens') }}</li>
                            @endforelse
                        </ul>
                    </div>

                    {{-- Toplu kapatma --}}
                    <div class="field-card">
                        <h2 class="h5 fw-bold mb-1">{{ __('site.devices.revoke_all') }}</h2>
                        <p class="text-muted small mb-4">{{ __('site.devices.revoke_all_hint') }}</p>

                        <form action="{{ route('account.devices.destroy-others') }}" method="POST"
                              data-confirm="{{ __('site.devices.confirm_all') }}"
                              data-confirm-title="{{ __('site.devices.revoke_all') }}"
                              data-confirm-btn="{{ __('site.devices.revoke_all_btn') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fa-solid fa-power-off"></i> {{ __('site.devices.revoke_all_btn') }}
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
