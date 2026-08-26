@extends('layouts.admin')

@section('title', 'Yeni Kullanıcı')

@section('content')
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb breadcrumb-reset fs-13">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="breadcrumb-link">Kullanıcılar</a></li>
            <li class="breadcrumb-item active text-teal">Yeni Kullanıcı</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title">Yeni Kullanıcı Oluştur</h1>
                <p class="page-subtitle">Sisteme yeni bir kullanıcı ekleyin ve gerekli bilgileri doldurun</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-glass"><i class="bi bi-x-lg me-1"></i> İptal</a>
            <button type="submit" form="userForm" class="btn-teal"><i class="bi bi-check2 me-1"></i> Kullanıcı Oluştur</button>
        </div>
    </div>

    <form id="userForm" method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" data-validate novalidate>
        @csrf
        @include('admin.users._form', ['roles' => $roles])
    </form>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/user-form.js') }}"></script>
@endpush
