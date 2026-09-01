@extends('layouts.admin')

@section('title', 'Sürüm Geçmişi')

@section('content')
    @php
        $editUrl = route($meta['edit'], $target);
        // Listenin başındaki sürüm her zaman içeriğin şu anki hâli: her
        // kaydetme bir sürüm yazıyor.
        $currentId = $revisions->first()?->id;
    @endphp

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route($meta['index']) }}" class="breadcrumb-link">{{ $meta['label'] }}</a>
            </li>
            <li class="breadcrumb-item active text-teal">Sürüm Geçmişi</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
        <div>
            <h1 class="page-title">Sürüm Geçmişi</h1>
            <p class="page-subtitle">
                {{ $target->title }}
                <span class="ms-2 menu-manage-tag menu-manage-tag--info">{{ strtoupper($target->locale) }}</span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <x-export-menu export="content-revisions" :total="$revisions->count()"
                           :params="['type' => $type, 'id' => $target->getKey()]" />
            <a href="{{ $editUrl }}" class="btn-glass">
                <i class="bi bi-pencil"></i> Düzenlemeye Dön
            </a>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2" data-aos="fade-up">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            Her kaydetmede o anki hâl buraya yazılıyor; en fazla <strong>{{ $keep }}</strong> sürüm
            saklanıyor ve tavan aşıldığında en eskisi siliniyor. Geçmiş <strong>dile özel</strong>:
            bu liste yalnız <strong>{{ strtoupper($target->locale) }}</strong> sürümünü gösteriyor,
            geri yükleme öteki dilleri etkilemiyor.
        </div>
    </div>

    <div class="card-dark mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Sürüm</th>
                            <th class="d-none d-lg-table-cell">Kaydeden</th>
                            <th class="d-none d-md-table-cell">Tarih</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($revisions as $revision)
                            <tr>
                                <td data-label="Sürüm">
                                    <div class="cmp-row">
                                        <span class="cmp-row__icon cmp-row__icon--{{ $revision->id === $currentId ? 'teal' : 'muted' }}">
                                            <i class="bi {{ $revision->id === $currentId ? 'bi-check-lg' : 'bi-clock-history' }}"></i>
                                        </span>
                                        <span class="cmp-row__text">
                                            <span class="cmp-row__name">
                                                {{ $revision->label() }}
                                                @if($revision->id === $currentId)
                                                    <span class="menu-manage-tag menu-manage-tag--success ms-2">Şu anki hâl</span>
                                                @endif
                                            </span>
                                            <span class="cmp-row__subject">
                                                {{ \Illuminate\Support\Str::limit(strip_tags((string) $revision->value('excerpt')), 80) ?: '—' }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="d-none d-lg-table-cell" data-label="Kaydeden">
                                    {{-- Hesap silinmiş olabilir; sürüm kalıyor çünkü içeriğin
                                         geçmişi onu yazanın hesabından bağımsız. --}}
                                    {{ $revision->author?->full_name ?? 'Sistem' }}
                                </td>
                                <td class="d-none d-md-table-cell" data-label="Tarih">
                                    <div class="sub-date">
                                        <span>{{ $revision->created_at?->format('d.m.Y H:i') }}</span>
                                        <small>{{ $revision->created_at?->diffForHumans() }}</small>
                                    </div>
                                </td>
                                <td class="text-end" data-label="İşlemler">
                                    <div class="usr-actions justify-content-end">
                                        <button type="button" class="usr-action-btn" title="Bu sürümü göster"
                                                data-action="surum-onizle" data-id="{{ $revision->id }}">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @if($revision->id !== $currentId)
                                            <button type="button" class="usr-action-btn" title="Bu sürüme dön"
                                                    data-action="surum-geri-yukle" data-id="{{ $revision->id }}"
                                                    data-label="{{ $revision->created_at?->format('d.m.Y H:i') }}">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <i class="bi bi-clock-history d-block mb-2 fs-2 text-muted"></i>
                                    <span class="text-muted">Bu dilde henüz kayıtlı sürüm yok.</span>
                                    <br>
                                    <small class="text-muted">İçeriği bir kez kaydettiğinizde ilk sürüm burada görünür.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sürüm içerikleri: önizleme kutusunun okuyacağı veri. Sayfaya bir kez
         basılıyor, açılışta değil tıklanınca gösteriliyor. --}}
    <script type="application/json" id="revisionPayloads" nonce="{{ csp_nonce() }}">
        @json($revisions->mapWithKeys(fn ($r) => [$r->id => collect($fields)->mapWithKeys(fn ($f) => [$f => $r->value($f)])]))
    </script>

    {{-- Önizleme --}}
    <div class="modal fade modal-custom" id="revisionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-body p-4">
                    <h5 class="mb-3">Sürüm içeriği</h5>
                    <div class="rdr-meta" id="revisionFields"></div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Geri yükleme onayı --}}
    <div class="modal fade modal-custom" id="revisionRestoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-theme">
            <div class="modal-content modal-content-theme">
                <div class="modal-body text-center p-4">
                    <div class="delete-modal-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
                    <h5 class="mt-3">Bu sürüme dön</h5>
                    <p class="text-clr-secondary mb-4">
                        <span id="revisionRestoreLabel"></span> tarihli sürüm geri yüklenecek.
                        Şu anki hâl kaybolmuyor — listenin başına yeni bir sürüm olarak yazılıyor.
                    </p>
                    <form method="POST" id="revisionRestoreForm">
                        @csrf
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn-teal">Geri Yükle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script nonce="{{ csp_nonce() }}">
        window.revisionRestoreUrl = @js(route('admin.revisions.restore', ['type' => $type, 'id' => $target->getKey(), 'revision' => 'REVISION']));
    </script>
    <script src="{{ versioned_asset('assets/admin/js/content-revisions.js') }}"></script>
@endpush
