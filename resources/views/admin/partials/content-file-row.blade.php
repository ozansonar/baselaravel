{{--
    Tek ek satırı.

    Aynı biçim JS tarafında da üretiliyor (assets/admin/js/content-files.js):
    kayıtlı ek ile az önce yüklenen ek aynı listede, aynı görünüyor. İkisi ayrı
    çizilseydi kullanıcı hangisinin kaydedildiğini satırın şeklinden çıkarmaya
    çalışırdı.

    İçeriği olmayan ek "bekleyen"dir: o dilin satırı henüz yok. Belirtecini gizli
    alanla taşıyor, satır doğduğunda iliştiriliyor.

    @var \App\Models\ContentFile $file
    @var string $locale
--}}
@php
    $kind = $file->kind();
    $pending = $file->attachable_id === null;
@endphp
<div class="bpf-file bpf-file--{{ $kind->color() }}" data-bpf-item
     @if($pending) data-token="{{ $file->token }}" @else data-file-id="{{ $file->id }}" @endif>
  <span class="bpf-file__icon">
    @if($file->isImage())
      <img src="{{ $file->url() }}" alt="{{ $file->original_name }}" loading="lazy">
    @else
      <i class="bi {{ $kind->icon() }}"></i>
    @endif
  </span>

  <div class="bpf-file__body">
    <div class="bpf-file__top">
      <span class="bpf-file__name" title="{{ $file->original_name }}">{{ $file->original_name }}</span>
      <span class="bpf-file__badge">{{ $kind->label() }}</span>
    </div>
    <div class="bpf-file__meta">
      <span class="bpf-file__ext">.{{ $file->extension }}</span>
      <span>{{ $file->humanSize() }}</span>
    </div>
  </div>

  <div class="bpf-file__actions">
    <a href="{{ $file->url() }}" class="usr-action-btn" target="_blank" rel="noopener" title="Yeni sekmede aç">
      <i class="bi bi-box-arrow-up-right"></i>
    </a>
    <button type="button" class="usr-action-btn danger" data-bpf-remove title="Kaldır">
      <i class="bi bi-trash3"></i>
    </button>
  </div>

  @if($pending)
    <input type="hidden" name="translations[{{ $locale }}][file_tokens][]"
           value="{{ $file->token }}" data-fv-ignore>
  @endif
</div>
