@extends('layouts.admin')

@section('title', 'Instagram Gönderisi Düzenle')
@section('page_title', 'Instagram Gönderisi Düzenle')
@section('page_description', 'Yayın tarihi geldiğinde otomatik paylaşılır.')

@section('content')
    @include('admin.instagram-posts._form', [
        'post'            => $post,
        'products'        => $products,
        'previewSettings' => $previewSettings,
        'fbConfigured'    => $fbConfigured,
    ])
@endsection
