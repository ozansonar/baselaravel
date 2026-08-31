{{--
    Tekli görsel alanı: yüklü görselin önizlemesi + dosya seçici.

    Önceden her modül kendi dosya girdisini elle yazıyordu ve çoğunda yalnız
    boş bir girdi vardı: kayıtlı görsel formda hiç görünmüyordu, dolayısıyla
    "yüklü mü, hangisi yüklü, kaldırayım mı" sorularının ekranda karşılığı
    yoktu. Kutu tek yere alındı; bir modülde düzeltilen bir şey hepsinde
    düzeliyor.

    Kural da burada tek yerde duruyor — panelde tekli yüklenen her görsel
    yalnızca görsel ve en fazla 1 MB. İstemci sınırı (data-max-size, MB) ile
    sunucudaki max: (KB) birebir aynı; sunucu her zaman son söz.

    Önizlemeyi ve kaldırmayı assets/admin/js/cover-image.js sürüyor:
    data-cover* kancaları onun beklediği adlar.

    @param string  $name      Girdi adı — translations[tr][image] gibi
    @param string  $id        Girdi id'si (label ile eşleşir)
    @param ?string $current   Kayıtlı görselin yolu; yoksa önizleme gizli açılır
    @param ?string $errorKey  Hata torbasındaki anahtar (varsayılan: $name'den türer)
    @param ?string $removeName Kaldırma bayrağının adı; verilmezse kaldır düğmesi çıkmaz
--}}
@props([
    'name',
    'id',
    'label'      => 'Görsel',
    'current'    => null,
    'title'      => null,
    'errorKey'   => null,
    'required'   => false,
    'hint'       => null,
    'gallery'    => null,
    'removeName' => null,
])

@php
    // Panel genelinde tek kural: yalnız görsel, en fazla 1 MB. Sınır niteliğe
    // düz yazılıyor — SingleImageUploadTest görünümleri okuyarak denetliyor ve
    // Blade ifadesi orada çözülmemiş kalırdı.
    $accept = 'image/png,image/jpeg,image/webp';

    // "translations[tr][image]" → "translations.tr.image"
    $errorKey ??= str_replace(['[', ']'], ['.', ''], $name);
    $gallery  ??= $id;
    $title    ??= $label;

    $hasImage = filled($current);
@endphp

@once
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/vendor/glightbox/css/glightbox.min.css') }}">
@endpush
@push('scripts')
<script src="{{ versioned_asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/cover-image.js') }}"></script>
@endpush
@endonce

<div data-cover="{{ $id }}">
    <label class="form-label" for="{{ $id }}">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>

    {{-- Yüklü görsel: küçük resim, adı ve eylemleri tek satırda.

         Görsel yokken src ve href hiç basılmıyor: boş src="" tarayıcıya
         sayfanın kendi adresini yükletiyor ve her dil sekmesi için bir
         başarısız istek doğuruyordu. Kutu boşken zaten gizli; dosya
         seçilince adresleri cover-image.js yazıyor. --}}
    <div class="ca-cover {{ $hasImage ? '' : 'd-none' }}" data-cover-box>
        <a @if($hasImage) href="{{ upload_url($current) }}" @endif
           class="glightbox ca-cover__thumb"
           data-gallery="{{ $gallery }}"
           data-title="{{ $title }}"
           data-cover-link>
            <img @if($hasImage) src="{{ upload_url($current, 'thumb') }}" @endif
                 alt="{{ image_alt($title) }}" loading="lazy" data-cover-img>
            <span class="ca-cover__zoom"><i class="bi bi-arrows-fullscreen"></i></span>
        </a>

        <div class="ca-cover__info">
            <span class="ca-cover__name" data-cover-name>{{ $hasImage ? basename($current) : '' }}</span>
            <span class="ca-cover__hint">Büyütmek için görsele tıkla</span>
        </div>

        @if($removeName)
            <button type="button" class="ca-cover__remove" data-cover-remove>
                <i class="bi bi-trash3"></i><span>Kaldır</span>
            </button>
        @endif
    </div>

    @if($removeName)
        {{-- Kaldırma bayrağı: sunucuda prepareImageField bunu görünce dosyayı
             diskten de siliyor. --}}
        <input type="hidden" name="{{ $removeName }}" value="0" data-cover-flag data-fv-ignore>
    @endif

    <input type="file"
           class="form-control @error($errorKey) is-invalid @enderror"
           id="{{ $id }}"
           name="{{ $name }}"
           accept="{{ $accept }}"
           data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]"
           data-max-size="1"
           data-accept="image/jpeg,image/png,image/webp">

    @error($errorKey)
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    <div class="form-text">PNG, JPG, WebP | Maks. 1 MB{{ $hint ? ' | ' . $hint : '' }}</div>
</div>
