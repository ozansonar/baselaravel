@extends('layouts.admin')

@section('title', 'Yeni İçerik Oluştur')

@section('content')
{{-- data-validate hands the form to form-validation.js; the rules themselves
     live on the fields as data-validation-engine attributes. --}}
<form method="POST" action="{{ route('admin.blog-posts.store') }}" enctype="multipart/form-data" id="blogPostForm" data-validate novalidate>
    @csrf

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('admin.blog-posts.index') }}" class="breadcrumb-link">İçerikler</a>
          </li>
          <li class="breadcrumb-item active text-teal">Yeni İçerik Ekle</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
          <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass" title="Geri Dön">
            <i class="bi bi-arrow-left"></i>
          </a>
          <div>
            <h1 class="page-title mb-0">Yeni İçerik Oluştur</h1>
            <p class="page-subtitle mb-0">Tüm alanları doldurarak yeni bir içerik yayınlayın</p>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button type="submit" class="btn-teal">
            <i class="bi bi-check-lg me-1"></i>Kaydet
          </button>
        </div>
      </div>


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
          <x-language-tabs :languages="$formLanguages" id="postLangTabs">
            @foreach($formLanguages as $language)
              <x-language-tab-pane
                :language="$language"
                :active-locale="old('active_locale', $formLanguages->first()?->code)"
                id="postLangTabs">
                @include('admin.blog-posts._translatable-fields', [
                    'language'    => $language,
                    'translation' => null,
                ])
              </x-language-tab-pane>
            @endforeach
          </x-language-tabs>

          {{-- No language is mandatory on its own, but the post cannot be empty
               in every language; this guard carries that one rule. --}}


          <!-- ==================== FORM ACTIONS ==================== -->
          <div class="card-dark mb-4">
            <div class="card-body-custom">
              <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                <div class="d-flex gap-2">
                  <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass">
                    <i class="bi bi-x-lg me-1"></i>Vazgeç
                  </a>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="submit" class="btn-teal">
                    <i class="bi bi-check-lg me-1"></i>İçeriği Kaydet
                  </button>
                </div>
              </div>
            </div>
          </div>

</form>

@include('partials.admin.tinymce', ['tinymceSelector' => 'textarea[id^=body_]'])
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/content-add.js') }}"></script>
@endpush
