@props([
    'languages',
    'model' => null,
    'id' => 'langTabs',
])

@php
    /** @var \Illuminate\Support\Collection $languages */
    $activeLocale = old('active_locale', $languages->first()?->code);
@endphp

{{-- Which tab was open when validation failed, so the user comes back to it --}}
<input type="hidden" name="active_locale" id="{{ $id }}ActiveLocale" value="{{ $activeLocale }}">

<ul class="nav lang-tabs mb-0" id="{{ $id }}" role="tablist" aria-label="İçerik dili">
    <li class="lang-tabs__label" aria-hidden="true">
        <i class="bi bi-translate"></i><span class="d-none d-sm-inline">İçerik dili</span>
    </li>
    @foreach($languages as $language)
        @php
            $translation = $model?->translation($language->code);
            $hasErrors = $errors->hasAny([
                "translations.{$language->code}.title",
                "translations.{$language->code}.content",
                "translations.{$language->code}.slug",
                "translations.{$language->code}.question",
                "translations.{$language->code}.answer",
                "translations.{$language->code}.name",
            ]);
        @endphp
        <li class="nav-item" role="presentation">
            <button class="nav-link lang-tabs__btn {{ $language->code === $activeLocale ? 'active' : '' }} {{ $hasErrors ? 'lang-tabs__btn--invalid' : '' }}"
                    id="{{ $id }}-{{ $language->code }}-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#{{ $id }}-{{ $language->code }}"
                    data-locale="{{ $language->code }}"
                    type="button"
                    role="tab"
                    aria-controls="{{ $id }}-{{ $language->code }}"
                    aria-selected="{{ $language->code === $activeLocale ? 'true' : 'false' }}">
                <span class="lang-tabs__flag">{{ $language->flag }}</span>
                <span>{{ $language->name }}</span>

                @if($language->is_default)
                    <span class="lang-tabs__badge lang-tabs__badge--default" title="Varsayılan dil">
                        <i class="bi bi-star-fill"></i> Varsayılan
                    </span>
                @elseif($model && ! $translation)
                    <span class="lang-tabs__badge lang-tabs__badge--missing" title="Bu dilde henüz içerik yok">
                        <i class="bi bi-dash-circle"></i> Çeviri yok
                    </span>
                @endif

                @if($hasErrors)
                    <i class="bi bi-exclamation-circle-fill lang-tabs__error" aria-hidden="true"></i>
                @endif
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content lang-tabs__content" id="{{ $id }}Content">
    {{ $slot }}
</div>
