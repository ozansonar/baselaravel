<li class="menu-tree-item" data-item-id="{{ $item->id }}">
    <div class="menu-tree-row {{ $item->is_active ? '' : 'is-inactive' }}">
        <span class="menu-drag-handle" title="Sürükle">
            <i class="bi bi-grip-vertical"></i>
        </span>
        <span class="menu-tree-icon">
            @if($item->icon)
                <i class="{{ $item->icon }}"></i>
            @else
                <i class="bi bi-link-45deg"></i>
            @endif
        </span>
        <div class="menu-tree-info">
            <div class="menu-tree-label">{{ $item->label }}</div>
            <div class="menu-tree-meta">
                @if($item->link_type === 'route')
                    <span class="menu-tree-chip menu-tree-chip--route"><i class="bi bi-signpost-split"></i> {{ $item->route_name }}</span>
                @else
                    <span class="menu-tree-chip menu-tree-chip--url"><i class="bi bi-link-45deg"></i> {{ Str::limit($item->url, 40) }}</span>
                @endif
                @if($item->display_type === 'mega_menu')
                    <span class="menu-tree-chip menu-tree-chip--mega"><i class="bi bi-grid-3x3-gap-fill"></i> Mega Menu</span>
                @elseif($item->display_type === 'dropdown')
                    <span class="menu-tree-chip menu-tree-chip--dropdown"><i class="bi bi-chevron-down"></i> Dropdown</span>
                @endif
                @if($item->target === '_blank')
                    <span class="menu-tree-chip menu-tree-chip--warn"><i class="bi bi-box-arrow-up-right"></i> Yeni Sekme</span>
                @endif
                @if(! $item->is_active)
                    <span class="menu-tree-chip menu-tree-chip--danger"><i class="bi bi-pause-circle-fill"></i> Pasif</span>
                @endif
            </div>
        </div>
        <div class="menu-tree-actions">
            <button type="button" class="menu-tree-btn menu-tree-btn--add js-add-child"
                    data-parent-id="{{ $item->id }}" title="Alt öğe ekle">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button type="button" class="menu-tree-btn menu-tree-btn--edit js-edit-item"
                    data-item='@json($item)' title="Düzenle">
                <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" action="{{ route('admin.menus.items.destroy', $item) }}"
                  class="d-inline js-delete-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="menu-tree-btn menu-tree-btn--delete" title="Sil"
                        data-confirm="Bu menü öğesi ve varsa alt öğeleri silinecek. Onaylıyor musunuz?">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <ul class="menu-tree-nested">
        @foreach($item->children as $child)
            @include('admin.menus.partials.item', ['item' => $child])
        @endforeach
    </ul>
</li>
