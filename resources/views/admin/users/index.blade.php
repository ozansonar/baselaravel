@extends('layouts.admin')

@section('title', 'Kullanıcı Yönetimi')
@section('page_title', 'Kullanıcı Yönetimi')
@section('page_description', 'Sistemdeki tüm kullanıcıları yönetin, roller atayın ve erişimleri kontrol edin')

@section('content')
    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Kullanıcı Yönetimi</h1>
            <p class="page-subtitle">Sistemdeki tüm kullanıcıları yönetin, roller atayın ve erişimleri kontrol edin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.users.create') }}" class="btn-teal">
                <i class="bi bi-person-plus me-1"></i> Yeni Kullanıcı
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Kullanıcı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif Kullanıcı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['active'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Yeni Kayıtlar</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['new_this_month'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-pink">
                    <i class="bi bi-person-dash-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Pasif Kullanıcı</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['inactive'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="cl-status-tabs mb-4" data-aos="fade-up">
        <a href="{{ route('admin.users.index', request()->except(['status', 'page'])) }}"
           class="cl-status-tab {{ !request('status') ? 'active' : '' }}">
            Tümü <span class="cl-tab-count">{{ $statusCounts['all'] }}</span>
        </a>
        <a href="{{ route('admin.users.index', array_merge(request()->except('page'), ['status' => 'active'])) }}"
           class="cl-status-tab {{ request('status') === 'active' ? 'active' : '' }}">
            Aktif <span class="cl-tab-count">{{ $statusCounts['active'] }}</span>
        </a>
        <a href="{{ route('admin.users.index', array_merge(request()->except('page'), ['status' => 'inactive'])) }}"
           class="cl-status-tab {{ request('status') === 'inactive' ? 'active' : '' }}">
            Pasif <span class="cl-tab-count">{{ $statusCounts['inactive'] }}</span>
        </a>
        @if($statusCounts['trashed'] > 0)
        <a href="{{ route('admin.users.index', array_merge(request()->except('page'), ['status' => 'trashed'])) }}"
           class="cl-status-tab {{ request('status') === 'trashed' ? 'active' : '' }}">
            Silinmiş <span class="cl-tab-count">{{ $statusCounts['trashed'] }}</span>
        </a>
        @endif
    </div>

    <!-- Toolbar -->
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.users.index') }}" id="filterForm">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="usr-toolbar">
                    <div class="usr-search">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" placeholder="Ad, e-posta veya rol ile ara..."
                               value="{{ request('search') }}" data-fv-ignore>
                    </div>

                    <div class="usr-filters">
                        <select class="usr-filter-select" name="role" data-submit-form="filterForm" data-fv-ignore>
                            <option value="">Tüm Roller</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->slug }}" {{ request('role') === $role->slug ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="usr-toolbar-actions">
                        <select class="usr-filter-select" name="per_page" data-submit-form="filterForm" data-fv-ignore>
                            @foreach([10, 25, 50, 100] as $pp)
                                <option value="{{ $pp }}" {{ (int) request('per_page', 10) === $pp ? 'selected' : '' }}>
                                    {{ $pp }} / sayfa
                                </option>
                            @endforeach
                        </select>
                        <div class="usr-view-toggle">
                            <button type="button" class="usr-view-btn active" data-action="gorunum" data-view="table" title="Tablo Görünümü">
                                <i class="bi bi-list-ul"></i>
                            </button>
                            <button type="button" class="usr-view-btn" data-action="gorunum" data-view="grid" title="Kart Görünümü">
                                <i class="bi bi-grid-3x3-gap"></i>
                            </button>
                        </div>
                        @if(request()->hasAny(['search', 'role']))
                            <a href="{{ route('admin.users.index', request()->only('status')) }}" class="btn-glass">
                                <i class="bi bi-arrow-clockwise me-1"></i> Sıfırla
                            </a>
                        @endif

                        <x-export-menu export="users" :total="$users->total()" />
                    </div>
                </div>

                {{-- Sürücü: assets/admin/js/bulk-actions.js --}}
                <div class="usr-bulk-actions d-none" data-bulk-bar>
                    <span class="usr-bulk-count"><span data-bulk-count>0</span> seçili</span>
                    @if(request('status') === 'trashed')
                        <button type="button" class="btn-glass"
                                data-bulk-action="bulkRestoreForm"
                                data-bulk-title="Toplu Geri Yükleme"
                                data-bulk-message=":count kullanıcı geri yüklenecek. Onaylıyor musunuz?"
                                data-bulk-confirm="Evet, Geri Yükle"
                                data-bulk-icon="bi bi-arrow-counterclockwise"><i class="bi bi-arrow-counterclockwise me-1"></i>Geri Yükle</button>
                    @else
                        <button type="button" class="btn-glass"
                                data-bulk-action="bulkDeleteForm"
                                data-bulk-title="Toplu Silme Onayı"
                                data-bulk-message=":count kullanıcı silinecek. Kendi hesabınız seçili olsa bile silinmez."
                                data-bulk-type="danger"
                                data-bulk-confirm="Evet, Sil"
                                data-bulk-icon="bi bi-trash3"><i class="bi bi-trash me-1"></i>Sil</button>
                    @endif
                    <button type="button" class="btn-glass" data-bulk-clear><i class="bi bi-x-lg me-1"></i>Seçimi Bırak</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table View -->
    <div class="card-dark mb-4" id="tableView" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="usr-table">
                    <thead>
                        <tr>
                            <th class="usr-th-checkbox">
                                <input type="checkbox" class="usr-checkbox" data-bulk-all aria-label="Tümünü seç" data-fv-ignore>
                            </th>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Durum</th>
                            <th class="d-none d-xl-table-cell">Kayıt Tarihi</th>
                            <th class="usr-th-actions">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        @forelse($users as $user)
                        <tr>
                            <td><input type="checkbox" class="usr-checkbox" data-bulk-item value="{{ $user->id }}" data-fv-ignore></td>
                            <td>
                                <div class="usr-user-cell">
                                    <div class="usr-avatar">
                                        @if($user->avatar)
                                            <img src="{{ upload_url($user->avatar, 'thumb') }}" alt="{{ $user->full_name }}">
                                        @else
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($user->full_name) }}&background=14b8a6&color=fff&size=40" alt="{{ $user->full_name }}">
                                        @endif
                                    </div>
                                    <div class="usr-user-info">
                                        <span class="usr-user-name">{{ $user->full_name }}</span>
                                        <span class="usr-user-email">{{ $user->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @foreach($user->roles as $role)
                                    <span class="usr-role-badge {{ $role->slug }}">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if($user->trashed())
                                    <span class="usr-status-badge suspended">Silinmiş</span>
                                @elseif($user->is_active)
                                    <span class="usr-status-badge active">Aktif</span>
                                @else
                                    <span class="usr-status-badge inactive">Pasif</span>
                                @endif
                            </td>
                            <td class="d-none d-xl-table-cell">
                                <span class="usr-meta">{{ $user->created_at->translatedFormat('d M Y H:i') }}</span>
                            </td>
                            <td>
                                <div class="usr-actions">
                                    @if($user->trashed())
                                        <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="usr-action-btn" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        </form>
                                    @else
                                        <a class="usr-action-btn" title="Düzenle" href="{{ route('admin.users.edit', $user) }}"><i class="bi bi-pencil"></i></a>
                                        @if(auth()->id() !== $user->id)
                                            <button type="button" class="usr-action-btn danger" title="Sil"
                                                    data-action="sil" data-id="{{ $user->id }}" data-label="'{{ $user->full_name }}'">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-people d-block fs-1 mb-2"></i>
                                Kullanıcı bulunamadı.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.admin.pagination', ['paginator' => $users, 'itemLabel' => 'kullanıcı'])
        </div>
    </div>

    <!-- Grid View -->
    <div id="gridView" class="d-none">
        <div class="row g-4 mb-4">
            @forelse($users as $user)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="usr-card {{ $user->trashed() ? 'suspended' : (!$user->is_active ? 'suspended' : '') }}">
                    <div class="usr-card-header">
                        <div class="usr-card-menu">
                            <a class="usr-action-btn" href="{{ route('admin.users.edit', $user->trashed() ? '#' : $user) }}">
                                <i class="bi bi-three-dots-vertical"></i>
                            </a>
                        </div>
                        <div class="usr-card-avatar">
                            @if($user->avatar)
                                <img src="{{ upload_url($user->avatar, 'thumb') }}" alt="{{ $user->full_name }}">
                            @else
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($user->full_name) }}&background=14b8a6&color=fff&size=80" alt="{{ $user->full_name }}">
                            @endif
                        </div>
                        <h5 class="usr-card-name">{{ $user->full_name }}</h5>
                        <span class="usr-card-email">{{ $user->email }}</span>
                        @foreach($user->roles as $role)
                            <span class="usr-role-badge {{ $role->slug }} mt-2">{{ $role->name }}</span>
                        @endforeach
                        @if(!$user->is_active)
                            <span class="usr-status-badge inactive mt-2">Pasif</span>
                        @endif
                    </div>
                    <div class="usr-card-body">
                        <div class="usr-card-stats">
                            <div class="usr-card-stat">
                                <span class="usr-card-stat-val">{{ $user->created_at->translatedFormat('d M Y') }}</span>
                                <span class="usr-card-stat-label">Kayıt Tarihi</span>
                            </div>
                        </div>
                    </div>
                    <div class="usr-card-footer">
                        @if(!$user->trashed())
                            <a class="usr-card-btn primary" href="{{ route('admin.users.edit', $user) }}"><i class="bi bi-pencil"></i> Düzenle</a>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-people d-block fs-1 mb-2"></i>
                Kullanıcı bulunamadı.
            </div>
            @endforelse
        </div>
    </div>

    {{-- Toplu işlem formları — kimlikleri bulk-actions.js dolduruyor. --}}
    <form method="POST" action="{{ route('admin.users.bulk-destroy', request()->query()) }}" id="bulkDeleteForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>
    <form method="POST" action="{{ route('admin.users.bulk-restore', request()->query()) }}" id="bulkRestoreForm" class="d-none">
        @csrf
        @method('PATCH')
    </form>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/users.js') }}"></script>
@endpush
