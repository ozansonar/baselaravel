@extends('layouts.admin')

@section('title', 'Yeni Dil')
@section('page_title', 'Yeni Dil')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.languages.index') }}" class="breadcrumb-link">Diller</a></li>
            <li class="breadcrumb-item active text-teal">Yeni Dil</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.languages.store') }}" data-validate novalidate>
        @csrf

        <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
            <div>
                <h1 class="page-title">Yeni Dil</h1>
                <p class="page-subtitle">Siteye yeni bir yayın dili ekleyin</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.languages.index') }}" class="btn-glass">Vazgeç</a>
                <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Kaydet</button>
            </div>
        </div>

        {{-- Dil eklemenin üç adımı formdan önce anlatılıyor: içerik ile arayüz
             metinlerinin ayrı işler olduğunu kaydettikten sonra öğrenen kullanıcı,
             sitesini yarım çevrilmiş sanıyordu. --}}
        <div class="lng-steps mb-4" data-aos="fade-up">
            <div class="lng-step">
                <span class="lng-step__no">1</span>
                <div class="lng-step__body">
                    <strong>Dili tanımlayın</strong>
                    <small>Kod, ad ve bayrak. Hazır listeden seçerseniz alanlar kendiliğinden dolar.</small>
                </div>
            </div>
            <div class="lng-step">
                <span class="lng-step__no">2</span>
                <div class="lng-step__body">
                    <strong>İçeriği girin</strong>
                    <small>Sayfa, yazı ve galeri kayıtları panelde dil sekmeleriyle ayrı ayrı yazılır.</small>
                </div>
            </div>
            <div class="lng-step">
                <span class="lng-step__no">3</span>
                <div class="lng-step__body">
                    <strong>Arayüzü çevirin</strong>
                    <small><code>lang/tr/</code> klasörünü yeni dil koduyla kopyalayıp çevirin; o zamana kadar menü ve butonlar varsayılan dilde görünür.</small>
                </div>
            </div>
        </div>

        @if($suggestions !== [])
            <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="50">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6><i class="bi bi-lightning-charge me-2 text-teal"></i>Hızlı Doldur</h6>
                    <span class="cmp-badge">{{ count($suggestions) }} hazır dil</span>
                </div>
                <div class="card-body-custom">
                    <p class="stg-hint mb-3">
                        Bir dile tıkladığınızda kod, ad, yerel ad ve bayrak aşağıdaki forma dolar;
                        sonrasında hepsini değiştirebilirsiniz. Listede eklediğiniz diller görünmez.
                    </p>

                    <div class="lng-preset-search">
                        <i class="bi bi-search"></i>
                        <label class="visually-hidden" for="presetSearch">Hazır dillerde ara</label>
                        <input type="text" id="presetSearch" class="stg-input stg-input--sm"
                               placeholder="Dil ara: almanca, deutsch, de..." autocomplete="off"
                               data-validation-engine="validate[maxSize[60]]">
                    </div>

                    <div class="lng-preset-grid" id="presetGrid">
                        @foreach($suggestions as $item)
                            <button type="button"
                                    class="uf-role-card lng-preset js-language-preset"
                                    data-code="{{ $item['code'] }}"
                                    data-name="{{ $item['name'] }}"
                                    data-native="{{ $item['native'] }}"
                                    data-flag="{{ $item['flag'] }}"
                                    data-search="{{ $item['name'] }} {{ $item['native'] }} {{ $item['code'] }}">
                                <span class="uf-role-card-icon lng-preset__flag">{{ $item['flag'] }}</span>
                                <span class="uf-role-card-info">
                                    <strong>{{ $item['native'] }}</strong>
                                    <small>{{ $item['name'] }} · {{ $item['code'] }}</small>
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <p class="stg-hint mt-3 mb-0 d-none" id="presetEmpty">
                        <i class="bi bi-search me-1"></i>
                        Bu aramayla eşleşen hazır dil yok — alanları aşağıdan elle doldurabilirsiniz.
                    </p>
                </div>
            </div>
        @else
            <div class="alert alert-info" data-aos="fade-up" data-aos-delay="50">
                <i class="bi bi-check-circle me-1"></i>
                Hazır listedeki dillerin hepsi eklenmiş. Yeni dili aşağıdan elle tanımlayabilirsiniz.
            </div>
        @endif

        @include('admin.languages._form', ['language' => null])

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('admin.languages.index') }}" class="btn-glass">Vazgeç</a>
            <button type="submit" class="btn-teal btn-lg"><i class="bi bi-check-lg"></i> Kaydet</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/languages.js') }}"></script>
@endpush
