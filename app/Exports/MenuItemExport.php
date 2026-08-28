<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Menü öğeleri listesinin dışa aktarma tanımı.
 *
 * Ekranda öğeler ağaç olarak duruyor; dosyada düz satırlar olarak, üst öğesi
 * kendi sütununda. Hangi menünün öğeleri olduğu adres satırındaki menu
 * değerinden geliyor — ekranda da her menünün kendi sayfası var.
 */
final class MenuItemExport extends ListExport
{
    public function title(): string
    {
        return 'Menü Öğeleri';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', MenuItem::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return ['menu'];
    }

    public function query(array $filters): Builder
    {
        $query = MenuItem::query()
            ->with(['parent:id,label'])
            ->orderBy('sort_order')
            ->orderBy('id');

        // Menü seçilmemişse liste boş kalmaz, tüm menülerin öğeleri iner;
        // menü adı sütunu hangi öğenin nereye ait olduğunu söylüyor.
        if (($filters['menu'] ?? '') !== '') {
            $query->where('menu_id', (int) $filters['menu']);
        }

        return $query->with('menu:id,name');
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Menü', static fn (MenuItem $item): string => (string) ($item->menu?->name ?? ''))->width(20),
            ExportColumn::make('Etiket', static fn (MenuItem $item): string => (string) $item->label)->width(24),
            ExportColumn::make('Üst Öğe', static fn (MenuItem $item): string => (string) ($item->parent?->label ?? ''))->width(20),
            // Bağlantı ya bir rota adı ya da elle yazılmış adres; ekranda da
            // ikisinden hangisiyse o gösteriliyor.
            ExportColumn::make('Bağlantı', static fn (MenuItem $item): string => $item->link_type === 'route'
                ? (string) $item->route_name
                : (string) $item->url)->width(34),
            ExportColumn::make('Görünüm', static fn (MenuItem $item): string => match ($item->display_type) {
                'mega_menu' => 'Mega Menü',
                'dropdown'  => 'Açılır Menü',
                default     => 'Bağlantı',
            })->width(14),
            ExportColumn::make('Sıra', static fn (MenuItem $item): int => (int) $item->sort_order)
                ->asNumber()
                ->width(8),
            ExportColumn::make('Durum', static fn (MenuItem $item): string => $item->is_active ? 'Aktif' : 'Pasif')->width(10),
        ];
    }
}
