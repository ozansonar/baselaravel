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

        @if($suggestions !== [])
            <div class="card-dark mb-4" data-aos="fade-up">
                <div class="card-header-custom">
                    <h6><i class="bi bi-lightning-charge me-2 text-teal"></i>Hızlı Doldur</h6>
                </div>
                <div class="card-body-custom">
                    <p class="text-clr-secondary small mb-3">
                        Tıkladığında kod, ad ve bayrak otomatik doldurulur. Sonrasında düzenleyebilirsin.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($suggestions as $item)
                            <button type="button" class="btn-glass btn-sm js-language-preset"
                                    data-code="{{ $item['code'] }}" data-name="{{ $item['name'] }}"
                                    data-native="{{ $item['native'] }}" data-flag="{{ $item['flag'] }}">
                                {{ $item['flag'] }} {{ $item['native'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @include('admin.languages._form', ['language' => null])

        <div class="alert alert-warning" data-aos="fade-up">
            <i class="bi bi-info-circle me-1"></i>
            Yeni dilin <strong>içeriği</strong> panelden dil sekmeleriyle girilir.
            <strong>Arayüz metinleri</strong> için <code>lang/tr/</code> klasörünü yeni dil koduyla
            kopyalayıp çevirmek gerekir; o zamana kadar arayüz varsayılan dilde görünür.
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('admin.languages.index') }}" class="btn-glass">Vazgeç</a>
            <button type="submit" class="btn-teal btn-lg"><i class="bi bi-check-lg"></i> Kaydet</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/languages.js') }}"></script>
@endpush
