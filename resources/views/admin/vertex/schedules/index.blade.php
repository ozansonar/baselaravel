@extends('layouts.admin')

@section('title', 'Vertex Zamanlama')
@section('meta_description', 'Otomatik görsel üretim zamanlamaları')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('admin.vertex.index') }}" class="breadcrumb-link">Vertex</a>
            </li>
            <li class="breadcrumb-item active text-teal">Zamanlama</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">Vertex Zamanlama</h1>
            <p class="page-subtitle">Otomatik tekrarlayan görsel üretim zamanlamaları</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn-teal" onclick="openScheduleModal()">
                <i class="bi bi-plus-lg me-1"></i> Yeni Zamanlama
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-alarm-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Zamanlama</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif Zamanlama</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-image-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bugün Üretilen</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['today_generated'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple">
                    <i class="bi bi-bar-chart-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Üretim</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total_generated'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedules Table -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="0">
        <div class="card-body-custom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                    <h5 class="rpr-chart-title">Zamanlamalar</h5>
                    <p class="rpr-chart-sub">Otomatik çalışan ve planlanan üretimler</p>
                </div>
                <button class="btn-teal btn-sm" onclick="openScheduleModal()">
                    <i class="bi bi-plus-lg me-1"></i> Yeni Plan
                </button>
            </div>

            @if($schedules->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-alarm text-muted fs-1"></i>
                    <p class="text-muted mt-3">Henüz zamanlama oluşturulmamış.</p>
                    <button class="btn-teal" onclick="openScheduleModal()">
                        <i class="bi bi-plus-lg me-1"></i> İlk Zamanlamayı Oluştur
                    </button>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover cl-table mb-0">
                        <thead>
                            <tr>
                                <th>Zamanlama</th>
                                <th class="d-none d-md-table-cell">Sıklık</th>
                                <th class="d-none d-lg-table-cell">Son Çalışma</th>
                                <th class="d-none d-xl-table-cell">Sonraki</th>
                                <th>Adet</th>
                                <th>Durum</th>
                                <th class="cl-th-actions">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="schedulesTableBody">
                            @foreach($schedules as $schedule)
                                <tr id="schedule-row-{{ $schedule->id }}" data-aos="fade-right" data-aos-delay="{{ $loop->index * 60 }}">
                                    <td>
                                        <div class="rpr-sched-name">
                                            <div class="rpr-sched-icon rpr-sched-icon-teal"><i class="bi bi-stars"></i></div>
                                            <div>
                                                <span class="rpr-sched-title">{{ $schedule->name }}</span>
                                                <span class="rpr-sched-sub">{{ $schedule->prompt?->name ?? '—' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <span class="rpr-freq-badge">{{ $schedule->frequencyLabel() }}</span>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <span class="usr-meta">{{ $schedule->last_run_at?->format('d.m.Y H:i') ?? '—' }}</span>
                                    </td>
                                    <td class="d-none d-xl-table-cell">
                                        @if($schedule->is_active && $schedule->next_run_at)
                                            <span class="rpr-next-date text-neon-green">{{ $schedule->next_run_at->format('d.m.Y H:i') }}</span>
                                        @else
                                            <span class="usr-meta">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $schedule->count }} görsel</span>
                                    </td>
                                    <td>
                                        <span class="rpr-status-badge {{ $schedule->is_active ? 'active' : 'inactive' }}">
                                            {{ $schedule->is_active ? 'Aktif' : 'Pasif' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="usr-actions">
                                            <button class="usr-action-btn" title="Düzenle" onclick="openEditModal({{ $schedule->id }})">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            @if($schedule->is_active)
                                                <button class="usr-action-btn" title="Şimdi Çalıştır" onclick="runNow({{ $schedule->id }})">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            @endif
                                            <button class="usr-action-btn {{ $schedule->is_active ? '' : 'success' }}" title="{{ $schedule->is_active ? 'Pasif Yap' : 'Aktif Yap' }}" onclick="toggleSchedule({{ $schedule->id }})">
                                                <i class="bi bi-{{ $schedule->is_active ? 'pause-circle' : 'lightning-charge' }}"></i>
                                            </button>
                                            <button class="usr-action-btn danger" title="Sil" onclick="openDeleteModal({{ $schedule->id }}, '{{ e($schedule->name) }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>


<!-- ==================== MODAL: OLUŞTUR / DÜZENLE ==================== -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title prd-modal-title">
                    <i class="bi bi-alarm me-2"></i><span id="scheduleModalTitle">Yeni Zamanlama</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scheduleId" value="">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Zamanlama Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="scheduleName" placeholder="Örn: Günlük İnfografik Üretimi">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Görsel Adedi <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="scheduleCount" value="3" min="1" max="50">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Prompt Şablonu <span class="text-danger">*</span></label>
                        <select class="form-select" id="schedulePromptId" onchange="onPromptChange()">
                            <option value="">Şablon seçin...</option>
                            @foreach($prompts as $prompt)
                                <option value="{{ $prompt->id }}"
                                        data-prompt="{{ e($prompt->prompt) }}"
                                        data-random-options='@json($prompt->random_options ?? new \stdClass())'
                                        data-aspect-ratio="{{ $prompt->aspect_ratio }}">
                                    {{ $prompt->name }} ({{ $prompt->aspect_ratio }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">En-boy Oranı (Override)</label>
                        <select class="form-select" id="scheduleAspectRatio">
                            <option value="">Şablondan al</option>
                            <option value="1:1">1:1 (Feed)</option>
                            <option value="4:5">4:5 (Feed Dikey)</option>
                            <option value="9:16">9:16 (Story/Reels)</option>
                            <option value="16:9">16:9 (Yatay)</option>
                        </select>
                    </div>

                    <!-- Dynamic Variables -->
                    <div class="col-12 d-none" id="variablesContainer"></div>

                    <div class="col-md-4">
                        <label class="form-label">Sıklık <span class="text-danger">*</span></label>
                        <select class="form-select" id="scheduleFrequency" onchange="onFrequencyChange()">
                            <option value="daily">Her Gün</option>
                            <option value="weekly">Haftanın Belirli Günleri</option>
                            <option value="monthly">Ayın Belirli Günü</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Saat <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="scheduleTime" value="09:00">
                    </div>

                    <!-- Weekly days -->
                    <div class="col-md-12 d-none" id="weeklyDaysContainer">
                        <label class="form-label">Günler</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="1" class="day-checkbox"> <span>Pzt</span>
                            </label>
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="2" class="day-checkbox"> <span>Sal</span>
                            </label>
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="3" class="day-checkbox"> <span>Çar</span>
                            </label>
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="4" class="day-checkbox"> <span>Per</span>
                            </label>
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="5" class="day-checkbox"> <span>Cum</span>
                            </label>
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="6" class="day-checkbox"> <span>Cmt</span>
                            </label>
                            <label class="vtx-sched-day-check">
                                <input type="checkbox" value="7" class="day-checkbox"> <span>Paz</span>
                            </label>
                        </div>
                    </div>

                    <!-- Monthly day -->
                    <div class="col-md-4 d-none" id="monthlyDayContainer">
                        <label class="form-label">Ayın Günü</label>
                        <input type="number" class="form-control" id="scheduleDayOfMonth" value="1" min="1" max="28">
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <button class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                    <button class="btn-teal" id="scheduleSubmitBtn" onclick="submitSchedule()">
                        <i class="bi bi-check-lg me-1"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ==================== MODAL: SİLME ONAY ==================== -->
<div class="modal fade" id="deleteScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="status-modal-icon danger">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h5 class="cl-modal-heading">Zamanlamayı Sil</h5>
                <p class="cl-modal-body-text"><strong id="deleteScheduleName"></strong> zamanlaması silinecek.</p>
                <p class="cl-modal-warning"><i class="bi bi-exclamation-circle me-1"></i>Bu işlem geri alınamaz.</p>
                <input type="hidden" id="deleteScheduleId" value="">
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                    <button class="btn-teal btn-danger-gradient" onclick="confirmDelete()">
                        <i class="bi bi-trash me-1"></i> Sil
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    @php
        $jsData = [];
        foreach ($schedules as $s) {
            $jsData[$s->id] = [
                'id'                     => $s->id,
                'name'                   => $s->name,
                'prompt_id'              => $s->prompt_id,
                'variables'              => $s->variables,
                'count'                  => $s->count,
                'aspect_ratio_override'  => $s->aspect_ratio_override,
                'frequency'              => $s->frequency,
                'days_of_week'           => $s->days_of_week,
                'day_of_month'           => $s->day_of_month,
                'time'                   => $s->time,
                'is_active'              => $s->is_active,
            ];
        }
    @endphp
    window._vtxSchedData = {!! json_encode($jsData, JSON_UNESCAPED_UNICODE) !!};
</script>
<script src="{{ asset('assets/admin/js/vertex-schedules.js') }}"></script>
@endpush
