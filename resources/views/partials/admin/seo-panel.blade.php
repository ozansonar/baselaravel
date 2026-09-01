{{--
  SEO denetim paneli.

  İçerik formlarının içine giriyor ve yazar daha kaydetmeden denetim yapıyor:
  gövde, başlık ve meta alanları olduğu gibi sunucuya gidiyor, bulgular geri
  geliyor. Kaydettikten sonra söylenen bir uyarı, düzeltmesi bir tur daha
  gerektiren bir uyarıdır.

  Panel formun bir parçası değil — kendi alanı yok, hiçbir şey göndermiyor.
  Kaydetmeyi de engellemiyor: bulgular uyarı, doğrulama değil.

  @param string $seoLocale  Hangi dilin sekmesine ait
  @param string $seoType    'page' | 'blog_post' — alan adları buna göre okunuyor
--}}

@props([])

@php
    $panelId = 'seoPanel_' . $seoLocale;
@endphp

<div class="seo-panel" id="{{ $panelId }}"
     data-seo-panel
     data-seo-locale="{{ $seoLocale }}"
     data-seo-type="{{ $seoType }}"
     data-seo-url="{{ route('admin.seo.audit') }}">

    <div class="seo-panel__head">
        <div class="seo-panel__title">
            <i class="bi bi-search"></i>
            <span>{{ __('seo.panel.title') }}</span>
        </div>

        <div class="seo-panel__score" data-seo-score hidden>
            <span class="seo-score" data-seo-score-value>—</span>
            <span class="seo-score__label">{{ __('seo.panel.score') }}</span>
        </div>

        <button type="button" class="btn-glass btn-sm" data-seo-run>
            <i class="bi bi-arrow-repeat me-1"></i>{{ __('seo.panel.run') }}
        </button>
    </div>

    {{-- Başlangıç durumu: denetim henüz koşmadı. Boş bir liste göstermek
         "sorun yok" gibi okunurdu. --}}
    <p class="seo-panel__idle" data-seo-idle>{{ __('seo.panel.idle') }}</p>

    <p class="seo-panel__busy" data-seo-busy hidden>{{ __('seo.panel.running') }}</p>

    <p class="seo-panel__clean" data-seo-clean hidden>
        <i class="bi bi-check-circle me-1"></i>{{ __('seo.panel.clean') }}
    </p>

    <p class="seo-panel__failed" data-seo-failed hidden>
        <i class="bi bi-exclamation-triangle me-1"></i>{{ __('seo.panel.failed') }}
    </p>

    <ul class="seo-panel__list" data-seo-list hidden></ul>

    <p class="seo-panel__note">{{ __('seo.panel.note') }}</p>
</div>
