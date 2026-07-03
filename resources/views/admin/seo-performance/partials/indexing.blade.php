{{-- TAB: Indexing Requests --}}
<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
    <div class="card-body-custom">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-info-circle-fill text-info fs-4"></i>
            <div>
                <strong class="d-block mb-1">İndeksleme Kuyruğu</strong>
                <small class="text-muted">
                    Yeni eklenen veya güncellenen ürün/blog/şehir sayfaları otomatik olarak Google'a "tara" emri gönderir.
                    Failed olanları yeniden denemek için aşağıdaki butonu kullan. Bu özellik <code>Admin → Ayarlar</code> sekmesinden açılıp kapatılabilir.
                </small>
            </div>
        </div>
    </div>
</div>

<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
    <div class="card-body-custom p-0">
        <div class="table-responsive">
            <table class="cl-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th class="d-none d-md-table-cell">Tip</th>
                        <th>Durum</th>
                        <th class="d-none d-lg-table-cell">Gönderildi</th>
                        <th class="cl-th-actions">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($indexingRequests as $req)
                        <tr>
                            <td>
                                <div class="cl-content-info">
                                    <a href="{{ $req->url }}" target="_blank" rel="noopener noreferrer" class="cl-content-title text-decoration-none">
                                        {{ Str::limit(parse_url($req->url, PHP_URL_PATH) ?? $req->url, 60) }}
                                        <i class="bi bi-box-arrow-up-right small ms-1 text-muted"></i>
                                    </a>
                                    @if($req->error_message)
                                        <span class="cl-content-meta text-danger">{{ Str::limit($req->error_message, 80) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="cl-category-badge">
                                    @if($req->type === 'URL_DELETED')
                                        <i class="bi bi-x-circle me-1"></i>Silindi
                                    @else
                                        <i class="bi bi-arrow-clockwise me-1"></i>Güncellendi
                                    @endif
                                </span>
                            </td>
                            <td>
                                @switch($req->status)
                                    @case('success')
                                        <span class="usr-status-badge active"><i class="bi bi-check-circle"></i> Başarılı</span>
                                        @break
                                    @case('pending')
                                        <span class="usr-status-badge pending"><i class="bi bi-hourglass"></i> Bekliyor</span>
                                        @break
                                    @case('sent')
                                        <span class="usr-status-badge pending"><i class="bi bi-send"></i> Gönderildi</span>
                                        @break
                                    @case('failed')
                                        <span class="usr-status-badge inactive"><i class="bi bi-x-circle"></i> Başarısız</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="d-none d-lg-table-cell text-muted">
                                @if($req->sent_at)
                                    {{ $req->sent_at->translatedFormat('d M Y H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <div class="usr-actions">
                                    @if(in_array($req->status, ['failed', 'pending'], true))
                                        <form method="POST" action="{{ route('admin.seo-performance.reindex', $req) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="usr-action-btn" title="Yeniden Dene">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-arrow-up-right-square d-block fs-1 mb-2"></i>
                                <p class="mb-1">Henüz indeksleme isteği yok.</p>
                                <small>Yeni ürün/blog/şehir sayfası eklediğinde burada görünecek.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
