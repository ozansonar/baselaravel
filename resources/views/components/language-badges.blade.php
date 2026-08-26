@props(['locales' => []])

{{--
    One list row stands for a record in every language; these badges say which
    translations exist. A missing language stays outlined and greyed out.
--}}
@php
    $existing = array_filter((array) $locales);
    $languages = app(\App\Services\LanguageService::class)->active();
@endphp

@if($languages->count() > 1)
    <span class="cl-lang-badges">
        @foreach($languages as $language)
            @php $translated = in_array($language->code, $existing, true); @endphp
            <span class="cl-lang-badge {{ $translated ? '' : 'cl-lang-badge--missing' }}"
                  title="{{ $language->name }}{{ $translated ? '' : ' — çeviri yok' }}">
                <span class="cl-lang-badge__flag">{{ $language->flag }}</span>{{ strtoupper($language->code) }}
            </span>
        @endforeach
    </span>
@endif
