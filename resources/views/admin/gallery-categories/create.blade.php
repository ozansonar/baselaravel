@extends('layouts.admin')

@section('title', 'Yeni Galeri Kategorisi')
@section('page_title', 'Yeni Kategori')
@section('page_description', 'Galeri öğeleri için yeni kategori oluşturun')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.gallery-categories.index') }}" class="breadcrumb-link">Galeri Kategorileri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Kategori</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.gallery-categories.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Galeri Kategorisi</h1>
                <p class="page-subtitle mb-0">Galeri öğeleri için yeni kategori oluşturun</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" form="categoryForm" class="btn-teal">
                <i class="bi bi-check-lg me-1"></i>Kaydet
            </button>
        </div>
    </div>

    {{-- Form --}}
    @include('admin.gallery-categories._form')

@endsection
