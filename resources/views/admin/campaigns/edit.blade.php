@extends('layouts.admin')

@section('title', 'Kampanyayı Düzenle')
@section('page_title', 'Kampanyayı Düzenle')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}" class="breadcrumb-link">Mail Kampanyaları</a></li>
            <li class="breadcrumb-item active text-teal">{{ $campaign->name }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}" enctype="multipart/form-data" data-validate novalidate>
        @csrf
        @method('PUT')

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">{{ $campaign->name }}</h1>
                <p class="page-subtitle">Gönderim başlamadan önce içerik ve alıcılar değiştirilebilir</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-save"></i> Kaydet</button>
            </div>
        </div>

        @include('admin.campaigns._form')
    </form>

    @include('partials.admin.tinymce', ['tinymceSelector' => '#body'])

    {{-- Attachment removal posts on its own, so it cannot sit inside the form above. --}}
    <form method="POST" id="attachmentForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/campaigns.js') }}"></script>
    <script>
        window.campaignAttachmentUrl = @js(route('admin.campaigns.attachments.destroy', [$campaign, 'ATTACHMENT_ID']));
    </script>
@endpush
