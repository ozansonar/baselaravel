<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Redirect;
use App\Services\RedirectService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Yönlendirme listesinin dışa aktarma tanımı. */
final class RedirectExport extends ListExport
{
    public function __construct(
        private readonly RedirectService $redirects,
    ) {}

    public function title(): string
    {
        return 'Yönlendirmeler';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Redirect::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->redirects->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->redirects->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('#', static fn (Redirect $redirect): int => (int) $redirect->id)
                ->asNumber()
                ->width(6),
            ExportColumn::make('Eski URL', static fn (Redirect $redirect): string => (string) $redirect->old_url)->width(30),
            ExportColumn::make('Yeni URL', static fn (Redirect $redirect): string => (string) $redirect->new_url)->width(30),
            ExportColumn::make('Durum Kodu', static fn (Redirect $redirect): int => (int) ($redirect->status_code?->value ?? 0))
                ->asNumber()
                ->width(10),
            ExportColumn::make('Hit', static fn (Redirect $redirect): int => (int) $redirect->hit_count)
                ->asNumber()
                ->width(8),
            ExportColumn::make('Son Hit', static fn (Redirect $redirect): ?\DateTimeInterface => $redirect->last_hit_at)
                ->asDateTime()
                ->width(14),
            ExportColumn::make('Durum', static fn (Redirect $redirect): string => match (true) {
                $redirect->trashed()        => 'Silinmiş',
                (bool) $redirect->is_active => 'Aktif',
                default                     => 'Pasif',
            })->width(10),
        ];
    }
}
