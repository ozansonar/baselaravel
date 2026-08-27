{{--
    Admin Pagination Partial
    Usage: @include('partials.admin.pagination', ['paginator' => $items, 'itemLabel' => 'kayıt'])
--}}
@if($paginator->hasPages())
    <div class="cl-pagination-wrapper" data-aos="fade-up">
        {{-- Ek yerine ayraç: "kayıttan" doğruydu ama aynı kalıp "mesajtan",
             "kategoritan", "kullanıcıtan" üretiyordu. Etiket ne olursa olsun
             bozulmayan tek biçim, eki hiç kullanmamak. --}}
        <div class="cl-pagination-info">
            <span>Toplam <strong>{{ number_format($paginator->total(), 0, ',', '.') }}</strong> {{ $itemLabel ?? 'kayıt' }} ·
                <strong>{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }}</strong> arası gösteriliyor</span>
        </div>
        <nav class="cl-pagination">
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $start = max(1, $current - 2);
                $end = min($last, $current + 2);
            @endphp

            {{-- İlk Sayfa --}}
            @if($current > 1)
                <a href="{{ $paginator->withQueryString()->url(1) }}" class="cl-page-btn" title="İlk Sayfa"><i class="bi bi-chevron-double-left"></i></a>
            @else
                <span class="cl-page-btn" disabled><i class="bi bi-chevron-double-left"></i></span>
            @endif

            {{-- Önceki --}}
            @if($paginator->onFirstPage())
                <span class="cl-page-btn" disabled><i class="bi bi-chevron-left"></i></span>
            @else
                <a href="{{ $paginator->withQueryString()->previousPageUrl() }}" class="cl-page-btn" title="Önceki"><i class="bi bi-chevron-left"></i></a>
            @endif

            {{-- Sayfa Numaraları --}}
            @if($start > 1)
                <a href="{{ $paginator->withQueryString()->url(1) }}" class="cl-page-btn">1</a>
                @if($start > 2)
                    <span class="cl-page-ellipsis">...</span>
                @endif
            @endif

            @for($i = $start; $i <= $end; $i++)
                @if($i === $current)
                    <span class="cl-page-btn active">{{ $i }}</span>
                @else
                    <a href="{{ $paginator->withQueryString()->url($i) }}" class="cl-page-btn">{{ $i }}</a>
                @endif
            @endfor

            @if($end < $last)
                @if($end < $last - 1)
                    <span class="cl-page-ellipsis">...</span>
                @endif
                <a href="{{ $paginator->withQueryString()->url($last) }}" class="cl-page-btn">{{ $last }}</a>
            @endif

            {{-- Sonraki --}}
            @if($paginator->hasMorePages())
                <a href="{{ $paginator->withQueryString()->nextPageUrl() }}" class="cl-page-btn" title="Sonraki"><i class="bi bi-chevron-right"></i></a>
            @else
                <span class="cl-page-btn" disabled><i class="bi bi-chevron-right"></i></span>
            @endif

            {{-- Son Sayfa --}}
            @if($current < $last)
                <a href="{{ $paginator->withQueryString()->url($last) }}" class="cl-page-btn" title="Son Sayfa"><i class="bi bi-chevron-double-right"></i></a>
            @else
                <span class="cl-page-btn" disabled><i class="bi bi-chevron-double-right"></i></span>
            @endif
        </nav>
    </div>
@endif
