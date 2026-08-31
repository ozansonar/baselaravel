@extends('layouts.app')

@section('title', '402 - ' . __('site.errors.402_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '402',
        'title'   => __('site.errors.402_title'),
        'message' => __('site.errors.402'),
    ])
@endsection
