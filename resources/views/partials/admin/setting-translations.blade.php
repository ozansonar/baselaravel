{{--
    Bir ayarın öteki dillerdeki karşılıkları.

    Asıl alan (varsayılan dil) yukarıda duruyor ve olduğu gibi kalıyor; bu blok
    onun altına ekleniyor. Kapalı açılıyor: ayarların çoğu tek dilli ve on üç
    alanın hepsini açık göstermek ekranı iki katına çıkarırdı. Açma işi
    <details>'e bırakıldı, JS'e gerek yok — aynı desen SEO ve kampanya
    ekranlarında da kullanılıyor.

    Boş bırakılan bir dil "çevrilmedi" demek: kayıtta o satır siliniyor ve
    ziyaretçi asıl değeri görüyor. Boş bir alt bilgi göstermek, çevirmemekten
    kötüdür.

    @var string $key     Ayar anahtarı
    @var string $label   Alanın ekrandaki adı (başlıkta tekrar ediliyor)
    @var int|null $rows  Verilirse textarea, verilmezse tek satırlık input
--}}
@php
    /** @var \Illuminate\Support\Collection $otherLanguages */
    $rows ??= null;
    $current = $translations[$key] ?? [];
    $filled = collect($otherLanguages)->filter(fn ($l): bool => ($current[$l->code] ?? '') !== '')->count();
@endphp

@if($otherLanguages->isNotEmpty())
    <details class="stg-i18n" @if($filled > 0) open @endif>
        <summary class="stg-i18n__summary">
            <i class="bi bi-translate"></i>
            <span>Diğer diller</span>
            @if($filled > 0)
                <span class="stg-i18n__badge">{{ $filled }}/{{ $otherLanguages->count() }}</span>
            @else
                <span class="stg-i18n__badge stg-i18n__badge--empty">çeviri yok</span>
            @endif
        </summary>

        <div class="stg-i18n__body">
            @foreach($otherLanguages as $language)
                <div class="stg-field stg-i18n__field">
                    <label class="stg-label" for="i18n-{{ $key }}-{{ $language->code }}">
                        <span class="stg-i18n__flag">{{ $language->flag }}</span>
                        {{ $language->displayName() }}
                    </label>

                    @if($rows)
                        <textarea class="stg-textarea"
                                  id="i18n-{{ $key }}-{{ $language->code }}"
                                  name="settings_translations[{{ $language->code }}][{{ $key }}]"
                                  rows="{{ $rows }}"
                                  lang="{{ $language->code }}"
                                  placeholder="Boş bırakılırsa {{ $label }} olduğu gibi kullanılır"
                                  data-validation-engine="validate[maxSize[10000]]">{{ $current[$language->code] ?? '' }}</textarea>
                    @else
                        <input type="text"
                               class="stg-input"
                               id="i18n-{{ $key }}-{{ $language->code }}"
                               name="settings_translations[{{ $language->code }}][{{ $key }}]"
                               value="{{ $current[$language->code] ?? '' }}"
                               lang="{{ $language->code }}"
                               placeholder="Boş bırakılırsa {{ $label }} olduğu gibi kullanılır"
                               data-validation-engine="validate[maxSize[10000]]">
                    @endif
                </div>
            @endforeach
        </div>
    </details>
@endif
