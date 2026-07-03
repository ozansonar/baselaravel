@extends('layouts.admin')

@section('title', 'Toplu Şehir Oluştur')

@section('content')
<form method="POST" action="{{ route('admin.city-pages.bulk-store') }}" id="bulkCityForm">
    @csrf

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.city-pages.index') }}" class="breadcrumb-link">Şehir Sayfaları</a>
            </li>
            <li class="breadcrumb-item active text-teal">Toplu Oluştur</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.city-pages.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Toplu Şehir Oluştur</h1>
                <p class="page-subtitle mb-0">Tier listesinden seçtiğiniz şehirlere temel landing page oluşturulur. Detaylı içerik için her sayfada "AI ile İçerik Üret" kullanın.</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn-teal" id="btnBulkCreate" disabled>
                <i class="bi bi-check2-circle me-1"></i>Seçilenleri Oluştur (<span id="selectedCounter">0</span>)
            </button>
        </div>
    </div>

    {{-- Info Card --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-info-circle-fill text-info fs-3"></i>
                <div>
                    <strong class="d-block mb-1">Nasıl Çalışır?</strong>
                    <small class="text-muted">
                        Aşağıdaki listeden istediğiniz şehirleri işaretleyin → "Seçilenleri Oluştur" butonuna basın.
                        Her şehir için landing page oluşturulur (URL: <code>/sehir-koy-urunleri</code>).
                        Daha önce oluşturulmuş şehirler atlanır.
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- AI Fill Toggle --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <div class="stg-toggle-item border-0 p-0">
                <div class="stg-toggle-info">
                    <span><i class="bi bi-robot me-1 text-teal"></i>AI ile İçerik Üret</span>
                    <small>
                        Açık ise her oluşturulan şehir için Gemini AI çağrılır ve şehre özel başlık,
                        meta, hero ve detaylı HTML içerik otomatik doldurulur. <strong>Her şehir 30-60 saniye sürer</strong>
                        — 5 şehir için ~3 dakika, 10 şehir için ~6 dakika sabırla bekleyin.
                        Pasif bırakırsanız sayfalar boş şablonla oluşur, daha sonra her birinde
                        "AI ile İçerik Üret" butonu kullanabilirsiniz.
                    </small>
                </div>
                <label class="stg-switch">
                    <input type="hidden" name="fill_with_ai" value="0" form="bulkCityForm">
                    <input type="checkbox" name="fill_with_ai" value="1" form="bulkCityForm" {{ old('fill_with_ai') ? 'checked' : '' }}>
                    <span class="stg-switch-slider"></span>
                </label>
            </div>
        </div>
    </div>

    {{-- Tier Lists --}}
    @php $cityIndex = 0; @endphp
    @foreach($cityCatalog as $tierGroup)
        <div class="card-dark mb-4">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon bg-icon-{{ ['teal','purple','blue','orange'][$tierGroup['tier'] - 1] ?? 'teal' }}">
                        <i class="bi bi-{{ $tierGroup['tier'] }}-circle-fill"></i>
                    </div>
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0">{{ $tierGroup['label'] }}</h6>
                            <small class="text-muted">{{ count($tierGroup['cities']) }} şehir mevcut</small>
                        </div>
                        <button type="button" class="btn-glass btn-sm" onclick="toggleTier({{ $tierGroup['tier'] }})">
                            <i class="bi bi-check-all me-1"></i>Tümünü Seç
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    @foreach($tierGroup['cities'] as $city)
                        @php
                            $key   = $cityIndex++;
                            $exists = \App\Models\CityLandingPage::withTrashed()
                                ->where('city_slug', \Illuminate\Support\Str::slug($city['name']))
                                ->exists();
                        @endphp
                        <div class="col-md-6 col-lg-4">
                            <label class="d-flex align-items-start gap-2 p-3 rounded border bulk-city-item {{ $exists ? 'opacity-50' : '' }}" for="city-{{ $key }}">
                                <input type="checkbox"
                                       class="form-check-input mt-1 bulk-city-checkbox"
                                       id="city-{{ $key }}"
                                       data-tier="{{ $tierGroup['tier'] }}"
                                       {{ $exists ? 'disabled' : '' }}
                                       onchange="updateBulkSelection()">

                                <input type="hidden" name="cities[{{ $key }}][city_name]" value="{{ $city['name'] }}" data-input-for="city-{{ $key }}" disabled>
                                <input type="hidden" name="cities[{{ $key }}][region]"    value="{{ $city['region'] }}" data-input-for="city-{{ $key }}" disabled>
                                <input type="hidden" name="cities[{{ $key }}][tier]"      value="{{ $tierGroup['tier'] }}" data-input-for="city-{{ $key }}" disabled>

                                <div class="flex-grow-1">
                                    <strong class="d-block">{{ $city['name'] }}</strong>
                                    <small class="text-muted">{{ $city['region'] }}</small>
                                    @if($exists)
                                        <small class="d-block text-warning"><i class="bi bi-check-circle me-1"></i>Zaten mevcut</small>
                                    @endif
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    {{-- Bottom Actions --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <div class="d-flex justify-content-between flex-wrap gap-3 align-items-center">
                <a href="{{ route('admin.city-pages.index') }}" class="btn-glass">
                    <i class="bi bi-x-lg me-1"></i>Vazgeç
                </a>
                <button type="submit" class="btn-teal" disabled>
                    <i class="bi bi-check2-circle me-1"></i>Seçilenleri Oluştur
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/city-bulk-create.js') }}"></script>
@endpush
