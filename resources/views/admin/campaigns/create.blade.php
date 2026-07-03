@extends('layouts.admin')

@section('title', 'Yeni Kampanya')
@section('page_title', 'Yeni Kampanya')
@section('page_description', 'Yeni kampanya oluştur')


@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        @include('admin.campaigns._form')
    </div>
</div>
@endsection
