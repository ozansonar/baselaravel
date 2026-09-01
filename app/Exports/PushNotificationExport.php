<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PushNotification;
use App\Services\PushBroadcastService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Push duyuruları listesinin dışa aktarma tanımı. */
final class PushNotificationExport extends ListExport
{
    public function __construct(
        private readonly PushBroadcastService $broadcasts,
    ) {}

    public function title(): string
    {
        return 'Push Duyuruları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', PushNotification::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->broadcasts->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->broadcasts->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Başlık', static fn (PushNotification $n): string => (string) $n->title)->width(26),
            ExportColumn::make('Metin', static fn (PushNotification $n): string => (string) $n->body)->width(40),
            // Hedefin adı kayıt anındaki değil bugünkü hâli: rol yeniden
            // adlandırılmışsa dosyada da yeni adı görünsün.
            ExportColumn::make('Hedef', static fn (PushNotification $n): string => $n->audienceLabel())->width(20),
            ExportColumn::make('Durum', static fn (PushNotification $n): string => $n->status?->label() ?? '')->width(12),
            ExportColumn::make('Cihaz', static fn (PushNotification $n): int => (int) $n->total_devices)
                ->asNumber()
                ->width(10),
            ExportColumn::make('Ulaşan', static fn (PushNotification $n): int => (int) $n->sent_count)
                ->asNumber()
                ->width(10),
            ExportColumn::make('Başarısız', static fn (PushNotification $n): int => (int) $n->failed_count)
                ->asNumber()
                ->width(11),
            ExportColumn::make('Gönderen', static fn (PushNotification $n): string => $n->sender?->full_name ?? '')->width(20),
            ExportColumn::make('Tarih', static fn (PushNotification $n): ?\DateTimeInterface => $n->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
