@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var array $previewSettings */
    /** @var bool $fbConfigured */
    /** @var \Illuminate\Database\Eloquent\Collection $products */
    $post            = $post ?? null;
    $previewSettings = $previewSettings ?? ['site_name' => '', 'logo_url' => null, 'ig_username' => ''];
    $fbConfigured    = $fbConfigured ?? false;
    $products        = $products ?? collect();
    $isEdit = $post !== null;
    $isPublished = $isEdit && $post->status === \App\Enums\InstagramPostStatus::Published;
    $hashtagsStr = old('hashtags', $isEdit && is_array($post->hashtags) ? implode(' ', $post->hashtags) : '');
    $scheduledAtValue = old('scheduled_at',
        $isEdit && $post->scheduled_at ? $post->scheduled_at->format('Y-m-d\TH:i') : ''
    );
    $mediaTypeValue = old('media_type',
        $isEdit && $post->media_type ? $post->media_type->value : 'image'
    );

    // Partials için ortak data (Blade @include implicit data passing zaten yapar,
    // ama açıkça liste tutmak referans için faydalı)
    $partialData = compact(
        'post', 'isEdit', 'isPublished', 'mediaTypeValue', 'hashtagsStr',
        'scheduledAtValue', 'previewSettings', 'fbConfigured', 'products',
    );
@endphp

<form method="POST"
      action="{{ $isEdit ? route('admin.instagram-posts.update', $post->id) : route('admin.instagram-posts.store') }}"
      enctype="multipart/form-data"
      id="instagramPostForm"
      data-ig-form
      data-ig-autosave-key="ig-post-draft-{{ $isEdit ? $post->id : 'new' }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <input type="hidden" name="action" id="formAction" value="save_draft">

    @include('admin.instagram-posts._partials.header', $partialData)

    <div class="row g-4">

        {{-- SOL: Form alanları --}}
        <div class="col-lg-8">
            @include('admin.instagram-posts._partials.media-type', $partialData)
            @include('admin.instagram-posts._partials.image-uploader', $partialData)
            @include('admin.instagram-posts._partials.video-uploader', $partialData)
            @include('admin.instagram-posts._partials.carousel', $partialData)
            @include('admin.instagram-posts._partials.ai-caption', $partialData)
            @include('admin.instagram-posts._partials.caption', $partialData)
            @include('admin.instagram-posts._partials.hashtags', $partialData)
        </div>

        {{-- SAĞ: Önizleme + Yayın planı --}}
        <div class="col-lg-4">
            @include('admin.instagram-posts._partials.preview', $partialData)
            @include('admin.instagram-posts._partials.publish-plan', $partialData)
            @include('admin.instagram-posts._partials.log-history', $partialData)
        </div>
    </div>

</form>

{{-- Modallar --}}
@include('admin.instagram-posts._partials.modals.guide')

@if(! $isPublished)
    @include('admin.instagram-posts._partials.modals.cropper')
    @include('admin.instagram-posts._partials.modals.ai-image')
    @include('admin.instagram-posts._partials.modals.vertex-gallery')
@endif

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/cropperjs/cropper.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/cropperjs/cropper.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/admin/js/instagram-posts-form.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vertex-gallery-picker.js') }}" defer></script>
@endpush
