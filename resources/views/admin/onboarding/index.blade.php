@extends('layouts.admin')

@section('title', 'Kurulum Sihirbazı')

@push('styles')
<style>
    .ob-step { transition: transform .15s, box-shadow .15s; }
    .ob-step:hover { transform: translateY(-2px); }
    .ob-step--done { border-left: 4px solid #198754; }
    .ob-step--next { border-left: 4px solid #ffc107; box-shadow: 0 0 0 .15rem rgba(255, 193, 7, .25); }
    .ob-step--pending { border-left: 4px solid #adb5bd; }
    .ob-num { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
    .ob-num--done { background: #d1e7dd; color: #0a3622; }
    .ob-num--next { background: #fff3cd; color: #664d03; }
    .ob-num--pending { background: #e9ecef; color: #6c757d; }
</style>
@endpush

@section('content')
<div class="container py-4 ob-page">
    <div class="text-center mb-4">
        <i class="bi bi-magic text-warning" style="font-size: 3rem;"></i>
        <h2 class="page-title mt-2">Kurulum Sihirbazı</h2>
        <p class="text-muted">
            Sitenizi tamamen çalışır hale getirmek için aşağıdaki {{ $total }} adımı tamamlayın.
        </p>
    </div>

    {{-- Progress bar --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <strong>İlerleme</strong>
                <span class="text-muted">{{ $completed }} / {{ $total }} adım — %{{ $percent }}</span>
            </div>
            <div class="progress" style="height: 12px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%"></div>
            </div>
        </div>
    </div>

    @if ($isComplete)
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Tebrikler!</strong> Kurulum tamamlandı. Sihirbaz tekrar görüntülenmeyecek.
        </div>
    @endif

    {{-- Steps --}}
    @foreach ($steps as $step)
        @php
            $isNext = ! $step['done'] && $nextStep && $nextStep['step'] === $step['step'];
            $cardCls = $step['done'] ? 'ob-step--done' : ($isNext ? 'ob-step--next' : 'ob-step--pending');
            $numCls  = $step['done'] ? 'ob-num--done' : ($isNext ? 'ob-num--next' : 'ob-num--pending');
        @endphp
        <div class="card ob-step {{ $cardCls }} mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="ob-num {{ $numCls }}">
                        @if ($step['done'])
                            <i class="bi bi-check-lg"></i>
                        @else
                            {{ $step['step'] }}
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1">
                            {{ $step['title'] }}
                            @if ($step['done'])
                                <span class="badge bg-success ms-2">Tamamlandı</span>
                            @elseif ($isNext)
                                <span class="badge bg-warning text-dark ms-2">Sıradaki</span>
                            @endif
                        </h5>
                        <p class="text-muted mb-0 small">{{ $step['description'] }}</p>
                    </div>
                    <div>
                        @if ($step['route'])
                            <a href="{{ route($step['route']) }}" class="btn btn-{{ $step['done'] ? 'outline-secondary' : 'primary' }}">
                                @if ($step['done'])
                                    <i class="bi bi-pencil me-1"></i> Düzenle
                                @else
                                    <i class="bi bi-arrow-right me-1"></i> Ayarla
                                @endif
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Actions --}}
    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-house me-1"></i> Dashboard'a Dön
        </a>

        @if (! $isComplete)
            <form method="POST" action="{{ route('admin.onboarding.complete') }}">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Kurulumu Tamamla
                </button>
            </form>
        @else
            @if (app()->environment(['local', 'development']))
                <form method="POST" action="{{ route('admin.onboarding.reset') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Sıfırla (dev)
                    </button>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection
