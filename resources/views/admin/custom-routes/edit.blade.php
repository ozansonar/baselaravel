@extends('layouts.admin')

@section('title', 'Özel Adresi Düzenle')
@section('page_title', 'Özel Adresi Düzenle')
@section('page_description', $route->slug)

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.custom-routes.index') }}" class="breadcrumb-link">Özel Adresler</a></li>
            <li class="breadcrumb-item active text-teal">{{ $route->slug }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.custom-routes.update', $route) }}" data-validate novalidate>
        @csrf
        @method('PUT')
        @include('admin.custom-routes._form')
    </form>
@endsection
