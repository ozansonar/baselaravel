@if ($paginator->hasPages())
@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();

    // Mobilde 1 komşu, masaüstünde 2 komşu göster
    $onEachSide = 1;

    $window = [];

    // Her zaman ilk sayfa
    $window[] = 1;

    // Sol taraftaki "..."
    if ($currentPage - $onEachSide > 3) {
        $window[] = '...';
    }

    // İlk sayfa ile current arasındaki boşluk küçükse hepsini göster
    $start = max(2, $currentPage - $onEachSide);
    $end = min($lastPage - 1, $currentPage + $onEachSide);

    // Eğer başlangıç 3 ise ve "..." eklemedik ise 2'yi de göster
    if ($start === 3) {
        $window[] = 2;
    }

    for ($i = $start; $i <= $end; $i++) {
        if (!in_array($i, $window)) {
            $window[] = $i;
        }
    }

    // Sağ taraftaki "..."
    if ($currentPage + $onEachSide < $lastPage - 2) {
        $window[] = '...';
    }

    // Eğer bitiş lastPage-2 ise ve "..." eklemedik ise lastPage-1'i göster
    if ($end === $lastPage - 2) {
        if (!in_array($lastPage - 1, $window)) {
            $window[] = $lastPage - 1;
        }
    }

    // Her zaman son sayfa (lastPage > 1 ise)
    if ($lastPage > 1) {
        $window[] = $lastPage;
    }
@endphp
<nav class="pagination-container" aria-label="Sayfalama">
    {{-- Previous --}}
    @if ($paginator->onFirstPage())
    <span class="pagination-btn pagination-btn--nav disabled" aria-disabled="true">
        <i class="fas fa-chevron-left"></i>
        <span class="pagination-btn__label">Önceki</span>
    </span>
    @else
    <a href="{{ $paginator->previousPageUrl() }}" class="pagination-btn pagination-btn--nav" rel="prev">
        <i class="fas fa-chevron-left"></i>
        <span class="pagination-btn__label">Önceki</span>
    </a>
    @endif

    {{-- Page Numbers --}}
    <div class="pagination-pages">
        @foreach ($window as $item)
            @if ($item === '...')
            <span class="pagination-btn pagination-btn--dots" aria-hidden="true">&hellip;</span>
            @elseif ($item == $currentPage)
            <span class="pagination-btn pagination-btn--active" aria-current="page">{{ $item }}</span>
            @else
            <a href="{{ $paginator->url($item) }}" class="pagination-btn">{{ $item }}</a>
            @endif
        @endforeach
    </div>

    {{-- Next --}}
    @if ($paginator->hasMorePages())
    <a href="{{ $paginator->nextPageUrl() }}" class="pagination-btn pagination-btn--nav" rel="next">
        <span class="pagination-btn__label">Sonraki</span>
        <i class="fas fa-chevron-right"></i>
    </a>
    @else
    <span class="pagination-btn pagination-btn--nav disabled" aria-disabled="true">
        <span class="pagination-btn__label">Sonraki</span>
        <i class="fas fa-chevron-right"></i>
    </span>
    @endif
</nav>
@endif
