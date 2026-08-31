@extends('layouts.app')

@section('title', __('site.offline.title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.offline.desc'))
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center">
                    {{-- İkon satır içi SVG: ikon fontu önbelleğe alınmıyor
                         (megabaytlarca) ve tam da bağlantı yokken gereken
                         sayfanın, inmesi gereken bir dosyaya bağlı olması
                         çelişki olurdu. --}}
                    <span class="offline-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 1l22 22"></path>
                            <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
                            <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
                            <path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path>
                            <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
                            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                            <line x1="12" y1="20" x2="12.01" y2="20"></line>
                        </svg>
                    </span>

                    <h1 class="section__title mt-4">{{ __('site.offline.title') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.offline.lead') }}</p>

                    {{-- Yeniden dene: sayfayı tazeliyor. Ağ geri geldiyse
                         servis çalışanı isteği yine ağa götürüyor. --}}
                    <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                        {{ __('site.offline.retry') }}
                    </button>
                </div>
            </div>
        </div>
    </section>

@endsection
