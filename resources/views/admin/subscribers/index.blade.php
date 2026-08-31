@extends('layouts.admin')

@section('title', 'Mail Listesi')
@section('page_title', 'Mail Listesi')
@section('page_description', 'Bülten abonelerini yönetin, Excel veya CSV ile toplu ekleyin')

@section('content')

    @php
        use App\Enums\SubscriberSource;
        use App\Enums\SubscriberStatus;

        // Liste sekmesi kendi göstergesi; rozetlerde yer almıyor.
        $chipFilters = collect($filters)->except('list_id');
        $hasFilter = $chipFilters->filter(fn ($value) => (string) $value !== '')->isNotEmpty();

        $activeFilters = collect([
            'search' => ['label' => 'Arama', 'value' => $filters['search']],
            'status' => [
                'label' => 'Durum',
                'value' => $filters['status'] !== ''
                    ? (SubscriberStatus::tryFrom($filters['status'])?->label() ?? '')
                    : '',
            ],
            'source' => [
                'label' => 'Kaynak',
                'value' => $filters['source'] !== ''
                    ? (SubscriberSource::tryFrom($filters['source'])?->label() ?? '')
                    : '',
            ],
            'locale' => [
                'label' => 'Dil',
                'value' => $filters['locale'] !== '' ? strtoupper($filters['locale']) : '',
            ],
            'unlisted' => [
                'label' => 'Liste',
                'value' => $filters['unlisted'] !== '' ? 'Hiçbir listede değil' : '',
            ],
            'from' => [
                'label' => 'Başlangıç',
                'value' => $filters['from'] !== '' ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d.m.Y') : '',
            ],
            'to' => [
                'label' => 'Bitiş',
                'value' => $filters['to'] !== '' ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d.m.Y') : '',
            ],
            'sort' => [
                'label' => 'Sıralama',
                'value' => $sortOptions[$filters['sort']] ?? '',
            ],
        ])->filter(fn (array $chip): bool => $chip['value'] !== '');
    @endphp

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
        <div class="d-flex gap-2 flex-wrap">
            @if($hasFilter)
                <a href="{{ route('admin.subscribers.index', request()->only('list_id')) }}" class="btn-glass">
                    <i class="bi bi-arrow-counterclockwise"></i> Filtreleri Sıfırla
                </a>
            @endif
        @can('create', App\Models\Subscriber::class)
                <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#listsModal">
                    <i class="bi bi-collection"></i> Listeler
                </button>
                <button type="button" class="btn-glass" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel/CSV Yükle
                </button>
                <button type="button" class="btn-teal" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg"></i> Abone Ekle
                </button>
        @endcan
        </div>
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
            Bir kişi birden fazla listede olabilir; iki listeye birden gönderdiğinizde
            maili yine bir kez alır. Site formundan kaydolanlar
            <strong>{{ $defaultList?->name ?? 'varsayılan liste' }}</strong> listesine düşer.
            Abonelikten çıkan bir adrese hangi listede olursa olsun mail gitmez.
        </div>
    </div>

    {{-- Listesiz aboneler liste hedefli kampanyalarda gözden kaçıyor; sayfanın
         uyarması gereken tek durum bu. --}}
    @if($stats['unlisted'] > 0 && $filters['unlisted'] === '')
        <div class="sub-warning mb-4" data-aos="fade-up" data-aos-delay="130">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>{{ number_format($stats['unlisted'], 0, ',', '.') }} aktif abone hiçbir listede değil.</strong>
                Liste seçilerek gönderilen kampanyalarda bu kişilere mail gitmez.
            </div>
            <a href="{{ route('admin.subscribers.index', ['unlisted' => 1]) }}" class="btn-glass btn-sm">
                Göster
            </a>
        </div>
    @endif

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

    {{-- ==================== SÜZGEÇLER ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.subscribers.index') }}" id="filterForm" class="cl-toolbar">
                {{-- Liste sekmesi seçiliyken süzgeç değiştirmek sekmeden
                     düşürmemeli. --}}
                @if($filters['list_id'] !== '')
                    <input type="hidden" name="list_id" value="{{ $filters['list_id'] }}">
                @endif

                <div class="cl-search {{ $filters['search'] !== '' ? 'cl-search--clearable' : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Ad, soyad veya e-posta ile ara..." data-fv-ignore>
                    @if($filters['search'] !== '')
                        <a href="{{ route('admin.subscribers.index', request()->except(['search', 'page'])) }}"
                           class="cl-search-clear" title="Aramayı temizle" aria-label="Aramayı temizle">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>

                <div class="cl-filters mt-filters">
                    <div class="mt-field">
                        <span>Durum</span>
                        <select class="cl-filter-select" name="status" aria-label="Durum"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tüm durumlar</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" {{ $filters['status'] === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Kaynak</span>
                        <select class="cl-filter-select" name="source" aria-label="Kaynak"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tüm kaynaklar</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->value }}" {{ $filters['source'] === $source->value ? 'selected' : '' }}>
                                    {{ $source->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>Dil</span>
                        <select class="cl-filter-select" name="locale" aria-label="Dil"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            <option value="">Tüm diller</option>
                            @foreach($languages as $language)
                                <option value="{{ $language->code }}" {{ $filters['locale'] === $language->code ? 'selected' : '' }}>
                                    {{ $language->flag }} {{ $language->native_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field">
                        <span>
                            Başlangıç
                            @if($filters['from'] !== '')
                                <a href="{{ route('admin.subscribers.index', request()->except(['from', 'page'])) }}"
                                   class="ml-field-clear" title="Başlangıç tarihini temizle" aria-label="Başlangıç tarihini temizle">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </span>
                        <input type="date" class="cl-filter-select" name="from" value="{{ $filters['from'] }}" aria-label="Kayıt başlangıç tarihi" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>
                            Bitiş
                            @if($filters['to'] !== '')
                                <a href="{{ route('admin.subscribers.index', request()->except(['to', 'page'])) }}"
                                   class="ml-field-clear" title="Bitiş tarihini temizle" aria-label="Bitiş tarihini temizle">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </span>
                        <input type="date" class="cl-filter-select" name="to" value="{{ $filters['to'] }}" aria-label="Kayıt bitiş tarihi" data-fv-ignore>
                    </div>

                    <div class="mt-field">
                        <span>Sıralama</span>
                        <select class="cl-filter-select" name="sort" aria-label="Sıralama"
                                onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            @foreach($sortOptions as $sortValue => $sortLabel)
                                <option value="{{ $sortValue }}" {{ ($filters['sort'] ?: 'recent') === $sortValue ? 'selected' : '' }}>{{ $sortLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-field mt-field--actions ms-auto">
                        <div class="cl-toolbar-actions">
                            <label class="cmp-check sub-unlisted-toggle">
                                <input type="checkbox" name="unlisted" value="1"
                                       {{ $filters['unlisted'] !== '' ? 'checked' : '' }}
                                       onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                                <span class="cmp-check__text">Yalnızca listesizler</span>
                            </label>
                            <button type="submit" class="usr-action-btn" title="Süz"><i class="bi bi-funnel"></i></button>
                            <a href="{{ route('admin.subscribers.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <div class="cl-per-page">
                                <label for="perPage">Göster:</label>
                                <select name="per_page" id="perPage" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <x-export-menu export="subscribers" :total="$subscribers->total()" />
                        </div>
                    </div>
                </div>
            </form>

            @include('partials.admin.filter-chips', [
                'chips' => $activeFilters,
                'route' => 'admin.subscribers.index',
            ])
        </div>
    </div>

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
                    <select class="cl-filter-select" name="list_id" aria-label="Liste" data-fv-ignore>
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
                                <input type="checkbox" id="bulkSelectAll" aria-label="Tümünü seç" data-fv-ignore>
                            </th>
                            <th>Abone</th>
                            <th class="d-none d-lg-table-cell">Listeler</th>
                            <th class="d-none d-xl-table-cell">Kaynak</th>
                            <th>Durum</th>
                            <th class="d-none d-xxl-table-cell">Kayıt</th>
                            <th class="cl-th-actions">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscribers as $subscriber)
                            @php $source = SubscriberSource::tryFrom((string) $subscriber->source); @endphp
                            <tr>
                                <td class="sub-check-col">
                                    <input type="checkbox" form="bulkListForm" name="subscriber_ids[]"
                                           value="{{ $subscriber->id }}" class="js-bulk-row" data-fv-ignore
                                           aria-label="{{ $subscriber->email }} seç">
                                </td>
                                <td data-label="Abone">
                                    {{-- Ad ve adres tek hücrede: dokuz düz sütun
                                         dar ekranda okunmuyordu. --}}
                                    <div class="sub-person">
                                        <span class="sub-person__avatar {{ $subscriber->status->badgeClass() === 'success' ? '' : 'sub-person__avatar--muted' }}">
                                            {{ mb_strtoupper(mb_substr($subscriber->first_name ?: $subscriber->email, 0, 1)) }}
                                        </span>
                                        <span class="sub-person__text">
                                            <span class="sub-person__email">{{ $subscriber->email }}</span>
                                            <span class="sub-person__name">
                                                {{ $subscriber->full_name ?? 'İsim girilmemiş' }}
                                                @if($subscriber->locale)
                                                    <span class="sub-person__locale">{{ strtoupper($subscriber->locale) }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Listeler" class="d-none d-lg-table-cell">
                                    @forelse($subscriber->lists as $list)
                                        <span class="sub-list-tag">{{ $list->name }}</span>
                                    @empty
                                        <span class="sub-list-tag sub-list-tag--none">Listesiz</span>
                                    @endforelse
                                </td>
                                <td data-label="Kaynak" class="d-none d-xl-table-cell">
                                    @if($source !== null)
                                        <span class="sub-source sub-source--{{ $source->color() }}">
                                            <i class="bi {{ $source->icon() }}"></i>{{ $source->label() }}
                                        </span>
                                    @else
                                        <span class="text-clr-secondary">—</span>
                                    @endif
                                </td>
                                <td data-label="Durum">
                                    <span class="menu-manage-tag menu-manage-tag--{{ $subscriber->status->badgeClass() }}">
                                        {{ $subscriber->status->label() }}
                                    </span>
                                </td>
                                <td data-label="Kayıt" class="d-none d-xxl-table-cell">
                                    <div class="sub-date">
                                        <span>{{ $subscriber->created_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                        <small>{{ $subscriber->created_at?->diffForHumans() }}</small>
                                    </div>
                                </td>
                                <td data-label="İşlemler">
                                    <div class="usr-actions">
                                        @can('update', $subscriber)
                                            {{-- Düzenleme aynı modalda açılıyor; satırın verisi
                                                 nitelikte taşınıyor, ayrı bir istek gerekmiyor. --}}
                                            <button type="button" class="usr-action-btn js-edit-subscriber"
                                                    title="Düzenle"
                                                    data-id="{{ $subscriber->id }}"
                                                    data-url="{{ route('admin.subscribers.update', $subscriber) }}"
                                                    data-email="{{ $subscriber->email }}"
                                                    data-first-name="{{ $subscriber->first_name }}"
                                                    data-last-name="{{ $subscriber->last_name }}"
                                                    data-locale="{{ $subscriber->locale }}"
                                                    data-status="{{ $subscriber->status->value }}"
                                                    data-lists="{{ $subscriber->lists->pluck('id')->implode(',') }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            @if($subscriber->status === SubscriberStatus::Subscribed)
                                                <form method="POST" action="{{ route('admin.subscribers.unsubscribe', $subscriber) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="usr-action-btn" title="Abonelikten çıkar">
                                                        <i class="bi bi-person-dash"></i>
                                                    </button>
                                                </form>
                                            @else
                                                {{-- Listeye eklemek durumu değiştirmiyor; yanlışlıkla
                                                     çıkarılan birini geri almanın görünür yolu bu. --}}
                                                <form method="POST" action="{{ route('admin.subscribers.resubscribe', $subscriber) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="usr-action-btn success" title="Yeniden abone yap">
                                                        <i class="bi bi-person-check"></i>
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
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-envelope-heart d-block mb-2 fs-2 text-muted"></i>
                                    @if($hasFilter)
                                        <span class="text-muted">Bu filtreyle eşleşen abone yok.</span>
                                        <br>
                                        <a href="{{ route('admin.subscribers.index', request()->only('list_id')) }}" class="text-teal">Filtreleri temizle</a>
                                    @elseif($activeList !== null)
                                        <span class="text-muted">
                                            Bu listede henüz kimse yok. Aboneleri seçip
                                            <strong>Listeye ekle</strong> ile taşıyabilirsiniz.
                                        </span>
                                    @else
                                        <span class="text-muted">Henüz abone yok.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('partials.admin.pagination', ['paginator' => $subscribers, 'itemLabel' => 'abone'])

    @can('create', App\Models\Subscriber::class)
        {{-- Add modal --}}
        <div class="modal fade modal-custom" id="addModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-theme">
                <div class="modal-content modal-content-theme">
                    {{-- Aynı modal hem ekliyor hem düzenliyor: iki ayrı form aynı
                         alanları iki kez tanımlar, biri güncellenince diğeri unutulurdu. --}}
                    <form method="POST" action="{{ route('admin.subscribers.store') }}"
                          id="subscriberForm" data-store-url="{{ route('admin.subscribers.store') }}"
                          data-validate novalidate>
                        @csrf
                        <input type="hidden" name="_method" id="subscriberFormMethod" value="POST">
                        <div class="modal-header">
                            <h6 class="modal-title" id="subscriberModalTitle"><i class="bi bi-plus-lg me-2 text-teal"></i>Abone Ekle</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body">
                            <div class="stg-field mb-3">
                                <label class="stg-label" for="sub_email">E-posta <span class="text-neon-red">*</span></label>
                                <input type="text" class="stg-input" id="sub_email" name="email" data-validation-engine="validate[required,custom[email],maxSize[191]]">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <div class="stg-field">
                                        <label class="stg-label" for="sub_first_name">Ad</label>
                                        <input type="text" class="stg-input" id="sub_first_name" name="first_name"
                                               data-validation-engine="validate[custom[letters],maxSize[191]]"
                                               data-fv-mask="letters">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="stg-field">
                                        <label class="stg-label" for="sub_last_name">Soyad</label>
                                        <input type="text" class="stg-input" id="sub_last_name" name="last_name"
                                               data-validation-engine="validate[custom[letters],maxSize[191]]"
                                               data-fv-mask="letters">
                                    </div>
                                </div>
                            </div>
                            <div class="stg-field mb-3">
                                <label class="stg-label" for="sub_locale">Dil</label>
                                <select class="stg-select" id="sub_locale" name="locale" data-fv-ignore>
                                    <option value="">Belirtme</option>
                                    @foreach($languages as $language)
                                        <option value="{{ $language->code }}">{{ $language->flag }} {{ $language->native_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Yalnızca düzenlemede görünür: yeni kayıt her zaman abone
                                 olarak açılıyor, ekleme sırasında sorulacak bir şey değil. --}}
                            <div class="stg-field mb-3 d-none" id="subscriberStatusField">
                                <label class="stg-label" for="sub_status">Durum</label>
                                <select class="stg-select" id="sub_status" name="status" data-fv-ignore>
                                    @foreach(SubscriberStatus::cases() as $case)
                                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                                <small class="stg-hint">
                                    Yanlışlıkla çıkmış görünen bir adresi buradan geri alabilirsiniz.
                                </small>
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
                                <small class="stg-hint d-none" id="subscriberListsHint">
                                    İşareti kaldırılan listeden abone çıkarılır.
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal" id="subscriberSubmit">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Import modal --}}
        <div class="modal fade modal-custom" id="importModal" tabindex="-1" aria-hidden="true">
            {{-- Önizleme tablosu açıldığında genişliyor; dosya seçme adımında
                 dar kalıyor ki boş bir dev kutu görünmesin. --}}
            <div class="modal-dialog modal-dialog-centered modal-theme" id="importDialog">
                <div class="modal-content modal-content-theme">
                    <form method="POST" action="{{ route('admin.subscribers.import') }}" id="importForm"
                          data-preview-url="{{ route('admin.subscribers.import.preview') }}"
                          enctype="multipart/form-data" data-validate novalidate>
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="bi bi-file-earmark-spreadsheet me-2 text-teal"></i>Excel / CSV Yükle</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body">
                            <div class="stg-field mb-3" id="importFileField">
                                <label class="stg-label" for="import_file">Dosya <span class="text-neon-red">*</span></label>
                                {{-- Kural JS tarafından yönetiliyor: önizlemeye geçilince
                                     dosya değil satırlar gönderiliyor, alan zorunlu kalmamalı. --}}
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
                                <select class="stg-select" id="import_locale" name="locale" data-fv-ignore>
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

                            {{-- Önizleme: dosya okunduktan sonra dolar. Satırlar burada
                                 düzeltilebiliyor ve kaydedilen bu hâli oluyor. --}}
                            <div class="sub-preview d-none" id="importPreview">
                                <div class="sub-preview__head">
                                    <span class="sub-preview__summary" id="importSummary"></span>
                                    <button type="button" class="btn-glass btn-sm" id="importReset">
                                        <i class="bi bi-arrow-counterclockwise"></i> Başka dosya seç
                                    </button>
                                </div>
                                <p class="stg-hint" id="importTruncated" hidden>
                                    Dosya çok büyük olduğu için ilk 1000 satır gösteriliyor.
                                    Tamamını aktarmak isterseniz dosyayı bölerek yükleyin.
                                </p>
                                <div class="sub-preview__scroll">
                                    <table class="cl-table sub-preview__table">
                                        <thead>
                                            <tr>
                                                <th class="sub-preview__num">#</th>
                                                <th>E-posta</th>
                                                <th>Ad</th>
                                                <th>Soyad</th>
                                                <th class="cl-th-actions">Çıkar</th>
                                            </tr>
                                        </thead>
                                        <tbody id="importRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="button" class="btn-teal" id="importPreviewBtn">
                                <i class="bi bi-eye"></i> Önizle
                            </button>
                            <button type="submit" class="btn-teal d-none" id="importSaveBtn">
                                <i class="bi bi-check-lg"></i> Kaydet
                            </button>
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
                                <form method="POST" action="{{ route('admin.subscriber-lists.update', $list) }}" class="sub-list-row" data-validate novalidate>
                                    @csrf
                                    @method('PUT')
                                    <div class="sub-list-row__fields">
                                        <input type="text" class="stg-input" name="name" value="{{ $list->name }}"
                                               data-validation-engine="validate[required,maxSize[191]]"
                                               aria-label="Liste adı">
                                        <input type="text" class="stg-input" name="description" value="{{ $list->description }}"
                                               data-validation-engine="validate[maxSize[500]]"
                                               placeholder="Açıklama (isteğe bağlı)" aria-label="Açıklama">
                                    </div>
                                    <label class="cmp-check sub-list-row__default">
                                        <input type="checkbox" name="is_default" value="1" data-fv-ignore {{ $list->is_default ? 'checked' : '' }}>
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
                                            <select class="cl-filter-select" id="deleteListSelect" data-fv-ignore
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
                                           data-validation-engine="validate[maxSize[500]]"
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
