@extends('layouts.admin')

@section('title', 'Yeni Kampanya')
@section('page_title', 'Yeni Kampanya')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}" class="breadcrumb-link">Mail Kampanyaları</a></li>
            <li class="breadcrumb-item active text-teal">Yeni Kampanya</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.campaigns.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">Yeni Kampanya</h1>
                <p class="page-subtitle">Taslak olarak kaydedin, göndermeden önce test edin</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.campaigns.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-save"></i> Taslağı Kaydet</button>
            </div>
        </div>

        @include('admin.campaigns._form')
    </form>

    @include('partials.admin.tinymce', ['tinymceSelector' => '#body'])
@endsection


@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/campaigns.js') }}"></script>
@endpush
