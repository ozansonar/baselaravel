@extends('layouts.admin')

@section('title', 'Dosya Yöneticisi')
@section('page_title', 'Dosya Yöneticisi')
@section('page_description', 'Blog ve sayfalarda kullanmak için dosya yükle, link al')

@section('content')

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">Dosya Yöneticisi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Dosya Yöneticisi</h1>
            <p class="page-subtitle">
                PDF, Word, Excel, görsel ve diğer dosyaları yükle, public URL'ini blog/sayfalarda kullan.
            </p>
        </div>
    </div>

    {{-- ==================== STAT CARDS ==================== --}}
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-folder-fill"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Dosya</span>
                    <h3 class="usr-stat-value" data-fmgr-stat="total" data-count="{{ $stats['total_files'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-purple"><i class="bi bi-hdd"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Boyut</span>
                    <h3 class="usr-stat-value" data-fmgr-stat="size" data-fmgr-bytes="{{ $stats['total_size'] }}">{{ \Illuminate\Support\Number::fileSize($stats['total_size'], precision: 1) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green"><i class="bi bi-calendar-month"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Bu Ay Eklenen</span>
                    <h3 class="usr-stat-value" data-fmgr-stat="month" data-count="{{ $stats['this_month'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red"><i class="bi bi-images"></i></div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Görsel Sayısı</span>
                    <h3 class="usr-stat-value" data-fmgr-stat="image" data-count="{{ $stats['by_category']['image'] ?? 0 }}">0</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== UPLOAD ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex align-items-center gap-3">
            <div class="form-section-icon bg-icon-teal"><i class="bi bi-cloud-upload-fill"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-0">Dosya Yükle</h6>
                <small class="text-muted">
                    Dosya bırakılır bırakılmaz otomatik yüklenir · 6 paralel istek ·
                    aynı dosya ikinci kez kaydedilmez (SHA256)
                </small>
            </div>
        </div>
        <div class="card-body-custom">

            {{-- Bırakma alanı: önizlemeler buraya değil, alttaki kuyruk paneline
                 düşer. Alan her zaman aynı yükseklikte kalır. --}}
            <div id="fileManagerDropzone" class="fmgr-dz"
                 data-upload-url="{{ route('admin.files.upload') }}">
                <div class="dz-message fmgr-dz__message">
                    <div class="fmgr-dz__icon"><i class="bi bi-cloud-arrow-up"></i></div>
                    <div>
                        <p class="fmgr-dz__title">Dosyaları buraya sürükle veya <u>bilgisayarından seç</u></p>
                        <p class="fmgr-dz__hint">Dosya başına en fazla 50 MB · tek seferde 50 dosya · SVG kabul edilmiyor</p>
                        <ul class="fmgr-dz__chips">
                            <li><i class="bi bi-image"></i>JPG · PNG · WebP · GIF</li>
                            <li><i class="bi bi-file-earmark-text"></i>PDF · DOC · XLSX · CSV</li>
                            <li><i class="bi bi-file-earmark-zip"></i>ZIP</li>
                            <li><i class="bi bi-play-btn"></i>MP4 · MP3</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Yükleme kuyruğu: dosya eklenene kadar gizli --}}
            <div class="fmgr-queue d-none" id="fmgrQueue">
                <div class="fmgr-queue__head">
                    <span class="fmgr-queue__title">
                        <i class="bi bi-list-check"></i>Kuyruk (<span id="fmgrCountTotal">0</span>)
                    </span>

                    <div class="fmgr-queue__counts">
                        <span class="fmgr-queue__count fmgr-queue__count--ok is-empty" id="fmgrCountOk" title="Yüklendi">
                            <i class="bi bi-check-circle-fill"></i><span data-fmgr-value>0</span>
                        </span>
                        <span class="fmgr-queue__count fmgr-queue__count--dup is-empty" id="fmgrCountDup" title="Zaten yüklüydü">
                            <i class="bi bi-arrow-repeat"></i><span data-fmgr-value>0</span>
                        </span>
                        <span class="fmgr-queue__count fmgr-queue__count--err is-empty" id="fmgrCountErr" title="Başarısız">
                            <i class="bi bi-exclamation-triangle-fill"></i><span data-fmgr-value>0</span>
                        </span>
                    </div>

                    <div class="fmgr-queue__track" role="progressbar" aria-label="Toplam yükleme ilerlemesi">
                        <span class="fmgr-queue__fill" id="fmgrQueueFill"></span>
                    </div>

                    {{-- Yükleme sonucu buraya yazılır. Modal kullanılmıyor:
                         engelleyici kutu kapatılana kadar kullanıcı listeyi
                         göremiyordu, oysa sonucun görüleceği yer tam da liste. --}}
                    <span class="fmgr-queue__summary" id="fmgrQueueSummary" role="status" aria-live="polite"></span>

                    <button type="button" class="btn-glass btn-sm d-none" id="fmgrReloadBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i>Listeyi Yenile
                    </button>
                    <button type="button" class="btn-glass btn-sm" id="fmgrClearAllBtn">
                        <i class="bi bi-x-circle me-1"></i>Temizle
                    </button>
                </div>

                <div class="fmgr-queue__list" id="fmgrQueueList"></div>
            </div>
        </div>
    </div>

    {{-- ==================== FILTERS ==================== --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom">
            <form method="GET" action="{{ route('admin.files.index') }}" class="cl-toolbar" id="filterForm">
                <div class="cl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" id="fmgrSearch"
                           placeholder="Dosya adı, başlık veya alt metinde ara..."
                           value="{{ $filters['q'] ?? '' }}" data-fv-ignore>
                </div>
                <div class="cl-filters">
                    <select class="cl-filter-select" name="category" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                        <option value="">Tüm Kategoriler</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['category'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="cl-filter-select" name="date_from"
                           value="{{ $filters['date_from'] ?? '' }}"
                           onchange="document.getElementById('filterForm').submit()"
                           title="Başlangıç tarihi" data-fv-ignore>
                    <input type="date" class="cl-filter-select" name="date_to"
                           value="{{ $filters['date_to'] ?? '' }}"
                           onchange="document.getElementById('filterForm').submit()"
                           title="Bitiş tarihi" data-fv-ignore>
                </div>
                <div class="cl-toolbar-actions">
                    <a href="{{ route('admin.files.index') }}" class="cl-filter-reset" title="Filtreleri Sıfırla">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <div class="cl-per-page">
                        <label>Göster:</label>
                        <select name="per_page" onchange="document.getElementById('filterForm').submit()" data-fv-ignore>
                            @foreach([12, 24, 48, 96] as $pp)
                                <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-export-menu export="files" :total="$files->total()" />
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== FILE COLLECTION ====================
         Gövdenin tamamı yükleme bitince sunucudan yeniden çekilip yerine
         konuyor (file-manager.js → refreshList). Kart işaretlemesi tek yerde
         kalsın diye liste JS tarafında elle kurulmuyor. --}}
    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-body-custom" id="fmgrListBody">
            @if($files->isEmpty())
                <div class="fmgr-empty">
                    <i class="bi bi-folder2-open"></i>
                    <h6>Dosya bulunamadı</h6>
                    <p class="text-muted small mb-0">
                        @if(! empty($filters['q']) || ! empty($filters['category']))
                            Filtreye uyan dosya yok. Filtreleri temizleyip tekrar dene.
                        @else
                            Henüz dosya yüklenmemiş. Yukarıdaki yükleme alanından dosya ekle.
                        @endif
                    </p>
                </div>
            @else
                {{-- Görünüm anahtarı: aynı kartlar iki düzende dizilir, tercih
                     localStorage'da tutulur. --}}
                <div class="fmgr-toolbar-row">
                    <p class="fmgr-toolbar-row__title">
                        <i class="bi bi-collection me-1"></i>{{ $files->firstItem() }}-{{ $files->lastItem() }}
                        / {{ number_format($files->total(), 0, ',', '.') }} dosya
                    </p>

                    <div class="fmgr-view-switch" role="group" aria-label="Görünüm">
                        <button type="button" class="fmgr-view-switch__btn is-active" data-fmgr-view="grid">
                            <i class="bi bi-grid-3x3-gap-fill"></i>Izgara
                        </button>
                        <button type="button" class="fmgr-view-switch__btn" data-fmgr-view="list">
                            <i class="bi bi-list-ul"></i>Liste
                        </button>
                    </div>
                </div>

                <div class="fmgr-collection fmgr-collection--grid" id="fmgrCollection">
                    @foreach($files as $file)
                        <article class="fmgr-card">
                            <a href="{{ route('admin.files.show', $file) }}" class="fmgr-card__thumb" title="Detay">
                                @if($file->isImage())
                                    <img src="{{ $file->thumbnailUrl() }}"
                                         alt="{{ $file->alt_text ?? $file->original_name }}"
                                         loading="lazy" class="img-fluid">
                                @else
                                    <i class="bi {{ $file->iconClass() }} {{ $file->iconColorClass() }} fmgr-card__icon"></i>
                                @endif
                            </a>

                            <span class="fmgr-card__badge" title="{{ $file->categoryLabel() }}">
                                <i class="bi {{ $file->iconClass() }}"></i><span>{{ $file->categoryLabel() }}</span>
                            </span>

                            <div class="fmgr-card__body">
                                <h3 class="fmgr-card__name" title="{{ $file->original_name }}">{{ $file->original_name }}</h3>
                                <div class="fmgr-card__meta">
                                    <span title="Orijinal boyut"><i class="bi bi-hdd"></i>{{ $file->humanSize() }}</span>
                                    @if($file->isImage() && $file->webp_size)
                                        <span class="fmgr-card__webp" title="WebP boyut">
                                            <i class="bi bi-arrow-down-circle"></i>{{ $file->webpSizeHuman() }}
                                        </span>
                                    @endif
                                    <span title="{{ $file->created_at->format('d.m.Y H:i') }}">
                                        <i class="bi bi-clock"></i>{{ $file->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>

                            <div class="fmgr-card__actions">
                                <button type="button" class="fmgr-card__btn" data-fmgr-copy
                                        data-fmgr-url="{{ $file->fullUrl() }}"
                                        data-fmgr-name="{{ $file->original_name }}"
                                        title="Full URL kopyala" aria-label="Full URL kopyala">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <a href="{{ route('admin.files.show', $file) }}" class="fmgr-card__btn"
                                   title="Detay" aria-label="Detay">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="fmgr-card__btn fmgr-card__btn--danger" data-fmgr-delete
                                        data-fmgr-action="{{ route('admin.files.destroy', $file) }}"
                                        data-fmgr-name="{{ $file->original_name }}"
                                        title="Sil" aria-label="Sil">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-4">
                    @include('partials.admin.pagination', ['paginator' => $files, 'itemLabel' => 'dosya'])
                </div>
            @endif
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ versioned_asset('assets/admin/js/file-manager-upload.js') }}" defer></script>
    <script src="{{ versioned_asset('assets/admin/js/file-manager.js') }}" defer></script>
@endpush
