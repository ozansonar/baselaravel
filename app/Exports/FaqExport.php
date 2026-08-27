<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Faq;
use App\Services\FaqService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Sık sorulan sorular listesinin dışa aktarma tanımı. */
final class FaqExport extends ListExport
{
    public function __construct(
        private readonly FaqService $faqs,
    ) {}

    public function title(): string
    {
        return 'Sık Sorulan Sorular';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Faq::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->faqs->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->faqs->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Soru', static fn (Faq $faq): string => (string) $faq->question)->width(40),
            ExportColumn::make('Cevap', static fn (Faq $faq): string => (string) $faq->answer)->width(50),
            ExportColumn::make('Durum', static fn (Faq $faq): string => match (true) {
                $faq->trashed()        => 'Silinmiş',
                (bool) $faq->is_active => 'Aktif',
                default                => 'Pasif',
            })->width(10),
            ExportColumn::make('Sıra', static fn (Faq $faq): int => (int) $faq->sort_order)
                ->asNumber()
                ->width(8),
        ];
    }
}
