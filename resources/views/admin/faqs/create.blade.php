@extends('layouts.admin')

@section('title', 'Yeni Soru')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.faqs.index') }}" class="breadcrumb-link">SSS</a>
            </li>
            <li class="breadcrumb-item active text-teal">Yeni Soru</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-start align-items-sm-center justify-content-between flex-column flex-sm-row gap-3 mb-4" data-aos="fade-down">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.faqs.index') }}" class="btn-glass" title="Geri Dön">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Yeni Soru</h1>
                <p class="page-subtitle mb-0">Sıkça sorulan sorulara yeni bir soru ekleyin</p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('admin.faqs.store') }}">
                @csrf

                {{-- Soru & Cevap --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-teal"><i class="bi bi-question-circle"></i></div>
                            <div>
                                <h6 class="mb-0">Soru & Cevap</h6>
                                <small class="text-muted">Soru metnini ve cevabını yazın</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="question">
                                    Soru <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('question') is-invalid @enderror"
                                       id="question" name="question" value="{{ old('question') }}"
                                       placeholder="Soruyu girin..." required>
                                @error('question')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="answer">
                                    Cevap <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control @error('answer') is-invalid @enderror"
                                          id="answer" name="answer" rows="6"
                                          placeholder="Cevabı yazın..." required>{{ old('answer') }}</textarea>
                                @error('answer')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ayarlar --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-header-custom">
                        <div class="form-section-header mb-0">
                            <div class="form-section-icon bg-icon-purple"><i class="bi bi-gear"></i></div>
                            <div>
                                <h6 class="mb-0">Ayarlar</h6>
                                <small class="text-muted">Sıralama ve durum bilgisi</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sort_order">Sıralama</label>
                                <input type="number"
                                       class="form-control @error('sort_order') is-invalid @enderror"
                                       id="sort_order" name="sort_order"
                                       value="{{ old('sort_order', 0) }}" min="0">
                                @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Düşük değer = Daha üstte</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="is_active">Durum</label>
                                <select class="form-select @error('is_active') is-invalid @enderror"
                                        id="is_active" name="is_active">
                                    <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ old('is_active', 1) == 0 ? 'selected' : '' }}>Pasif</option>
                                </select>
                                @error('is_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="card-dark mb-4" data-aos="fade-up">
                    <div class="card-body-custom">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                            <a href="{{ route('admin.faqs.index') }}" class="btn-glass">
                                <i class="bi bi-arrow-left me-1"></i>SSS Listesine Dön
                            </a>
                            <button type="submit" class="btn-teal">
                                <i class="bi bi-check-lg me-1"></i>Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
