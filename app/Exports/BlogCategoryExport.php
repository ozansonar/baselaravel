<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BlogCategory;
use App\Services\BlogCategoryService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** İçerik kategorileri listesinin dışa aktarma tanımı. */
final class BlogCategoryExport extends ListExport
{
    public function __construct(
        private readonly BlogCategoryService $categories,
    ) {}

    public function title(): string
    {
        return 'İçerik Kategorileri';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', BlogCategory::class);
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
            ExportColumn::make('Kategori', static fn (BlogCategory $category): string => (string) $category->name)->width(26),
            ExportColumn::make('Slug', static fn (BlogCategory $category): string => (string) $category->slug)->width(24),
            ExportColumn::make('İçerik Sayısı', static fn (BlogCategory $category): int => (int) $category->posts_count)
                ->asNumber()
                ->width(12),
            ExportColumn::make('Durum', static fn (BlogCategory $category): string => match (true) {
                $category->trashed()  => 'Silinmiş',
                (bool) $category->is_active => 'Aktif',
                default               => 'Pasif',
            })->width(10),
            ExportColumn::make('Sıra', static fn (BlogCategory $category): int => (int) $category->sort_order)
                ->asNumber()
                ->width(8),
        ];
    }
}
