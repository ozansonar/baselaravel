@extends('layouts.admin')

@section('title', 'Özel Gün Görsel Üretimi')
@section('page_title', 'Özel Gün Görsel Üretimi')
@section('page_description', 'Özel günler için otomatik Vertex AI görsel üretimi — Instagram galeri entegrasyonu.')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.vertex.index') }}" class="breadcrumb-link">Vertex</a>
            </li>
            <li class="breadcrumb-item active text-teal">Özel Gün Görselleri</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title"><i class="bi bi-calendar-heart text-teal me-2"></i>Özel Gün Görsel Üretimi</h1>
            <p class="page-subtitle">Tarih aralığı seç, şablon ata — özel günler için otomatik Feed + Story üret.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.vertex.index') }}" class="btn-glass">
                <i class="bi bi-stars"></i> Vertex Ana Sayfa
            </a>
            <a href="{{ route('admin.special-days.index') }}" class="btn-glass">
                <i class="bi bi-calendar3"></i> Özel Gün Takvimi
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-4 mb-4" data-aos="fade-up">
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-calendar-event"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Özel Gün</span><h3 class="usr-stat-value" data-count="{{ $stats['total_days'] }}">0</h3></div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-image"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Feed Görseli Var</span><h3 class="usr-stat-value" data-count="{{ $stats['with_feed'] }}">0</h3></div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-phone"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Story Görseli Var</span><h3 class="usr-stat-value" data-count="{{ $stats['with_story'] }}">0</h3></div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                <div class="usr-stat-info"><span class="usr-stat-label">Aktif Batch</span><h3 class="usr-stat-value" data-count="{{ $stats['pending_batch'] }}">0</h3></div>
            </div>
        </div>
    </div>

    @if(!$apiKeySet)
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Vertex API Key tanımlı değil. <a href="{{ route('admin.settings.index') }}#ai" class="alert-link">Ayarlar → AI</a> bölümünden ekleyin.
        </div>
    @endif

    {{-- Generation Panel --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom">
            <h5 class="card-title-custom"><i class="bi bi-magic text-teal me-2"></i>Toplu Üretim Başlat</h5>
        </div>
        <div class="card-body-custom">
            <form id="sdv-generate-form">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label text-clr-muted">Başlangıç</label>
                        <input type="date" name="from" class="form-control form-control-dark" value="{{ $from->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-clr-muted">Bitiş</label>
                        <input type="date" name="to" class="form-control form-control-dark" value="{{ $to->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-clr-muted">Feed Şablonu (1:1)</label>
                        <select name="feed_prompt_id" class="form-select form-select-dark" required>
                            <option value="">Seçin...</option>
                            @foreach($prompts as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-clr-muted">Story Şablonu (9:16)</label>
                        <select name="story_prompt_id" class="form-select form-select-dark" required>
                            <option value="">Seçin...</option>
                            @foreach($prompts as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-clr-muted">Gün Başına Adet</label>
                        <select name="count_per_day" class="form-select form-select-dark">
                            <option value="1">1</option>
                            <option value="2" selected>2</option>
                            <option value="3">3</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="sdv-preview-btn" class="btn-glass w-100">
                            <i class="bi bi-eye me-1"></i> Önizle
                        </button>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn-teal w-100" {{ !$apiKeySet ? 'disabled' : '' }}>
                            <i class="bi bi-rocket-takeoff me-1"></i> Üretimi Başlat
                        </button>
                    </div>
                </div>
            </form>

            {{-- Preview area --}}
            <div id="sdv-preview-area" class="mt-4 d-none">
                <h6 class="text-clr-muted mb-3"><i class="bi bi-list-check me-1"></i>Önizleme — <span id="sdv-preview-count">0</span> özel gün</h6>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-sm">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Özel Gün</th>
                                <th>Kategori</th>
                                <th>Feed</th>
                                <th>Story</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody id="sdv-preview-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Active Batches --}}
    @if($activeBatches->isNotEmpty())
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom">
            <h5 class="card-title-custom"><i class="bi bi-arrow-repeat text-warning me-2"></i>Aktif Batch'ler</h5>
        </div>
        <div class="card-body-custom">
            <div class="row g-3">
                @foreach($activeBatches as $batch)
                    <div class="col-lg-6">
                        <div class="vtx-batch-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong class="text-clr-white">{{ $batch->specialDay?->name ?? 'Bilinmeyen' }}</strong>
                                    <span class="badge bg-teal-soft ms-2">{{ $batch->media_type === 'feed' ? 'Feed 1:1' : 'Story 9:16' }}</span>
                                </div>
                                <span class="vtx-status-{{ $batch->status }}">{{ $batch->status }}</span>
                            </div>
                            <div class="vtx-progress mb-2">
                                <div class="vtx-progress-bar" role="progressbar" aria-valuenow="{{ $batch->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-clr-muted">{{ $batch->completed_count }}/{{ $batch->total_count }} tamamlandı</small>
                                <small class="text-clr-muted">{{ $batch->formattedDuration() }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Special Days Table --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="card-title-custom"><i class="bi bi-calendar3 text-teal me-2"></i>Özel Günler — {{ $from->format('d.m.Y') }} / {{ $to->format('d.m.Y') }}</h5>
            <div class="d-flex gap-2">
                @for($m = 1; $m <= 12; $m++)
                    <a href="{{ route('admin.vertex.special-days.index', ['year' => $year, 'month' => $m]) }}"
                       class="cl-status-tab {{ (int)$month === $m ? 'active' : '' }}">
                        {{ \Carbon\Carbon::create(null, $m)->translatedFormat('M') }}
                    </a>
                @endfor
            </div>
        </div>
        <div class="card-body-custom">
            @if($specialDays->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x vtx-empty-icon"></i>
                    <p class="text-clr-muted mt-3">Bu dönemde özel gün bulunamadı.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Özel Gün</th>
                                <th>Kategori</th>
                                <th>Feed</th>
                                <th>Story</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($specialDays as $day)
                                <tr>
                                    <td>
                                        <span class="text-clr-white">{{ $day->date->format('d.m') }}</span>
                                        <small class="text-clr-muted ms-1">{{ $day->turkishWeekday() }}</small>
                                    </td>
                                    <td class="text-clr-white">{{ $day->name }}</td>
                                    <td><span class="badge {{ $day->categoryBadgeClass() }}">{{ $day->categoryLabel() }}</span></td>
                                    <td>
                                        @if(($day->feed_count ?? 0) > 0)
                                            <span class="badge bg-success">{{ $day->feed_count }} görsel</span>
                                        @else
                                            <span class="badge bg-dark">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(($day->story_count ?? 0) > 0)
                                            <span class="badge bg-success">{{ $day->story_count }} görsel</span>
                                        @else
                                            <span class="badge bg-dark">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Recent Batches --}}
    @if($recentBatches->isNotEmpty())
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom">
            <h5 class="card-title-custom"><i class="bi bi-clock-history text-teal me-2"></i>Son Batch'ler</h5>
        </div>
        <div class="card-body-custom">
            <div class="table-responsive">
                <table class="table table-dark-custom table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Özel Gün</th>
                            <th>Tür</th>
                            <th>Şablon</th>
                            <th>İlerleme</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentBatches as $batch)
                            <tr>
                                <td class="text-clr-muted">{{ $batch->id }}</td>
                                <td class="text-clr-white">{{ $batch->specialDay?->name ?? '—' }}</td>
                                <td><span class="badge bg-teal-soft">{{ $batch->media_type === 'feed' ? 'Feed' : 'Story' }}</span></td>
                                <td class="text-clr-muted">{{ $batch->prompt?->name ?? '—' }}</td>
                                <td>
                                    <div class="vtx-progress">
                                        <div class="vtx-progress-bar" role="progressbar" aria-valuenow="{{ $batch->progress() }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </td>
                                <td><span class="vtx-status-{{ $batch->status }}">{{ $batch->status }}</span></td>
                                <td class="text-clr-muted">{{ $batch->created_at->format('d.m H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
<script>
(function() {
    var form = document.getElementById('sdv-generate-form');
    var previewBtn = document.getElementById('sdv-preview-btn');
    var previewArea = document.getElementById('sdv-preview-area');
    var previewBody = document.getElementById('sdv-preview-body');
    var previewCount = document.getElementById('sdv-preview-count');
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function setProgressWidths() {
        document.querySelectorAll('.vtx-progress-bar').forEach(function(bar) {
            bar.style.width = bar.getAttribute('aria-valuenow') + '%';
        });
    }
    setProgressWidths();

    previewBtn.addEventListener('click', function() {
        var fromVal = form.querySelector('[name="from"]').value;
        var toVal = form.querySelector('[name="to"]').value;
        if (!fromVal || !toVal) return;

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Yükleniyor...';

        fetch('{{ route("admin.vertex.special-days.preview") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ from: fromVal, to: toVal })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-eye me-1"></i> Önizle';

            if (!data.success) {
                if (window.AdminModal) AdminModal.alert({ title: 'Hata', message: data.message || 'Bir hata oluştu.', type: 'danger' });
                return;
            }

            previewArea.classList.remove('d-none');
            previewCount.textContent = data.total;
            previewBody.innerHTML = '';

            data.days.forEach(function(day) {
                var status = '';
                if (day.has_feed_batch && day.has_story_batch) status = '<span class="badge bg-warning">Zaten kuyrukta</span>';
                else if (day.feed_count > 0 && day.story_count > 0) status = '<span class="badge bg-success">Görseller mevcut</span>';
                else status = '<span class="badge bg-dark">Üretilecek</span>';

                previewBody.innerHTML += '<tr>' +
                    '<td class="text-clr-white">' + day.date + '</td>' +
                    '<td class="text-clr-white">' + day.name + '</td>' +
                    '<td><span class="badge bg-teal-soft">' + day.category + '</span></td>' +
                    '<td>' + (day.feed_count > 0 ? '<span class="badge bg-success">' + day.feed_count + '</span>' : '<span class="badge bg-dark">0</span>') + '</td>' +
                    '<td>' + (day.story_count > 0 ? '<span class="badge bg-success">' + day.story_count + '</span>' : '<span class="badge bg-dark">0</span>') + '</td>' +
                    '<td>' + status + '</td>' +
                    '</tr>';
            });
        })
        .catch(function() {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-eye me-1"></i> Önizle';
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var submitBtn = form.querySelector('[type="submit"]');
        var fromVal = form.querySelector('[name="from"]').value;
        var toVal = form.querySelector('[name="to"]').value;
        var feedId = form.querySelector('[name="feed_prompt_id"]').value;
        var storyId = form.querySelector('[name="story_prompt_id"]').value;
        var countVal = form.querySelector('[name="count_per_day"]').value;

        if (!fromVal || !toVal || !feedId || !storyId) {
            if (window.AdminModal) AdminModal.alert({ title: 'Eksik Alan', message: 'Lütfen tüm alanları doldurun.', type: 'warning' });
            return;
        }

        if (window.AdminModal && typeof AdminModal.confirm === 'function') {
            AdminModal.confirm({
                title: 'Toplu Üretim',
                message: 'Seçilen tarih aralığındaki tüm özel günler için Feed + Story görselleri üretilecek. Devam edilsin mi?',
                type: 'info',
                confirmText: 'Başlat',
                confirmIcon: 'bi bi-rocket-takeoff',
            }).then(function(confirmed) {
                if (confirmed) doGenerate();
            });
        } else {
            doGenerate();
        }

        function doGenerate() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Kuyruğa alınıyor...';

            fetch('{{ route("admin.vertex.special-days.generate") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    from: fromVal,
                    to: toVal,
                    feed_prompt_id: parseInt(feedId),
                    story_prompt_id: parseInt(storyId),
                    count_per_day: parseInt(countVal)
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-rocket-takeoff me-1"></i> Üretimi Başlat';

                if (data.success) {
                    if (window.AdminModal) AdminModal.alert({ title: 'Başarılı', message: data.message, type: 'success' });
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    if (window.AdminModal) AdminModal.alert({ title: 'Hata', message: data.message, type: 'danger' });
                }
            })
            .catch(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-rocket-takeoff me-1"></i> Üretimi Başlat';
                if (window.AdminModal) AdminModal.alert({ title: 'Hata', message: 'Sunucu hatası oluştu.', type: 'danger' });
            });
        }
    });
})();
</script>
@endpush
