@php
    $languageService = app(\App\Services\LanguageService::class);
    $availableLanguages = $languageService->active();
    $currentLanguage = $languageService->findByCode(app()->getLocale()) ?? $languageService->default();
@endphp

{{-- A single language is not a choice, so the switcher stays hidden. --}}
@if($availableLanguages->count() > 1 && $currentLanguage)
    <div class="dropdown lang-switcher">
        <button class="btn btn-light dropdown-toggle lang-switcher__toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="Dil seçimi — aktif dil {{ $currentLanguage->name }}">
            <span class="lang-switcher__flag">{{ $currentLanguage->flag }}</span>
            <span class="lang-switcher__code">{{ strtoupper($currentLanguage->code) }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end lang-switcher__menu">
            @foreach($availableLanguages as $language)
                <li>
                    <a class="dropdown-item {{ $language->code === $currentLanguage->code ? 'active' : '' }}"
                       href="{{ route('locale.switch', $language->code) }}"
                       @if($language->code === $currentLanguage->code) aria-current="true" @endif>
                        <span class="lang-switcher__flag">{{ $language->flag }}</span>
                        {{ $language->native_name ?: $language->name }}
                        @if($language->code === $currentLanguage->code)
                            <i class="fa-solid fa-check ms-auto"></i>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
