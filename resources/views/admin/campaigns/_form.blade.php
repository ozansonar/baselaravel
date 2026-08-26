{{-- Shared by create and edit. Vars: $campaign (nullable), $audiences, $roles, $languages, $hourlyLimit, $perRunQuota --}}
@php
    $campaign = $campaign ?? null;
    $currentAudience = old('audience', $campaign?->audience?->value ?? App\Enums\CampaignAudience::Subscribers->value);
    $filter = $campaign?->audience_filter ?? [];
@endphp

<div class="row g-4">
    {{-- LEFT: content --}}
    <div class="col-xl-8">
        <div class="card-dark mb-4" data-aos="fade-up">
            <div class="card-header-custom">
                <h6><i class="bi bi-pencil-square me-2 text-teal"></i>Mail İçeriği</h6>
            </div>
            <div class="card-body-custom">
                <div class="stg-field mb-3">
                    <label class="stg-label" for="name">Kampanya Adı <span class="text-neon-red">*</span></label>
                    <input type="text" class="stg-input @error('name') is-invalid @enderror"
                           id="name" name="name" data-validation-engine="validate[required,maxSize[191]]" value="{{ old('name', $campaign?->name) }}"
                           placeholder="Ağustos Bülteni">
                    <small class="stg-hint">Yalnızca panelde görünür, alıcılar görmez.</small>
                    @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field mb-3">
                    <label class="stg-label" for="subject">Mail Konusu <span class="text-neon-red">*</span></label>
                    <input type="text" class="stg-input @error('subject') is-invalid @enderror"
                           id="subject" name="subject" data-validation-engine="validate[required,maxSize[191]]" value="{{ old('subject', $campaign?->subject) }}"
                           placeholder="Merhaba {name}, bu ayın haberleri">
                    <small class="stg-hint">
                        Kişiselleştirme: <code>{name}</code> alıcının adı, <code>{email}</code> adresi,
                        <code>{site_name}</code> site adı.
                    </small>
                    @error('subject') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field">
                    <label class="stg-label" for="body">Mail Metni <span class="text-neon-red">*</span></label>
                    <textarea id="body" name="body" class="stg-textarea campaign-editor @error('body') is-invalid @enderror"
                              rows="18">{{ old('body', $campaign?->body) }}</textarea>
                    <small class="stg-hint">
                        Görsel eklemek için araç çubuğundaki resim düğmesini kullanın. Görseller mailin
                        içine gömülerek gönderilir, böylece alıcının mail programı görselleri engellese
                        bile görünür.
                    </small>
                    @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Attachments --}}
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header-custom">
                <h6><i class="bi bi-paperclip me-2 text-teal"></i>Ekler</h6>
            </div>
            <div class="card-body-custom">
                @if($campaign && $campaign->attachments->isNotEmpty())
                    <div class="mb-3">
                        @foreach($campaign->attachments as $attachment)
                            <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                <div>
                                    <i class="bi bi-file-earmark me-2"></i>{{ $attachment->original_name }}
                                    <small class="text-clr-secondary ms-2">{{ $attachment->humanSize() }}</small>
                                </div>
                                <button type="button" class="usr-action-btn danger"
                                        onclick="removeAttachment({{ $attachment->id }})" title="Kaldır">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="stg-field">
                    <input type="file" class="stg-input @error('attachments.*') is-invalid @enderror"
                           name="attachments[]" multiple>
                    <small class="stg-hint">En fazla 10 dosya, her biri en fazla 10 MB.</small>
                    @error('attachments.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT: audience & sending --}}
    <div class="col-xl-4">
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header-custom">
                <h6><i class="bi bi-people-fill me-2 text-teal"></i>Kimlere Gidecek</h6>
            </div>
            <div class="card-body-custom">
                @foreach($audiences as $audience)
                    <label class="stg-toggle-item cursor-pointer d-flex align-items-start gap-2 mb-2">
                        <input type="radio" name="audience" data-fv-ignore value="{{ $audience->value }}"
                               class="js-audience-radio mt-1"
                               {{ $currentAudience === $audience->value ? 'checked' : '' }}>
                        <div class="stg-toggle-info">
                            <span><i class="bi {{ $audience->icon() }} me-1"></i>{{ $audience->label() }}</span>
                            <small>{{ $audience->description() }}</small>
                        </div>
                    </label>
                @endforeach
                @error('audience') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                <hr class="my-3">

                {{-- Site members --}}
                <div class="js-audience-panel" data-audience="users">
                    <div class="stg-toggle-list">
                        <div class="stg-toggle-item">
                            <div class="stg-toggle-info"><span>Yalnızca aktif üyeler</span></div>
                            <label class="stg-switch">
                                <input type="checkbox" name="active_only" data-fv-ignore value="1"
                                       {{ old('active_only', $filter['active_only'] ?? true) ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                        <div class="stg-toggle-item">
                            <div class="stg-toggle-info"><span>Yalnızca e-postası doğrulanmış</span></div>
                            <label class="stg-switch">
                                <input type="checkbox" name="verified_only" data-fv-ignore value="1"
                                       {{ old('verified_only', $filter['verified_only'] ?? false) ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="stg-field mt-3">
                        <label class="stg-label">Roller</label>
                        @foreach($roles as $role)
                            <label class="d-block">
                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"
                                       {{ in_array($role->id, old('role_ids', $filter['role_ids'] ?? []), false) ? 'checked' : '' }}>
                                {{ $role->name }}
                            </label>
                        @endforeach
                        <small class="stg-hint">Hiçbiri seçilmezse tüm roller dahil edilir.</small>
                    </div>
                </div>

                {{-- Mailing list --}}
                <div class="js-audience-panel" data-audience="subscribers">
                    <div class="stg-toggle-list">
                        <div class="stg-toggle-item">
                            <div class="stg-toggle-info">
                                <span>Yalnızca kampanya diliyle eşleşenler</span>
                                <small>Abonenin kayıt olduğu dil</small>
                            </div>
                            <label class="stg-switch">
                                <input type="checkbox" name="match_locale" data-fv-ignore value="1"
                                       {{ old('match_locale', $filter['match_locale'] ?? false) ? 'checked' : '' }}>
                                <span class="stg-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Excel / CSV --}}
                <div class="js-audience-panel" data-audience="import">
                    <div class="stg-field">
                        <label class="stg-label" for="recipient_file">Excel veya CSV dosyası</label>
                        <input type="file" class="stg-input @error('recipient_file') is-invalid @enderror"
                               id="recipient_file" name="recipient_file" accept=".xlsx,.xls,.ods,.csv,.txt">
                        <small class="stg-hint">
                            Başlık satırında <code>Ad</code> ve <code>E-posta</code> sütunları olsun.
                            Başlık yoksa adresler ilk sütundan okunur.
                        </small>
                        <a href="{{ route('admin.campaigns.template') }}" class="btn-glass btn-sm mt-2">
                            <i class="bi bi-download"></i> Örnek şablonu indir (.xlsx)
                        </a>
                        @error('recipient_file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @if(!empty($filter['recipients']))
                            <div class="alert alert-info mt-2 mb-0 py-2">
                                <i class="bi bi-check-circle me-1"></i>
                                {{ count($filter['recipients']) }} alıcı yüklü. Yeni dosya yüklerseniz bu liste değişir.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Typed by hand --}}
                <div class="js-audience-panel" data-audience="manual">
                    <div class="stg-field">
                        <label class="stg-label" for="manual_recipients">Alıcılar</label>
                        <textarea class="stg-textarea @error('manual_recipients') is-invalid @enderror"
                                  id="manual_recipients" name="manual_recipients" rows="8"
                                  placeholder="Ahmet Yılmaz &lt;ahmet@ornek.com&gt;&#10;Ayşe Demir;ayse@ornek.com&#10;bilgi@ornek.com">{{ old('manual_recipients', $campaign && $campaign->audience === App\Enums\CampaignAudience::Manual ? collect($filter['recipients'] ?? [])->map(fn ($r) => ($r['name'] ?? '') ? "{$r['name']} <{$r['email']}>" : $r['email'])->implode("\n") : '') }}</textarea>
                        <small class="stg-hint">
                            Her satıra bir kişi. <code>Ad Soyad &lt;mail@ornek.com&gt;</code>,
                            <code>Ad Soyad;mail@ornek.com</code> veya yalnızca adres.
                        </small>
                        @error('manual_recipients') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Sending --}}
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header-custom">
                <h6><i class="bi bi-send me-2 text-teal"></i>Gönderim</h6>
            </div>
            <div class="card-body-custom">
                <div class="stg-toggle-list mb-3">
                    <div class="stg-toggle-item">
                        <div class="stg-toggle-info">
                            <span>Yayarak gönder</span>
                            <small>Saatte {{ $hourlyLimit }} mail, her {{ App\Services\CampaignDispatcher::RUN_INTERVAL_MINUTES }} dakikada {{ $perRunQuota }} adet</small>
                        </div>
                        <label class="stg-switch">
                            <input type="checkbox" name="throttled" data-fv-ignore value="1"
                                   {{ old('throttled', $campaign?->throttled ?? true) ? 'checked' : '' }}>
                            <span class="stg-switch-slider"></span>
                        </label>
                    </div>
                </div>
                <small class="stg-hint d-block mb-3">
                    Kapatırsanız saatlik limit dolana kadar aralıksız gönderilir. Limit her hâlükârda aşılmaz.
                </small>

                <div class="stg-field mb-3">
                    <label class="stg-label" for="locale">Dil</label>
                    <select class="stg-select" id="locale" name="locale" data-fv-ignore>
                        <option value="">Belirtme</option>
                        @foreach($languages as $language)
                            <option value="{{ $language->code }}" {{ old('locale', $campaign?->locale) === $language->code ? 'selected' : '' }}>
                                {{ $language->flag }} {{ $language->native_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="stg-field mb-3">
                    <label class="stg-label" for="from_name">Gönderen Adı</label>
                    <input type="text" class="stg-input" id="from_name" name="from_name" data-validation-engine="validate[maxSize[191]]"
                           value="{{ old('from_name', $campaign?->from_name) }}"
                           placeholder="{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}">
                </div>

                <div class="stg-field mb-3">
                    <label class="stg-label" for="from_email">Gönderen Adresi</label>
                    <input type="email" class="stg-input @error('from_email') is-invalid @enderror"
                           id="from_email" name="from_email" data-validation-engine="validate[custom[email],maxSize[191]]" value="{{ old('from_email', $campaign?->from_email) }}"
                           placeholder="{{ config('mail.from.address') }}">
                    <small class="stg-hint">SPF/DKIM kayıtları olmayan bir adres spam'e düşer.</small>
                    @error('from_email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field">
                    <label class="stg-label" for="reply_to">Yanıt Adresi</label>
                    <input type="email" class="stg-input @error('reply_to') is-invalid @enderror"
                           id="reply_to" name="reply_to" data-validation-engine="validate[custom[email],maxSize[191]]" value="{{ old('reply_to', $campaign?->reply_to) }}">
                    @error('reply_to') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>
