@extends('layouts.admin')

@section('title', 'SEO Denetimi')
@section('page_title', 'SEO Denetimi')
@section('page_description', 'Bütün içeriklerin SEO durumu, en düşük puanlı başta')

@section('content')
    @php
        $seciliSeviye = $filters['level'] ?? '';

        $aktifSuzgecler = collect([
            'search' => ['label' => 'Arama', 'value' => $filters['search'] ?? ''],
            'type'   => ['label' => 'Tür', 'value' => match ($filters['type'] ?? '') {
                'page'      => 'Sayfa',
                'blog_post' => 'Blog Yazısı',
                default     => '',
            }],
            'locale' => ['label' => 'Dil', 'value' => strtoupper($filters['locale'] ?? '')],
        ])->filter(fn (array $c): bool => $c['value'] !== '');
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">SEO Denetimi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">SEO Denetimi</h1>
            <p class="page-subtitle">Eksik meta, fazladan başlık, alt metinsiz görsel ve kırık bağlantılar tek listede</p>
        </div>
        <x-export-menu export="seo-audit" :total="$rows->total()" />
    </div>

    {{-- İstatistik kartları --}}
    <div class="row g-4 mb-4">
        @foreach([
            ['Toplam İçerik', $summary['total'], 'bi-collection', 'blue'],
            ['Hatalı', $summary['error'], 'bi-x-octagon', 'red'],
            ['Uyarılı', $summary['warning'], 'bi-exclamation-triangle', 'orange'],
            ['Sorunsuz', $summary['clean'], 'bi-check-circle', 'green'],
        ] as $i => [$etiket, $sayi, $ikon, $renk])
            <div class="col-xl-3 col-sm-6" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
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

    {{-- Seviye sekmeleri --}}
    <div class="cl-status-tabs mb-3" data-aos="fade-up">
        @foreach([
            ''        => ['Tümü', $summary['total']],
            'error'   => ['Hata', $summary['error']],
            'warning' => ['Uyarı', $summary['warning']],
            'info'    => ['Öneri', $summary['info']],
            'clean'   => ['Sorunsuz', $summary['clean']],
        ] as $deger => [$etiket, $sayi])
            <a href="{{ route('admin.seo.index', array_filter(array_merge($filters, ['level' => $deger]))) }}"
               class="cl-status-tab {{ $seciliSeviye === $deger ? 'active' : '' }}">
                {{ $etiket }}
                <span class="cl-tab-count">{{ $sayi }}</span>
            </a>
        @endforeach
    </div>

    {{-- Süzgeçler --}}
    <form method="GET" action="{{ route('admin.seo.index') }}" class="cl-toolbar mb-3" id="filterForm" data-aos="fade-up">
        <input type="hidden" name="level" value="{{ $seciliSeviye }}" data-fv-ignore>

        <div class="cl-search">
            <i class="bi bi-search"></i>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Başlıkta ara..." data-validation-engine="validate[maxSize[191]]">
        </div>

        <div class="cl-filters">
            <select name="type" class="cl-filter-select" data-fv-ignore
                    data-submit-form="filterForm">
                <option value="">Tüm türler</option>
                <option value="page" {{ ($filters['type'] ?? '') === 'page' ? 'selected' : '' }}>Sayfalar</option>
                <option value="blog_post" {{ ($filters['type'] ?? '') === 'blog_post' ? 'selected' : '' }}>Blog Yazıları</option>
            </select>

            <select name="locale" class="cl-filter-select" data-fv-ignore
                    data-submit-form="filterForm">
                <option value="">Tüm diller</option>
                @foreach($languages as $language)
                    <option value="{{ $language->code }}" {{ ($filters['locale'] ?? '') === $language->code ? 'selected' : '' }}>
                        {{ $language->name }}
                    </option>
                @endforeach
            </select>

            <div class="cl-per-page">
                <label for="perPage">Göster:</label>
                <select name="per_page" id="perPage" data-fv-ignore
                        data-submit-form="filterForm">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-teal"><i class="bi bi-funnel"></i> Süzgeçle</button>

            @if($aktifSuzgecler->isNotEmpty())
                <a href="{{ route('admin.seo.index') }}" class="btn-glass"><i class="bi bi-x-lg"></i> Temizle</a>
            @endif
        </div>
    </form>

    {{-- Tablo --}}
    <div class="card-dark" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom p-0">
            @if($rows->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-search display-5 text-clr-secondary d-block mb-3"></i>
                    <p class="text-clr-secondary mb-0">Bu süzgeçlere uyan içerik yok.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="cl-table mb-0">
                        <thead>
                            <tr>
                                <th>Puan</th>
                                <th>Başlık</th>
                                <th>Tür</th>
                                <th>Dil</th>
                                <th>Bulgular</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $satir)
                                <tr>
                                    <td>
                                        <span class="seo-score seo-score--{{ $satir['grade'] }}">{{ $satir['score'] }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold d-block">{{ $satir['title'] }}</span>
                                        <small class="text-clr-secondary">/{{ $satir['slug'] }}</small>
                                    </td>
                                    <td>{{ $satir['type'] === 'page' ? 'Sayfa' : 'Blog Yazısı' }}</td>
                                    <td>{{ strtoupper($satir['locale']) }}</td>
                                    <td>
                                        @if($satir['score'] === 100)
                                            <span class="text-clr-secondary">—</span>
                                        @else
                                            <div class="seo-count-group">
                                                @foreach(['error' => 'Hata', 'warning' => 'Uyarı', 'info' => 'Öneri'] as $seviye => $etiket)
                                                    @if($satir['counts'][$seviye] > 0)
                                                        <span class="seo-count seo-count--{{ $seviye }}"
                                                              title="{{ $etiket }}">
                                                            {{ $satir['counts'][$seviye] }} {{ $etiket }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                            <details class="seo-details">
                                                <summary>Ayrıntı</summary>
                                                <ul class="seo-details__list">
                                                    @foreach($satir['issues'] as $bulgu)
                                                        <li class="seo-details__item seo-details__item--{{ $bulgu['level'] }}">
                                                            {{ $bulgu['message'] }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ $satir['type'] === 'page'
                                            ? route('admin.pages.edit', $satir['id'])
                                            : route('admin.blog-posts.edit', $satir['id']) }}"
                                           class="usr-action-btn" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($rows->hasPages())
                    <div class="p-3">{{ $rows->links() }}</div>
                @endif
            @endif
        </div>
    </div>
@endsection
