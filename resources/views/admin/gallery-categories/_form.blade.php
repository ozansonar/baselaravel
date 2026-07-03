{{-- Shared form partial for gallery-categories create/edit --}}
@php
    $isEdit = isset($category);
    $formAction = $isEdit
        ? route('admin.gallery-categories.update', $category)
        : route('admin.gallery-categories.store');
@endphp

<form method="POST" action="{{ $formAction }}" id="categoryForm">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    {{-- SECTION 1: TEMEL BİLGİLER --}}
    <div class="card-dark mb-4" id="section-basic">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-text-paragraph"></i></div>
                <div>
                    <h6 class="mb-0">Temel Bilgiler</h6>
                    <small class="text-muted">Kategori adını belirleyin</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">

                {{-- Kategori Adı --}}
                <div class="col-12">
                    <label class="form-label" for="name">
                        Kategori Adı <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name', $isEdit ? $category->name : '') }}"
                        placeholder="Kategori adını yazın..."
                        required
                    >
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
        </div>
    </div>

    {{-- SECTION 2: AYARLAR --}}
    <div class="card-dark mb-4" id="section-settings">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-purple"><i class="bi bi-gear"></i></div>
                <div>
                    <h6 class="mb-0">Ayarlar</h6>
                    <small class="text-muted">Sıralama ve görünürlük ayarlarını yapın</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">

                {{-- Sıralama --}}
                <div class="col-12 col-md-6">
                    <label class="form-label" for="sort_order">Sıralama</label>
                    <input
                        type="number"
                        class="form-control @error('sort_order') is-invalid @enderror"
                        id="sort_order"
                        name="sort_order"
                        value="{{ old('sort_order', $isEdit ? $category->sort_order : 0) }}"
                        min="0"
                        max="999"
                    >
                    @error('sort_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Düşük sayı önce gösterilir (0-999)</div>
                </div>

                {{-- Durum --}}
                <div class="col-12 col-md-6">
                    <label class="form-label">Durum</label>
                    <div class="ca-toggle-list">
                        <div class="ca-toggle-item">
                            <div class="ca-toggle-info">
                                <span>Aktif</span>
                                <small>Kategori sitede görünür olsun</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', $isEdit ? $category->is_active : true) ? 'checked' : '' }}
                                >
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- FORM ACTIONS --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.gallery-categories.index') }}" class="btn-glass">
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
