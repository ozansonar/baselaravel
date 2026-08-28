@extends('layouts.admin')

@section('title', 'Roller & İzinler')

@php
    /** @var \App\Services\RoleService $roleService */
    $accents = ['accent-teal', 'accent-blue', 'accent-purple', 'accent-orange', 'accent-green', 'accent-pink'];
@endphp

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Roller & İzinler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Roller & İzinler</h1>
            <p class="page-subtitle">Rolleri yönetin ve her rolün hangi işlemleri yapabileceğini belirleyin</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <x-export-menu export="roles" :total="$roles->count()" />
            @can('create', App\Models\Role::class)
            <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#roleModal" id="addRoleBtn">
                <i class="bi bi-plus-lg"></i> Yeni Rol
            </button>
            @endcan
        </div>
    </div>

    {{-- ==================== SECTION 1: STATS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="rp-stat-card">
                <div class="rp-stat-icon accent-teal"><i class="bi bi-shield-fill"></i></div>
                <div class="rp-stat-info">
                    <span class="rp-stat-label">Toplam Rol</span>
                    <h3 class="rp-stat-value" data-count="{{ $stats['roles'] }}">0</h3>
                    <span class="rp-stat-sub">{{ $stats['system_roles'] }} sistem, {{ $stats['roles'] - $stats['system_roles'] }} özel</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="rp-stat-card">
                <div class="rp-stat-icon accent-purple"><i class="bi bi-key-fill"></i></div>
                <div class="rp-stat-info">
                    <span class="rp-stat-label">Toplam İzin</span>
                    <h3 class="rp-stat-value" data-count="{{ $stats['permissions'] }}">0</h3>
                    <span class="rp-stat-sub">{{ $stats['groups'] }} kategori</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="rp-stat-card">
                <div class="rp-stat-icon accent-blue"><i class="bi bi-people-fill"></i></div>
                <div class="rp-stat-info">
                    <span class="rp-stat-label">Atanmış Kullanıcı</span>
                    <h3 class="rp-stat-value" data-count="{{ $stats['assigned_users'] }}">0</h3>
                    <span class="rp-stat-sub">tüm roller dahil</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="rp-stat-card">
                <div class="rp-stat-icon accent-orange"><i class="bi bi-clock-history"></i></div>
                <div class="rp-stat-info">
                    <span class="rp-stat-label">Son Güncelleme</span>
                    <h3 class="rp-stat-value fs-18">{{ $roles->max('updated_at')?->diffForHumans() ?? '—' }}</h3>
                    <span class="rp-stat-sub">rol veya izin değişikliği</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: ROLE CARDS ==================== --}}
    <div class="form-section-header">
        <div class="form-section-icon"><i class="bi bi-shield-fill-check"></i></div>
        <div>
            <h6 class="mb-0">Roller</h6>
            <small class="text-muted">Sistem rolleri silinemez; özel roller eklenebilir ve kaldırılabilir</small>
        </div>
    </div>

    <div class="row g-4 mb-4">
        @foreach($roles as $index => $role)
            @php
                $accent = $accents[$index % count($accents)];
                $isSystem = $roleService->isSystemRole($role);
                $isLocked = $roleService->isLocked($role);
            @endphp
            <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="{{ ($index % 3) * 50 }}">
                <div class="rp-role-card" data-role="{{ $role->slug }}">
                    <div class="rp-role-header">
                        <div class="rp-role-icon {{ $accent }}"><i class="bi bi-shield-fill"></i></div>
                        <div class="rp-role-title">
                            <h5>{{ $role->name }}</h5>
                            <span class="rp-role-type {{ $isSystem ? 'system' : '' }}">
                                {{ $isSystem ? 'Sistem Rolü' : 'Özel Rol' }}
                            </span>
                        </div>
                        @canany(['update', 'delete'], $role)
                        <div class="rp-role-menu">
                            <div class="dropdown">
                                <button class="usr-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @can('update', $role)
                                    <li>
                                        <button type="button" class="dropdown-item js-edit-role"
                                                data-id="{{ $role->id }}"
                                                data-name="{{ $role->name }}"
                                                data-slug="{{ $role->slug }}"
                                                data-description="{{ $role->description }}"
                                                data-system="{{ $isSystem ? '1' : '0' }}">
                                            <i class="bi bi-pencil me-2"></i> Düzenle
                                        </button>
                                    </li>
                                    @endcan
                                    @can('delete', $role)
                                        @if(!$isSystem)
                                        <li>
                                            <button type="button" class="dropdown-item text-danger js-delete-role"
                                                    data-id="{{ $role->id }}" data-name="{{ $role->name }}">
                                                <i class="bi bi-trash me-2"></i> Sil
                                            </button>
                                        </li>
                                        @endif
                                    @endcan
                                </ul>
                            </div>
                        </div>
                        @endcanany
                    </div>

                    <p class="rp-role-desc">{{ $role->description ?: 'Açıklama girilmemiş.' }}</p>

                    <div class="rp-role-meta">
                        <div class="rp-role-meta-item">
                            <i class="bi bi-people"></i><span>{{ $role->users_count }} kullanıcı</span>
                        </div>
                        <div class="rp-role-meta-item">
                            <i class="bi bi-key"></i><span>{{ $role->permissions_count }}/{{ $stats['permissions'] }} izin</span>
                        </div>
                    </div>

                    @if($isLocked)
                        <div class="rp-role-meta">
                            <div class="rp-role-meta-item">
                                <i class="bi bi-lock-fill"></i><span>Tüm izinler kalıcı olarak açık</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ==================== SECTION 3: PERMISSION MATRIX ==================== --}}
    <form method="POST" action="{{ route('admin.roles.permissions.sync') }}" id="matrixForm">
        @csrf
        @method('PUT')

        <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="mb-0"><i class="bi bi-grid-3x3 me-2 text-teal"></i>İzin Matrisi</h6>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm form-control-theme" style="width:auto" id="matrixCategoryFilter" data-fv-ignore>
                        <option value="all">Tüm Kategoriler</option>
                        @foreach($matrix as $key => $section)
                            <option value="{{ $key }}">{{ $section['group']->label() }}</option>
                        @endforeach
                    </select>
                    @can('managePermissions', App\Models\Role::class)
                    <button type="submit" class="btn-teal btn-sm">
                        <i class="bi bi-check-lg"></i> İzinleri Kaydet
                    </button>
                    @endcan
                </div>
            </div>

            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="rp-matrix-table">
                        <thead>
                            <tr>
                                <th class="rp-matrix-perm-col">İzin</th>
                                @foreach($roles as $index => $role)
                                    <th class="rp-matrix-role-col">
                                        <div class="rp-matrix-role-head {{ $accents[$index % count($accents)] }}">
                                            <i class="bi bi-shield-fill"></i><span>{{ $role->name }}</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrix as $key => $section)
                                <tr class="rp-matrix-group" data-cat="{{ $key }}">
                                    <td colspan="{{ $roles->count() + 1 }}">
                                        <i class="bi {{ $section['group']->icon() }} me-2"></i>{{ $section['group']->label() }}
                                    </td>
                                </tr>
                                @foreach($section['permissions'] as $permission)
                                    <tr data-cat="{{ $key }}">
                                        <td class="rp-perm-name">
                                            <span>{{ $permission->name }}</span>
                                            <small>{{ $permission->key }}</small>
                                        </td>
                                        @foreach($roles as $role)
                                            @php
                                                $granted = $role->permissions->contains('key', $permission->key);
                                                $locked  = $roleService->isLocked($role);
                                            @endphp
                                            <td>
                                                <label class="rp-check {{ $granted ? 'granted' : '' }}">
                                                    <input type="checkbox" data-fv-ignore
                                                           name="permissions[{{ $role->slug }}][]"
                                                           value="{{ $permission->key }}"
                                                           @checked($granted)
                                                           @disabled($locked)>
                                                    <span></span>
                                                </label>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>

    {{-- Rol ekleme / düzenleme modalı --}}
    @can('create', App\Models\Role::class)
    <div class="modal fade modal-custom" id="roleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.roles.store') }}" id="roleForm" data-validate novalidate>
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="roleFormMethod">

                    <div class="modal-header">
                        <h5 class="modal-title" id="roleModalTitle">Yeni Rol</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-clr-secondary" for="roleName">Rol Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-theme @error('name') is-invalid @enderror"
                                   id="roleName" name="name" value="{{ old('name') }}" data-validation-engine="validate[required,maxSize[100]]">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-clr-secondary" for="roleSlug">Rol Anahtarı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-theme @error('slug') is-invalid @enderror"
                                   id="roleSlug" name="slug" value="{{ old('slug') }}" placeholder="ornek-rol" data-validation-engine="validate[required,custom[slug],maxSize[100]]">
                            <small class="form-text text-clr-muted">Kodda kullanılan benzersiz anahtar. Sistem rollerinde değiştirilemez.</small>
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-clr-secondary" for="roleDescription">Açıklama</label>
                            <textarea class="form-control form-control-theme" id="roleDescription"
                                      name="description" rows="2"
                                      data-validation-engine="validate[maxSize[255]]">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn-teal"><i class="bi bi-check-lg"></i> Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- Silme onay modalı --}}
    @can('deleteAny', App\Models\Role::class)
    <div class="modal fade modal-custom" id="deleteRoleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="delete-modal-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <h5 class="mt-3 mb-2">Rolü sil</h5>
                    <p class="text-clr-muted mb-0">
                        <strong id="deleteRoleName"></strong> rolü silinecek. Bu role atanmış
                        kullanıcıların rolü kaldırılacak.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                    <form method="POST" id="deleteRoleForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger-solid"><i class="bi bi-trash"></i> Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endcan
@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/roles.js') }}"></script>
@endpush
