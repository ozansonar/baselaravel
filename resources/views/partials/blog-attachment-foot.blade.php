{{--
    Oynatıcının altındaki künye: dosya adı, boyutu ve indirme bağlantısı.
    Video ile ses aynı künyeyi kullanıyor; iki yere ayrı yazılsaydı biri
    değişince öteki geride kalırdı.

    @var \App\Models\BlogPostFile $file
--}}
<div class="att-media__foot">
    <span class="att-media__name" title="{{ $file->original_name }}">{{ $file->original_name }}</span>
    <span class="att-media__meta">
        <span class="att-file__ext">.{{ $file->extension }}</span>
        <span>{{ $file->humanSize() }}</span>
    </span>
    <a href="{{ $file->downloadUrl() }}" class="att-media__dl">
        <i class="fa-solid fa-download"></i><span>{{ __('site.attachments.download') }}</span>
    </a>
</div>
