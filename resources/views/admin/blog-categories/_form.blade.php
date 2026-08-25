{{-- Shared form partial for blog-categories create/edit --}}
@php
    $isEdit = isset($category);
    $formAction = $isEdit
        ? route('admin.blog-categories.update', $category)
        : route('admin.blog-categories.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
@endphp

<form method="POST" action="{{ $formAction }}" id="categoryForm">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
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
    <div class="card-dark mb-4">
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
