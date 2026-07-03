@php
    /** @var \App\Models\InstagramPost|null $post */
    /** @var bool $isEdit */
@endphp

@if($isEdit && $post->logs->isNotEmpty())
<div class="card-dark mb-4">
    <div class="card-header-custom">
        <div class="form-section-header mb-0">
            <div class="form-section-icon bg-icon-red"><i class="bi bi-journal-text"></i></div>
            <div>
                <h6 class="mb-0">API Geçmişi</h6>
                <small class="text-muted">Son 10 kayıt</small>
            </div>
        </div>
    </div>
    <div class="card-body-custom">
        <div class="small">
            @foreach($post->logs->take(10) as $log)
            <div class="d-flex justify-content-between align-items-start py-2 border-bottom border-secondary">
                <div>
                    <strong>{{ $log->action }}</strong>
                    <div class="text-muted">{{ $log->created_at->format('d.m.Y H:i:s') }}</div>
                    @if($log->error_message)
                    <div class="text-danger small">{{ \Illuminate\Support\Str::limit($log->error_message, 120) }}</div>
                    @endif
                </div>
                <span class="usr-status-badge {{ $log->status === 'success' ? 'active' : 'danger' }}">
                    {{ $log->status }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif
