{{-- Shared by create and edit. Vars: $language (nullable) --}}
@php $language = $language ?? null; @endphp

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom">
                <h6><i class="bi bi-translate me-2 text-teal"></i>Dil Bilgileri</h6>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="stg-field">
                            <label class="stg-label" for="code">Dil Kodu <span class="text-neon-red">*</span></label>
                            <input type="text" class="stg-input @error('code') is-invalid @enderror"
                                   id="code" name="code" value="{{ old('code', $language?->code) }}"
                                   maxlength="2" required placeholder="de" pattern="[A-Za-z]{2}" autocomplete="off">
                            <small class="stg-hint">İki harf (ISO 639-1): tr, en, de</small>
                            @error('code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stg-field">
                            <label class="stg-label" for="flag">Bayrak</label>
                            <input type="text" class="stg-input @error('flag') is-invalid @enderror"
                                   id="flag" name="flag" value="{{ old('flag', $language?->flag) }}"
                                   maxlength="16" placeholder="🇩🇪">
                            <small class="stg-hint">Emoji, dil seçicide görünür</small>
                            @error('flag') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stg-field">
                            <label class="stg-label" for="sort_order">Sıra</label>
                            <input type="number" class="stg-input @error('sort_order') is-invalid @enderror"
                                   id="sort_order" name="sort_order"
                                   value="{{ old('sort_order', $language?->sort_order ?? 0) }}" min="0" max="255">
                            <small class="stg-hint">Dil seçicideki sıra</small>
                            @error('sort_order') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="stg-field">
                            <label class="stg-label" for="name">Adı <span class="text-neon-red">*</span></label>
                            <input type="text" class="stg-input @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $language?->name) }}"
                                   required placeholder="Almanca">
                            <small class="stg-hint">Panelde görünen ad</small>
                            @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="stg-field">
                            <label class="stg-label" for="native_name">Kendi Dilinde</label>
                            <input type="text" class="stg-input @error('native_name') is-invalid @enderror"
                                   id="native_name" name="native_name"
                                   value="{{ old('native_name', $language?->native_name) }}" placeholder="Deutsch">
                            <small class="stg-hint">Ziyaretçiye gösterilen ad</small>
                            @error('native_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="80">
            <div class="card-header-custom">
                <h6><i class="bi bi-toggles me-2 text-teal"></i>Yayın Durumu</h6>
            </div>
            <div class="card-body-custom">
                <div class="stg-toggle-list">
                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>Yayında</span>
                            <small>
                                @if($language?->is_default)
                                    Varsayılan dil kapatılamaz
                                @else
                                    Kapalıysa sitede ve dil seçicide görünmez
                                @endif
                            </small>
                        </div>
                        <label class="stg-switch">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $language?->is_active ?? true) ? 'checked' : '' }}
                                   {{ $language?->is_default ? 'disabled' : '' }}>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>
                </div>

                @if($language?->is_default)
                    <div class="alert alert-info mt-3 mb-0 py-2">
                        <small>
                            <i class="bi bi-star-fill me-1"></i>
                            Bu <strong>varsayılan dil</strong>. Değiştirmek için listeden başka bir dili
                            varsayılan yapmalısın.
                        </small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
