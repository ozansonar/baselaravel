<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Slider;
use App\Services\SliderService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Slider listesinin dışa aktarma tanımı. */
final class SliderExport extends ListExport
{
    public function __construct(
        private readonly SliderService $sliders,
    ) {}

    public function title(): string
    {
        return 'Sliderlar';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Slider::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->sliders->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->sliders->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Başlık', static fn (Slider $slider): string => (string) $slider->title)->width(26),
            ExportColumn::make('Alt Başlık', static fn (Slider $slider): string => (string) $slider->subtitle)->width(30),
            ExportColumn::make('Buton', static fn (Slider $slider): string => (string) $slider->button_text)->width(16),
            ExportColumn::make('Durum', static fn (Slider $slider): string => match (true) {
                $slider->trashed()        => 'Silinmiş',
                (bool) $slider->is_active => 'Aktif',
                default                   => 'Pasif',
            })->width(10),
            ExportColumn::make('Sıra', static fn (Slider $slider): int => (int) $slider->sort_order)
                ->asNumber()
                ->width(8),
        ];
    }
}
