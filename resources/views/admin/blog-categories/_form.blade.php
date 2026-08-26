{{-- Shared form partial for blog-categories create/edit --}}
@php
    $isEdit = isset($category);
    $formAction = $isEdit
        ? route('admin.blog-categories.update', $category)
        : route('admin.blog-categories.store');
@endphp

{{-- data-validate hands the form to form-validation.js; the rules themselves
     live on the fields as data-validation-engine attributes. --}}
<form method="POST" action="{{ $formAction }}" id="categoryForm" data-validate novalidate>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    {{-- The value is not posted anywhere; it is there because the plugin
         discards findings on a field whose own value is empty. --}}
    <input type="hidden" id="langGuard" value="1"
           data-validation-engine="validate[funcCall[FormValidation.rules.anyLanguageFilled]]"
           data-prompt-target="langGuardError">
    <div id="langGuardError" class="mb-4">
        @error('translations')
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $message }}
            </div>
        @enderror
    </div>

    {{-- Her dil kendi sekmesinde --}}
    <x-language-tabs :languages="$formLanguages" :model="$isEdit ? $category : null" id="blogCategoryLangTabs">
        @foreach($formLanguages as $language)
            <x-language-tab-pane
                :language="$language"
                :active-locale="old('active_locale', $formLanguages->first()?->code)"
                id="blogCategoryLangTabs">
                @include('admin.blog-categories._translatable-fields', [
                    'language'    => $language,
                    'translation' => $isEdit ? $category->translation($language->code) : null,
                ])
            </x-language-tab-pane>
        @endforeach
    </x-language-tabs>


    <!-- ==================== FORM ACTIONS ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-body-custom">
            <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.blog-categories.index') }}" class="btn-glass">
                        <i class="bi bi-x-lg me-1"></i>Vazgeç
                    </a>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn-teal">
                        <i class="bi bi-check-lg me-1"></i>{{ $isEdit ? 'Güncelle' : 'Kaydet' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

</form>
