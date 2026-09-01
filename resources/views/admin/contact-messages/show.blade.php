@extends('layouts.admin')

@section('title', 'Mesaj Detayı')
@section('page_title', 'Mesaj Detayı')
@section('page_description', $message->subject)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Mesajlara Dön
    </a>
    <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" id="deleteMessageForm">
        @csrf
        @method('DELETE')
        <button type="button" class="btn btn-sm btn-outline-danger"
                data-confirm-submit="deleteMessageForm"
                data-confirm-title="Silme Onayı"
                data-confirm-message="Bu mesajı silmek istediğinize emin misiniz?"
                data-confirm-text="Evet, Sil"
                data-confirm-icon="bi bi-trash3">
            <i class="bi bi-trash me-1"></i> Sil
        </button>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h6>{{ $message->subject }}</h6>
                <small class="text-muted">{{ $message->created_at->format('d.m.Y H:i') }}</small>
            </div>
            <div class="admin-card-body">
                <div class="mb-4 text-pre-wrap">{{ $message->message }}</div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Sender Info --}}
        <div class="admin-card mb-3">
            <div class="admin-card-header">
                <h6><i class="bi bi-person-fill me-2"></i>Gönderen</h6>
            </div>
            <div class="admin-card-body">
                <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                    <span class="text-muted">Ad Soyad</span>
                    <span class="fw-medium">{{ $message->name }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                    <span class="text-muted">E-posta</span>
                    <a href="mailto:{{ $message->email }}">{{ $message->email }}</a>
                </div>
                @if($message->phone)
                <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                    <span class="text-muted">Telefon</span>
                    <a href="tel:{{ $message->phone }}">{{ $message->phone }}</a>
                </div>
                @endif
                <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                    <span class="text-muted">IP Adresi</span>
                    <span>{{ $message->ip_address }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Tarih</span>
                    <span>{{ $message->created_at->format('d.m.Y H:i') }}</span>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="admin-card">
            <div class="admin-card-header">
                <h6><i class="bi bi-lightning-fill me-2"></i>Hızlı İşlemler</h6>
            </div>
            <div class="admin-card-body">
                <div class="d-grid gap-2">
                    <a href="mailto:{{ $message->email }}?subject=Re: {{ $message->subject }}"
                       class="btn btn-outline-teal">
                        <i class="bi bi-reply me-1"></i> E-posta ile Yanıtla
                    </a>
                    @if($message->phone)
                    <a href="tel:{{ $message->phone }}" class="btn btn-outline-secondary">
                        <i class="bi bi-telephone me-1"></i> Telefon ile Ara
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
