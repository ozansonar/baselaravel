@extends('layouts.admin')

@section('title', 'Yeni Instagram Gönderisi')
@section('page_title', 'Yeni Instagram Gönderisi')
@section('page_description', 'Görsel yükleyip yazı ve tagları ekleyin, yayın tarihini planlayın.')

@section('content')
    @include('admin.instagram-posts._form', ['post' => null])
@endsection
