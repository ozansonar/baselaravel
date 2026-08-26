@extends('layouts.admin')

@section('title', 'İçerik Kategorisi Düzenle')
@section('page_title', 'Kategori Düzenle')
@section('page_description', $category->name)

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.blog-categories.index') }}" class="breadcrumb-link">İçerik Kategorileri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Düzenle</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.blog-categories.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Kategori Düzenle</h1>
                <p class="page-subtitle mb-0">{{ $category->name }}</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" form="categoryForm" class="btn-teal">
                <i class="bi bi-check-lg me-1"></i>Güncelle
            </button>
        </div>
    </div>

    @include('admin.blog-categories._form', ['category' => $category])

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/blog-category-form.js') }}"></script>
@endpush
