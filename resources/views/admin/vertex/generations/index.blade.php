@extends('layouts.admin')

@section('title', 'Vertex Üretim Geçmişi')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.vertex.index') }}" class="breadcrumb-link">Vertex</a></li>
            <li class="breadcrumb-item active text-teal">Üretim Geçmişi</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-clock-history text-teal me-2"></i>Vertex Üretim Geçmişi</h1>
            <p class="page-subtitle">Tüm Vertex AI üretimleri — başarılı, başarısız, beklemede.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-images"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Toplam</span><h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3></div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Başarılı</span><h3 class="usr-stat-value" data-count="{{ $stats['completed'] }}">0</h3></div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Başarısız</span><h3 class="usr-stat-value" data-count="{{ $stats['failed'] }}">0</h3></div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Bekleyen</span><h3 class="usr-stat-value" data-count="{{ $stats['pending'] }}">0</h3></div>
            </div>
        </div>
    </div>

    {{-- Status filter --}}
    @php
        $keepFilters = array_filter($filters, static fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="cl-status-tabs mb-3">
        <a href="{{ route('admin.vertex.generations.index', $keepFilters) }}" class="cl-status-tab {{ $status === '' ? 'active' : '' }}">Tümü <span class="cl-tab-count">{{ $stats['total'] }}</span></a>
        <a href="{{ route('admin.vertex.generations.index', array_merge($keepFilters, ['status' => 'completed'])) }}" class="cl-status-tab {{ $status === 'completed' ? 'active' : '' }}">Başarılı <span class="cl-tab-count vtx-tab-count-success">{{ $stats['completed'] }}</span></a>
        <a href="{{ route('admin.vertex.generations.index', array_merge($keepFilters, ['status' => 'failed'])) }}" class="cl-status-tab {{ $status === 'failed' ? 'active' : '' }}">Başarısız <span class="cl-tab-count vtx-tab-count-danger">{{ $stats['failed'] }}</span></a>
        <a href="{{ route('admin.vertex.generations.index', array_merge($keepFilters, ['status' => 'pending'])) }}" class="cl-status-tab {{ $status === 'pending' ? 'active' : '' }}">Beklemede <span class="cl-tab-count vtx-tab-count-warning">{{ $stats['pending'] }}</span></a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.vertex.generations.index') }}" class="cl-filters mb-3" id="vtxFilterForm">
        @if($status !== '')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif

        <select name="prompt_id" class="cl-filter-select" onchange="document.getElementById('vtxFilterForm').submit()">
            <option value="">Tüm Şablonlar</option>
            @foreach($prompts as $prompt)
                <option value="{{ $prompt->id }}" {{ ($filters['prompt_id'] ?? '') == $prompt->id ? 'selected' : '' }}>{{ $prompt->name }}</option>
            @endforeach
        </select>

        <select name="aspect_ratio" class="cl-filter-select" onchange="document.getElementById('vtxFilterForm').submit()">
            <option value="">Tüm Boyutlar</option>
            @foreach($aspectRatios as $ratio)
                <option value="{{ $ratio }}" {{ ($filters['aspect_ratio'] ?? '') === $ratio ? 'selected' : '' }}>{{ $ratio }}</option>
            @endforeach
        </select>

        <select name="share_status" class="cl-filter-select" onchange="document.getElementById('vtxFilterForm').submit()">
            <option value="">Tüm Paylaşım Durumları</option>
            <option value="not_shared" {{ ($filters['share_status'] ?? '') === 'not_shared' ? 'selected' : '' }}>Paylaşılmamış ({{ $stats['not_shared'] }})</option>
            <option value="ig_published" {{ ($filters['share_status'] ?? '') === 'ig_published' ? 'selected' : '' }}>Instagram'da Yayınlandı ({{ $stats['shared_ig'] }})</option>
            <option value="fb_published" {{ ($filters['share_status'] ?? '') === 'fb_published' ? 'selected' : '' }}>Facebook'ta Yayınlandı ({{ $stats['shared_fb'] }})</option>
            <option value="tt_published" {{ ($filters['share_status'] ?? '') === 'tt_published' ? 'selected' : '' }}>TikTok'ta Yayınlandı ({{ $stats['shared_tt'] }})</option>
            <option value="scheduled" {{ ($filters['share_status'] ?? '') === 'scheduled' ? 'selected' : '' }}>Planlanmış ({{ $stats['scheduled'] }})</option>
            <option value="any_shared" {{ ($filters['share_status'] ?? '') === 'any_shared' ? 'selected' : '' }}>Herhangi Birinde Paylaşıldı</option>
        </select>

        @if(!empty($filters['prompt_id']) || !empty($filters['aspect_ratio']) || !empty($filters['share_status']))
            <a href="{{ route('admin.vertex.generations.index', $status !== '' ? ['status' => $status] : []) }}" class="cl-filter-reset" title="Filtreleri Temizle">
                <i class="bi bi-x-lg"></i>
            </a>
        @endif
    </form>

    @if($generations->isNotEmpty())
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('admin.vertex.generations.download-by-filter', array_filter(['prompt_id' => $filters['prompt_id'] ?? null, 'aspect_ratio' => $filters['aspect_ratio'] ?? null])) }}"
           class="btn-glass" id="vtxDownloadAllJpg">
            <i class="bi bi-download me-1"></i>Tümünü JPG İndir
            <span class="badge bg-teal ms-1">{{ $generations->total() }}</span>
        </a>
    </div>
    @endif

    {{-- Bulk Action Bar --}}
    <div class="vtx-bulk-bar d-none" id="vtxBulkBar">
        <div class="vtx-bulk-bar-inner">
            <span class="vtx-bulk-bar-count"><strong id="vtxBulkCount">0</strong> öğe seçildi</span>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="vtx-bulk-bar-btn" id="vtxBulkDeselectAll"><i class="bi bi-x-lg me-1"></i>Seçimi Kaldır</button>
                <button type="button" class="vtx-bulk-bar-btn vtx-bulk-bar-btn-primary" id="vtxBulkDownload"><i class="bi bi-file-earmark-zip me-1"></i>ZIP İndir</button>
                <button type="button" class="vtx-bulk-bar-btn vtx-bulk-bar-btn-danger" id="vtxBulkDelete"><i class="bi bi-trash3 me-1"></i>Sil</button>
            </div>
        </div>
    </div>

    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-body-custom">
            @if($generations->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox vtx-empty-icon"></i>
                    <p class="text-clr-muted mt-3">Bu filtrede üretim bulunamadı.</p>
                </div>
            @else
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <label class="vtx-bulk-select-all">
                        <input type="checkbox" id="vtxSelectAll">
                        <span>Tümünü Seç</span>
                    </label>
                </div>
                <div class="ai-gallery-grid">
                    @foreach($generations as $g)
                        <div class="ai-gallery-item status-{{ $g->status }}">
                            <label class="vtx-bulk-checkbox" title="Seç">
                                <input type="checkbox" class="vtx-bulk-cb" value="{{ $g->id }}" data-has-image="{{ $g->isCompleted() && $g->output_image_path ? '1' : '0' }}">
                                <span class="vtx-bulk-check-icon"><i class="bi bi-check-lg"></i></span>
                            </label>
                            @if($g->isCompleted() && $g->output_image_path)
                                <a href="javascript:void(0)"
                                   data-vtx-detail="{{ $g->id }}"
                                   class="ai-gallery-thumb">
                                    <img src="{{ upload_url($g->output_image_path, 'md') }}" alt="{{ $g->prompt?->name ?? 'Vertex Generated' }}" loading="lazy">
                                </a>
                            @elseif($g->isFailed())
                                <a href="javascript:void(0)"
                                   data-vtx-detail="{{ $g->id }}"
                                   class="ai-gallery-thumb ai-gallery-failed">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </a>
                            @else
                                <div class="ai-gallery-thumb ai-gallery-failed">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            @endif
                            <div class="ai-gallery-info">
                                <div class="ai-gallery-prompt" title="{{ $g->prompt_used }}">
                                    {{ \Illuminate\Support\Str::limit($g->prompt_used, 160) }}
                                </div>
                                @if($g->isFailed() && $g->error_message)
                                    <div class="small text-danger mt-1">
                                        <i class="bi bi-exclamation-circle me-1"></i>{{ \Illuminate\Support\Str::limit($g->error_message, 80) }}
                                    </div>
                                @endif
                                <div class="ai-gallery-meta">
                                    <small class="text-clr-muted"><i class="bi bi-clock me-1"></i>{{ ($g->finished_at ?? $g->created_at)->diffForHumans() }}</small>
                                    @if($g->prompt)
                                        <small class="badge bg-teal-soft">{{ $g->prompt->name }}</small>
                                    @endif
                                    <small class="badge bg-dark">{{ $g->aspect_ratio }}</small>
                                    @if($g->instagramPosts->isNotEmpty())
                                        @php
                                            $shareLines = $g->instagramPosts->map(function ($sp) {
                                                $type = $sp->media_type?->value === 'story' ? 'Story' : 'Feed';
                                                $platforms = [];
                                                if ($sp->permalink) $platforms[] = 'IG';
                                                if ($sp->fb_published_at) $platforms[] = 'FB';
                                                $platformStr = $platforms !== [] ? ' (' . implode('+', $platforms) . ')' : '';
                                                $date = $sp->published_at?->format('d.m.Y H:i')
                                                    ?? ($sp->scheduled_at?->format('d.m.Y H:i') . ' planlandı');
                                                return $type . $platformStr . ' — ' . $date;
                                            })->implode("\n");
                                        @endphp
                                        <small class="badge vtx-share-badge" title="{{ $shareLines }}"><i class="bi bi-send-check me-1"></i>{{ $g->instagramPosts->count() }}× paylaşıldı</small>
                                    @endif
                                </div>
                                <div class="ai-gallery-actions">
                                    @if($g->isFailed())
                                        <button type="button" class="usr-action-btn vtx-retry-btn" data-retry-id="{{ $g->id }}" title="Tekrar Dene">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    @endif
                                    @if($g->isCompleted() && $g->output_image_path)
                                        <a href="{{ upload_url($g->output_image_path, 'lg') }}" download class="usr-action-btn" title="İndir">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.vertex.generations.destroy', $g) }}" class="d-inline js-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="usr-action-btn danger" title="Sil"
                                                data-confirm="Bu üretimi silmek istediğine emin misin?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @include('partials.admin.pagination', ['paginator' => $generations, 'itemLabel' => 'üretim'])

    {{-- Detail Gallery Modal --}}
    <div class="vtx-detail-overlay" id="vtxDetailOverlay">
        <div class="vtx-detail-container">
            <button type="button" class="vtx-detail-close" id="vtxDetailClose"><i class="bi bi-x-lg"></i></button>
            <button type="button" class="vtx-detail-nav vtx-detail-prev" id="vtxDetailPrev"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="vtx-detail-nav vtx-detail-next" id="vtxDetailNext"><i class="bi bi-chevron-right"></i></button>

            <div class="vtx-detail-body">
                <div class="vtx-detail-image-side">
                    <div class="vtx-detail-image-wrap">
                        <img src="" alt="" id="vtxDetailImg" class="vtx-detail-img">
                    </div>
                    <div class="vtx-detail-counter" id="vtxDetailCounter"></div>
                </div>
                <div class="vtx-detail-info-side" id="vtxDetailInfo">
                    <div class="vtx-detail-loading">
                        <div class="spinner-border text-teal" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    var bulkBar = document.getElementById('vtxBulkBar');
    var bulkCount = document.getElementById('vtxBulkCount');
    var selectAll = document.getElementById('vtxSelectAll');
    var downloadBtn = document.getElementById('vtxBulkDownload');
    var deleteBtn = document.getElementById('vtxBulkDelete');
    var deselectBtn = document.getElementById('vtxBulkDeselectAll');

    function getChecked() {
        return document.querySelectorAll('.vtx-bulk-cb:checked');
    }

    function hasImageInSelection() {
        var checked = getChecked();
        for (var i = 0; i < checked.length; i++) {
            if (checked[i].dataset.hasImage === '1') return true;
        }
        return false;
    }

    function updateBulkBar() {
        var count = getChecked().length;
        bulkCount.textContent = count;
        bulkBar.classList.toggle('d-none', count === 0);

        if (downloadBtn) downloadBtn.classList.toggle('d-none', !hasImageInSelection());

        var total = document.querySelectorAll('.vtx-bulk-cb').length;
        if (selectAll) selectAll.checked = total > 0 && count === total;
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('vtx-bulk-cb') || e.target.id === 'vtxSelectAll') {
            if (e.target.id === 'vtxSelectAll') {
                var checked = e.target.checked;
                document.querySelectorAll('.vtx-bulk-cb').forEach(function(cb) { cb.checked = checked; });
            }
            updateBulkBar();
        }
    });

    if (deselectBtn) {
        deselectBtn.addEventListener('click', function() {
            document.querySelectorAll('.vtx-bulk-cb:checked').forEach(function(cb) { cb.checked = false; });
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    }

    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            var checked = getChecked();
            if (checked.length === 0) return;

            var ids = [];
            checked.forEach(function(cb) {
                if (cb.dataset.hasImage === '1') ids.push(parseInt(cb.value, 10));
            });
            if (ids.length === 0) return;

            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Hazırlanıyor… (' + ids.length + ' görsel)';

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.vertex.generations.bulk-download") }}';

            var csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            ids.forEach(function(id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'ids[]';
                inp.value = id;
                form.appendChild(inp);
            });

            document.body.appendChild(form);
            form.submit();
            form.remove();

            setTimeout(function() {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = '<i class="bi bi-file-earmark-zip me-1"></i>ZIP İndir';
            }, 3000);
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            var checked = getChecked();
            if (checked.length === 0) return;

            var ids = [];
            checked.forEach(function(cb) { ids.push(parseInt(cb.value, 10)); });

            AdminModal.confirm({
                title: 'Toplu Silme',
                message: ids.length + ' üretim kaydı kalıcı olarak silinecek. Görselleri olan kayıtların dosyaları da silinir. Onaylıyor musunuz?',
                confirmText: 'Sil (' + ids.length + ')',
                type: 'danger',
            }).then(function(confirmed) {
                if (!confirmed) return;

                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Siliniyor…';

                fetch('{{ route("admin.vertex.generations.bulk-destroy") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ ids: ids })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        AdminModal.status({
                            title: 'Silindi',
                            message: data.message,
                            type: 'success',
                        }).then(function() { location.reload(); });
                    } else {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i>Sil';
                        AdminModal.status({ title: 'Hata', message: data.message || 'Bir hata oluştu.', type: 'danger' });
                    }
                })
                .catch(function() {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i>Sil';
                    AdminModal.status({ title: 'Hata', message: 'Bağlantı hatası oluştu.', type: 'danger' });
                });
            });
        });
    }
})();
</script>
<script>
(function() {
    var overlay = document.getElementById('vtxDetailOverlay');
    var imgEl = document.getElementById('vtxDetailImg');
    var infoEl = document.getElementById('vtxDetailInfo');
    var counterEl = document.getElementById('vtxDetailCounter');
    var ids = [];
    var currentIdx = -1;

    document.querySelectorAll('[data-vtx-detail]').forEach(function(el) {
        ids.push(parseInt(el.dataset.vtxDetail, 10));
    });

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-vtx-detail]');
        if (!trigger) return;
        e.preventDefault();
        var id = parseInt(trigger.dataset.vtxDetail, 10);
        var idx = ids.indexOf(id);
        if (idx !== -1) openDetail(idx);
    });

    document.getElementById('vtxDetailClose').addEventListener('click', closeDetail);
    document.getElementById('vtxDetailPrev').addEventListener('click', function() { navigate(-1); });
    document.getElementById('vtxDetailNext').addEventListener('click', function() { navigate(1); });

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeDetail();
    });

    document.addEventListener('keydown', function(e) {
        if (!overlay.classList.contains('vtx-detail-open')) return;
        if (e.key === 'Escape') closeDetail();
        if (e.key === 'ArrowLeft') navigate(-1);
        if (e.key === 'ArrowRight') navigate(1);
    });

    function openDetail(idx) {
        currentIdx = idx;
        overlay.classList.add('vtx-detail-open');
        document.body.classList.add('overflow-hidden');
        loadDetail(ids[idx]);
        updateNav();
    }

    function closeDetail() {
        overlay.classList.remove('vtx-detail-open');
        document.body.classList.remove('overflow-hidden');
        imgEl.src = '';
        currentIdx = -1;
    }

    function navigate(dir) {
        var next = currentIdx + dir;
        if (next < 0 || next >= ids.length) return;
        currentIdx = next;
        loadDetail(ids[next]);
        updateNav();
    }

    function updateNav() {
        document.getElementById('vtxDetailPrev').classList.toggle('d-none', currentIdx <= 0);
        document.getElementById('vtxDetailNext').classList.toggle('d-none', currentIdx >= ids.length - 1);
        counterEl.textContent = (currentIdx + 1) + ' / ' + ids.length;
    }

    function loadDetail(id) {
        infoEl.innerHTML = '<div class="vtx-detail-loading"><div class="spinner-border text-teal" role="status"></div></div>';
        imgEl.src = '';

        fetch('{{ route("admin.vertex.generations.index") }}/' + id, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.image_url) imgEl.src = d.image_url;
            infoEl.innerHTML = buildInfoHtml(d);
        })
        .catch(function() {
            infoEl.innerHTML = '<p class="text-danger p-3">Detay yüklenemedi.</p>';
        });
    }

    function buildInfoHtml(d) {
        var html = '';

        if (d.resolved_variables && typeof d.resolved_variables === 'object') {
            var keys = Object.keys(d.resolved_variables);
            if (keys.length > 0) {
                html += '<div class="vtx-detail-section">';
                html += '<div class="vtx-detail-label"><i class="bi bi-braces text-warning me-1"></i>Seçilen Değişkenler</div>';
                html += '<div class="vtx-detail-vars">';
                for (var vi = 0; vi < keys.length; vi++) {
                    var vk = keys[vi];
                    html += '<div class="vtx-detail-var-item">';
                    html += '<span class="vtx-detail-var-name">' + escHtml(humanizeVarName(vk)) + '</span>';
                    html += '<span class="vtx-detail-var-value">' + escHtml(d.resolved_variables[vk]) + '</span>';
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';
            }
        }

        if (d.ig_title) {
            html += '<div class="vtx-detail-section">';
            html += '<div class="vtx-detail-label"><i class="bi bi-type-bold me-1"></i>Başlık</div>';
            html += '<div class="vtx-detail-value vtx-detail-title">' + escHtml(d.ig_title) + '</div>';
            html += '</div>';
        }

        if (d.ig_caption) {
            html += '<div class="vtx-detail-section">';
            html += '<div class="vtx-detail-label"><i class="bi bi-chat-quote me-1"></i>Caption</div>';
            html += '<div class="vtx-detail-value">' + escHtml(d.ig_caption) + '</div>';
            html += '</div>';
        }

        if (d.ig_hashtags) {
            html += '<div class="vtx-detail-section">';
            html += '<div class="vtx-detail-label"><i class="bi bi-hash me-1"></i>Hashtag\'ler</div>';
            html += '<div class="vtx-detail-value vtx-detail-hashtags">' + formatHashtags(d.ig_hashtags) + '</div>';
            html += '</div>';
        }

        if (!d.ig_title && !d.ig_caption && !d.ig_hashtags) {
            html += '<div class="vtx-detail-section">';
            html += '<div class="vtx-detail-empty"><i class="bi bi-info-circle me-1"></i>Caption bilgisi henüz oluşturulmamış.</div>';
            html += '</div>';
        }

        html += '<div class="vtx-detail-divider"></div>';

        html += '<div class="vtx-detail-meta-grid">';
        if (d.prompt && d.prompt.name) {
            html += metaItem('bi-file-earmark-text', 'Şablon', d.prompt.name);
        }
        html += metaItem('bi-aspect-ratio', 'Oran', d.aspect_ratio || '-');
        if (d.width && d.height) {
            html += metaItem('bi-arrows-fullscreen', 'Boyut', d.width + '×' + d.height);
        }
        html += metaItem('bi-cpu', 'Model', d.model || '-');
        if (d.generation_time_ms) {
            html += metaItem('bi-stopwatch', 'Süre', (d.generation_time_ms / 1000).toFixed(1) + 's');
        }
        if (d.token_count) {
            html += metaItem('bi-coin', 'Token', d.token_count.toLocaleString('tr-TR'));
        }
        if (d.usage_count !== undefined) {
            html += metaItem('bi-arrow-repeat', 'Kullanım', d.usage_count + ' kez');
        }
        html += metaItem('bi-calendar3', 'Tarih', d.created_at || '-');
        html += '</div>';

        if (d.shares && d.shares.length > 0) {
            html += '<div class="vtx-share-history">';
            html += '<div class="vtx-detail-label"><i class="bi bi-clock-history me-1"></i>Paylaşım Geçmişi</div>';
            for (var si = 0; si < d.shares.length; si++) {
                var sh = d.shares[si];
                var mtLabel = sh.media_type === 'story' ? 'Story' : 'Feed';
                var mtIcon = sh.media_type === 'story' ? 'bi-circle-fill' : 'bi-image';
                html += '<div class="vtx-share-history-item">';
                html += '<span class="vtx-share-history-type" title="' + mtLabel + '"><i class="bi ' + mtIcon + '"></i></span>';
                if (sh.status === 'scheduled') {
                    html += '<span class="vtx-share-history-date">' + escHtml(sh.scheduled_at || '-') + '</span>';
                    html += '<span class="vtx-share-history-status">planlandı</span>';
                } else {
                    html += '<span class="vtx-share-history-date">' + escHtml(sh.published_at || '-') + '</span>';
                    if (sh.ig_published && sh.ig_permalink) {
                        html += '<a href="' + escHtml(sh.ig_permalink) + '" target="_blank" class="vtx-share-history-link vtx-share-history-ig"><i class="bi bi-instagram"></i></a>';
                    }
                    if (sh.fb_published && sh.fb_permalink) {
                        html += '<a href="' + escHtml(sh.fb_permalink) + '" target="_blank" class="vtx-share-history-link vtx-share-history-fb"><i class="bi bi-facebook"></i></a>';
                    }
                    if (!sh.ig_published && !sh.fb_published) {
                        html += '<span class="vtx-share-history-status">' + escHtml(sh.status || 'beklemede') + '</span>';
                    }
                }
                html += '</div>';
            }
            html += '</div>';
        }

        if (d.image_url) {
            html += '<div class="vtx-share-section">';
            html += '<div class="vtx-detail-label"><i class="bi bi-send me-1"></i>Paylaş</div>';

            html += '<div class="vtx-share-media-types">';
            html += '<button type="button" class="vtx-share-media-btn active" data-media-type="image"><i class="bi bi-image me-1"></i>Feed</button>';
            html += '<button type="button" class="vtx-share-media-btn" data-media-type="story"><i class="bi bi-circle-fill me-1"></i>Story</button>';
            html += '</div>';

            html += '<div class="vtx-share-platforms">';
            html += '<label class="vtx-share-platform">';
            html += '<input type="checkbox" class="vtx-share-cb" value="instagram" data-gen-id="' + d.id + '" checked>';
            html += '<span class="vtx-share-platform-label"><i class="bi bi-instagram"></i> Instagram</span>';
            html += '</label>';
            html += '<label class="vtx-share-platform" id="vtxFbPlatform">';
            html += '<input type="checkbox" class="vtx-share-cb" value="facebook" data-gen-id="' + d.id + '">';
            html += '<span class="vtx-share-platform-label"><i class="bi bi-facebook"></i> Facebook</span>';
            html += '</label>';
            html += '</div>';

            html += '<div class="vtx-share-schedule">';
            html += '<label class="vtx-share-platform">';
            html += '<input type="checkbox" id="vtxScheduleToggle">';
            html += '<span class="vtx-share-platform-label"><i class="bi bi-calendar-event"></i> İleri tarihte planla</span>';
            html += '</label>';
            html += '<div class="vtx-share-schedule-picker" id="vtxSchedulePicker">';
            html += '<input type="datetime-local" id="vtxScheduleDate" class="vtx-share-date-input" min="' + getMinDatetime() + '">';
            html += '</div>';
            html += '</div>';

            html += '<div class="vtx-share-actions">';
            html += '<button type="button" class="vtx-share-btn" data-share-id="' + d.id + '"><i class="bi bi-send-fill me-1"></i>Paylaş</button>';
            html += '</div>';
            html += '<div class="vtx-share-result" id="vtxShareResult"></div>';
            html += '</div>';

            html += '<div class="vtx-detail-actions">';
            html += '<a href="' + escHtml(d.image_url) + '" download class="vtx-detail-action-btn vtx-action-download" title="Orijinal İndir"><i class="bi bi-download"></i>Orijinal</a>';
            html += '<a href="' + jpgUrl(d.id) + '" class="vtx-detail-action-btn vtx-action-jpg" title="JPG Olarak İndir"><i class="bi bi-file-earmark-image"></i>JPG</a>';
            html += '<button type="button" class="vtx-detail-action-btn vtx-action-delete" data-delete-id="' + d.id + '" title="Sil"><i class="bi bi-trash3"></i>Sil</button>';
            html += '</div>';
        }

        if (d.status === 'failed') {
            html += '<div class="vtx-detail-section">';
            html += '<div class="vtx-detail-label text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Hata</div>';
            html += '<div class="vtx-detail-value text-danger vtx-detail-error-msg">' + escHtml(d.error_message || 'Bilinmeyen hata') + '</div>';
            html += '</div>';
            html += '<div class="vtx-detail-actions">';
            html += '<button type="button" class="vtx-detail-action-btn vtx-action-retry" data-retry-id="' + d.id + '"><i class="bi bi-arrow-clockwise"></i>Tekrar Dene</button>';
            html += '<button type="button" class="vtx-detail-action-btn vtx-action-delete" data-delete-id="' + d.id + '" title="Sil"><i class="bi bi-trash3"></i>Sil</button>';
            html += '</div>';
        }

        if (d.status === 'pending' || d.status === 'processing') {
            html += '<div class="vtx-detail-section">';
            html += '<div class="vtx-detail-label"><i class="bi bi-hourglass-split me-1"></i>Durum</div>';
            html += '<div class="vtx-detail-value">Üretim kuyruğunda bekliyor…</div>';
            html += '</div>';
        }

        return html;
    }

    function jpgUrl(id) {
        return '{{ route("admin.vertex.generations.index") }}/' + id + '/download-jpg';
    }

    function humanizeVarName(name) {
        var s = name.replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase();
        return s.split('_').filter(function(p) { return p !== ''; })
            .map(function(p) { return p.charAt(0).toUpperCase() + p.slice(1); })
            .join(' ');
    }

    function metaItem(icon, label, value) {
        return '<div class="vtx-detail-meta-item">'
            + '<i class="bi ' + icon + ' vtx-detail-meta-icon"></i>'
            + '<span class="vtx-detail-meta-label">' + label + '</span>'
            + '<span class="vtx-detail-meta-val">' + escHtml(String(value)) + '</span>'
            + '</div>';
    }

    function formatHashtags(str) {
        return escHtml(str).replace(/(#\S+)/g, '<span class="vtx-hashtag-badge">$1</span>');
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    document.addEventListener('click', function(e) {
        var retryBtn = e.target.closest('.vtx-retry-btn');
        if (retryBtn) {
            e.preventDefault();
            handleRetry(retryBtn, parseInt(retryBtn.dataset.retryId, 10));
        }
    });

    function handleRetry(btn, genId) {
        btn.disabled = true;
        var origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('{{ route("admin.vertex.generations.index") }}/' + genId + '/retry', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var card = document.querySelector('[data-retry-id="' + genId + '"]');
                if (card) {
                    var item = card.closest('.ai-gallery-item');
                    if (item) {
                        item.className = 'ai-gallery-item status-pending';
                        var thumb = item.querySelector('.ai-gallery-thumb');
                        if (thumb) thumb.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                        var errEl = item.querySelector('.text-danger');
                        if (errEl) errEl.remove();
                        card.remove();
                    }
                }
                if (overlay.classList.contains('vtx-detail-open')) {
                    loadDetail(genId);
                }
            } else {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });
    }

    document.querySelectorAll('.js-delete-form').forEach(function(formEl) {
        formEl.addEventListener('submit', function(e) {
            var btn = formEl.querySelector('button[data-confirm]');
            var msg = btn ? btn.dataset.confirm : 'Silmek istediğinize emin misiniz?';
            e.preventDefault();
            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Onay',
                    message: msg,
                    type: 'danger',
                    confirmText: 'Sil',
                    confirmIcon: 'bi bi-trash3',
                }).then(function(confirmed) {
                    if (confirmed) formEl.submit();
                });
            } else {
                formEl.submit();
            }
        });
    });

    var deleteTimer = null;

    infoEl.addEventListener('change', function(e) {
        if (e.target.id === 'vtxScheduleToggle') {
            var picker = document.getElementById('vtxSchedulePicker');
            if (picker) picker.classList.toggle('vtx-share-schedule-picker--open', e.target.checked);
            var shareBtn = infoEl.querySelector('[data-share-id]');
            if (shareBtn) {
                shareBtn.innerHTML = e.target.checked
                    ? '<i class="bi bi-calendar-check me-1"></i>Planla'
                    : '<i class="bi bi-send-fill me-1"></i>Paylaş';
            }
        }
    });

    infoEl.addEventListener('click', function(e) {
        var retryBtn = e.target.closest('[data-retry-id]');
        if (retryBtn) {
            handleRetry(retryBtn, parseInt(retryBtn.dataset.retryId, 10));
            return;
        }

        var mediaBtn = e.target.closest('[data-media-type]');
        if (mediaBtn) {
            infoEl.querySelectorAll('[data-media-type]').forEach(function(b) { b.classList.remove('active'); });
            mediaBtn.classList.add('active');
            var fbPlatform = document.getElementById('vtxFbPlatform');
            if (fbPlatform) {
                var isStory = mediaBtn.dataset.mediaType === 'story';
                fbPlatform.classList.toggle('vtx-share-platform--disabled', isStory);
                if (isStory) {
                    var fbCb = fbPlatform.querySelector('input[type="checkbox"]');
                    if (fbCb) fbCb.checked = false;
                }
            }
            return;
        }

        var shareBtn = e.target.closest('[data-share-id]');
        if (shareBtn) {
            handleShare(shareBtn);
            return;
        }

        var btn = e.target.closest('[data-delete-id]');
        if (!btn) return;

        var genId = parseInt(btn.dataset.deleteId, 10);

        if (btn.dataset.confirmed !== 'yes') {
            btn.dataset.confirmed = 'yes';
            btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i>Emin misin?';
            btn.classList.add('vtx-action-confirm');

            clearTimeout(deleteTimer);
            deleteTimer = setTimeout(function() {
                btn.dataset.confirmed = '';
                btn.innerHTML = '<i class="bi bi-trash3"></i>Sil';
                btn.classList.remove('vtx-action-confirm');
            }, 3000);
            return;
        }

        clearTimeout(deleteTimer);
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('{{ route("admin.vertex.generations.index") }}/' + genId, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var gridItem = document.querySelector('[data-vtx-detail="' + genId + '"]');
                if (gridItem) {
                    var card = gridItem.closest('.ai-gallery-item');
                    if (card) card.remove();
                }

                var delIdx = ids.indexOf(genId);
                if (delIdx !== -1) ids.splice(delIdx, 1);

                if (ids.length === 0) {
                    closeDetail();
                    return;
                }

                if (currentIdx >= ids.length) currentIdx = ids.length - 1;
                loadDetail(ids[currentIdx]);
                updateNav();
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.dataset.confirmed = '';
            btn.innerHTML = '<i class="bi bi-trash3"></i>Sil';
            btn.classList.remove('vtx-action-confirm');
        });
    });
    function getMinDatetime() {
        var d = new Date();
        d.setMinutes(d.getMinutes() + 5);
        return d.toISOString().slice(0, 16);
    }

    function handleShare(btn) {
        var genId = parseInt(btn.dataset.shareId, 10);
        var checkboxes = infoEl.querySelectorAll('.vtx-share-cb');
        var platforms = [];
        checkboxes.forEach(function(cb) { if (cb.checked) platforms.push(cb.value); });

        if (platforms.length === 0) {
            showShareResult('error', 'En az bir platform seç.');
            return;
        }

        var activeMedia = infoEl.querySelector('[data-media-type].active');
        var mediaType = activeMedia ? activeMedia.dataset.mediaType : 'image';

        var scheduleToggle = document.getElementById('vtxScheduleToggle');
        var scheduleDateInput = document.getElementById('vtxScheduleDate');
        var scheduledAt = null;

        if (scheduleToggle && scheduleToggle.checked) {
            if (!scheduleDateInput || !scheduleDateInput.value) {
                showShareResult('error', 'Planlama tarihi seç.');
                return;
            }
            scheduledAt = scheduleDateInput.value;
        }

        var isSchedule = scheduledAt !== null;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (isSchedule ? 'Planlanıyor…' : 'Paylaşılıyor…');
        showShareResult('', '');

        var payload = { platforms: platforms, media_type: mediaType };
        if (scheduledAt) payload.scheduled_at = scheduledAt;

        fetch('{{ route("admin.vertex.generations.index") }}/' + genId + '/share', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = isSchedule
                ? '<i class="bi bi-calendar-check me-1"></i>Planla'
                : '<i class="bi bi-send-fill me-1"></i>Paylaş';

            if (res.data.success) {
                if (res.data.scheduled) {
                    showShareResult('success', '<i class="bi bi-calendar-check me-1"></i>' + escHtml(res.data.message));
                } else {
                    var links = [];
                    if (res.data.ig_permalink) links.push('<a href="' + escHtml(res.data.ig_permalink) + '" target="_blank" class="vtx-share-link"><i class="bi bi-instagram"></i> Görüntüle</a>');
                    if (res.data.fb_permalink) links.push('<a href="' + escHtml(res.data.fb_permalink) + '" target="_blank" class="vtx-share-link"><i class="bi bi-facebook"></i> Görüntüle</a>');
                    showShareResult('success', '<i class="bi bi-check-circle-fill me-1"></i>Paylaşıldı! ' + links.join(' '));
                }
                setTimeout(function() { loadDetail(genId); }, 2000);
            } else {
                showShareResult('error', '<i class="bi bi-exclamation-circle me-1"></i>' + escHtml(res.data.message || 'Paylaşım başarısız.'));
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = isSchedule
                ? '<i class="bi bi-calendar-check me-1"></i>Planla'
                : '<i class="bi bi-send-fill me-1"></i>Paylaş';
            showShareResult('error', '<i class="bi bi-exclamation-circle me-1"></i>Bağlantı hatası.');
        });
    }

    function showShareResult(type, html) {
        var el = document.getElementById('vtxShareResult');
        if (!el) return;
        el.className = 'vtx-share-result' + (type ? ' vtx-share-result--' + type : '');
        el.innerHTML = html;
    }

})();
</script>
@endpush
