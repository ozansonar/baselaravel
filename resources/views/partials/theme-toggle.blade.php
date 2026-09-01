{{--
    Koyu/açık kip düğmesi.

    İki ikon aynı gözü paylaşıyor, yalnız etkin kipe ait olan görünüyor; düğme
    kip değişirken yeniden boyutlanmıyor. Etiket eylemi söylüyor ("Koyu tema"
    = koyuya geç), durumu değil — ekran okuyucunun duyması gereken bu.

    @var bool $wide Mobil menüde tam genişlik sürümü
--}}
@php $wide = $wide ?? false; @endphp

<button type="button"
        class="theme-toggle {{ $wide ? 'theme-toggle--wide' : '' }}"
        data-theme-toggle
        aria-label="{{ __('site.theme.dark') }}"
        title="{{ __('site.theme.dark') }}">
    <span class="theme-toggle__icons" aria-hidden="true">
        <i class="fa-solid fa-moon theme-toggle__moon"></i>
        <i class="fa-solid fa-sun theme-toggle__sun"></i>
    </span>
    @if($wide)
        <span data-theme-label>{{ __('site.theme.dark') }}</span>
    @endif
</button>
