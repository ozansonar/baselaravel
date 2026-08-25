@extends('layouts.app')

@section('title', '419 - ' . __('site.errors.419_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    @include('partials.error', [
        'code'    => '419',
        'title'   => __('site.errors.419_title'),
        'message' => __('site.errors.419'),
    ])
@endsection
