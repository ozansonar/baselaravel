@extends('layouts.admin')

@section('title', 'En İyi Paylaşım Saati')

@push('styles')
<style>
    .bt-heatmap { border-collapse: separate; border-spacing: 2px; font-size: .75rem; }
    .bt-heatmap th, .bt-heatmap td { padding: 4px 6px; text-align: center; min-width: 38px; }
    .bt-heatmap thead th { font-weight: 600; color: #6c757d; }
    .bt-heatmap tbody th { font-weight: 600; color: #6c757d; text-align: left; padding-right: 10px; }
    .bt-cell { border-radius: 4px; cursor: default; }
    .bt-cell--empty   { background: #f1f3f5; color: #adb5bd; }
    .bt-cell--low     { background: #e3f2fd; color: #1565c0; }
    .bt-cell--mid     { background: #90caf9; color: #0d47a1; }
    .bt-cell--high    { background: #1976d2; color: #fff; }
    .bt-cell--best    { background: #0d47a1; color: #fff; font-weight: 700; }
    .bt-bar { background: linear-gradient(to right, #4dabf7, #1971c2); height: 18px; border-radius: 3px; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4 bt-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-clock-history text-warning me-2"></i> En İyi Paylaşım Saati
            </h2>
            <small class="text-muted">
                {{ number_format($totalPosts) }} yayınlanmış post analiz edildi (son {{ $days }} gün).
                Engagement skoru = like + (yorum × 2).
            </small>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" @selected($days === $d)>Son {{ $d }} gün</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($totalPosts < 5)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Anlamlı analiz için en az 5 yayınlanmış post gerekiyor. Şu an: {{ $totalPosts }}.
        </div>
    @endif

    {{-- Top 3 önerilen slot --}}
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning-subtle">
            <h5 class="mb-0"><i class="bi bi-stars me-2"></i> Önerilen En İyi 3 Slot</h5>
        </div>
        <div class="card-body">
            @if (empty($topSlots))
                <p class="text-muted mb-0">Yeterli veri yok. En az 2 post yayınlanmış slot'lar değerlendiriliyor.</p>
            @else
                <div class="row g-3">
                    @foreach ($topSlots as $i => $slot)
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center @if($i === 0) bg-warning-subtle @endif">
                                <div class="text-muted small">#{{ $i + 1 }} En İyi</div>
                                <div class="h4 mb-1">{{ $slot['day_label'] }}</div>
                                <div class="h5 text-primary">{{ str_pad((string)$slot['hour'], 2, '0', STR_PAD_LEFT) }}:00</div>
                                <div class="small text-muted">
                                    Ort. engagement: <strong>{{ number_format($slot['avg_engagement'], 1) }}</strong>
                                    ({{ $slot['post_count'] }} post)
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- Saat dağılımı --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">Saat Bazlı Engagement</h6></div>
                <div class="card-body">
                    @php $maxHour = max(array_column($byHour, 'avg_engagement')) ?: 1; @endphp
                    @foreach ($byHour as $row)
                        <div class="d-flex align-items-center mb-1">
                            <div class="text-muted small" style="width: 50px;">
                                {{ str_pad((string)$row['hour'], 2, '0', STR_PAD_LEFT) }}:00
                            </div>
                            <div class="flex-grow-1">
                                <div class="bt-bar" style="width: {{ ($row['avg_engagement'] / $maxHour) * 100 }}%"></div>
                            </div>
                            <div class="text-end small ms-2" style="width: 90px;">
                                <strong>{{ number_format($row['avg_engagement'], 1) }}</strong>
                                <span class="text-muted">({{ $row['post_count'] }})</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Gün dağılımı --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">Gün Bazlı Engagement</h6></div>
                <div class="card-body">
                    @php $maxDow = max(array_column($byDayOfWeek, 'avg_engagement')) ?: 1; @endphp
                    @foreach ($byDayOfWeek as $row)
                        <div class="d-flex align-items-center mb-2">
                            <div class="text-muted small" style="width: 90px;">{{ $row['day_label'] }}</div>
                            <div class="flex-grow-1">
                                <div class="bt-bar" style="width: {{ ($row['avg_engagement'] / $maxDow) * 100 }}%; height: 24px;"></div>
                            </div>
                            <div class="text-end small ms-2" style="width: 90px;">
                                <strong>{{ number_format($row['avg_engagement'], 1) }}</strong>
                                <span class="text-muted">({{ $row['post_count'] }})</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Heatmap: gün × saat --}}
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Gün × Saat Isı Haritası</h6>
        </div>
        <div class="card-body table-responsive">
            @php
                // Tüm hücrelerin max engagement'ı (renk skalası için)
                $allEng = [];
                foreach ($heatmap as $hours) {
                    foreach ($hours as $cell) {
                        if ($cell['post_count'] > 0) $allEng[] = $cell['avg_engagement'];
                    }
                }
                $maxEng = $allEng ? max($allEng) : 1;
                $bestEng = $maxEng; // tek hücre tam max
            @endphp
            <table class="bt-heatmap">
                <thead>
                    <tr>
                        <th></th>
                        @for ($h = 0; $h < 24; $h++)
                            <th>{{ str_pad((string)$h, 2, '0', STR_PAD_LEFT) }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($heatmap as $d => $hours)
                        <tr>
                            <th>{{ ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'][$d - 1] }}</th>
                            @foreach ($hours as $h => $cell)
                                @php
                                    $cls = 'bt-cell--empty';
                                    if ($cell['post_count'] > 0) {
                                        $ratio = $maxEng > 0 ? $cell['avg_engagement'] / $maxEng : 0;
                                        if ($cell['avg_engagement'] >= $bestEng && $bestEng > 0) {
                                            $cls = 'bt-cell--best';
                                        } elseif ($ratio >= 0.66) {
                                            $cls = 'bt-cell--high';
                                        } elseif ($ratio >= 0.33) {
                                            $cls = 'bt-cell--mid';
                                        } else {
                                            $cls = 'bt-cell--low';
                                        }
                                    }
                                    $title = $cell['post_count'] > 0
                                        ? "Ort. engagement: {$cell['avg_engagement']} ({$cell['post_count']} post)"
                                        : 'Bu slotta yayınlanmış post yok';
                                @endphp
                                <td class="bt-cell {{ $cls }}" title="{{ $title }}">
                                    {{ $cell['post_count'] > 0 ? number_format($cell['avg_engagement'], 0) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            <span class="bt-cell bt-cell--empty d-inline-block px-2">—</span> Veri yok
            &nbsp; <span class="bt-cell bt-cell--low d-inline-block px-2">10</span> Düşük
            &nbsp; <span class="bt-cell bt-cell--mid d-inline-block px-2">50</span> Orta
            &nbsp; <span class="bt-cell bt-cell--high d-inline-block px-2">90</span> Yüksek
            &nbsp; <span class="bt-cell bt-cell--best d-inline-block px-2">100</span> En İyi
        </div>
    </div>
</div>
@endsection
