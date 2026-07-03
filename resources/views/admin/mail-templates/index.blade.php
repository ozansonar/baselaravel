@extends('layouts.admin')

@section('title', 'Mail Şablonları')

@section('content')
{{-- Breadcrumb --}}
<nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
        </li>
        <li class="breadcrumb-item active text-teal">Mail Şablonları</li>
    </ol>
</nav>

{{-- Page Header --}}
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
    <div>
        <h1 class="page-title">Mail Şablonları</h1>
        <p class="page-subtitle">Sistemden gönderilen e-posta şablonlarını düzenleyin</p>
    </div>
</div>

{{-- Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="0">
        <div class="usr-stat-card">
            <div class="usr-stat-icon usr-stat-icon-teal">
                <i class="bi bi-envelope-open"></i>
            </div>
            <div class="usr-stat-info">
                <span class="usr-stat-label">Toplam Şablon</span>
                <h3 class="usr-stat-value" data-count="{{ $templates->count() }}">0</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="100">
        <div class="usr-stat-card">
            <div class="usr-stat-icon usr-stat-icon-green">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="usr-stat-info">
                <span class="usr-stat-label">Aktif</span>
                <h3 class="usr-stat-value" data-count="{{ $templates->where('is_active', true)->count() }}">0</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="200">
        <div class="usr-stat-card">
            <div class="usr-stat-icon usr-stat-icon-orange">
                <i class="bi bi-pause-circle"></i>
            </div>
            <div class="usr-stat-info">
                <span class="usr-stat-label">Pasif</span>
                <h3 class="usr-stat-value" data-count="{{ $templates->where('is_active', false)->count() }}">0</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="300">
        <div class="usr-stat-card">
            <div class="usr-stat-icon usr-stat-icon-blue">
                <i class="bi bi-braces"></i>
            </div>
            <div class="usr-stat-info">
                <span class="usr-stat-label">Toplam Değişken</span>
                @php
                    $totalVars = $templates->sum(fn ($t) => is_array($t->variables) ? count($t->variables) : 0);
                @endphp
                <h3 class="usr-stat-value" data-count="{{ $totalVars }}">0</h3>
            </div>
        </div>
    </div>
</div>

{{-- Toolbar --}}
<div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
    <div class="card-body-custom">
        <div class="cl-toolbar">
            {{-- Search --}}
            <div class="cl-search">
                <i class="bi bi-search"></i>
                <input type="text" id="mtSearchInput" placeholder="Şablon adı veya anahtar ile ara...">
            </div>

            {{-- Filters & Result Count --}}
            <div class="cl-toolbar-actions">
                <select class="cl-filter-select" id="mtStatusFilter">
                    <option value="">Tümü</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Pasif</option>
                </select>
                <span class="text-muted ms-auto" id="mtResultCount">{{ $templates->count() }} şablon</span>
            </div>
        </div>
    </div>
</div>

{{-- Template Cards --}}
<div class="row g-4" id="mtTemplateGrid">
    @foreach($templates as $template)
    <div class="col-xl-4 col-md-6 mt-tpl-item"
         data-name="{{ mb_strtolower($template->name) }}"
         data-key="{{ mb_strtolower($template->key) }}"
         data-status="{{ $template->is_active ? 'active' : 'inactive' }}"
         data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 100 }}">
        <div class="mt-tpl-card {{ !$template->is_active ? 'mt-tpl-card--inactive' : '' }}">
            <div class="mt-tpl-header">
                <div class="mt-tpl-icon">
                    <i class="bi bi-envelope-open-fill"></i>
                </div>
                <div class="mt-tpl-title">
                    <h6>{{ $template->name }}</h6>
                    <code>{{ $template->key }}</code>
                </div>
                @if($template->is_active)
                    <span class="mt-tpl-badge mt-tpl-badge--active">Aktif</span>
                @else
                    <span class="mt-tpl-badge mt-tpl-badge--inactive">Pasif</span>
                @endif
            </div>

            @if($template->description)
                <p class="mt-tpl-desc">{{ $template->description }}</p>
            @endif

            <div class="mt-tpl-subject">
                <small class="mt-tpl-subject-label">Konu:</small>
                <span>{{ Str::limit($template->subject, 60) }}</span>
            </div>

            <div class="mt-tpl-vars">
                <small class="mt-tpl-vars-label">Değişkenler:</small>
                <div class="mt-tpl-vars-list">
                    @foreach(($template->variables ?? []) as $variable)
                        <span class="mt-tpl-var-tag">{{ '{' . $variable['key'] . '}' }}</span>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('admin.mail-templates.edit', $template) }}" class="mt-tpl-edit-btn">
                <i class="bi bi-pencil-square me-1"></i> Düzenle
            </a>
        </div>
    </div>
    @endforeach
</div>

{{-- Empty State --}}
<div class="d-none mt-4" id="mtEmptyState">
    <div class="card-dark">
        <div class="card-body-custom text-center py-5">
            <i class="bi bi-envelope-x d-block fs-1 mb-2 text-muted"></i>
            <h6 class="text-muted mb-1">Sonuç bulunamadı</h6>
            <p class="text-muted mb-3">Arama kriterlerinize uygun şablon bulunamadı.</p>
            <button type="button" class="btn-glass" id="mtClearFilters">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Filtreleri Temizle
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Counter animation
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target) || target === 0) { el.textContent = '0'; return; }
        var start = 0, duration = 800, startTime = null;
        function animate(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / duration, 1);
            el.textContent = Math.floor(progress * target).toLocaleString('tr-TR');
            if (progress < 1) requestAnimationFrame(animate);
            else el.textContent = target.toLocaleString('tr-TR');
        }
        requestAnimationFrame(animate);
    });

    // Filter & search
    var searchInput = document.getElementById('mtSearchInput');
    var statusFilter = document.getElementById('mtStatusFilter');
    var grid = document.getElementById('mtTemplateGrid');
    var items = grid.querySelectorAll('.mt-tpl-item');
    var resultCount = document.getElementById('mtResultCount');
    var emptyState = document.getElementById('mtEmptyState');
    var clearBtn = document.getElementById('mtClearFilters');

    function filterTemplates() {
        var query = searchInput.value.toLowerCase().trim();
        var status = statusFilter.value;
        var visible = 0;

        items.forEach(function (item) {
            var name = item.getAttribute('data-name');
            var key = item.getAttribute('data-key');
            var itemStatus = item.getAttribute('data-status');

            var matchSearch = !query || name.indexOf(query) > -1 || key.indexOf(query) > -1;
            var matchStatus = !status || itemStatus === status;

            if (matchSearch && matchStatus) {
                item.classList.remove('d-none');
                visible++;
            } else {
                item.classList.add('d-none');
            }
        });

        resultCount.textContent = visible + ' şablon';

        if (visible === 0) {
            emptyState.classList.remove('d-none');
        } else {
            emptyState.classList.add('d-none');
        }
    }

    searchInput.addEventListener('input', filterTemplates);
    statusFilter.addEventListener('change', filterTemplates);

    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        statusFilter.value = '';
        filterTemplates();
    });
});
</script>
@endpush
