@extends('layouts.app')

@section('title', 'Hesabım | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', 'Hesap bilgilerinizi yönetin.')
@section('robots', 'noindex, nofollow')

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="breadcrumb-custom">
            <a href="{{ route('home') }}"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Hesabım</span>
        </div>
        <h1 class="page-title">Hesabım</h1>
        <p class="page-subtitle">Hesap bilgilerinizi yönetin</p>
    </div>
</header>

{{-- Dashboard Section --}}
<section class="dashboard-section">
    <div class="container">
        <div class="row">
            {{-- Sidebar --}}
            <div class="col-lg-3">
                @include('account.partials.sidebar')
            </div>

            {{-- Content --}}
            <div class="col-lg-9">
                <div class="dashboard-content">
                    <div class="content-header">
                        <h3><i class="fa-solid fa-gauge"></i> Dashboard</h3>
                    </div>

                    {{-- Profile Summary --}}
                    <h4 class="text-green-dark mb-4">Profil Bilgileri</h4>

                    <div class="table-responsive">
                        <table class="orders-table">
                            <tbody>
                                <tr>
                                    <th>Ad Soyad</th>
                                    <td>{{ $user->full_name }}</td>
                                </tr>
                                <tr>
                                    <th>E-posta</th>
                                    <td>{{ $user->email }}</td>
                                </tr>
                                @if($user->phone)
                                <tr>
                                    <th>Telefon</th>
                                    <td>{{ $user->phone }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Üyelik Tarihi</th>
                                    <td>{{ $user->created_at->translatedFormat('d F Y') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('account.profile') }}" class="btn-view">
                            <i class="fa-solid fa-user-pen me-2"></i>Profili Düzenle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
