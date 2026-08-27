<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Campaign;
use App\Services\CampaignService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Mail kampanyaları listesinin dışa aktarma tanımı. */
final class CampaignExport extends ListExport
{
    public function __construct(
        private readonly CampaignService $campaigns,
    ) {}

    public function title(): string
    {
        return 'Mail Kampanyaları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Campaign::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->campaigns->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->campaigns->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Kampanya', static fn (Campaign $campaign): string => (string) $campaign->name)->width(26),
            ExportColumn::make('Konu', static fn (Campaign $campaign): string => (string) $campaign->subject)->width(30),
            ExportColumn::make('Alıcı Kitlesi', static fn (Campaign $campaign): string => $campaign->audience?->label() ?? '')->width(16),
            ExportColumn::make('Durum', static fn (Campaign $campaign): string => $campaign->status?->label() ?? '')->width(12),
            // Ekranda ilerleme çubuğu olarak duran bilgi, dosyada sayıya döner.
            ExportColumn::make('Alıcı', static fn (Campaign $campaign): int => (int) $campaign->total_recipients)
                ->asNumber()
                ->width(10),
            ExportColumn::make('Gönderilen', static fn (Campaign $campaign): int => (int) $campaign->sent_count)
                ->asNumber()
                ->width(12),
            ExportColumn::make('Tarih', static fn (Campaign $campaign): ?\DateTimeInterface => $campaign->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
