@extends('layouts.admin')

@section('title', $language->native_name ?: $language->name)
@section('page_title', 'Dili Düzenle')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.languages.index') }}" class="breadcrumb-link">Diller</a></li>
            <li class="breadcrumb-item active text-teal">{{ $language->native_name ?: $language->name }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.languages.update', $language) }}">
        @csrf
        @method('PUT')

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">
                    {{ $language->flag }} {{ $language->native_name ?: $language->name }}
                </h1>
                <p class="page-subtitle">
                    <code>{{ $language->code }}</code>
                    @if($language->is_default)
                        <span class="menu-manage-tag menu-manage-tag--info ms-2"><i class="bi bi-star-fill"></i> Varsayılan</span>
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.languages.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Kaydet</button>
            </div>
        </div>

        {{-- Durum özeti --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6" data-aos="fade-up">
                <div class="usr-stat-card">
                    <div class="usr-stat-icon {{ $hasFiles ? 'usr-stat-icon-green' : 'usr-stat-icon-orange' }}">
                        <i class="bi {{ $hasFiles ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"></i>
                    </div>
                    <div class="usr-stat-info">
                        <span class="usr-stat-label">Arayüz Çevirisi</span>
                        <h3 class="usr-stat-value" style="font-size:1.1rem">
                            {{ $hasFiles ? 'lang/' . $language->code . '/ var' : 'lang/' . $language->code . '/ yok' }}
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="80">
                <div class="usr-stat-card">
                    <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-file-earmark-text-fill"></i></div>
                    <div class="usr-stat-info">
                        <span class="usr-stat-label">Bu Dildeki İçerik</span>
                        <h3 class="usr-stat-value" data-count="{{ $contentCount }}">0</h3>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.languages._form', ['language' => $language])

        @unless($hasFiles)
            <div class="alert alert-warning" data-aos="fade-up">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Bu dilin arayüz çeviri dosyası yok. <code>lang/tr/</code> klasörünü
                <code>lang/{{ $language->code }}/</code> olarak kopyalayıp çevirene kadar butonlar ve
                etiketler varsayılan dilde görünür.
            </div>
        @endunless

        <div class="d-flex justify-content-between gap-2 mb-4 flex-wrap">
            <div>
                @can('delete', $language)
                    @unless($language->is_default)
                        <button type="button" class="btn-glass text-neon-red"
                                onclick="openLanguageDelete({{ $language->id }}, @js($language->native_name ?: $language->name), {{ $contentCount }})">
                            <i class="bi bi-trash"></i> Dili Sil
                        </button>
                    @endunless
                @endcan
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.languages.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal btn-lg"><i class="bi bi-check-lg"></i> Kaydet</button>
            </div>
        </div>
    </form>

    @can('delete', $language)
        <div class="modal fade modal-custom" id="deleteLanguageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <div class="modal-body text-center p-4">
                        <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <h5 class="mt-3">Dili sil</h5>
                        <p class="text-clr-secondary mb-2"><strong id="deleteLanguageName"></strong> silinecek.</p>
                        <p class="mb-4" id="deleteLanguageWarning"></p>
                        <form method="POST" id="deleteLanguageForm">
                            @csrf
                            @method('DELETE')
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                                <button type="submit" class="btn-danger-solid">Sil</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
<script>
    window.languageDeleteUrl = @js(route('admin.languages.destroy', ['language' => 'LANGUAGE_ID']));
</script>
<script src="{{ versioned_asset('assets/admin/js/languages.js') }}"></script>
@endpush
