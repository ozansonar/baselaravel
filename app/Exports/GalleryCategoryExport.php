<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GalleryCategory;
use App\Services\GalleryCategoryService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Galeri kategorileri listesinin dışa aktarma tanımı. */
final class GalleryCategoryExport extends ListExport
{
    public function __construct(
        private readonly GalleryCategoryService $categories,
    ) {}

    public function title(): string
    {
        return 'Galeri Kategorileri';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', GalleryCategory::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->categories->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->categories->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Kategori', static fn (GalleryCategory $category): string => (string) $category->name)->width(26),
            ExportColumn::make('Slug', static fn (GalleryCategory $category): string => (string) $category->slug)->width(22),
            ExportColumn::make('Öğe Sayısı', static fn (GalleryCategory $category): int => (int) $category->gallery_items_count)
                ->asNumber()
                ->width(12),
            ExportColumn::make('Durum', static fn (GalleryCategory $category): string => match (true) {
                $category->trashed()        => 'Silinmiş',
                (bool) $category->is_active => 'Aktif',
                default                     => 'Pasif',
            })->width(10),
            ExportColumn::make('Sıra', static fn (GalleryCategory $category): int => (int) $category->sort_order)
                ->asNumber()
                ->width(8),
        ];
    }
}
