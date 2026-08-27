@extends('layouts.admin')

@section('title', 'Yeni Yönlendirme')
@section('page_title', 'Yeni Yönlendirme')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.redirects.index') }}" class="breadcrumb-link">Yönlendirmeler</a></li>
            <li class="breadcrumb-item active text-teal">Yeni</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.redirects.store') }}" data-validate novalidate>
        @csrf

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">Yeni Yönlendirme</h1>
                <p class="page-subtitle">Eski bir adresi yeni adresine taşıyın</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.redirects.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Kaydet</button>
            </div>
        </div>

        @include('admin.redirects._form', ['redirect' => null])

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('admin.redirects.index') }}" class="btn-glass">Vazgeç</a>
            <button type="submit" class="btn-teal btn-lg"><i class="bi bi-check-lg"></i> Kaydet</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/redirect-form.js') }}"></script>
@endpush
