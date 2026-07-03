{{-- Shared form partial for campaigns create/edit --}}
@php
    $isEdit = isset($campaign);
    $formAction = $isEdit
        ? route('admin.campaigns.update', $campaign)
        : route('admin.campaigns.store');
@endphp

<form method="POST" action="{{ $formAction }}" id="campaignForm">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <!-- ==================== SECTION 1: TEMEL BİLGİLER ==================== -->
    <div class="card-dark mb-4" id="section-basic" data-aos="fade-up">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-teal"><i class="bi bi-megaphone"></i></div>
                <div>
                    <h6 class="mb-0">Temel Bilgiler</h6>
                    <small class="text-muted">Kampanya adı, türü ve indirim bilgilerini belirleyin</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">

                <!-- Kampanya Adı -->
                <div class="col-md-8">
                    <label class="form-label" for="name">
                        Kampanya Adı <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        value="{{ old('name', $isEdit ? $campaign->name : '') }}"
                        placeholder="Örn: Kış İndirimi 2026"
                        required
                    >
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Kampanya Türü -->
                <div class="col-md-4">
                    <label class="form-label" for="type">
                        Kampanya Türü <span class="text-danger">*</span>
                    </label>
                    <select
                        class="form-select @error('type') is-invalid @enderror"
                        id="type"
                        name="type"
                        required
                    >
                        @unless($isEdit)
                        <option value="">Seçin...</option>
                        @endunless
                        @foreach(\App\Enums\CampaignType::cases() as $type)
                        <option value="{{ $type->value }}"
                            {{ old('type', $isEdit ? $campaign->type->value : '') === $type->value ? 'selected' : '' }}>
                            {{ $type->label() }}
                        </option>
                        @endforeach
                    </select>
                    @error('type')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- İndirim Türü -->
                <div class="col-md-4">
                    <label class="form-label" for="discount_type">
                        İndirim Türü <span class="text-danger">*</span>
                    </label>
                    <select
                        class="form-select @error('discount_type') is-invalid @enderror"
                        id="discount_type"
                        name="discount_type"
                        required
                    >
                        <option value="percentage" {{ old('discount_type', $isEdit ? $campaign->discount_type : '') === 'percentage' ? 'selected' : '' }}>Yüzde (%)</option>
                        <option value="fixed" {{ old('discount_type', $isEdit ? $campaign->discount_type : '') === 'fixed' ? 'selected' : '' }}>Sabit Tutar (₺)</option>
                    </select>
                    @error('discount_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- İndirim Değeri -->
                <div class="col-md-4">
                    <label class="form-label" for="discount_value">
                        İndirim Değeri <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input
                            type="number"
                            class="form-control @error('discount_value') is-invalid @enderror"
                            id="discount_value"
                            name="discount_value"
                            value="{{ old('discount_value', $isEdit ? $campaign->discount_value : '') }}"
                            placeholder="15"
                            step="0.01"
                            min="0"
                            required
                        >
                        <span class="input-group-text" id="discountUnit">%</span>
                    </div>
                    @error('discount_value')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Kupon Kodu -->
                <div class="col-md-4">
                    <label class="form-label" for="code">
                        Kupon Kodu <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        class="form-control @error('code') is-invalid @enderror"
                        id="code"
                        name="code"
                        value="{{ old('code', $isEdit ? $campaign->code : '') }}"
                        placeholder="ORNEK20"
                        required
                    >
                    @error('code')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Açıklama -->
                <div class="col-12">
                    <label class="form-label" for="description">Açıklama</label>
                    <textarea
                        class="form-control @error('description') is-invalid @enderror"
                        id="description"
                        name="description"
                        rows="3"
                        placeholder="Kampanya açıklaması (opsiyonel)..."
                    >{{ old('description', $isEdit ? $campaign->description : '') }}</textarea>
                    @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: KOŞULLAR ==================== -->
    <div class="card-dark mb-4" id="section-conditions" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header-custom">
            <div class="form-section-header mb-0">
                <div class="form-section-icon bg-icon-purple"><i class="bi bi-sliders"></i></div>
                <div>
                    <h6 class="mb-0">Koşullar</h6>
                    <small class="text-muted">Tarih, limit ve minimum sipariş koşullarını ayarlayın</small>
                </div>
            </div>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">

                <!-- Başlangıç Tarihi -->
                <div class="col-md-4">
                    <label class="form-label" for="start_date">
                        Başlangıç Tarihi <span class="text-danger">*</span>
                    </label>
                    <input
                        type="datetime-local"
                        class="form-control @error('start_date') is-invalid @enderror"
                        id="start_date"
                        name="start_date"
                        value="{{ old('start_date', $isEdit ? $campaign->start_date->format('Y-m-d\TH:i') : '') }}"
                        required
                    >
                    @error('start_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Bitiş Tarihi -->
                <div class="col-md-4">
                    <label class="form-label" for="end_date">
                        Bitiş Tarihi <span class="text-danger">*</span>
                    </label>
                    <input
                        type="datetime-local"
                        class="form-control @error('end_date') is-invalid @enderror"
                        id="end_date"
                        name="end_date"
                        value="{{ old('end_date', $isEdit ? $campaign->end_date->format('Y-m-d\TH:i') : '') }}"
                        required
                    >
                    @error('end_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Min. Sipariş Tutarı -->
                <div class="col-md-4">
                    <label class="form-label" for="min_order_amount">Min. Sipariş Tutarı</label>
                    <div class="input-group">
                        <span class="input-group-text">₺</span>
                        <input
                            type="number"
                            class="form-control @error('min_order_amount') is-invalid @enderror"
                            id="min_order_amount"
                            name="min_order_amount"
                            value="{{ old('min_order_amount', $isEdit ? $campaign->min_order_amount : '') }}"
                            step="0.01"
                            min="0"
                            placeholder="100"
                        >
                    </div>
                    @error('min_order_amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Kullanım Limiti -->
                <div class="col-md-6">
                    <label class="form-label" for="usage_limit">Kullanım Limiti</label>
                    <input
                        type="number"
                        class="form-control @error('usage_limit') is-invalid @enderror"
                        id="usage_limit"
                        name="usage_limit"
                        value="{{ old('usage_limit', $isEdit ? $campaign->usage_limit : '') }}"
                        min="0"
                        placeholder="Sınırsız (0 = sınırsız)"
                    >
                    @error('usage_limit')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">0 veya boş bırakırsanız sınırsız kullanım</div>
                </div>

                <!-- Durum -->
                <div class="col-md-6">
                    <label class="form-label">Durum</label>
                    <div class="ca-toggle-list">
                        <div class="ca-toggle-item">
                            <div class="ca-toggle-info">
                                <span>Aktif</span>
                                <small>Kampanya aktif olarak çalışsın</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', $isEdit ? $campaign->is_active : true) ? 'checked' : '' }}
                                >
                            </div>
                        </div>
                    </div>
                    @if($isEdit)
                    <div class="form-text">
                        <i class="bi bi-bar-chart me-1"></i>Toplam kullanım: <strong>{{ $campaign->used_count }}</strong> kez
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>


    <!-- ==================== FORM ACTIONS ==================== -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom">
            <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.campaigns.index') }}" class="btn-glass">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const discountTypeSelect = document.getElementById('discount_type');
    const discountUnit = document.getElementById('discountUnit');

    function updateDiscountUnit() {
        if (discountTypeSelect.value === 'percentage') {
            discountUnit.textContent = '%';
        } else {
            discountUnit.textContent = '₺';
        }
    }

    discountTypeSelect.addEventListener('change', updateDiscountUnit);
    updateDiscountUnit();
});
</script>
@endpush
