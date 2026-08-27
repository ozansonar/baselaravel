{{-- Shared by create and edit. Vars: $campaign (nullable), $audiences, $roles, $languages, $hourlyLimit, $perRunQuota, $audienceCounts, $subscriberLists --}}
@php
    use App\Enums\CampaignAudience;
    use App\Services\CampaignDispatcher;

    $campaign = $campaign ?? null;
    $currentAudience = old('audience', $campaign?->audience?->value ?? CampaignAudience::Subscribers->value);
    $filter = $campaign?->audience_filter ?? [];

    // Elle girilen alıcılar satır satır tutuluyor. Doğrulama hatasından sonra
    // old() ile, düzenlemede kayıtlı listeyle, ilk açılışta tek boş satırla
    // başlıyor — form her hâlükârda en az bir satır gösteriyor.
    $manualRows = old('manual_rows');

    if ($manualRows === null) {
        $stored = ($campaign && $campaign->audience === CampaignAudience::Manual)
            ? ($filter['recipients'] ?? [])
            : [];

        $manualRows = collect($stored)->map(function (array $recipient): array {
            // Eski kampanyalarda tek parça "name" var; bölünerek okunuyor.
            $legacy = App\Support\PersonName::split($recipient['name'] ?? null);

            return [
                'email'      => $recipient['email'] ?? '',
                'first_name' => $recipient['first_name'] ?? $legacy['first_name'] ?? '',
                'last_name'  => $recipient['last_name'] ?? $legacy['last_name'] ?? '',
            ];
        })->all();
    }

    if ($manualRows === []) {
        $manualRows = [['email' => '', 'first_name' => '', 'last_name' => '']];
    }

    $importedCount = count($filter['recipients'] ?? []);
    $selectedLists = array_map('intval', $filter['list_ids'] ?? []);
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
                        Kişiselleştirme: <code>{first_name}</code> ad, <code>{last_name}</code> soyad,
                        <code>{name}</code> ikisi birlikte, <code>{email}</code> adres,
                        <code>{site_name}</code> site adı.
                    </small>
                    @error('subject') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field">
                    <label class="stg-label" for="body">Mail Metni <span class="text-neon-red">*</span></label>
                    <textarea id="body" name="body" class="stg-textarea campaign-editor @error('body') is-invalid @enderror"
                              data-validation-engine="validate[required]"
                              data-prompt-target="body_error"
                              rows="18">{{ old('body', $campaign?->body) }}</textarea>
                    {{-- The editor hides the textarea, so the message needs its own slot. --}}
                    <div id="body_error"></div>
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
                    <div class="cmp-attachments mb-3">
                        @foreach($campaign->attachments as $attachment)
                            <div class="cmp-attachment">
                                <i class="bi bi-file-earmark-fill"></i>
                                <span class="cmp-attachment__name">{{ $attachment->original_name }}</span>
                                <span class="cmp-attachment__size">{{ $attachment->humanSize() }}</span>
                                <button type="button" class="usr-action-btn danger"
                                        onclick="removeAttachment({{ $attachment->id }})" title="Kaldır">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Seçilen dosyalar buraya yüklenir; kampanya kaydedilince bağlanır. --}}
                <div class="cmp-attachments mb-3" id="pendingAttachments"></div>

                <div class="stg-field">
                    {{-- name yok: dosyalar forma binmiyor, tek tek kendi isteğiyle
                         gidiyor. Hepsi tek POST'ta gitseydi gövde post_max_size'ı
                         aşar, PHP her şeyi atar ve form 419 ile kaybolurdu. --}}
                    <input type="file" class="stg-input @error('attachments.*') is-invalid @enderror"
                           id="attachmentInput" multiple data-fv-ignore>
                    <small class="stg-hint">
                        En fazla {{ $attachmentLimits['max_files'] }} dosya,
                        her biri en fazla {{ $attachmentLimitLabel }}.
                    </small>
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
                {{-- Seçim kartları: işaret solda, metin hemen yanında. Kartın
                     tamamı tıklanabilir olduğu için küçük yuvarlağı bulmak
                     gerekmiyor. --}}
                <div class="cmp-choices">
                    @foreach($audiences as $audience)
                        <label class="cmp-choice">
                            <input type="radio" name="audience" data-fv-ignore value="{{ $audience->value }}"
                                   class="js-audience-radio"
                                   {{ $currentAudience === $audience->value ? 'checked' : '' }}>
                            <span class="cmp-choice__mark" aria-hidden="true"></span>
                            <span class="cmp-choice__icon"><i class="bi {{ $audience->icon() }}"></i></span>
                            <span class="cmp-choice__text">
                                <strong>{{ $audience->label() }}</strong>
                                <small>{{ $audience->description() }}</small>
                            </span>
                            @isset($audienceCounts[$audience->value])
                                <span class="cmp-choice__count">{{ number_format($audienceCounts[$audience->value], 0, ',', '.') }}</span>
                            @endisset
                        </label>
                    @endforeach
                </div>
                @error('audience') <div class="invalid-feedback d-block mt-2">{{ $message }}</div> @enderror

                {{-- Site members --}}
                <div class="js-audience-panel cmp-panel" data-audience="users">
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
                        <div class="cmp-check-list">
                            @foreach($roles as $role)
                                <label class="cmp-check">
                                    <input type="checkbox" name="role_ids[]" data-fv-ignore value="{{ $role->id }}"
                                           {{ in_array($role->id, old('role_ids', $filter['role_ids'] ?? []), false) ? 'checked' : '' }}>
                                    <span class="cmp-check__text">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <small class="stg-hint">Hiçbiri seçilmezse tüm roller dahil edilir.</small>
                    </div>
                </div>

                {{-- Mailing list --}}
                <div class="js-audience-panel cmp-panel" data-audience="subscribers">
                    {{-- Hangi listelere gideceği: hiçbiri seçilmezse liste ayrımı
                         yapılmaz, tüm aboneler hedeflenir. --}}
                    <div class="stg-field">
                        <label class="stg-label">Listeler</label>

                        @forelse($subscriberLists as $list)
                            <label class="cmp-check">
                                <input type="checkbox" name="list_ids[]" data-fv-ignore value="{{ $list->id }}"
                                       {{ in_array($list->id, old('list_ids', $selectedLists), false) ? 'checked' : '' }}>
                                <span class="cmp-check__text">
                                    {{ $list->name }}
                                    @if($list->is_default)
                                        <span class="cmp-check__tag">varsayılan</span>
                                    @endif
                                </span>
                                <span class="cmp-check__count">{{ number_format($list->active_members_count, 0, ',', '.') }}</span>
                            </label>
                        @empty
                            <p class="stg-hint mb-0">
                                Henüz liste yok.
                                <a href="{{ route('admin.subscribers.index') }}" class="text-teal">Mail listesi</a>
                                sayfasından oluşturabilirsiniz.
                            </p>
                        @endforelse

                        @if($subscriberLists->isNotEmpty())
                            <small class="stg-hint">
                                Hiçbirini seçmezseniz tüm abonelere gider. Birden fazla listede olan kişi maili bir kez alır.
                            </small>
                        @endif
                    </div>

                    <div class="stg-toggle-list mt-2">
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
                <div class="js-audience-panel cmp-panel" data-audience="import">
                    <div class="stg-field">
                        <label class="stg-label" for="recipient_file">Excel veya CSV dosyası</label>
                        <input type="file" class="stg-input @error('recipient_file') is-invalid @enderror"
                               id="recipient_file" name="recipient_file" accept=".xlsx,.xls,.ods,.csv,.txt"
                               data-preview-url="{{ route('admin.campaigns.recipients.preview') }}" data-fv-ignore>
                        <small class="stg-hint">
                            Başlık satırında <code>Ad</code>, <code>Soyad</code> ve <code>E-posta</code>
                            sütunları olsun. Ad ile soyadı tek sütunda veren eski dosyalar da okunur.
                        </small>
                        <a href="{{ route('admin.campaigns.template') }}" class="btn-glass btn-sm mt-2">
                            <i class="bi bi-download"></i> Örnek şablonu indir (.xlsx)
                        </a>
                        @error('recipient_file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                        @if($campaign?->audience === CampaignAudience::Import && $importedCount > 0)
                            <div class="cmp-import-note mt-3">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>{{ number_format($importedCount, 0, ',', '.') }} alıcı yüklü. Yeni dosya seçerseniz bu liste değişir.</span>
                            </div>
                        @endif

                        {{-- Dosya seçilir seçilmez okunup ne bulunduğu gösteriliyor;
                             yanlış sütun ya da bozuk adres kampanya kaydedilmeden
                             fark edilsin. --}}
                        <div class="cmp-import-preview mt-3 d-none" id="importPreview" aria-live="polite"></div>
                    </div>
                </div>

                {{-- Typed by hand --}}
                <div class="js-audience-panel cmp-panel" data-audience="manual">
                    <div class="cmp-rows-head">
                        <label class="stg-label mb-0">Alıcılar</label>
                        <span class="cmp-rows-count" id="manualRowsCount"></span>
                    </div>

                    <div class="cmp-rows" id="manualRows">
                        @foreach($manualRows as $index => $row)
                            <div class="cmp-row" data-row>
                                <div class="cmp-row__field">
                                    <label class="cmp-row__label">E-posta <span class="text-neon-red">*</span></label>
                                    <input type="text" class="stg-input"
                                           data-validation-engine="validate[custom[email],maxSize[191]]"
                                           name="manual_rows[{{ $index }}][email]"
                                           value="{{ $row['email'] ?? '' }}" placeholder="ahmet@ornek.com">
                                </div>
                                <div class="cmp-row__field">
                                    <label class="cmp-row__label">Ad</label>
                                    <input type="text" class="stg-input"
                                           data-validation-engine="validate[custom[letters],maxSize[100]]"
                                           data-fv-mask="letters"
                                           name="manual_rows[{{ $index }}][first_name]"
                                           value="{{ $row['first_name'] ?? '' }}" placeholder="Ahmet">
                                </div>
                                <div class="cmp-row__field">
                                    <label class="cmp-row__label">Soyad</label>
                                    <input type="text" class="stg-input"
                                           data-validation-engine="validate[custom[letters],maxSize[100]]"
                                           data-fv-mask="letters"
                                           name="manual_rows[{{ $index }}][last_name]"
                                           value="{{ $row['last_name'] ?? '' }}" placeholder="Yılmaz">
                                </div>
                                <button type="button" class="cmp-row__remove" data-remove-row title="Bu alıcıyı çıkar">
                                    <i class="bi bi-trash3"></i><span>Sil</span>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="cmp-add-row" id="manualAddRow">
                        <i class="bi bi-plus-lg"></i> Alıcı ekle
                    </button>

                    <small class="stg-hint d-block mt-2">
                        E-posta zorunlu, ad ve soyad isteğe bağlı. Girdikleriniz mailde
                        <code>{first_name}</code> ve <code>{last_name}</code> yerine yazılır.
                        Aynı adres iki kez girilirse bir kez gönderilir.
                    </small>

                    @error('manual_rows') <div class="invalid-feedback d-block mt-2">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Sending --}}
        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header-custom">
                <h6><i class="bi bi-send me-2 text-teal"></i>Gönderim</h6>
            </div>
            <div class="card-body-custom">
                {{-- Hız kampanya başına seçilmiyor: listeyi tek seferde boşaltmak
                     gönderen hesabı kısıtlatıyor. Tavan panelin mail ayarlarında. --}}
                <div class="cmp-rate">
                    <div class="cmp-rate__icon"><i class="bi bi-speedometer2"></i></div>
                    <div class="cmp-rate__text">
                        <strong>Saatte {{ number_format($hourlyLimit, 0, ',', '.') }} mail</strong>
                        <small>
                            Her {{ CampaignDispatcher::RUN_INTERVAL_MINUTES }} dakikada
                            {{ number_format($perRunQuota, 0, ',', '.') }} adet gönderilir. Gönderim saate
                            yayılır; mail sağlayıcıları bir anda boşalan listeyi kısıtlar ya da
                            kara listeye alır.
                        </small>
                        <a href="{{ route('admin.settings.index') }}#stg-email" class="cmp-rate__link">
                            <i class="bi bi-sliders"></i> Limiti ayarlardan değiştir
                        </a>
                    </div>
                </div>

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
                    <small class="stg-hint">Mail listesinde "yalnızca kampanya diliyle eşleşenler" seçeneği bu dile bakar.</small>
                </div>

                <div class="stg-field mb-3">
                    <label class="stg-label" for="from_name">Gönderen Adı</label>
                    <input type="text" class="stg-input" id="from_name" name="from_name" data-validation-engine="validate[maxSize[191]]"
                           value="{{ old('from_name', $campaign?->from_name) }}"
                           placeholder="{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}">
                </div>

                <div class="stg-field mb-3">
                    <label class="stg-label" for="from_email">Gönderen Adresi</label>
                    <input type="text" class="stg-input @error('from_email') is-invalid @enderror"
                           id="from_email" name="from_email" data-validation-engine="validate[custom[email],maxSize[191]]" value="{{ old('from_email', $campaign?->from_email) }}"
                           placeholder="{{ config('mail.from.address') }}">
                    <small class="stg-hint">SPF/DKIM kayıtları olmayan bir adres spam'e düşer.</small>
                    @error('from_email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="stg-field">
                    <label class="stg-label" for="reply_to">Yanıt Adresi</label>
                    <input type="text" class="stg-input @error('reply_to') is-invalid @enderror"
                           id="reply_to" name="reply_to" data-validation-engine="validate[custom[email],maxSize[191]]" value="{{ old('reply_to', $campaign?->reply_to) }}">
                    <small class="stg-hint">Alıcı "yanıtla" dediğinde mailin gideceği adres. Boşsa gönderen adresi kullanılır.</small>
                    @error('reply_to') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Yeni alıcı satırının kalıbı; JS bunu kopyalayıp sıra numarasını yazıyor. --}}
<template id="manualRowTemplate">
    <div class="cmp-row" data-row>
        <div class="cmp-row__field">
            <label class="cmp-row__label">E-posta <span class="text-neon-red">*</span></label>
            <input type="text" class="stg-input" name="manual_rows[__INDEX__][email]"
                   data-validation-engine="validate[custom[email],maxSize[191]]" placeholder="ahmet@ornek.com">
        </div>
        <div class="cmp-row__field">
            <label class="cmp-row__label">Ad</label>
            <input type="text" class="stg-input" name="manual_rows[__INDEX__][first_name]"
                   data-validation-engine="validate[custom[letters],maxSize[100]]" data-fv-mask="letters" placeholder="Ahmet">
        </div>
        <div class="cmp-row__field">
            <label class="cmp-row__label">Soyad</label>
            <input type="text" class="stg-input" name="manual_rows[__INDEX__][last_name]"
                   data-validation-engine="validate[custom[letters],maxSize[100]]" data-fv-mask="letters" placeholder="Yılmaz">
        </div>
        <button type="button" class="cmp-row__remove" data-remove-row title="Bu alıcıyı çıkar">
            <i class="bi bi-trash3"></i><span>Sil</span>
        </button>
    </div>
</template>
