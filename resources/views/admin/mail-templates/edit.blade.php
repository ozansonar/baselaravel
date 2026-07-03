@extends('layouts.admin')

@section('title', $template->name . ' — Mail Şablonu Düzenle')

@section('content')
<nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house"></i> Ana Sayfa</a></li>
        <li><a href="{{ route('admin.mail-templates.index') }}" class="breadcrumb-link">Mail Şablonları</a></li>
        <li class="breadcrumb-item active text-teal">{{ $template->name }}</li>
    </ol>
</nav>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
    <div>
        <h1 class="page-title">{{ $template->name }}</h1>
        <p class="page-subtitle">{{ $template->description }}</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn-glass" id="btnPreview">
            <i class="bi bi-eye me-1"></i> Önizle
        </button>
        <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#resetModal">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Varsayılana Dön
        </button>
    </div>
</div>

<form action="{{ route('admin.mail-templates.update', $template) }}" method="POST" id="templateForm">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Left: Editor --}}
        <div class="col-lg-8" data-aos="fade-up" data-aos-delay="50">
            {{-- Subject --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-chat-left-text me-2 text-teal"></i>E-posta Konusu</h6>
                </div>
                <div class="card-body-custom">
                    <div class="stg-field">
                        <label class="stg-label" for="subject">Konu Satırı</label>
                        <input type="text"
                               class="stg-input @error('subject') is-invalid @enderror"
                               id="subject"
                               name="subject"
                               value="{{ old('subject', $template->subject) }}"
                               placeholder="E-posta konu satırı">
                        @error('subject')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="stg-hint">Değişkenleri kullanabilirsiniz. Örn: <code>{site_name}</code></p>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-file-earmark-code me-2 text-teal"></i>E-posta İçeriği</h6>
                </div>
                <div class="card-body-custom">
                    <textarea id="body"
                              name="body"
                              class="@error('body') is-invalid @enderror">{{ old('body', $template->body) }}</textarea>
                    @error('body')
                        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                    <p class="stg-hint mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Değişkenleri doğrudan yazın: <code>{variable_name}</code> — HTML kaynak kodu için araç çubuğundaki <strong>Kod</strong> butonunu kullanın.
                    </p>
                </div>
            </div>

            {{-- Active Toggle + Save --}}
            <div class="card-dark mb-4">
                <div class="card-body-custom">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="stg-toggle-item border-0 p-0">
                            <div class="stg-toggle-info">
                                <span>Şablon Aktif</span>
                                <small>Pasif olduğunda sistem varsayılan şablonu kullanır</small>
                            </div>
                            <label class="stg-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i> Kaydet
                            </button>
                            <a href="{{ route('admin.mail-templates.index') }}" class="btn-glass">
                                <i class="bi bi-x-lg me-1"></i> İptal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Variables & Help --}}
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
            {{-- Variables Panel --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-braces me-2 text-teal"></i>Kullanılabilir Değişkenler</h6>
                </div>
                <div class="card-body-custom p-0">
                    <div class="mt-var-list">
                        @foreach(($template->variables ?? []) as $variable)
                        <div class="mt-var-item" data-var="{{ '{' . $variable['key'] . '}' }}">
                            <div class="mt-var-info">
                                <code class="mt-var-key">{{ '{' . $variable['key'] . '}' }}</code>
                                <span class="mt-var-label">{{ $variable['label'] }}</span>
                                @if(!empty($variable['example']))
                                    <small class="mt-var-example">Örn: {{ Str::limit($variable['example'], 40) }}</small>
                                @endif
                            </div>
                            <button type="button" class="mt-var-copy" title="Kopyala" data-clipboard="{{ '{' . $variable['key'] . '}' }}">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- CSS Classes Help --}}
            <div class="card-dark mb-4">
                <div class="card-header-custom">
                    <h6><i class="bi bi-palette me-2 text-teal"></i>Kullanılabilir CSS Sınıfları</h6>
                </div>
                <div class="card-body-custom">
                    <div class="mt-css-help">
                        <div class="mt-css-item">
                            <code>em-greeting</code>
                            <small>Üst etiket (küçük, büyük harf)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-heading</code>
                            <small>Ana başlık (24px, kalın)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-heading-sm</code>
                            <small>Alt başlık (18px)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-text</code>
                            <small>Normal metin paragrafı</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-text-sm</code>
                            <small>Küçük açıklama metni</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-btn</code>
                            <small>Ana buton (link etiketi ile)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-btn-wrap</code>
                            <small>Buton kapsayıcı (ortalama)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-info-box</code>
                            <small>Bilgi kutusu (yeşil kenarlık)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-highlight</code>
                            <small>Vurgu kutusu (sarı arka plan)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-divider</code>
                            <small>Yatay ayırıcı çizgi</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-table</code>
                            <small>Tablo (ürün listesi vb.)</small>
                        </div>
                        <div class="mt-css-item">
                            <code>em-badge</code>
                            <small>Durum etiketi (badge)</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Template Info --}}
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-info-circle me-2 text-teal"></i>Şablon Bilgisi</h6>
                </div>
                <div class="card-body-custom">
                    <div class="mt-info-row">
                        <span class="text-muted">Anahtar:</span>
                        <code>{{ $template->key }}</code>
                    </div>
                    <div class="mt-info-row">
                        <span class="text-muted">Oluşturulma:</span>
                        <span>{{ $template->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="mt-info-row">
                        <span class="text-muted">Son Güncelleme:</span>
                        <span>{{ $template->updated_at->format('d.m.Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Reset Modal --}}
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mt-modal-icon mt-modal-icon--warning mb-3">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </div>
                <h5 class="mb-2">Varsayılana Sıfırla</h5>
                <p class="text-muted">
                    <strong>{{ $template->name }}</strong> şablonunun konu ve içeriği varsayılan haline döndürülecek.
                    Bu işlem geri alınamaz.
                </p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                    <form action="{{ route('admin.mail-templates.reset', $template) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-teal">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Sıfırla
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Preview Modal --}}
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2 text-teal"></i>E-posta Önizleme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0">
                <div class="mt-preview-subject-bar">
                    <small class="text-muted">Konu:</small>
                    <strong id="previewSubject"></strong>
                </div>
                <iframe id="previewFrame" class="mt-preview-frame"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@include('partials.admin.tinymce-mail')

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/mail-template-edit.js') }}"></script>
@endpush
