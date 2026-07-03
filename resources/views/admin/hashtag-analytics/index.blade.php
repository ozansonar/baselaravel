@extends('layouts.admin')

@section('title', 'Hashtag Performans Raporu')

@section('content')
<div class="container-fluid py-4 ha-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1">
                <i class="bi bi-hash text-info me-2"></i> Hashtag Performans Raporu
            </h2>
            <small class="text-muted">
                Son {{ $days }} gün yayınlanmış post'lardaki hashtag'lerin engagement ortalaması.
            </small>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" @selected($days === $d)>Son {{ $d }} gün</option>
                @endforeach
            </select>
            <input type="hidden" name="sort" value="{{ $sort }}">
        </form>
    </div>

    {{-- Özet kartları --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">Benzersiz hashtag</div>
                            <div class="h3 mb-0">{{ number_format($summary['total_unique_tags']) }}</div>
                        </div>
                        <i class="bi bi-tags fs-1 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">Toplam kullanım</div>
                            <div class="h3 mb-0">{{ number_format($summary['total_usage']) }}</div>
                        </div>
                        <i class="bi bi-bar-chart fs-1 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="text-muted small">Post başına ort. tag</div>
                            <div class="h3 mb-0">{{ $summary['avg_tags_per_post'] }}</div>
                        </div>
                        <i class="bi bi-pie-chart fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sekme: sıralama seçenekleri --}}
    <div class="mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link {{ $sort === 'engagement' ? 'active' : '' }}"
                   href="{{ route('admin.hashtag-analytics.index', ['days' => $days, 'sort' => 'engagement']) }}">
                    <i class="bi bi-trophy me-1"></i> En Yüksek Engagement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $sort === 'usage' ? 'active' : '' }}"
                   href="{{ route('admin.hashtag-analytics.index', ['days' => $days, 'sort' => 'usage']) }}">
                    <i class="bi bi-bar-chart-fill me-1"></i> En Çok Kullanılan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $sort === 'low' ? 'active' : '' }}"
                   href="{{ route('admin.hashtag-analytics.index', ['days' => $days, 'sort' => 'low']) }}">
                    <i class="bi bi-arrow-down-circle me-1"></i> Düşük Performans
                </a>
            </li>
        </ul>
    </div>

    {{-- Tablo --}}
    <div class="card">
        <div class="card-body p-0">
            @if ($rows->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-emoji-frown fs-1 d-block mb-2"></i>
                    <p class="mb-0">Bu kriterlere uyan hashtag bulunamadı.</p>
                    <small>Henüz yayınlanmış post yok ya da hashtag bilgisi kaydedilmemiş olabilir.</small>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 40px;">#</th>
                                <th>Hashtag</th>
                                <th class="text-end">Kullanım</th>
                                <th class="text-end">Ort. Beğeni</th>
                                <th class="text-end">Ort. Yorum</th>
                                <th class="text-end">Ort. Reach</th>
                                <th class="text-end pe-3">Engagement %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr>
                                    <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info-emphasis fs-6">
                                            #{{ $row['tag'] }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ number_format($row['usage_count']) }}</td>
                                    <td class="text-end">{{ number_format($row['avg_likes'], 1) }}</td>
                                    <td class="text-end">{{ number_format($row['avg_comments'], 1) }}</td>
                                    <td class="text-end">{{ number_format($row['avg_reach'], 1) }}</td>
                                    <td class="text-end pe-3">
                                        @php
                                            $er = (float) $row['avg_engagement_rate'];
                                            $erClass = $er >= 5 ? 'success' : ($er >= 2 ? 'warning' : 'secondary');
                                        @endphp
                                        <span class="badge bg-{{ $erClass }}">
                                            {{ number_format($er, 2) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="alert alert-info mt-3 mb-0 small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Engagement Rate:</strong> (Ort. Beğeni + Ort. Yorum) / Ort. Reach × 100.
        Reach=0 olan post'lar hesaba katılırken, "Düşük Performans" sekmesinde reach=0 hashtag'ler gizlenir.
        Veriler 30 dakikada bir güncellenir (cache).
    </div>
</div>
@endsection
