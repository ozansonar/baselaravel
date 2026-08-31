{{--
    Açık sayfası olmayan istemci hataları.

    Laravel önce errors/{kod} arıyor, bulamazsa buraya düşüyor. Böylece
    ileride doğacak bir kod da çerçevenin çıplak sayfasıyla değil, sitenin
    kendi tasarımıyla karşılanıyor.

    Kodun kendisi $exception üzerinden geliyor; sabit yazılsaydı sayfa
    ziyaretçiye yanlış numarayı gösterirdi.
--}}
@php
    $status = $exception?->getStatusCode() ?? 400;
@endphp

@extends('layouts.app')

@section('title', $status . ' - ' . __('site.errors.generic_client_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => $status,
        'title'   => __('site.errors.generic_client_title'),
        'message' => __('site.errors.generic_client'),
    ])
@endsection
