@extends('layouts.admin')

@section('title', 'API ve Servis Ayarları')
@section('page_title', 'API ve Servis Ayarları')

@section('content')

<nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
        </li>
        <li class="breadcrumb-item active text-teal">API ve Servisler</li>
    </ol>
</nav>

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3" data-aos="fade-down">
    <div>
        <h1 class="page-title">API ve Servis Ayarları</h1>
        <p class="page-subtitle">
            Üçüncü taraf servislerin anahtarları. Buraya girilen değer
            <strong>kaydettiğiniz anda geçerli olur</strong> — sunucudaki hiçbir dosyaya
            dokunmanız gerekmez.
        </p>
    </div>
</div>

{{-- Kaynak rozetlerinin ne anlama geldiği. Bir alanın "env" görünmesi hata
     değil, bilgi: değer sunucunun .env dosyasından geliyor ve panelden
     doldurulduğu anda onun yerine geçiyor. --}}
<div class="svc-legend mb-4" data-aos="fade-up">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Değerler nereden okunuyor?</strong>
        Önce bu ekran, sonra sunucunun <code>.env</code> dosyası. Bir alanı burada
        doldurduğunuzda <code>.env</code>'deki karşılığı artık kullanılmaz; boşalttığınızda
        yeniden <code>.env</code> geçerli olur. Gizli anahtarlar
        <strong>şifrelenerek</strong> saklanır ve bir daha ekrana basılmaz.
    </div>
</div>

<form action="{{ route('admin.service-credentials.update') }}" method="POST" data-validate novalidate>
    @csrf
    @method('PUT')

    <div class="row g-4">
        @foreach($groups as $groupKey => $group)
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                <div class="card-dark h-100">
                    <div class="card-header-custom">
                        <h6><i class="bi {{ $group['icon'] }} me-2 text-teal"></i>{{ $group['label'] }}</h6>
                    </div>

                    <div class="card-body-custom">
                        <p class="svc-group-desc">{{ $group['description'] }}</p>

                        @if($group['doc'])
                            <a href="{{ $group['doc']['url'] }}" target="_blank" rel="noopener noreferrer"
                               class="svc-doc-link">
                                <i class="bi bi-box-arrow-up-right"></i> {{ $group['doc']['label'] }}
                            </a>
                        @endif

                        @if($groupKey === 'firebase')
                            <div class="svc-status {{ $fcmReady ? 'svc-status--ok' : 'svc-status--idle' }}">
                                <i class="bi {{ $fcmReady ? 'bi-check-circle-fill' : 'bi-dash-circle' }}"></i>
                                {{ $fcmReady
                                    ? 'Anahtar okunabiliyor; bildirim gönderimi hazır.'
                                    : 'Anahtar girilmemiş ya da okunamıyor; bildirimler gönderilmiyor.' }}
                            </div>
                        @endif

                        @foreach($group['fields'] as $key => $field)
                            <div class="stg-field svc-field">
                                <div class="svc-field-head">
                                    <label class="stg-label mb-0" for="svc-{{ $key }}">{{ $field['label'] }}</label>

                                    @if($filled[$key] === 'panel')
                                        <span class="svc-badge svc-badge--panel"><i class="bi bi-check-lg"></i> panelde</span>
                                    @elseif($filled[$key] === 'env')
                                        <span class="svc-badge svc-badge--env"><i class="bi bi-file-earmark-code"></i> .env</span>
                                    @else
                                        <span class="svc-badge svc-badge--empty">boş</span>
                                    @endif
                                </div>

                                @if($field['type'] === 'toggle')
                                    <label class="stg-switch">
                                        <input type="hidden" name="credentials[{{ $key }}]" value="0">
                                        <input type="checkbox" id="svc-{{ $key }}"
                                               name="credentials[{{ $key }}]" value="1"
                                               {{ $values[$key] === '1' ? 'checked' : '' }} data-fv-ignore>
                                        <span class="stg-switch-slider"></span>
                                    </label>
                                @elseif($field['type'] === 'textarea')
                                    <textarea class="stg-textarea" id="svc-{{ $key }}"
                                              name="credentials[{{ $key }}]" rows="4"
                                              placeholder="{{ $field['placeholder'] }}"
                                              spellcheck="false"
                                              data-validation-engine="validate[maxSize[20000]]">{{ $values[$key] }}</textarea>
                                @elseif($field['type'] === 'password')
                                    <input type="password" class="stg-input" id="svc-{{ $key }}"
                                           name="credentials[{{ $key }}]"
                                           value="" autocomplete="new-password" spellcheck="false"
                                           placeholder="{{ $filled[$key] === 'panel' ? '•••••••• (kayıtlı — değiştirmek için yenisini yazın)' : $field['placeholder'] }}"
                                           data-validation-engine="validate[maxSize[20000]]">
                                @else
                                    <input type="text" class="stg-input" id="svc-{{ $key }}"
                                           name="credentials[{{ $key }}]"
                                           value="{{ $values[$key] }}" spellcheck="false"
                                           placeholder="{{ $field['placeholder'] }}"
                                           data-validation-engine="validate[maxSize[20000]]">
                                @endif

                                {{-- Rehber metni: anahtarın nereden alınacağı.
                                     **kalın** işaretleri okunur hâle getiriliyor;
                                     metin kayıt defterinden geliyor, kullanıcıdan
                                     değil, o yüzden kaçırılıp sonra izin veriliyor. --}}
                                <p class="svc-help">
                                    {!! preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($field['help'])) !!}
                                </p>

                                @if($field['env'] !== '')
                                    <p class="svc-env">
                                        <code>.env</code> karşılığı: <code>{{ $field['env'] }}</code>
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Kimlik bilgisinden ibaret olmayan servisler. Bunları da buraya
             taşımak, aynı anahtarı iki formdan yönetilir hâle getirirdi. --}}
        <div class="col-12" data-aos="fade-up">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-signpost-split me-2 text-teal"></i>Kendi ekranında yönetilenler</h6>
                </div>
                <div class="card-body-custom">
                    <p class="svc-group-desc">
                        Bu iki servis yalnız anahtardan ibaret değil — bağlantı testi, gönderim
                        limitleri, mail teması ve bildirim seviyesi de aynı ekranda duruyor.
                        Aynı ayarı iki formdan yönetmek, ikisinin sessizce ayrışması demek olurdu.
                    </p>
                    <div class="svc-links">
                        <a href="{{ route('admin.settings.index') }}#stg-email" class="btn-glass">
                            <i class="bi bi-envelope-at"></i> Mail (SMTP) Ayarları
                        </a>
                        <a href="{{ route('admin.settings.index') }}#stg-system" class="btn-glass">
                            <i class="bi bi-telegram"></i> Telegram Bildirimleri
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="svc-actions" data-aos="fade-up">
        <button type="submit" class="btn-teal">
            <i class="bi bi-check-lg"></i> Kaydet
        </button>
    </div>
</form>

@endsection
