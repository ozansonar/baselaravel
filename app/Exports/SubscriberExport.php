<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\SubscriberSource;
use App\Models\Subscriber;
use App\Services\SubscriberService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Abone listesinin dışa aktarma tanımı. */
final class SubscriberExport extends ListExport
{
    public function __construct(
        private readonly SubscriberService $subscribers,
    ) {}

    public function title(): string
    {
        return 'Aboneler';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Subscriber::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->subscribers->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->subscribers->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('E-posta', static fn (Subscriber $subscriber): string => (string) $subscriber->email)->width(28),
            ExportColumn::make('Ad Soyad', static fn (Subscriber $subscriber): string => (string) ($subscriber->full_name ?? ''))->width(22),
            ExportColumn::make('Dil', static fn (Subscriber $subscriber): string => strtoupper((string) $subscriber->locale))->width(8),
            ExportColumn::make(
                'Listeler',
                static fn (Subscriber $subscriber): string => $subscriber->lists->pluck('name')->implode(', '),
            )->width(22),
            ExportColumn::make(
                'Kaynak',
                static fn (Subscriber $subscriber): string => SubscriberSource::tryFrom((string) $subscriber->source)?->label() ?? '',
            )->width(14),
            ExportColumn::make('Durum', static fn (Subscriber $subscriber): string => $subscriber->status?->label() ?? '')->width(12),
            ExportColumn::make('Kayıt', static fn (Subscriber $subscriber): ?\DateTimeInterface => $subscriber->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
