@extends('layouts.app')

@section('title', 'Hesabım | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Hesap bilgilerinizi görüntüleyin ve yönetin.')
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="row g-4">

                {{-- Sidebar --}}
                <div class="col-lg-3">
                    @include('account.partials.sidebar', ['user' => $user])
                </div>

                {{-- Content --}}
                <div class="col-lg-9">
                    <span class="section__eyebrow"><i class="fa-solid fa-house-user"></i> Hesabım</span>
                    <h1 class="section__title">Hoş geldiniz, {{ $user->first_name }}</h1>
                    <p class="section__lead mb-4">Hesap bilgilerinizin özetini aşağıda görebilir, profilinizi güncelleyebilirsiniz.</p>

                    <div class="field-card">
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <div class="contact-info__label">Ad Soyad</div>
                                <div class="contact-info__value">{{ $user->full_name }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">E-posta</div>
                                <div class="contact-info__value">{{ $user->email }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">Telefon</div>
                                <div class="contact-info__value">{{ $user->phone ?: '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">Üyelik Tarihi</div>
                                <div class="contact-info__value">{{ $user->created_at->translatedFormat('d M Y') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="contact-info__label">Rol</div>
                                <div class="contact-info__value">
                                    @forelse($user->roles as $role)
                                        {{ $role->name }}@if(!$loop->last), @endif
                                    @empty
                                        Üye
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <hr class="divider my-4">

                        <a href="{{ route('account.profile') }}" class="btn btn-primary">
                            <i class="fa-solid fa-user-pen"></i> Profili Düzenle
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
