@extends('layouts.admin')

@section('title', 'Aktivite Log Detay')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-clock-history text-warning me-2"></i> Log Detay #{{ $log->id }}
            </h2>
            <small class="text-muted">{{ $log->created_at->format('d.m.Y H:i:s') }}</small>
        </div>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn-glass">
            <i class="bi bi-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card-dark">
                <div class="card-body-custom">
                    <h6 class="mb-3"><i class="bi bi-info-circle me-1"></i> Genel Bilgi</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted">Event</dt>
                        <dd class="col-8"><span class="badge {{ $log->eventBadgeClass() }}">{{ $log->eventLabel() }}</span></dd>

                        <dt class="col-4 text-muted">Açıklama</dt>
                        <dd class="col-8">{{ $log->label ?? '—' }}</dd>

                        <dt class="col-4 text-muted">Model</dt>
                        <dd class="col-8"><code>{{ $log->modelLabel() }} #{{ $log->auditable_id ?? '—' }}</code></dd>

                        <dt class="col-4 text-muted">Kullanıcı</dt>
                        <dd class="col-8">
                            @if($log->user)
                                {{ trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? '')) ?: $log->user->email }}
                            @else
                                <em class="text-muted">— sistem —</em>
                            @endif
                        </dd>

                        <dt class="col-4 text-muted">IP</dt>
                        <dd class="col-8"><code>{{ $log->ip_address ?? '—' }}</code></dd>

                        <dt class="col-4 text-muted">URL</dt>
                        <dd class="col-8"><code class="small">{{ $log->url ?? '—' }}</code></dd>

                        <dt class="col-4 text-muted">User-Agent</dt>
                        <dd class="col-8"><small class="text-muted">{{ $log->user_agent ?? '—' }}</small></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            @php $changes = $log->changedFields(); @endphp
            @if($log->event === 'updated' && ! empty($changes))
                <div class="card-dark">
                    <div class="card-body-custom">
                        <h6 class="mb-3"><i class="bi bi-arrow-left-right me-1"></i> Değişiklikler ({{ count($changes) }})</h6>
                        <table class="table table-sm mb-0" style="color:#e5e7eb;">
                            <thead>
                                <tr>
                                    <th>Alan</th>
                                    <th>Eski</th>
                                    <th>Yeni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($changes as $field => $diff)
                                    <tr>
                                        <td><code class="small">{{ $field }}</code></td>
                                        <td><small class="text-danger">{{ Str::limit(json_encode($diff['old'], JSON_UNESCAPED_UNICODE), 80) }}</small></td>
                                        <td><small class="text-success">{{ Str::limit(json_encode($diff['new'], JSON_UNESCAPED_UNICODE), 80) }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12">
            <div class="row g-3">
                @if($log->old_values)
                    <div class="col-md-6">
                        <div class="card-dark">
                            <div class="card-body-custom">
                                <h6 class="mb-3"><i class="bi bi-file-text me-1"></i> Eski Değerler (JSON)</h6>
                                <pre class="small" style="max-height:400px;overflow:auto;color:#fca5a5;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </div>
                @endif

                @if($log->new_values)
                    <div class="col-md-6">
                        <div class="card-dark">
                            <div class="card-body-custom">
                                <h6 class="mb-3"><i class="bi bi-file-text me-1"></i> Yeni Değerler (JSON)</h6>
                                <pre class="small" style="max-height:400px;overflow:auto;color:#86efac;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
