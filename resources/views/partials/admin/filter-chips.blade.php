{{--
    Açık süzgeç rozetleri.

    Süzgeç sayısı ikiyi geçen listelerde "ne süzülüyor" sorusunun cevabı
    kutulara tek tek bakmadan görünsün diye. Her rozetin çarpısı yalnızca
    kendi süzgecini düşürür, diğerleri adres satırında kalır.

    Usage:
    @include('partials.admin.filter-chips', [
        'chips' => $activeFilters,             // Collection<string, array{label: string, value: string}>
        'route' => 'admin.mail-logs.index',    // rozet bağlantılarının hedefi
    ])
--}}
@if($chips->isNotEmpty())
    <div class="flt-active">
        <span class="flt-active__title">Açık süzgeçler:</span>

        @foreach($chips as $chipKey => $chip)
            <span class="flt-chip">
                <span class="flt-chip__label">{{ $chip['label'] }}:</span>
                <span class="flt-chip__value">{{ $chip['value'] }}</span>
                <a href="{{ route($route, request()->except([$chipKey, 'page'])) }}"
                   class="flt-chip__remove"
                   title="{{ $chip['label'] }} süzgecini kaldır"
                   aria-label="{{ $chip['label'] }} süzgecini kaldır">
                    <i class="bi bi-x-lg"></i>
                </a>
            </span>
        @endforeach

        {{-- Tek süzgeç açıkken toplu temizlik gereksiz: rozetin kendi çarpısı
             zaten aynı işi yapıyor. --}}
        @if($chips->count() > 1)
            <a href="{{ route($route) }}" class="flt-chip flt-chip--reset">
                <i class="bi bi-arrow-counterclockwise"></i> Tümünü temizle
            </a>
        @endif
    </div>
@endif
