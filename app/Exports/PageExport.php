<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Services\PageService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Sayfa listesinin dışa aktarma tanımı. */
final class PageExport extends ListExport
{
    public function __construct(
        private readonly PageService $pages,
    ) {}

    public function title(): string
    {
        return 'Sayfalar';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Page::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->pages->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->pages->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Sayfa', static fn (Page $page): string => (string) $page->title)->width(30),
            ExportColumn::make('Slug', static fn (Page $page): string => '/' . $page->slug)->width(22),
            ExportColumn::make('Durum', static fn (Page $page): string => match (true) {
                $page->trashed() => 'Silinmiş',
                $page->status === ContentStatus::Published => 'Yayında',
                $page->status === ContentStatus::Draft     => 'Taslak',
                $page->status === ContentStatus::Archived  => 'Arşiv',
                $page->status === ContentStatus::Scheduled => 'Zamanlanmış',
                default => '',
            })->width(12),
            ExportColumn::make('Sıra', static fn (Page $page): int => (int) $page->sort_order)
                ->asNumber()
                ->width(8),
            ExportColumn::make('Yayın Tarihi', static fn (Page $page): ?\DateTimeInterface => $page->published_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
