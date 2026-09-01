@extends('emails.layout')

@php $site = \App\Models\Setting::getValue('site_name', config('app.name')); @endphp

@section('content')
    <p class="em-greeting">{{ __('mail.common.greeting') }}</p>
    <h1 class="em-heading">{{ __('mail.welcome.heading', ['name' => $user->full_name]) }} &#127793;</h1>

    <p class="em-text">{{ __('mail.welcome.lead', ['site' => $site]) }}</p>

    <hr class="em-divider">

    <p class="em-heading-sm">{{ __('mail.welcome.features') }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
        @foreach ([
            '&#128100;' => 'mail.welcome.feature_profile',
            '&#128196;' => 'mail.welcome.feature_content',
            '&#128227;' => 'mail.welcome.feature_news',
            '&#9993;'   => 'mail.welcome.feature_contact',
        ] as $icon => $key)
        <tr>
            <td class="em-feature-td">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-feature-icon-td">{!! $icon !!}</td>
                        {{-- Anahtar <strong> taşıyor: vurgulanan kelime dile göre
                             değişiyor, cümleyi ikiye bölmek çeviriyi bozardı. --}}
                        <td class="em-feature-text-td">{!! __($key) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>
        @endforeach
    </table>

    <div class="em-btn-wrap">
        <a href="{{ localized_route('home') }}" class="em-btn">&#127807; {{ __('mail.welcome.explore') }}</a>
    </div>

    <hr class="em-divider">

    <p class="em-text">{{ __('mail.welcome.outro') }}</p>
@endsection
