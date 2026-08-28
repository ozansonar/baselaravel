<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PageView;
use App\Services\AnalyticsService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Ziyaret kaydı listesinin dışa aktarma tanımı. */
final class PageViewExport extends ListExport
{
    /**
     * Cihaz türü karşılıkları — ekrandaki rozetlerle aynı sözcükler.
     *
     * @var array<string, string>
     */
    private const DEVICE_LABELS = [
        'desktop' => 'Masaüstü',
        'mobile'  => 'Mobil',
        'tablet'  => 'Tablet',
        'bot'     => 'Bot',
        'other'   => 'Diğer',
    ];

    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function title(): string
    {
        return 'Ziyaret Kaydı';
    }

    public function authorize(): void
    {
        Gate::authorize('view-analytics');
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->analytics->visitFilterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->analytics->visitQuery($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Tarih', static fn (PageView $visit): ?\DateTimeInterface => $visit->viewed_at)
                ->asDateTime()
                ->width(16),
            ExportColumn::make('Sayfa', static fn (PageView $visit): string => (string) $visit->url_path)->width(34),
            ExportColumn::make('IP', static fn (PageView $visit): string => (string) $visit->ip_address)->width(14),
            ExportColumn::make(
                'Cihaz',
                static fn (PageView $visit): string => self::DEVICE_LABELS[$visit->device_type] ?? 'Diğer',
            )->width(12),
            // Üyeyse kim olduğu, bot değilse misafir: ekrandaki hücrenin aynısı.
            ExportColumn::make('Ziyaretçi', static fn (PageView $visit): string => match (true) {
                $visit->user !== null => (string) ($visit->user->full_name ?: $visit->user->email),
                (bool) $visit->is_bot => (string) ($visit->bot_name ?? 'bot'),
                default               => 'Misafir',
            })->width(20),
            ExportColumn::make('Tarayıcı', static fn (PageView $visit): string => (string) $visit->browser)->width(14),
            ExportColumn::make('İşletim Sistemi', static fn (PageView $visit): string => (string) $visit->os)->width(16),
            ExportColumn::make('Kaynak', static fn (PageView $visit): string => (string) ($visit->referrer_domain ?: 'Doğrudan'))->width(18),
            ExportColumn::make('Session', static fn (PageView $visit): string => (string) $visit->session_id)->width(20),
        ];
    }
}
