{{-- Ekleme ve düzenleme ekranlarının ortak gövdesi. Değişkenler: $redirect (nullable), $statuses --}}
@php $redirect = $redirect ?? null; @endphp

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom">
                <h6><i class="bi bi-signpost-split me-2 text-teal"></i>Yönlendirme</h6>
            </div>
            <div class="card-body-custom">
                <div class="stg-field">
                    <label class="stg-label" for="old_url">
                        Eski adres <span class="text-neon-red">*</span>
                    </label>
                    <input type="text" class="stg-input @error('old_url') is-invalid @enderror"
                           id="old_url" name="old_url"
                           value="{{ old('old_url', $redirect?->old_url) }}"
                           placeholder="/eski-sayfa" autocomplete="off"
                           data-validation-engine="validate[required,custom[sitePath],maxSize[500]]">
                    <small class="stg-hint">
                        Ziyaretçinin girdiği adres. Sitenizdeki yol olarak yazın: <code>/eski-sayfa</code>.
                        Alan adı yazmayın.
                    </small>
                    @error('old_url') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field">
                    <label class="stg-label" for="status_code">
                        Durum kodu <span class="text-neon-red">*</span>
                    </label>
                    <select class="stg-select @error('status_code') is-invalid @enderror"
                            id="status_code" name="status_code"
                            data-validation-engine="validate[required]">
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}"
                                    data-redirects="{{ $status->redirectsSomewhere() ? '1' : '0' }}"
                                    data-description="{{ $status->description() }}"
                                    {{ (int) old('status_code', $redirect?->status_code?->value ?? 301) === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                    {{-- Seçilen kodun ne yaptığı burada yazıyor: 301 ile 302 arasındaki
                         fark SEO'da kalıcı sonuç doğuruyor, kod numarasından anlaşılmıyor. --}}
                    <small class="stg-hint" id="statusDescription"></small>
                    @error('status_code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                {{-- 404 ve 410 bir yere göndermez; hedef alanı o zaman gizleniyor. --}}
                <div class="stg-field" id="newUrlField">
                    <label class="stg-label" for="new_url">
                        Yeni adres <span class="text-neon-red">*</span>
                    </label>
                    <input type="text" class="stg-input @error('new_url') is-invalid @enderror"
                           id="new_url" name="new_url"
                           value="{{ old('new_url', $redirect?->new_url) }}"
                           placeholder="/yeni-sayfa" autocomplete="off"
                           data-validation-engine="validate[required,custom[redirectTarget],maxSize[500]]">
                    <small class="stg-hint">
                        Ziyaretçinin götürüleceği yer. Site içi yol (<code>/yeni-sayfa</code>) ya da
                        izin verilen bir alan adına tam adres olabilir.
                    </small>
                    @error('new_url') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field">
                    <label class="stg-label" for="note">Not</label>
                    <textarea class="stg-textarea @error('note') is-invalid @enderror"
                              id="note" name="note" rows="2"
                              placeholder="Bu yönlendirme neden eklendi?"
                              data-validation-engine="validate[maxSize[500]]">{{ old('note', $redirect?->note) }}</textarea>
                    <small class="stg-hint">Yalnızca panelde görünür; aylar sonra bakan biri için.</small>
                    @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="80">
            <div class="card-header-custom">
                <h6><i class="bi bi-toggles me-2 text-teal"></i>Durum</h6>
            </div>
            <div class="card-body-custom">
                <div class="stg-toggle-list">
                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>Etkin</span>
                            <small>Kapalıyken adres yönlendirilmez, sayfa normal çalışır.</small>
                        </div>
                        <label class="stg-switch">
                            <input type="checkbox" name="is_active" value="1" data-fv-ignore
                                   {{ old('is_active', $redirect?->is_active ?? true) ? 'checked' : '' }}>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>
                </div>

                @if($redirect)
                    <div class="rdr-meta">
                        <div class="rdr-meta__row">
                            <span>Kullanım</span>
                            <strong>{{ number_format($redirect->hit_count) }} kez</strong>
                        </div>
                        <div class="rdr-meta__row">
                            <span>Son kullanım</span>
                            <strong>{{ $redirect->last_hit_at?->format('d.m.Y H:i') ?? 'hiç' }}</strong>
                        </div>
                        <div class="rdr-meta__row">
                            <span>Eklenme</span>
                            <strong>{{ $redirect->created_at?->format('d.m.Y') }}</strong>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="120">
            <div class="card-header-custom">
                <h6><i class="bi bi-lightbulb me-2 text-teal"></i>Bilinmesi iyi</h6>
            </div>
            <div class="card-body-custom">
                <ul class="rdr-tips">
                    <li><strong>301</strong> arama motoruna "burası kalıcı olarak taşındı" der; sıralama değeri yeni adrese geçer.</li>
                    <li><strong>302</strong> geçicidir, değer eski adreste kalır. Emin değilseniz 302 daha güvenlidir.</li>
                    <li>Bir adresi kendine ya da halka oluşturacak şekilde yönlendiremezsiniz; kaydederken uyarılırsınız.</li>
                    <li>Site dışına yönlendirme yalnızca izin verilen alan adları için yapılabilir.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
