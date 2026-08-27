@extends('layouts.admin')

@section('title', 'Yönlendirmeyi Düzenle')
@section('page_title', 'Yönlendirmeyi Düzenle')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.redirects.index') }}" class="breadcrumb-link">Yönlendirmeler</a></li>
            <li class="breadcrumb-item active text-teal">{{ Str::limit($redirect->old_url, 40) }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.redirects.update', $redirect) }}" data-validate novalidate>
        @csrf
        @method('PUT')

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">Yönlendirmeyi Düzenle</h1>
                <p class="page-subtitle">
                    <code>{{ $redirect->old_url }}</code>
                    <span class="menu-manage-tag menu-manage-tag--{{ $redirect->status_code->color() }} ms-1">
                        {{ $redirect->status_code->value }}
                    </span>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.redirects.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Kaydet</button>
            </div>
        </div>

        @include('admin.redirects._form', ['redirect' => $redirect])

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('admin.redirects.index') }}" class="btn-glass">Vazgeç</a>
            <button type="submit" class="btn-teal btn-lg"><i class="bi bi-check-lg"></i> Kaydet</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/redirect-form.js') }}"></script>
@endpush
