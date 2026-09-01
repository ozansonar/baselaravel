@php
    $languageService = app(\App\Services\LanguageService::class);
    $availableLanguages = $languageService->active();
    $currentLanguage = $languageService->findByCode(app()->getLocale()) ?? $languageService->default();
    // Her dil için bir adres ve o dilde sürüm olup olmadığı. Çevirisi olmayan
    // dil yine ana sayfaya gidiyor — dili değiştirmek yine de işe yarıyor — ama
    // artık bunu söylüyor: sessizce ana sayfaya düşen bağlantı, kullanıcıya
    // "dil düğmesi beni okuduğum yazıdan attı" diye görünüyordu.
    $languageTargets = app(\App\Services\LocalizedUrlService::class)->switcherTargets();
@endphp

{{-- A single language is not a choice, so the switcher stays hidden. --}}
@if($availableLanguages->count() > 1 && $currentLanguage)
    <div class="dropdown lang-switcher">
        <button class="btn btn-light dropdown-toggle lang-switcher__toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="{{ __('site.misc.language_aria', ['language' => $currentLanguage->displayName()]) }}">
            <span class="lang-switcher__flag">{{ $currentLanguage->flag }}</span>
            <span class="lang-switcher__code">{{ strtoupper($currentLanguage->code) }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end lang-switcher__menu">
            @foreach($availableLanguages as $language)
                @php $target = $languageTargets[$language->code] ?? null; @endphp
                <li>
                    <a class="dropdown-item {{ $language->code === $currentLanguage->code ? 'active' : '' }}"
                       href="{{ $target['url'] ?? route('home', ['locale' => $language->code]) }}"
                       hreflang="{{ $language->code }}"
                       @if($language->code === $currentLanguage->code) aria-current="true" @endif>
                        <span class="lang-switcher__flag">{{ $language->flag }}</span>
                        {{ $language->displayName() }}
                        @if($language->code === $currentLanguage->code)
                            <i class="fa-solid fa-check ms-auto"></i>
                        @elseif($target && ! $target['translated'])
                            {{-- Bu sayfanın o dilde sürümü yok: bağlantı ana sayfaya
                                 gider. Rozet olmadan tıklayan kişi okuduğu yazıyı
                                 kaybettiğini sanıyordu. --}}
                            <span class="lang-switcher__missing ms-auto">{{ __('site.misc.no_translation') }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
