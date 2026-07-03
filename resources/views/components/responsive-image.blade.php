{{--
    Responsive Image Component

    Usage:
    <x-responsive-image :path="$product->image" :alt="$product->name" />
    <x-responsive-image :path="$post->featured_image" alt="Blog görseli" size="md" class="rounded" />
    <x-responsive-image :path="$slider->image" :alt="$slider->title" :sizes-attr="'100vw'" />
    <x-responsive-image :path="$category->image" :alt="$category->name" size="thumb" />
--}}

@props([
    'path'          => null,
    'alt'           => '',
    'size'          => 'md',
    'sizesAttr'     => '(max-width: 576px) 300px, (max-width: 992px) 600px, 1200px',
    'srcsetSizes'   => null,
    'responsive'    => true,
    'loading'       => 'lazy',
    'fetchpriority' => null,
    'width'         => null,
    'height'        => null,
])

@php
    $src = upload_url($path, $size);
    $srcsetValue = $responsive ? upload_srcset($path, $srcsetSizes) : '';
    $decodingAttr = $loading === 'eager' ? 'sync' : 'async';
@endphp

@if($responsive && $srcsetValue)
    <img
        src="{{ $src }}"
        srcset="{{ $srcsetValue }}"
        sizes="{{ $sizesAttr }}"
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        decoding="{{ $decodingAttr }}"
        @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        @if($width) width="{{ $width }}" @endif
        @if($height) height="{{ $height }}" @endif
        {{ $attributes->merge(['class' => 'img-fluid']) }}
    >
@else
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        decoding="{{ $decodingAttr }}"
        @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        @if($width) width="{{ $width }}" @endif
        @if($height) height="{{ $height }}" @endif
        {{ $attributes->merge(['class' => 'img-fluid']) }}
    >
@endif
