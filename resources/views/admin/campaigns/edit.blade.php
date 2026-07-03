@extends('layouts.admin')

@section('title', 'Kampanya Düzenle')
@section('page_title', 'Kampanya Düzenle')
@section('page_description', $campaign->name)


@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        @include('admin.campaigns._form')
    </div>
</div>
@endsection
