@extends('layouts.app')

@section('title', '400 - ' . __('site.errors.400_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '400',
        'title'   => __('site.errors.400_title'),
        'message' => __('site.errors.400'),
    ])
@endsection
