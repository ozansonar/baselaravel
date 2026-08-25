@props([
    'language',
    'activeLocale' => null,
    'id' => 'langTabs',
])

<div class="tab-pane fade {{ $language->code === $activeLocale ? 'show active' : '' }}"
     id="{{ $id }}-{{ $language->code }}"
     role="tabpanel"
     aria-labelledby="{{ $id }}-{{ $language->code }}-tab">
    {{ $slot }}
</div>
