@extends('layouts.admin')

@section('title', $day->exists ? 'Özel Gün Düzenle' : 'Yeni Özel Gün')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-calendar-heart text-warning me-2"></i>
                {{ $day->exists ? 'Özel Gün Düzenle' : 'Yeni Özel Gün' }}
            </h2>
            <small class="text-muted">{{ $day->exists ? $day->name : 'Sektörel/lokal/manuel özel günler için' }}</small>
        </div>
        <a href="{{ route('admin.special-days.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form method="POST" action="{{ $day->exists ? route('admin.special-days.update', $day->id) : route('admin.special-days.store') }}">
        @csrf
        @if($day->exists) @method('PUT') @endif

        <div class="card-dark mb-4">
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tarih <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control form-control-theme" required
                               value="{{ old('date', $day->exists ? $day->date->toDateString() : now()->toDateString()) }}">
                        @error('date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ad <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-theme" required
                               maxlength="150" placeholder="Örn: Çiftlik Açılış Yıldönümü"
                               value="{{ old('name', $day->name) }}">
                        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="category" class="form-select form-control-theme" required>
                            @foreach(\App\Models\SpecialDay::CATEGORIES as $cat)
                                <option value="{{ $cat }}" {{ old('category', $day->category) === $cat ? 'selected' : '' }}>
                                    {{ ucfirst($cat) }}
                                </option>
                            @endforeach
                        </select>
                        @error('category')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hareketli mi?</label>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="is_movable" value="0">
                            <input class="form-check-input" type="checkbox" name="is_movable" value="1" id="isMovable"
                                   {{ old('is_movable', $day->is_movable) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isMovable">
                                Tarih her yıl değişiyor (Anneler Günü, Ramazan vs.)
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tema (içerik fikri — bulk import AI prompt'unda kullanılır)</label>
                        <textarea name="theme" class="form-control form-control-theme" rows="3" maxlength="1000"
                                  placeholder="Örn: Anne emeği, anneye özel kahvaltı paketi (sıcak, samimi, ev hissi)">{{ old('theme', $day->theme) }}</textarea>
                        @error('theme')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notlar (opsiyonel — sadece admin'e görünür)</label>
                        <textarea name="notes" class="form-control form-control-theme" rows="2" maxlength="2000"
                                  placeholder="Bu gün için özel notlar (Diyanet teyit linki vs.)">{{ old('notes', $day->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('admin.special-days.index') }}" class="btn-glass">İptal</a>
            <button type="submit" class="btn-teal">
                <i class="bi bi-check-lg me-1"></i> {{ $day->exists ? 'Güncelle' : 'Kaydet' }}
            </button>
        </div>
    </form>
</div>
@endsection
