@extends('layouts.admin')

@section('title', 'Yeni Galeri Öğesi')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.gallery-items.index') }}" class="breadcrumb-link">Galeri</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Öğe</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.gallery-items.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Galeri Öğesi</h1>
                <p class="page-subtitle mb-0">Galeriye yeni bir fotoğraf veya video ekleyin</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.gallery-items.store') }}" enctype="multipart/form-data">
        @csrf
                {{-- Her dil kendi sekmesinde --}}
                <x-language-tabs :languages="$formLanguages" id="galleryItemLangTabs">
                    @foreach($formLanguages as $language)
                        <x-language-tab-pane
                            :language="$language"
                            :active-locale="old('active_locale', $formLanguages->first()?->code)"
                            id="galleryItemLangTabs">
                            @include('admin.gallery-items._translatable-fields', [
                                'language'    => $language,
                                'translation' => null,
                            ])
                        </x-language-tab-pane>
                    @endforeach
                </x-language-tabs>

                {{-- Form Actions --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                            <a href="{{ route('admin.gallery-items.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>Galeriye Dön
                            </a>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i>Kaydet
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
@endsection
