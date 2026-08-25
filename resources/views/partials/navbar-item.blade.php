@php
    $menuItemService = app(\App\Services\MenuItemService::class);
    $itemUrl = $menuItemService->resolveUrl($item);
    $isActive = $menuItemService->isActive($item);
    $hasChildren = $item->activeChildren->isNotEmpty();
@endphp

@if($item->display_type === 'mega_menu' && $hasChildren)
    {{-- Mega Menu --}}
    <li class="nav-item nav-item-mega">
        <a class="nav-link {{ $isActive ? 'active' : '' }}"
           href="{{ $itemUrl }}" role="button"
           @if($item->target === '_blank') target="_blank" rel="noopener" @endif
           aria-expanded="false" aria-haspopup="true">
            @if($item->icon)
                <i class="{{ $item->icon }} nav-link-icon"></i>
            @endif
            {{ $item->label }}
            <i class="fa-solid fa-chevron-down nav-link-arrow"></i>
        </a>
        <div class="mega-menu">
            <div class="mega-menu-inner">
                <div class="mega-menu-header">
                    <h5 class="mega-menu-title">
                        <i class="fa-solid fa-leaf"></i> {{ $item->label }}
                    </h5>
                    <a href="{{ $itemUrl }}" class="mega-menu-all-link">
                        {{ __('site.actions.view_all') }} <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div class="mega-menu-grid">
                    @foreach($item->activeChildren as $child)
                        @php $childUrl = $menuItemService->resolveUrl($child); @endphp
                        <a href="{{ $childUrl }}" class="mega-menu-card"
                           @if($child->target === '_blank') target="_blank" rel="noopener" @endif>
                            <div class="mega-menu-card-icon">
                                @if($child->icon)
                                    <i class="{{ $child->icon }}"></i>
                                @else
                                    <i class="fa-solid fa-seedling"></i>
                                @endif
                            </div>
                            <div class="mega-menu-card-body">
                                <span class="mega-menu-card-name">{{ $child->label }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right mega-menu-card-arrow"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </li>
@elseif($item->display_type === 'dropdown' && $hasChildren)
    {{-- Standart Dropdown --}}
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ $isActive ? 'active' : '' }}"
           href="{{ $itemUrl }}" role="button"
           data-bs-toggle="dropdown" aria-expanded="false"
           @if($item->target === '_blank') target="_blank" rel="noopener" @endif>
            @if($item->icon)
                <i class="{{ $item->icon }} nav-link-icon"></i>
            @endif
            {{ $item->label }}
        </a>
        <ul class="dropdown-menu">
            @foreach($item->activeChildren as $child)
                @php $childUrl = $menuItemService->resolveUrl($child); @endphp
                <li>
                    <a class="dropdown-item {{ $menuItemService->isActive($child) ? 'active' : '' }}"
                       href="{{ $childUrl }}"
                       @if($child->target === '_blank') target="_blank" rel="noopener" @endif>
                        @if($child->icon)
                            <i class="{{ $child->icon }} me-2"></i>
                        @endif
                        {{ $child->label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@else
    {{-- Standart Link --}}
    <li class="nav-item">
        <a class="nav-link {{ $isActive ? 'active' : '' }}"
           href="{{ $itemUrl }}"
           @if($item->target === '_blank') target="_blank" rel="noopener" @endif>
            @if($item->icon)
                <i class="{{ $item->icon }} nav-link-icon"></i>
            @endif
            {{ $item->label }}
        </a>
    </li>
@endif
