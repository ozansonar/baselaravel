@extends('layouts.admin')

@section('title', 'Mail Listesi')
@section('page_title', 'Mail Listesi')
@section('page_description', 'Bülten abonelerini yönetin, Excel veya CSV ile toplu ekleyin')

@section('content')
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}" class="breadcrumb-link">Mail Kampanyaları</a></li>
            <li class="breadcrumb-item active text-teal">Mail Listesi</li>
        </ol>
    </nav>

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Mail Listesi</h1>
            <p class="page-subtitle">Bülten abonelerini yönetin, Excel veya CSV ile toplu ekleyin</p>
        </div>
        @can('create', App\Models\Subscriber::class)
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#listsModal">
                    <i class="bi bi-collection"></i> Listeler
                </button>
                <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel/CSV Yükle
                </button>
                <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg"></i> Abone Ekle
                </button>
            </div>
        @endcan
    </div>

    {{-- STATS --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-people-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Kayıt</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-envelope-heart-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Aktif Abone</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['subscribed'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange"><i class="bi bi-person-dash-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ayrılan</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['unsubscribed'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-envelope-slash-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Ulaşılamayan</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['bounced'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Listelerin ne işe yaradığı --}}
    <div class="nt-info-note mb-4" data-aos="fade-up" data-aos-delay="120">
        <i class="bi bi-collection-fill"></i>
        <div>
            <strong>Aboneler listelere ayrılır, kampanya bir listeye gönderilir.</strong>
            Tedarikçiler, pazarlamacılar, bülten… Bir kişi birden fazla listede olabilir;
            iki listeye birden gönderdiğinizde maili yine bir kez alır. Site formundan
            kaydolanlar <strong>{{ $defaultList?->name ?? 'varsayılan liste' }}</strong> listesine düşer.
            Abonelikten çıkan bir adrese hangi listede olursa olsun mail gitmez.
        </div>
    </div>

    {{-- LİSTE SEKMELERİ --}}
    <div class="cl-status-tabs mb-4" data-aos="fade-up" data-aos-delay="140">
        <a href="{{ route('admin.subscribers.index', request()->except(['list_id', 'page'])) }}"
           class="cl-status-tab {{ $activeList === null ? 'active' : '' }}">
            <span>Tüm Aboneler</span>
            <span class="cl-tab-count">{{ $stats['total'] }}</span>
        </a>
        @foreach($lists as $list)
            <a href="{{ route('admin.subscribers.index', array_merge(request()->except(['list_id', 'page']), ['list_id' => $list->id])) }}"
               class="cl-status-tab {{ $activeList === $list->id ? 'active' : '' }}">
                @if($list->is_default)
                    <i class="bi bi-star-fill text-neon-orange" title="Site formundan gelenler buraya düşer"></i>
                @endif
                <span>{{ $list->name }}</span>
                <span class="cl-tab-count">{{ $list->active_members_count }}</span>
            </a>
        @endforeach
    </div>

    {{-- FILTERS --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.subscribers.index') }}" id="filterForm" class="cl-toolbar">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ad, soyad veya e-posta ile ara...">
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="status" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Durumlar</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                    <select class="cl-filter-select" name="locale" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tüm Diller</option>
                        @foreach($languages as $language)
                            <option value="{{ $language->code }}" {{ request('locale') === $language->code ? 'selected' : '' }}>
                                {{ $language->flag }} {{ $language->native_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.subscribers.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- TABLE --}}
    {{-- Toplu liste işlemi: mevcut bir aboneyi yeni açılan listeye taşımanın
         tek tek düzenlemekten başka yolu yoktu.

         Form tabloyu sarmıyor — satırlardaki tekil işlemler de form, iç içe
         form geçersiz. Satır kutuları HTML5 form niteliğiyle buraya bağlanıyor. --}}
    @can('create', App\Models\Subscriber::class)
        @if($lists->isNotEmpty())
            <form method="POST" action="{{ route('admin.subscribers.bulk-list') }}" id="bulkListForm">
                @csrf
                <div class="sub-bulk d-none" id="bulkBar">
                    <span class="sub-bulk__count"><strong id="bulkCount">0</strong> abone seçildi</span>
                    <select class="cl-filter-select" name="list_id" aria-label="Liste">
                        @foreach($lists as $list)
                            <option value="{{ $list->id }}">{{ $list->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" name="action" value="add" class="btn-teal btn-sm">
                        <i class="bi bi-plus-lg"></i> Listeye ekle
                    </button>
                    <button type="submit" name="action" value="remove" class="btn-glass btn-sm">
                        <i class="bi bi-dash-lg"></i> Listeden çıkar
                    </button>
                    <button type="button" class="sub-bulk__clear" id="bulkClear">Seçimi bırak</button>
                </div>
            </form>
        @endif
    @endcan

    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th class="sub-check-col">
                                <input type="checkbox" id="bulkSelectAll" aria-label="Tümünü seç">
                            </th>
                            <th>E-posta</th>
                            <th class="d-none d-md-table-cell">Ad</th>
                            <th class="d-none d-md-table-cell">Soyad</th>
                            <th class="d-none d-xl-table-cell">Listeler</th>
                            <th class="d-none d-lg-table-cell">Dil</th>
                            <th>Durum</th>
                            <th class="d-none d-xxl-table-cell">Kayıt</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscribers as $subscriber)
                            <tr>
                                <td class="sub-check-col">
                                    <input type="checkbox" form="bulkListForm" name="subscriber_ids[]"
                                           value="{{ $subscriber->id }}" class="js-bulk-row"
                                           aria-label="{{ $subscriber->email }} seç">
                                </td>
                                <td class="fw-semibold">{{ $subscriber->email }}</td>
                                <td class="d-none d-md-table-cell">{{ $subscriber->first_name ?: '—' }}</td>
                                <td class="d-none d-md-table-cell">{{ $subscriber->last_name ?: '—' }}</td>
                                <td class="d-none d-xl-table-cell">
                                    @forelse($subscriber->lists as $list)
                                        <span class="sub-list-tag">{{ $list->name }}</span>
                                    @empty
                                        <span class="text-clr-secondary">—</span>
                                    @endforelse
                                </td>
                                <td class="d-none d-lg-table-cell">{{ $subscriber->locale ? strtoupper($subscriber->locale) : '—' }}</td>
                                <td>
                                    <span class="menu-manage-tag menu-manage-tag--{{ $subscriber->status->badgeClass() }}">
                                        {{ $subscriber->status->label() }}
                                    </span>
                                </td>
                                <td class="d-none d-xxl-table-cell">
                                    <small class="text-clr-secondary">{{ $subscriber->subscribed_at?->format('d.m.Y') ?? '—' }}</small>
                                </td>
                                <td class="text-end">
                                    @can('update', $subscriber)
                                        @if($subscriber->status === App\Enums\SubscriberStatus::Subscribed)
                                            <form method="POST" action="{{ route('admin.subscribers.unsubscribe', $subscriber) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="usr-action-btn" title="Abonelikten çıkar">
                                                    <i class="bi bi-person-dash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('delete', $subscriber)
                                        <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="usr-action-btn danger" title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="bi bi-envelope-heart d-block mb-2 fs-2"></i>
                                    @if($activeList !== null)
                                        Bu listede henüz kimse yok. Aboneleri seçip
                                        <strong>Listeye ekle</strong> ile taşıyabilirsiniz.
                                    @else
                                        Henüz abone yok.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($subscribers->hasPages())
        <div class="cl-pagination-wrapper" data-aos="fade-up">
            <span class="text-clr-secondary">
                {{ $subscribers->firstItem() }}–{{ $subscribers->lastItem() }} / {{ $subscribers->total() }} kayıt
            </span>
            {{ $subscribers->links('pagination::bootstrap-5') }}
        </div>
    @endif

    @can('create', App\Models\Subscriber::class)
        {{-- Add modal --}}
        <div class="modal fade modal-custom" id="addModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <form method="POST" action="{{ route('admin.subscribers.store') }}" data-validate novalidate>
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="bi bi-plus-lg me-2 text-teal"></i>Abone Ekle</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body">
                            <div class="stg-field mb-3">
                                <label class="stg-label" for="sub_email">E-posta <span class="text-neon-red">*</span></label>
                                <input type="email" class="stg-input" id="sub_email" name="email" data-validation-engine="validate[required,custom[email],maxSize[255]]">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <div class="stg-field">
                                        <label class="stg-label" for="sub_first_name">Ad</label>
                                        <input type="text" class="stg-input" id="sub_first_name" name="first_name">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="stg-field">
                                        <label class="stg-label" for="sub_last_name">Soyad</label>
                                        <input type="text" class="stg-input" id="sub_last_name" name="last_name">
                                    </div>
                                </div>
                            </div>
                            <div class="stg-field mb-3">
                                <label class="stg-label" for="sub_locale">Dil</label>
                                <select class="stg-select" id="sub_locale" name="locale">
                                    <option value="">Belirtme</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->code }}">{{ $language->flag }} {{ $language->native_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stg-field">
                                <label class="stg-label">Listeler</label>
                                @forelse($lists as $list)
                                    <label class="cmp-check">
                                        <input type="checkbox" name="list_ids[]" data-fv-ignore value="{{ $list->id }}"
                                               {{ $activeList === $list->id || ($activeList === null && $list->is_default) ? 'checked' : '' }}>
                                        <span class="cmp-check__text">{{ $list->name }}</span>
                                    </label>
                                @empty
                                    <small class="stg-hint">Henüz liste yok, önce "Listeler"den bir tane oluşturun.</small>
                                @endforelse
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Import modal --}}
        <div class="modal fade modal-custom" id="importModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <form method="POST" action="{{ route('admin.subscribers.import') }}" enctype="multipart/form-data" data-validate novalidate>
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="bi bi-file-earmark-spreadsheet me-2 text-teal"></i>Excel / CSV Yükle</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body">
                            <div class="stg-field mb-3">
                                <label class="stg-label" for="import_file">Dosya <span class="text-neon-red">*</span></label>
                                <input type="file" class="stg-input" id="import_file" name="file"
                                       accept=".xlsx,.xls,.ods,.csv,.txt"
                                       data-validation-engine="validate[required]">
                                <small class="stg-hint">
                                    Başlık satırında <code>Ad</code>, <code>Soyad</code> ve <code>E-posta</code>
                                    sütunları olsun. Ad ile soyadı tek sütunda veren eski dosyalar da okunur.
                                    En fazla 10 MB.
                                </small>
                                @can('create', App\Models\Campaign::class)
                                    <a href="{{ route('admin.campaigns.template') }}" class="btn-glass btn-sm mt-2">
                                        <i class="bi bi-download"></i> Örnek şablonu indir (.xlsx)
                                    </a>
                                @endcan
                            </div>
                            <div class="stg-field mb-3">
                                <label class="stg-label" for="import_locale">Dil</label>
                                <select class="stg-select" id="import_locale" name="locale">
                                    <option value="">Belirtme</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->code }}">{{ $language->flag }} {{ $language->native_name }}</option>
                                    @endforeach
                                </select>
                                <small class="stg-hint">Dosyadaki herkes bu dile atanır.</small>
                            </div>
                            <div class="stg-field">
                                <label class="stg-label">Listeler</label>
                                @forelse($lists as $list)
                                    <label class="cmp-check">
                                        <input type="checkbox" name="list_ids[]" data-fv-ignore value="{{ $list->id }}"
                                               {{ $activeList === $list->id || ($activeList === null && $list->is_default) ? 'checked' : '' }}>
                                        <span class="cmp-check__text">{{ $list->name }}</span>
                                    </label>
                                @empty
                                    <small class="stg-hint">Henüz liste yok, önce "Listeler"den bir tane oluşturun.</small>
                                @endforelse
                                <small class="stg-hint">Dosyadaki herkes seçilen listelere eklenir.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal">Yükle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Liste yönetimi --}}
        <div class="modal fade modal-custom" id="listsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    <div class="modal-header">
                        <h6 class="modal-title"><i class="bi bi-collection me-2 text-teal"></i>Abone Listeleri</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>
                    <div class="modal-body">
                        <p class="stg-hint mb-3">
                            Kampanyalar bu listelerden birine ya da birkaçına gönderilir.
                            Yıldızlı liste site formundan kaydolanların düştüğü listedir.
                        </p>

                        <div class="sub-lists">
                            @foreach($lists as $list)
                                <form method="POST" action="{{ route('admin.subscriber-lists.update', $list) }}" class="sub-list-row">
                                    @csrf
                                    @method('PUT')
                                    <div class="sub-list-row__fields">
                                        <input type="text" class="stg-input" name="name" value="{{ $list->name }}"
                                               aria-label="Liste adı" required>
                                        <input type="text" class="stg-input" name="description" value="{{ $list->description }}"
                                               placeholder="Açıklama (isteğe bağlı)" aria-label="Açıklama">
                                    </div>
                                    <label class="cmp-check sub-list-row__default">
                                        <input type="checkbox" name="is_default" value="1" {{ $list->is_default ? 'checked' : '' }}>
                                        <span class="cmp-check__text">Varsayılan</span>
                                    </label>
                                    <span class="sub-list-row__count">{{ $list->active_members_count }} kişi</span>
                                    <button type="submit" class="usr-action-btn" title="Kaydet"><i class="bi bi-check-lg"></i></button>
                                </form>
                            @endforeach
                        </div>

                        @can('manageLists', App\Models\Subscriber::class)
                            @if($lists->count() > 1)
                                <div class="sub-lists-delete">
                                    <form method="POST" action="{{ route('admin.subscriber-lists.destroy', $lists->first()) }}"
                                          id="deleteListForm">
                                        @csrf
                                        @method('DELETE')
                                        <label class="stg-label" for="deleteListSelect">Liste sil</label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <select class="cl-filter-select" id="deleteListSelect"
                                                    data-url-template="{{ route('admin.subscriber-lists.destroy', ['subscriberList' => 'LIST_ID']) }}">
                                                @foreach($lists as $list)
                                                    <option value="{{ $list->id }}">{{ $list->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn-glass btn-sm text-neon-red">
                                                <i class="bi bi-trash3"></i> Sil
                                            </button>
                                        </div>
                                        <small class="stg-hint">
                                            Liste silinir, aboneler silinmez — yalnızca bu listeden çıkarılır.
                                        </small>
                                    </form>
                                </div>
                            @endif
                        @endcan

                        <hr class="my-4">

                        <form method="POST" action="{{ route('admin.subscriber-lists.store') }}" data-validate novalidate>
                            @csrf
                            <label class="stg-label">Yeni liste</label>
                            <div class="sub-list-row">
                                <div class="sub-list-row__fields">
                                    <input type="text" class="stg-input" name="name" placeholder="Tedarikçiler"
                                           data-validation-engine="validate[required,maxSize[191]]" aria-label="Liste adı">
                                    <input type="text" class="stg-input" name="description"
                                           placeholder="Açıklama (isteğe bağlı)" aria-label="Açıklama">
                                </div>
                                <button type="submit" class="btn-teal btn-sm"><i class="bi bi-plus-lg"></i> Ekle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/subscribers.js') }}"></script>
@endpush
