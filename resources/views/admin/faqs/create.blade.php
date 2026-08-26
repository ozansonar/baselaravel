@extends('layouts.admin')

@section('title', 'Yeni Soru')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.faqs.index') }}" class="breadcrumb-link">SSS</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Soru</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.faqs.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Soru</h1>
                <p class="page-subtitle mb-0">Sıkça sorulan sorulara yeni bir soru ekleyin</p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('admin.faqs.store') }}" data-validate novalidate>
                @csrf

                {{-- Her dil kendi sekmesinde --}}

                {{-- The value is not posted anywhere; it is there because the plugin discards
                     findings on a field whose own value is empty. --}}
                <input type="hidden" id="langGuard" value="1" data-fv-modal
                       data-validation-engine="validate[funcCall[FormValidation.rules.anyLanguageFilled]]"
                       data-prompt-target="langGuardError">
                <div id="langGuardError" class="mb-4">
                    @error('translations')
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                <x-language-tabs :languages="$formLanguages" id="faqLangTabs">
                    @foreach($formLanguages as $language)
                        <x-language-tab-pane
                            :language="$language"
                            :active-locale="old('active_locale', $formLanguages->first()?->code)"
                            id="faqLangTabs">
                            @include('admin.faqs._translatable-fields', [
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
                            <a href="{{ route('admin.faqs.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>SSS Listesine Dön
                            </a>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i>Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
