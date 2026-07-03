@extends('layouts.auth')

@section('title', 'Şifre Sıfırla')

@section('content')
<h5 class="text-center mb-4">Yeni Şifre Belirle</h5>

<form method="POST" action="{{ route('password.update') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label">E-posta Adresi</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
            <input type="email" class="form-control @error('email') is-invalid @enderror"
                   id="email" name="email" value="{{ old('email', $email) }}"
                   required readonly>
        </div>
        @error('email')
        <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Yeni Şifre</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" class="form-control @error('password') is-invalid @enderror"
                   id="password" name="password" placeholder="En az 8 karakter" required autofocus>
        </div>
        @error('password')
        <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Şifre Tekrar</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" class="form-control"
                   id="password_confirmation" name="password_confirmation"
                   placeholder="Şifrenizi tekrar girin" required>
        </div>
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-green">
            <i class="fa-solid fa-check me-1"></i> Şifreyi Sıfırla
        </button>
    </div>
</form>
@endsection
