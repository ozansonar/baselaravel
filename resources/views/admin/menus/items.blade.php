@extends('layouts.admin')

@section('title', $menu->name . ' Öğeleri')
@section('page_title', $menu->name . ' — Öğe Yönetimi')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item"><a href="{{ route('admin.menus.index') }}" class="breadcrumb-link">Menü Yönetimi</a></li>
            <li class="breadcrumb-item active text-teal">{{ $menu->name }}</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">{{ $menu->name }} Öğeleri</h1>
            <p class="page-subtitle">
                <span class="badge bg-secondary me-1">{{ $menuLanguage?->flag }} {{ $menuLanguage?->native_name ?? strtoupper($menu->locale) }}</span>
                Sürükle-bırak ile sıralayın, düzenleyin ve alt öğeler ekleyin
            </p>
        </div>
        <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#menuItemModal" id="addRootItemBtn">
            <i class="bi bi-plus-lg"></i> Yeni Öğe Ekle
        </button>
    </div>


    <div class="admin-card menu-tree-card" data-aos="fade-up">
        <div class="admin-card-body">
            <div class="menu-tree-toolbar mb-3">
                <span class="menu-tree-hint">
                    <i class="bi bi-info-circle"></i>
                    Öğeleri sürükle-bırak ile sıralayabilir, başka bir öğenin altına bırakarak alt öğe yapabilirsiniz.
                </span>
                <span id="reorderStatus" class="menu-tree-saved d-none">
                    <i class="bi bi-check-circle-fill"></i> Sıralama kaydedildi
                </span>
            </div>

            @if($menu->rootItems->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-list-nested" style="font-size: 3rem; opacity: 0.4;"></i>
                    <p class="mt-3 text-clr-secondary mb-0">Henüz menü öğesi yok. Yeni bir öğe ekleyerek başlayın.</p>
                </div>
            @else
                <ul class="menu-tree" id="menuTree" data-menu-id="{{ $menu->id }}"
                    data-reorder-url="{{ route('admin.menus.items.reorder', $menu) }}">
                    @foreach($menu->rootItems as $item)
                        @include('admin.menus.partials.item', ['item' => $item])
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Add/Edit Modal --}}
    <div class="modal fade" id="menuItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-theme">
            <div class="modal-content modal-content-theme">
                <form method="POST" id="menuItemForm">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" name="parent_id" id="parentIdInput" value="">

                    <div class="modal-header modal-header-theme">
                        <h5 class="modal-title" id="menuItemModalTitle">
                            <i class="bi bi-plus-square-fill me-2 text-teal"></i> Yeni Menü Öğesi
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>

                    <div class="modal-body modal-body-theme">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="itemLabel" class="form-label text-clr-secondary">Etiket <span class="text-danger">*</span></label>
                                <div class="input-group input-group-theme">
                                    <span class="input-group-text"><i class="bi bi-tag-fill"></i></span>
                                    <input type="text" class="form-control form-control-theme" id="itemLabel" name="label" required maxlength="255" placeholder="Anasayfa">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="itemIcon" class="form-label text-clr-secondary">İkon (FontAwesome)</label>
                                <div class="input-group input-group-theme">
                                    <span class="input-group-text"><i class="bi bi-emoji-smile"></i></span>
                                    <input type="text" class="form-control form-control-theme" id="itemIcon" name="icon"
                                           placeholder="fa-solid fa-house" maxlength="100">
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-clr-secondary">Bağlantı Tipi <span class="text-danger">*</span></label>
                                <div class="menu-link-type-switch">
                                    <input type="radio" class="btn-check" name="link_type" id="linkTypeRoute" value="route" checked>
                                    <label class="menu-link-type-btn" for="linkTypeRoute">
                                        <i class="bi bi-signpost-split"></i>
                                        <span>Route <small class="d-block opacity-75">İç Sayfa</small></span>
                                    </label>
                                    <input type="radio" class="btn-check" name="link_type" id="linkTypeUrl" value="url">
                                    <label class="menu-link-type-btn" for="linkTypeUrl">
                                        <i class="bi bi-link-45deg"></i>
                                        <span>Custom URL <small class="d-block opacity-75">Harici / Özel</small></span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12" id="routeFields">
                                <label for="itemRouteName" class="form-label text-clr-secondary">Route Adı <span class="text-danger">*</span></label>
                                <select class="form-select form-select-theme" id="itemRouteName" name="route_name">
                                    <option value="">— Seçiniz —</option>
                                    @foreach($availableRoutes as $name => $label)
                                        <option value="{{ $name }}">{{ $label }} ({{ $name }})</option>
                                    @endforeach
                                </select>
                                <div id="routeParamsWrapper" class="mt-3 d-none">
                                    <label class="form-label text-clr-secondary">Parametreler</label>
                                    <div id="routeParamsList" class="d-flex flex-column gap-2"></div>
                                    <small class="form-text text-clr-muted">Örnek: slug=hakkimizda, categorySlug=duyurular</small>
                                </div>
                            </div>

                            <div class="col-12 d-none" id="urlFields">
                                <label for="itemUrl" class="form-label text-clr-secondary">URL <span class="text-danger">*</span></label>
                                <div class="input-group input-group-theme">
                                    <span class="input-group-text"><i class="bi bi-link"></i></span>
                                    <input type="text" class="form-control form-control-theme" id="itemUrl" name="url"
                                           placeholder="/ozel-sayfa veya https://harici-site.com" maxlength="500">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="itemDisplayType" class="form-label text-clr-secondary">Görüntüleme Tipi</label>
                                <select class="form-select form-select-theme" id="itemDisplayType" name="display_type">
                                    <option value="link">Standart Link</option>
                                    <option value="dropdown">Dropdown (Alt Menü)</option>
                                    <option value="mega_menu">Mega Menu (Grid Card)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="itemTarget" class="form-label text-clr-secondary">Hedef</label>
                                <select class="form-select form-select-theme" id="itemTarget" name="target">
                                    <option value="_self">Aynı Sekme</option>
                                    <option value="_blank">Yeni Sekme</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="itemIsActive" name="is_active" value="1" checked>
                                    <label class="form-check-label text-clr-secondary" for="itemIsActive">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-footer-theme">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn-teal" id="saveItemBtn">
                            <i class="bi bi-check-lg me-1"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Hidden data for JS --}}
    <script id="menuItemsData" type="application/json">@json($menu->rootItems->map(fn($i) => $i->toArray()))</script>
    <script>
        window.menuConfig = {
            menuId: {{ $menu->id }},
            storeUrl: '{{ route('admin.menus.items.store', $menu) }}',
            itemUrlTemplate: '{{ url('admin/menus/items') }}/__ID__',
            csrfToken: '{{ csrf_token() }}'
        };
    </script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/sortablejs/Sortable.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/menu-items.js') }}"></script>
    <script>
    (function () {
        @if(session('success'))
            if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({ title: 'Başarılı', message: @json(session('success')), type: 'success' });
            }
        @endif
        @if($errors->any())
            if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({ title: 'Hata', message: @json(implode('\n', $errors->all())), type: 'danger' });
            }
        @endif
    })();
    </script>
@endpush
