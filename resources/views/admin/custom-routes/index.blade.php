@extends('layouts.admin')

@section('title', 'Özel Adresler')
@section('page_title', 'Özel Adresler')
@section('page_description', 'Kendi adreslerinizi açın ve var olan sayfalara bağlayın')

@section('content')
    @php
        $durumEtiketleri = ['active' => 'Aktif', 'passive' => 'Pasif', 'trashed' => 'Silinmiş'];
        $seciliDurum = $filters['status'] ?? '';

        $aktifSuzgecler = collect([
            'search' => ['label' => 'Arama', 'value' => $filters['search'] ?? ''],
            'locale' => ['label' => 'Dil', 'value' => ($filters['locale'] ?? '') === 'all' ? 'Tüm diller' : strtoupper($filters['locale'] ?? '')],
            'target' => ['label' => 'Hedef', 'value' => $targets[$filters['target'] ?? ''] ?? ''],
        ])->filter(fn (array $c): bool => $c['value'] !== '');
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Özel Adresler</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Özel Adresler</h1>
            <p class="page-subtitle">Bir adres açıp var olan bir sayfaya bağlayın; dil ön ekini sistem koyar</p>
        </div>
        @can('create', App\Models\CustomRoute::class)
            <a href="{{ route('admin.custom-routes.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni Adres
            </a>
        @endcan
    </div>

    {{-- İstatistik kartları --}}
    <div class="row g-4 mb-4">
        @foreach([
            ['Aktif', $statusCounts['active'], 'bi-check-circle', 'green'],
            ['Pasif', $statusCounts['passive'], 'bi-pause-circle', 'orange'],
            ['Silinmiş', $statusCounts['trashed'], 'bi-trash3', 'red'],
        ] as $i => [$etiket, $sayi, $ikon, $renk])
            <div class="col-xl-4 col-sm-6" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                <div class="usr-stat-card">
                    <div class="usr-stat-icon usr-stat-icon-{{ $renk }}"><i class="bi {{ $ikon }}"></i></div>
                    <div class="usr-stat-info">
                        <span class="usr-stat-label">{{ $etiket }}</span>
                        <h3 class="usr-stat-value" data-count="{{ $sayi }}">0</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Durum sekmeleri --}}
    <div class="cl-status-tabs mb-3" data-aos="fade-up">
        @foreach(['' => 'Tümü', 'active' => 'Aktif', 'passive' => 'Pasif', 'trashed' => 'Silinmiş'] as $deger => $etiket)
            <a href="{{ route('admin.custom-routes.index', array_filter(array_merge($filters, ['status' => $deger]))) }}"
               class="cl-status-tab {{ $seciliDurum === $deger ? 'active' : '' }}">
                {{ $etiket }}
                @if($deger !== '')
                    <span class="cl-tab-count">{{ $statusCounts[$deger] ?? 0 }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Süzgeçler --}}
    <form method="GET" action="{{ route('admin.custom-routes.index') }}" class="cl-toolbar mb-3" data-aos="fade-up">
        <input type="hidden" name="status" value="{{ $seciliDurum }}" data-fv-ignore>
        <div class="cl-search">
            <i class="bi bi-search"></i>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Adres veya not ara..." data-validation-engine="validate[maxSize[191]]">
        </div>
        <div class="cl-filters">
            <select name="locale" class="cl-filter-select" data-fv-ignore>
                <option value="">Tüm diller (süzgeç yok)</option>
                <option value="all" {{ ($filters['locale'] ?? '') === 'all' ? 'selected' : '' }}>Dilden bağımsız kayıtlar</option>
                @foreach($languages as $language)
                    <option value="{{ $language->code }}" {{ ($filters['locale'] ?? '') === $language->code ? 'selected' : '' }}>
                        {{ $language->name }}
                    </option>
                @endforeach
            </select>
            <select name="target" class="cl-filter-select" data-fv-ignore>
                <option value="">Tüm hedefler</option>
                @foreach($targets as $ad => $etiket)
                    <option value="{{ $ad }}" {{ ($filters['target'] ?? '') === $ad ? 'selected' : '' }}>{{ $etiket }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-teal"><i class="bi bi-funnel"></i> Süzgeçle</button>
            @if($aktifSuzgecler->isNotEmpty())
                <a href="{{ route('admin.custom-routes.index') }}" class="btn-glass"><i class="bi bi-x-lg"></i> Temizle</a>
            @endif
            <x-export-menu export="custom-routes" :total="$routes->total()" />
        </div>
    </form>

    {{-- Toplu işlem çubuğu --}}
    <div class="d-none align-items-center gap-2 mb-3" data-bulk-bar>
        <span class="text-clr-secondary"><strong data-bulk-count>0</strong> kayıt seçildi</span>
        @if($seciliDurum === 'trashed')
            <form method="POST" action="{{ route('admin.custom-routes.bulk-restore') }}" data-bulk-action
                  data-bulk-message=":count adres geri yüklenecek. Onaylıyor musunuz?"
                  data-bulk-confirm="Evet, Geri Yükle">
                @csrf @method('PATCH')
                <button type="submit" class="btn-glass"><i class="bi bi-arrow-counterclockwise"></i> Geri yükle</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.custom-routes.bulk-destroy') }}" data-bulk-action
                  data-bulk-message=":count adres silinecek. Silinenler Silinmiş sekmesinden geri alınabilir."
                  data-bulk-confirm="Evet, Sil">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger-custom"><i class="bi bi-trash3"></i> Sil</button>
            </form>
        @endif
        <button type="button" class="btn-glass" data-bulk-clear>Seçimi bırak</button>
    </div>

    {{-- Liste --}}
    <div class="card-dark" data-aos="fade-up">
        <div class="card-body-custom p-0">
            @if($routes->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-signpost-split display-5 text-clr-secondary d-block mb-3"></i>
                    <p class="text-clr-secondary mb-0">Henüz özel adres tanımlanmamış.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="cl-table mb-0">
                        <thead>
                            <tr>
                                <th class="cl-th-check"><input type="checkbox" data-bulk-all aria-label="Tümünü seç"></th>
                                <th>Adres</th>
                                <th>Dil</th>
                                <th>Hedef</th>
                                <th>Tür</th>
                                <th>Durum</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($routes as $kayit)
                                <tr>
                                    <td><input type="checkbox" data-bulk-item value="{{ $kayit->id }}" aria-label="{{ $kayit->slug }} seç"></td>
                                    <td>
                                        <code class="text-teal">/{{ $kayit->locale ?? '{dil}' }}/{{ $kayit->slug }}</code>
                                        @if($kayit->note)
                                            <small class="d-block text-clr-secondary">{{ $kayit->note }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $kayit->localeLabel() }}</td>
                                    <td>
                                        {{ $targets[$kayit->target_route] ?? $kayit->target_route }}
                                        @if($kayit->target_params)
                                            <small class="d-block text-clr-secondary">{{ collect($kayit->target_params)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') }}</small>
                                        @endif
                                    </td>
                                    <td><span class="badge-soft">{{ $kayit->type->label() }}</span></td>
                                    <td>
                                        @if($kayit->trashed())
                                            <span class="badge-soft badge-soft-red">Silinmiş</span>
                                        @elseif($kayit->is_active)
                                            <span class="badge-soft badge-soft-green">Aktif</span>
                                        @else
                                            <span class="badge-soft badge-soft-orange">Pasif</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($kayit->trashed())
                                            @can('restore', $kayit)
                                                <form method="POST" action="{{ route('admin.custom-routes.restore', $kayit->id) }}" class="d-inline">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="usr-action-btn" title="Geri yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                            @endcan
                                        @else
                                            @can('update', $kayit)
                                                <a href="{{ route('admin.custom-routes.edit', $kayit) }}" class="usr-action-btn" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                            @endcan
                                            @can('delete', $kayit)
                                                <button type="button" class="usr-action-btn danger" title="Sil"
                                                        onclick="openDeleteModal({{ $kayit->id }}, @js($kayit->slug))"><i class="bi bi-trash3"></i></button>
                                            @endcan
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

    @if($routes->hasPages())
        <div class="mt-4">{{ $routes->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    @endif
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/bulk-actions.js') }}"></script>
<script>
    function openDeleteModal(id, slug) {
        AdminModal.confirm({
            title: 'Adres Silinsin Mi?',
            // Adres mesaja gömülmüyor: pencerenin ayrıntı kutusu onu
            // biçimlendirmesiz gösteriyor.
            message: 'Bu özel adres kaldırılacak. Ziyaretçiler bu adrese gittiğinde artık sayfa bulunamayacak.',
            detailTitle: slug,
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3'
        }).then(function (onaylandi) {
            if (!onaylandi) return;

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('admin.custom-routes.index') }}/' + id;
            form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name=csrf-token]').content + '">'
                           + '<input type="hidden" name="_method" value="DELETE">';
            document.body.appendChild(form);
            form.submit();
        });
    }
</script>
@endpush
