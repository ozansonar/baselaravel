<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AdminNotification;
use App\Services\NotificationCenter;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Bildirim listesinin dışa aktarma tanımı. */
final class NotificationExport extends ListExport
{
    public function title(): string
    {
        return 'Bildirimler';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', AdminNotification::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return NotificationCenter::filterKeys();
    }

    public function query(array $filters): Builder
    {
        // Bildirimler kişiye bağlı: dosya, ekranda kimin bildirimleri
        // görünüyorsa onları taşır.
        return NotificationCenter::listQuery(Auth::id(), $filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Başlık', static fn (AdminNotification $notification): string => (string) $notification->title)->width(30),
            ExportColumn::make('Mesaj', static fn (AdminNotification $notification): string => (string) $notification->message)->width(40),
            ExportColumn::make('Seviye', static fn (AdminNotification $notification): string => $notification->level?->label() ?? '')->width(12),
            ExportColumn::make('Tür', static fn (AdminNotification $notification): string => $notification->typeLabel())->width(18),
            ExportColumn::make('Durum', static fn (AdminNotification $notification): string => $notification->isUnread() ? 'Okunmadı' : 'Okundu')->width(10),
            ExportColumn::make('Tarih', static fn (AdminNotification $notification): ?\DateTimeInterface => $notification->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
