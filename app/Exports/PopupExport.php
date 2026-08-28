<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PopupPage;
use App\Models\Popup;
use App\Services\PopupService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Popup / modal listesinin dışa aktarma tanımı. */
final class PopupExport extends ListExport
{
    public function __construct(
        private readonly PopupService $popups,
    ) {}

    public function title(): string
    {
        return 'Popup ve Modallar';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Popup::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->popups->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->popups->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Başlık', static fn (Popup $popup): string => (string) $popup->title)->width(26),
            ExportColumn::make('Buton', static fn (Popup $popup): string => (string) $popup->button_text)->width(16),
            ExportColumn::make('Boyut', static fn (Popup $popup): string => $popup->size?->label() ?? '')->width(10),
            // Ekranda rozet olarak duran sayfa listesi, dosyada tek hücrede
            // okunabilir kalsın diye virgülle birleştiriliyor.
            ExportColumn::make('Sayfalar', static fn (Popup $popup): string => collect($popup->pages ?? [])
                ->map(static fn (string $page): ?string => PopupPage::tryFrom($page)?->label())
                ->filter()
                ->implode(', '))->width(24),
            ExportColumn::make('Başlangıç', static fn (Popup $popup): ?\DateTimeInterface => $popup->start_date)
                ->asDate()
                ->width(12),
            ExportColumn::make('Bitiş', static fn (Popup $popup): ?\DateTimeInterface => $popup->end_date)
                ->asDate()
                ->width(12),
            ExportColumn::make('Durum', static fn (Popup $popup): string => match (true) {
                $popup->trashed()        => 'Silinmiş',
                (bool) $popup->is_active => 'Aktif',
                default                  => 'Pasif',
            })->width(10),
            ExportColumn::make('Sıra', static fn (Popup $popup): int => (int) $popup->sort_order)
                ->asNumber()
                ->width(8),
        ];
    }
}
