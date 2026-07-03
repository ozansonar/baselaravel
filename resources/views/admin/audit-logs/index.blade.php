@extends('layouts.admin')

@section('title', 'Aktivite Logları')

@section('content')
<div class="container-fluid py-4 al-page">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-clock-history text-warning me-2"></i> Aktivite Logları
            </h2>
            <small class="text-muted">Kim ne zaman ne yaptı? — kritik modellerin tüm değişiklik geçmişi</small>
        </div>
    </div>

    {{-- Filtre --}}
    <div class="card-dark mb-4">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted">Event</label>
                    <select name="event" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach($eventTypes as $key => $label)
                            <option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Kullanıcı</label>
                    <select name="user_id" class="form-select form-select-sm form-control-theme">
                        <option value="">Hepsi</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((int) request('user_id') === $u->id)>
                                {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Model</label>
                    <input type="text" name="model" value="{{ request('model') }}" class="form-control form-control-sm form-control-theme"
                           placeholder="InstagramPost">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Başlangıç</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm form-control-theme">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Bitiş</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm form-control-theme">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn-teal btn-sm w-100"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Liste --}}
    <div class="card-dark">
        <div class="card-body-custom p-0">
            @if($logs->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-3 d-block mb-2 opacity-25"></i>
                    <small>Bu filtreyle hiç log bulunamadı.</small>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table mb-0" style="color:#e5e7eb;">
                        <thead>
                            <tr>
                                <th style="width:160px;">Zaman</th>
                                <th style="width:160px;">Kullanıcı</th>
                                <th style="width:110px;">Event</th>
                                <th>Açıklama</th>
                                <th style="width:120px;">IP</th>
                                <th style="width:60px;" class="text-end">Detay</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>
                                        <small>{{ $log->created_at->format('d.m.Y H:i:s') }}</small><br>
                                        <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        @if($log->user)
                                            <small>{{ trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? '')) ?: $log->user->email }}</small>
                                        @else
                                            <small class="text-muted">— sistem —</small>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $log->eventBadgeClass() }}">{{ $log->eventLabel() }}</span></td>
                                    <td>
                                        @if($log->label)
                                            <span>{{ $log->label }}</span>
                                        @else
                                            <small class="text-muted">{{ $log->modelLabel() }} #{{ $log->auditable_id }}</small>
                                        @endif
                                    </td>
                                    <td><code class="small text-muted">{{ $log->ip_address ?? '—' }}</code></td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.audit-logs.show', $log->id) }}" class="btn-glass btn-glass-sm" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">{{ $logs->total() }} kayıt — sayfa {{ $logs->currentPage() }}/{{ $logs->lastPage() }}</small>
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
