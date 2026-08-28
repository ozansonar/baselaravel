@extends('layouts.admin')

@section('title', 'İçerik Düzenle')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/vendor/glightbox/css/glightbox.min.css') }}">
@endpush

@section('content')
{{-- data-validate hands the form to form-validation.js; the rules themselves
     live on the fields as data-validation-engine attributes. --}}
<form method="POST" action="{{ route('admin.blog-posts.update', $post) }}" enctype="multipart/form-data" id="blogPostForm" data-validate novalidate>
    @csrf
    @method('PUT')

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('admin.blog-posts.index') }}" class="breadcrumb-link">İçerikler</a>
          </li>
          <li class="breadcrumb-item active text-teal">Düzenle</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
          <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass" title="Geri Dön">
            <i class="bi bi-arrow-left"></i>
          </a>
          <div>
            <h1 class="page-title mb-0">İçerik Düzenle</h1>
            <p class="page-subtitle mb-0">{{ $post->title }}</p>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn-glass"
                  onclick="shareBlogToSocial({{ $post->id }})"
                  title="Bu yazıyı Instagram + Facebook'ta paylaşmak için taslak oluşturur">
            <i class="bi bi-share me-1"></i>Sosyal Medyada Paylaş
          </button>
          <button type="submit" class="btn-teal">
            <i class="bi bi-check-lg me-1"></i>Kaydet
          </button>
        </div>
      </div>

          {{-- Her dil kendi sekmesinde --}}
          <x-language-tabs :languages="$formLanguages" :model="$post" id="postLangTabs">
            @foreach($formLanguages as $language)
              <x-language-tab-pane
                :language="$language"
                :active-locale="old('active_locale', $formLanguages->first()?->code)"
                id="postLangTabs">
                @include('admin.blog-posts._translatable-fields', [
                    'language'    => $language,
                    'translation' => $post->translation($language->code),
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
                    <i class="bi bi-check-lg me-1"></i>Değişiklikleri Kaydet
                  </button>
                </div>
              </div>
            </div>
          </div>

</form>

@include('partials.admin.tinymce', ['tinymceSelector' => 'textarea[id^=body_]'])
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/cover-image.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/slug.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/content-form.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/content-add.js') }}"></script>
<script>
'use strict';

/**
 * Blog yazısını sosyal medya gönderisine dönüştür — POST atar, backend
 * draft InstagramPost oluşturur ve kullanıcıyı edit sayfasına yönlendirir.
 */
function shareBlogToSocial(blogId) {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) {
        if (typeof showToast === 'function') {
            showToast('CSRF token bulunamadı, sayfayı yenile.', 'error');
        }
        return;
    }

    var doSubmit = function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/blog-posts/' + blogId + '/share-to-social';
        form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">';
        document.body.appendChild(form);
        form.submit();
    };

    if (window.AdminModal && typeof AdminModal.confirm === 'function') {
        AdminModal.confirm({
            title: 'Sosyal Medyada Paylaş',
            message: 'Bu blog yazısı için Instagram taslağı oluşturulacak. Caption, hashtag ve Facebook seçeneğini bir sonraki sayfada düzenleyebilirsin.',
            type: 'info',
            confirmText: 'Evet, Taslak Oluştur',
            confirmIcon: 'bi bi-share',
        }).then(function (confirmed) {
            if (confirmed) doSubmit();
        });
    } else {
        doSubmit();
    }
}
</script>
@endpush
