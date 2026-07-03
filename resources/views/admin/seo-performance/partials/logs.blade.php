{{-- TAB: API Logs --}}
<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="cl-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Servis</th>
                        <th class="d-none d-md-table-cell">İşlem</th>
                        <th>Durum</th>
                        <th class="text-end d-none d-lg-table-cell">HTTP</th>
                        <th class="text-end d-none d-lg-table-cell">Süre</th>
                        <th class="d-none d-xl-table-cell">Hata</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($apiLogs as $log)
                        <tr>
                            <td class="text-muted small">
                                {{ $log->created_at->translatedFormat('d M Y H:i:s') }}
                            </td>
                            <td>
                                <span class="cl-category-badge">
                                    @if($log->service === 'gsc')
                                        <i class="bi bi-search me-1"></i>GSC
                                    @elseif($log->service === 'indexing')
                                        <i class="bi bi-arrow-up-right-square me-1"></i>Indexing
                                    @else
                                        {{ strtoupper($log->service) }}
                                    @endif
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell text-muted">{{ $log->operation }}</td>
                            <td>
                                @if($log->status === 'success')
                                    <span class="usr-status-badge active"><i class="bi bi-check"></i> Başarılı</span>
                                @else
                                    <span class="usr-status-badge inactive"><i class="bi bi-x"></i> Hata</span>
                                @endif
                            </td>
                            <td class="text-end d-none d-lg-table-cell text-muted">{{ $log->http_code ?? '—' }}</td>
                            <td class="text-end d-none d-lg-table-cell text-muted">
                                @if($log->duration_ms)
                                    {{ number_format($log->duration_ms, 0, ',', '.') }} ms
                                @else
                                    —
                                @endif
                            </td>
                            <td class="d-none d-xl-table-cell">
                                @if($log->error_message)
                                    <small class="text-danger">{{ Str::limit($log->error_message, 80) }}</small>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-bug d-block fs-1 mb-2"></i>
                                <p class="mb-1">Henüz API çağrısı yapılmadı.</p>
                                <small>İlk cron çalıştığında veya manuel veri çektiğinde burada görünecek.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
