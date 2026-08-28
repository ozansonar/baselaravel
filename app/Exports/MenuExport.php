<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Menu;
use App\Services\MenuService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Menü listesinin dışa aktarma tanımı. */
final class MenuExport extends ListExport
{
    /**
     * Konum karşılıkları.
     *
     * Görünümdeki harita ikon ve açıklama da taşıyor; dosyaya yalnız okunabilir
     * ad gerekiyor, o yüzden burada yalnız etiketler duruyor.
     *
     * @var array<string, string>
     */
    private const LOCATION_LABELS = [
        'header' => 'Üst Menü',
        'footer' => 'Alt Menü',
    ];

    public function __construct(
        private readonly MenuService $menus,
    ) {}

    public function title(): string
    {
        return 'Menüler';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Menu::class);
    }

    /**
     * Menü ekranında süzgeç yok: liste olduğu gibi iniyor.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return [];
    }

    public function query(array $filters): Builder
    {
        return $this->menus->listQuery();
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Menü', static fn (Menu $menu): string => (string) $menu->name)->width(26),
            ExportColumn::make(
                'Konum',
                static fn (Menu $menu): string => self::LOCATION_LABELS[$menu->location] ?? ucfirst((string) $menu->location),
            )->width(16),
            ExportColumn::make('Dil', static fn (Menu $menu): string => strtoupper((string) $menu->locale))->width(8),
            ExportColumn::make('Öğe Sayısı', static fn (Menu $menu): int => (int) $menu->items_count)
                ->asNumber()
                ->width(12),
            ExportColumn::make('Durum', static fn (Menu $menu): string => $menu->is_active ? 'Aktif' : 'Pasif')->width(10),
        ];
    }
}
