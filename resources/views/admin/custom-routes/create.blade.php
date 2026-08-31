@extends('layouts.admin')

@section('title', 'Yeni Özel Adres')
@section('page_title', 'Yeni Özel Adres')
@section('page_description', 'Bir adres açın ve var olan bir sayfaya bağlayın')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.custom-routes.index') }}" class="breadcrumb-link">Özel Adresler</a></li>
            <li class="breadcrumb-item active text-teal">Yeni</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.custom-routes.store') }}" data-validate novalidate>
        @csrf
        @include('admin.custom-routes._form')
    </form>
@endsection
