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
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>E-posta</th>
                            <th class="d-none d-md-table-cell">Ad</th>
                            <th class="d-none d-md-table-cell">Soyad</th>
                            <th class="d-none d-lg-table-cell">Dil</th>
                            <th>Durum</th>
                            <th class="d-none d-xl-table-cell">Kayıt</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscribers as $subscriber)
                            <tr>
                                <td class="fw-semibold">{{ $subscriber->email }}</td>
                                <td class="d-none d-md-table-cell">{{ $subscriber->first_name ?: '—' }}</td>
                                <td class="d-none d-md-table-cell">{{ $subscriber->last_name ?: '—' }}</td>
                                <td class="d-none d-lg-table-cell">{{ $subscriber->locale ? strtoupper($subscriber->locale) : '—' }}</td>
                                <td>
                                    <span class="menu-manage-tag menu-manage-tag--{{ $subscriber->status->badgeClass() }}">
                                        {{ $subscriber->status->label() }}
                                    </span>
                                </td>
                                <td class="d-none d-xl-table-cell">
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
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-envelope-heart d-block mb-2" style="font-size: 2rem;"></i>
                                    Henüz abone yok.
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
                            <div class="stg-field">
                                <label class="stg-label" for="sub_locale">Dil</label>
                                <select class="stg-select" id="sub_locale" name="locale">
                                    <option value="">Belirtme</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->code }}">{{ $language->flag }} {{ $language->native_name }}</option>
                                    @endforeach
                                </select>
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
                            <div class="stg-field">
                                <label class="stg-label" for="import_locale">Dil</label>
                                <select class="stg-select" id="import_locale" name="locale">
                                    <option value="">Belirtme</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->code }}">{{ $language->flag }} {{ $language->native_name }}</option>
                                    @endforeach
                                </select>
                                <small class="stg-hint">Dosyadaki herkes bu dile atanır.</small>
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
    @endcan
@endsection
