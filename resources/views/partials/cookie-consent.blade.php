{{--
    Çerez rıza bandı.

    Betik olmadan da çalışıyor: band düz bir form, düğmeler gerçek submit.
    Yalnızca JavaScript'le çalışsaydı betiği engelleyen ziyaretçi hiç
    sorulmadan izlenirdi — kapatmaya çalıştığımız şeyin ta kendisi.

    Kategorilerin hangileri olduğu enum'dan, metinleri dil dosyasından geliyor:
    biri kodun, diğeri çevirinin sorumluluğu.
--}}
@php
    $categories = \App\Enums\ConsentCategory::cases();
    $decided = $consent->decided();
    $current = $consent->current()['categories'] ?? [];

    $texts = [
        'necessary' => ['label' => __('site.consent.necessary_label'), 'text' => __('site.consent.necessary_text')],
        'analytics' => ['label' => __('site.consent.analytics_label'), 'text' => __('site.consent.analytics_text')],
        'marketing' => ['label' => __('site.consent.marketing_label'), 'text' => __('site.consent.marketing_text')],
    ];

    $policyUrl = page_url('gizlilik-politikasi');
@endphp

{{-- Karar verilmişse band gizli, ama DOM'da duruyor: alt bilgideki
     "Çerez tercihleri" bağlantısı `#cookieConsent` adresine gidiyor ve
     `:target` kuralı bandı yeniden açıyor. Böylece tercihi değiştirmek
     JavaScript gerektirmiyor. --}}
<div class="cc-banner{{ $decided ? ' cc-banner--hidden' : '' }}"
     id="cookieConsent"
     role="dialog"
     aria-modal="false"
     aria-labelledby="cookieConsentTitle">
    <form method="POST" action="{{ route('consent.store') }}" id="cookieConsentForm" class="cc-inner" novalidate>
        @csrf

        <div class="cc-text">
            <h2 class="cc-title" id="cookieConsentTitle">{{ __('site.consent.title') }}</h2>
            <p class="cc-intro">
                {{ __('site.consent.intro') }}
                @if($policyUrl)
                    <a href="{{ $policyUrl }}" class="cc-link">{{ __('site.consent.policy') }}</a>
                @endif
            </p>

            {{-- Ayrıntı kapalı başlıyor: karar vermek isteyen iki tıkla,
                 incelemek isteyen üçüncüsüyle ulaşıyor. --}}
            <div class="cc-details" id="cookieConsentDetails" hidden>
                @foreach($categories as $category)
                    @php $copy = $texts[$category->value] ?? ['label' => $category->label(), 'text' => $category->description()]; @endphp
                    <label class="cc-option">
                        <input type="checkbox"
                               name="categories[]"
                               value="{{ $category->value }}"
                               class="cc-check"
                               @checked($category->isRequired() || in_array($category->value, $current, true))
                               @disabled($category->isRequired())>
                        <span class="cc-option-body">
                            <span class="cc-option-label">
                                {{ $copy['label'] }}
                                @if($category->isRequired())
                                    <em class="cc-always">{{ __('site.consent.always_on') }}</em>
                                @endif
                            </span>
                            <span class="cc-option-text">{{ $copy['text'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="cc-actions">
            <button type="button" class="cc-btn cc-btn--ghost" id="cookieConsentCustomise"
                    aria-expanded="false" aria-controls="cookieConsentDetails">
                {{ __('site.consent.customise') }}
            </button>

            {{-- Reddetmek kabul etmek kadar kolay olmalı: iki düğme aynı
                 görünürlükte ve aynı sayıda tıkla ulaşılıyor. --}}
            <button type="submit" class="cc-btn cc-btn--ghost" name="choice" value="necessary" id="cookieConsentNecessary">
                {{ __('site.consent.necessary_only') }}
            </button>

            <button type="submit" class="cc-btn cc-btn--solid" name="choice" value="all" id="cookieConsentAcceptAll">
                {{ __('site.consent.accept_all') }}
            </button>

            <button type="submit" class="cc-btn cc-btn--solid cc-save" name="choice" value="custom" id="cookieConsentSave" hidden>
                {{ __('site.consent.save') }}
            </button>
        </div>
    </form>
</div>
