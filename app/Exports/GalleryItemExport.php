<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GalleryItem;
use App\Services\GalleryService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Galeri öğeleri listesinin dışa aktarma tanımı. */
final class GalleryItemExport extends ListExport
{
    public function __construct(
        private readonly GalleryService $items,
    ) {}

    public function title(): string
    {
        return 'Galeri Öğeleri';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', GalleryItem::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->items->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->items->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Başlık', static fn (GalleryItem $item): string => (string) $item->title)->width(28),
            ExportColumn::make('Açıklama', static fn (GalleryItem $item): string => (string) $item->description)->width(34),
            ExportColumn::make('Tür', static fn (GalleryItem $item): string => $item->type?->label() ?? '')->width(12),
            ExportColumn::make('Kategori', static fn (GalleryItem $item): string => (string) ($item->galleryCategory?->name ?? ''))->width(18),
            ExportColumn::make('Durum', static fn (GalleryItem $item): string => match (true) {
                $item->trashed()        => 'Silinmiş',
                (bool) $item->is_active => 'Aktif',
                default                 => 'Pasif',
            })->width(10),
            ExportColumn::make('Sıra', static fn (GalleryItem $item): int => (int) $item->sort_order)
                ->asNumber()
                ->width(8),
            ExportColumn::make('Tarih', static fn (GalleryItem $item): ?\DateTimeInterface => $item->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
