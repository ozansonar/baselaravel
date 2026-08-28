{{--
    Bu içeriğin öteki dillerdeki sürümüne doğrudan geçiş.

    Başlıktaki dil değiştirici sitenin dilini değiştiriyor; okunan yazının
    çevirisine götürdüğü, üstüne tıklamadan anlaşılmıyordu. Çeviri varsa bağlantı
    burada, metnin yanında duruyor.

    İki ayrı durumu anlatıyor:
      - Öteki dilde sürüm var → o sürüme bağlantı.
      - Okunan metin sayfanın dilinde değil → çevirisi henüz yok, kendi diliyle
        basılıyor. Ziyaretçi bunu satırın kendisinden öğreniyor, yoksa sitesi
        İngilizceyken Türkçe metin gören biri bir hata olduğunu sanıyor.

    @var string $contentLocale Metnin gerçekten yazıldığı dil
--}}
@php
    $languageService = app(\App\Services\LanguageService::class);
    $alternates = app(\App\Services\LocalizedUrlService::class)->alternates();
    $viewLocale = app()->getLocale();

    // Bu dilin sürümü zaten okunuyor; kendine bağlantı verilmez.
    $otherLanguages = $languageService->active()
        ->filter(fn ($language): bool => $language->code !== $contentLocale && isset($alternates[$language->code]))
        ->values();

    $untranslated = $contentLocale !== $viewLocale;
@endphp

@if($otherLanguages->isNotEmpty() || $untranslated)
    <div class="content-langs">
        @if($untranslated)
            <span class="content-langs__note">
                <i class="fa-solid fa-circle-info"></i>
                {{ __('site.misc.reading_original', [
                    'language' => $languageService->findByCode($contentLocale)?->native_name
                        ?? strtoupper($contentLocale),
                ]) }}
            </span>
        @endif

        @foreach($otherLanguages as $language)
            <a href="{{ $alternates[$language->code] }}"
               class="content-langs__link"
               hreflang="{{ $language->code }}"
               lang="{{ $language->code }}">
                <span class="content-langs__flag">{{ $language->flag }}</span>
                {{-- Etiket hedef dilde yazılıyor: bağlantı İngilizce metne
                     gidiyorsa "Read in English" diyor. Sayfanın dilinde
                     yazılsaydı Türkçe sayfada "English oku" gibi ikisi de
                     olmayan bir cümle çıkıyordu. --}}
                {{ __('site.misc.read_in', ['language' => $language->native_name ?: $language->name], $language->code) }}
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        @endforeach
    </div>
@endif
