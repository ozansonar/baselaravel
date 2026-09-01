{{--
    Özel adres formu.

    Hedef açılır listeden seçiliyor: yönetici rota adını elle yazmıyor, yazım
    hatasıyla hiçbir yere gitmeyen bir adres açamıyor. Seçilen hedef parametre
    istiyorsa (dinamik sayfa gibi) o alanlar kendiliğinden görünüyor.

    @var \App\Models\CustomRoute|null $route
--}}
@php
    $seciliHedef = old('target_route', $route?->target_route ?? array_key_first($targets));
    $seciliTur   = old('type', $route?->type?->value ?? \App\Enums\CustomRouteType::Render->value);
    $seciliDil   = old('locale', $route?->locale ?? '');
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom"><h6><i class="bi bi-link-45deg me-2 text-teal"></i>Adres</h6></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="slug">Adres (slug) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-theme">
                            <span class="input-group-text">/{dil}/</span>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror"
                                   id="slug" name="slug" value="{{ old('slug', $route?->slug) }}"
                                   data-validation-engine="validate[required,custom[slug],maxSize[191]]"
                                   placeholder="bize-ulas">
                        </div>
                        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Dil ön ekini yazmayın; sistem koyar. Örnek: <code>bize-ulas</code></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="locale">Dil</label>
                        <select class="form-select @error('locale') is-invalid @enderror" id="locale" name="locale" data-fv-ignore>
                            <option value="all" {{ $seciliDil === '' || $seciliDil === 'all' ? 'selected' : '' }}>Tüm diller</option>
                            @foreach($languages as $language)
                                <option value="{{ $language->code }}" {{ $seciliDil === $language->code ? 'selected' : '' }}>
                                    {{ $language->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('locale')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Dile özgü kayıt, tüm dilleri kapsayandan önce gelir.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="note">Not</label>
                        <input type="text" class="form-control @error('note') is-invalid @enderror"
                               id="note" name="note" value="{{ old('note', $route?->note) }}"
                               data-validation-engine="validate[maxSize[191]]"
                               placeholder="Bu adresi ne için açtınız?">
                        @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card-dark" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header-custom"><h6><i class="bi bi-signpost-2 me-2 text-teal"></i>Hedef</h6></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="target_route">Hedef sayfa <span class="text-danger">*</span></label>
                        <select class="form-select @error('target_route') is-invalid @enderror"
                                id="target_route" name="target_route" data-fv-ignore
                                data-route-params>
                            @foreach($targets as $ad => $etiket)
                                <option value="{{ $ad }}" {{ $seciliHedef === $ad ? 'selected' : '' }}>{{ $etiket }}</option>
                            @endforeach
                        </select>
                        @error('target_route')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    {{-- Hedefin istediği parametreler. Her hedef için ayrı kutu
                         basılıyor, seçime göre biri görünüyor: seçenekler
                         sunucudan geliyor, tarayıcıda uydurulmuyor. --}}
                    @foreach($parameters as $hedef => $alanlar)
                        @continue($alanlar === [])
                        <div class="col-12 custom-route-params" data-target="{{ $hedef }}"
                             @if($seciliHedef !== $hedef) hidden @endif>
                            <div class="row g-3">
                                @foreach($alanlar as $alan)
                                    <div class="col-md-6">
                                        <label class="form-label" for="param_{{ $hedef }}_{{ $alan }}">{{ $alan }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error("target_params.{$alan}") is-invalid @enderror"
                                               id="param_{{ $hedef }}_{{ $alan }}"
                                               @if($seciliHedef === $hedef) name="target_params[{{ $alan }}]" @endif
                                               value="{{ old("target_params.{$alan}", $route?->target_params[$alan] ?? '') }}"
                                               data-validation-engine="validate[maxSize[191]]"
                                               placeholder="{{ $alan }}">
                                        @error("target_params.{$alan}")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header-custom"><h6><i class="bi bi-sliders me-2 text-teal"></i>Davranış</h6></div>
            <div class="card-body-custom">
                <label class="form-label" for="type">Yönlendirme türü <span class="text-danger">*</span></label>
                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" data-fv-ignore
                        data-hint-target="type_hint" data-hint-default="">
                    @foreach($types as $tur)
                        <option value="{{ $tur->value }}" data-hint="{{ $tur->description() }}"
                                {{ $seciliTur === $tur->value ? 'selected' : '' }}>{{ $tur->label() }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text" id="type_hint">
                    {{ (\App\Enums\CustomRouteType::tryFrom($seciliTur) ?? \App\Enums\CustomRouteType::Render)->description() }}
                </div>

                <div class="stg-toggle-list mt-4">
                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>Aktif</span>
                            <small>Kapalıyken adres çalışmaz.</small>
                        </div>
                        <label class="stg-switch">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $route?->is_active ?? true) ? 'checked' : '' }} data-fv-ignore>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Kaydet</button>
            <a href="{{ route('admin.custom-routes.index') }}" class="btn-glass text-center">Vazgeç</a>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ csp_nonce() }}">
    /**
     * Seçilen hedefin istediği alanları gösterir.
     *
     * Gizli kutulardaki girdilerin "name" niteliği kaldırılıyor: kalsaydı
     * form görünmeyen alanları da gönderir ve doğrulama, kullanıcının hiç
     * görmediği bir alan için hata verirdi.
     */
    function customRouteParams(hedef) {
        document.querySelectorAll('.custom-route-params').forEach(function (kutu) {
            var acik = kutu.dataset.target === hedef;
            kutu.hidden = !acik;

            kutu.querySelectorAll('input').forEach(function (girdi) {
                if (acik) {
                    girdi.name = 'target_params[' + girdi.id.split('_').pop() + ']';
                } else {
                    girdi.removeAttribute('name');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var secim = document.getElementById('target_route');
        if (secim) customRouteParams(secim.value);
    });
</script>
@endpush
