@extends('layouts.admin')

@section('title', 'Yeni Push Duyurusu')
@section('page_title', 'Yeni Push Duyurusu')

@section('content')
    @php
        use App\Enums\PushAudience;
        use App\Http\Requests\Admin\StorePushNotificationRequest as Kural;

        $currentAudience = old('audience', PushAudience::All->value);
    @endphp

    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.push-notifications.index') }}" class="breadcrumb-link">Push Duyuruları</a></li>
            <li class="breadcrumb-item active text-teal">Yeni Duyuru</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.push-notifications.store') }}" data-validate novalidate>
        @csrf

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">Yeni Push Duyurusu</h1>
                <p class="page-subtitle">Kaydettiğiniz anda sıraya girer; en geç {{ $interval }} dakika içinde gönderilmeye başlar</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.push-notifications.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-send"></i> Sıraya Al</button>
            </div>
        </div>

        @unless($configured)
            <div class="alert alert-warning d-flex align-items-start gap-2" data-aos="fade-up">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <strong>Bildirim taşıyıcısı yapılandırılmamış.</strong>
                    Duyuru kaydedilir ve sıraya alınır ama hiçbir cihaza ulaşmaz.
                    Ayar <code>.env</code> dosyasındaki <code>PUSH_DRIVER</code> ve
                    <code>FCM_SERVER_KEY</code> değerleriyle yapılır.
                </div>
            </div>
        @endunless

        <div class="row g-4">
            {{-- LEFT: duyurunun kendisi --}}
            <div class="col-xl-8">
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-bell me-2 text-teal"></i>Duyuru</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="stg-field">
                            <label class="stg-label" for="title">
                                Başlık <span class="text-neon-red">*</span>
                            </label>
                            <input type="text" class="stg-input @error('title') is-invalid @enderror"
                                   id="title" name="title"
                                   value="{{ old('title') }}"
                                   placeholder="Bildirimde kalın yazıyla görünecek satır"
                                   autocomplete="off"
                                   maxlength="{{ Kural::TITLE_MAX }}"
                                   data-push-preview="title"
                                   data-validation-engine="validate[required,maxSize[{{ Kural::TITLE_MAX }}]]">
                            <div class="d-flex justify-content-between mt-1">
                                <small class="stg-hint">Telefonun kilit ekranında ilk görünen satır. Kısa tutun.</small>
                                <small class="stg-hint"><span id="titleCount">{{ mb_strlen((string) old('title', '')) }}</span>/{{ Kural::TITLE_MAX }}</small>
                            </div>
                            @error('title') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="stg-field">
                            <label class="stg-label" for="body">
                                Metin <span class="text-neon-red">*</span>
                            </label>
                            <textarea class="stg-textarea @error('body') is-invalid @enderror"
                                      id="body" name="body" rows="4"
                                      placeholder="Duyurunun kendisi"
                                      maxlength="{{ Kural::BODY_MAX }}"
                                      data-push-preview="body"
                                      data-validation-engine="validate[required,maxSize[{{ Kural::BODY_MAX }}]]">{{ old('body') }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="stg-hint">Uzun metinler cihazda kırpılır; ilk iki satır her zaman görünür.</small>
                                <small class="stg-hint"><span id="bodyCount">{{ mb_strlen((string) old('body', '')) }}</span>/{{ Kural::BODY_MAX }}</small>
                            </div>
                            @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="stg-field">
                            <label class="stg-label" for="link">Bağlantı</label>
                            <input type="text" class="stg-input @error('link') is-invalid @enderror"
                                   id="link" name="link"
                                   value="{{ old('link') }}"
                                   placeholder="/blog/duyuru-basligi"
                                   autocomplete="off"
                                   maxlength="{{ Kural::LINK_MAX }}"
                                   data-validation-engine="validate[custom[redirectTarget],maxSize[{{ Kural::LINK_MAX }}]]">
                            <small class="stg-hint">
                                Bildirime dokunulduğunda açılacak yer. Site içi yol
                                (<code>/blog/duyuru</code>) ya da izin verilen bir alan adına
                                tam adres olabilir. Boş bırakılırsa uygulama kendi ana ekranını açar.
                            </small>
                            @error('link') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="60">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-phone me-2 text-teal"></i>Cihazda Nasıl Görünecek</h6>
                    </div>
                    <div class="card-body-custom">
                        {{-- Önizleme, yazılanı olduğu gibi gösteriyor: başlık ile
                             metnin cihazda hangi sırayla okunacağı formda
                             görünmüyordu ve kısaltmanın nereye düştüğü ancak
                             gönderdikten sonra anlaşılıyordu. --}}
                        <div class="cmp-row">
                            <span class="cmp-row__icon cmp-row__icon--teal"><i class="bi bi-app"></i></span>
                            <span class="cmp-row__text">
                                <span class="cmp-row__name" id="previewTitle">{{ old('title') ?: 'Başlık' }}</span>
                                <span class="cmp-row__subject" id="previewBody">{{ old('body') ?: 'Duyuru metni burada görünür.' }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: hedef --}}
            <div class="col-xl-4">
                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-people-fill me-2 text-teal"></i>Kimlere Gidecek</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="cmp-choices">
                            @foreach($audiences as $audience)
                                <label class="cmp-choice">
                                    <input type="radio" name="audience" data-fv-ignore value="{{ $audience->value }}"
                                           class="js-push-audience"
                                           {{ $currentAudience === $audience->value ? 'checked' : '' }}>
                                    <span class="cmp-choice__mark" aria-hidden="true"></span>
                                    <span class="cmp-choice__icon"><i class="bi {{ $audience->icon() }}"></i></span>
                                    <span class="cmp-choice__text">
                                        <strong>{{ $audience->label() }}</strong>
                                        <small>{{ $audience->description() }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('audience') <div class="invalid-feedback d-block mt-2">{{ $message }}</div> @enderror

                        {{-- Rol seçimi --}}
                        <div class="js-push-panel cmp-panel" data-audience="role">
                            <div class="stg-field mb-0">
                                <label class="stg-label" for="roleSelect">Rol <span class="text-neon-red">*</span></label>
                                <select class="stg-select" id="roleSelect" data-fv-ignore>
                                    <option value="">Rol seçin</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}"
                                                {{ (string) old('audience_id') === (string) $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Kullanıcı seçimi --}}
                        <div class="js-push-panel cmp-panel" data-audience="user">
                            <div class="stg-field mb-0">
                                <label class="stg-label" for="userSearch">Kullanıcı <span class="text-neon-red">*</span></label>
                                <input type="text" class="stg-input" id="userSearch"
                                       placeholder="Ad, soyad ya da e-posta ile arayın"
                                       autocomplete="off" data-fv-ignore>
                                {{-- Tam liste basılmıyor: binlerce kullanıcılı bir
                                     kurulumda seçim kutusu sayfadan uzun olurdu. --}}
                                <div class="cmp-check-list mt-2" id="userResults" hidden></div>
                                <small class="stg-hint" id="userChosen">
                                    @if($selectedUser)
                                        Seçili: {{ $selectedUser->full_name }} ({{ $selectedUser->email }})
                                    @else
                                        Henüz kullanıcı seçilmedi.
                                    @endif
                                </small>
                            </div>
                        </div>

                        {{-- Hedefin kimliği: rol ya da kullanıcı, tek alanda. --}}
                        <input type="hidden" name="audience_id" id="audienceId" value="{{ old('audience_id') }}">
                        @error('audience_id') <div class="invalid-feedback d-block mt-2">{{ $message }}</div> @enderror

                        <div class="rdr-meta mt-3">
                            <div class="rdr-meta__row">
                                <span>Ulaşılacak cihaz</span>
                                <strong id="audienceCount">{{ number_format($reach, 0, ',', '.') }}</strong>
                            </div>
                            <div class="rdr-meta__row">
                                <span>Kayıtlı cihaz</span>
                                <strong>{{ number_format($devices, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                        <small class="stg-hint mt-2 d-block">
                            Duyuruyu kapatmış kullanıcılar ve pasife alınmış hesaplar sayıya dâhil değil.
                        </small>
                    </div>
                </div>

                <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="140">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-lightbulb me-2 text-teal"></i>Bilinmesi iyi</h6>
                    </div>
                    <div class="card-body-custom">
                        <ul class="rdr-tips">
                            <li>Duyurunun taslağı yoktur: kaydettiğiniz anda sıraya girer.</li>
                            <li>Gönderim {{ $interval }} dakikada bir çalışan görevle parça parça yapılır; büyük listelerde bir süre "gönderiliyor" görünür.</li>
                            <li>Sıradaki bir duyuru iptal edilebilir; gönderimi başlamış olan <strong>geri alınamaz</strong>.</li>
                            <li>Cihaza ulaşmayan jetonlar gönderim sırasında silinir; "başarısız" sayısı bunu gösterir.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script nonce="{{ csp_nonce() }}">
        window.pushForm = @js([
            'sizeUrl'   => route('admin.push-notifications.audience-size'),
            'searchUrl' => route('admin.push-notifications.users.search'),
        ]);
    </script>
    <script src="{{ versioned_asset('assets/admin/js/push-notification-form.js') }}"></script>
@endpush
