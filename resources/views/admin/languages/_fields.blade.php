{{-- Shared by the add and edit modals. Var: $language (nullable) --}}
<div class="row g-3">
    <div class="col-6">
        <div class="stg-field">
            <label class="stg-label" for="code{{ $language?->id }}">Dil Kodu <span class="text-neon-red">*</span></label>
            <input type="text" class="stg-input" id="code{{ $language?->id }}" name="code"
                   value="{{ old('code', $language?->code) }}" maxlength="2" required
                   placeholder="de" pattern="[a-z]{2}" autocomplete="off">
            <small class="stg-hint">İki küçük harf (ISO 639-1): tr, en, de, fr, it</small>
        </div>
    </div>
    <div class="col-6">
        <div class="stg-field">
            <label class="stg-label" for="flag{{ $language?->id }}">Bayrak</label>
            <input type="text" class="stg-input" id="flag{{ $language?->id }}" name="flag"
                   value="{{ old('flag', $language?->flag) }}" maxlength="16" placeholder="🇩🇪">
            <small class="stg-hint">Emoji, dil seçicide görünür</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stg-field">
            <label class="stg-label" for="name{{ $language?->id }}">Adı <span class="text-neon-red">*</span></label>
            <input type="text" class="stg-input" id="name{{ $language?->id }}" name="name"
                   value="{{ old('name', $language?->name) }}" required placeholder="Almanca">
            <small class="stg-hint">Panelde görünen ad</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stg-field">
            <label class="stg-label" for="native{{ $language?->id }}">Kendi Dilinde</label>
            <input type="text" class="stg-input" id="native{{ $language?->id }}" name="native_name"
                   value="{{ old('native_name', $language?->native_name) }}" placeholder="Deutsch">
            <small class="stg-hint">Ziyaretçiye gösterilen ad</small>
        </div>
    </div>
    <div class="col-6">
        <div class="stg-field">
            <label class="stg-label" for="sort{{ $language?->id }}">Sıra</label>
            <input type="number" class="stg-input" id="sort{{ $language?->id }}" name="sort_order"
                   value="{{ old('sort_order', $language?->sort_order ?? 0) }}" min="0" max="255">
            <small class="stg-hint">Dil seçicideki sıra</small>
        </div>
    </div>
    <div class="col-6 d-flex align-items-center">
        <div class="stg-toggle-item w-100">
            <div class="stg-toggle-info">
                <span>Yayında</span>
                <small>{{ $language?->is_default ? 'Varsayılan dil kapatılamaz' : 'Kapalıysa sitede görünmez' }}</small>
            </div>
            <label class="stg-switch">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $language?->is_active ?? true) ? 'checked' : '' }}
                       {{ $language?->is_default ? 'disabled' : '' }}>
                <span class="stg-switch-slider"></span>
            </label>
        </div>
    </div>
</div>
