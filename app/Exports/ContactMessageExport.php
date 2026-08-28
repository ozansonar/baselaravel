<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\ContactMessage;
use App\Services\ContactMessageService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** İletişim mesajları listesinin dışa aktarma tanımı. */
final class ContactMessageExport extends ListExport
{
    public function __construct(
        private readonly ContactMessageService $messages,
    ) {}

    public function title(): string
    {
        return 'İletişim Mesajları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', ContactMessage::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->messages->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->messages->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Gönderen', static fn (ContactMessage $message): string => (string) $message->name)->width(20),
            // Ekranda iletişim bilgisi mesaj açılınca görünüyor; dosyada
            // satırın yanında durmazsa liste dışarıda işe yaramıyor.
            ExportColumn::make('E-posta', static fn (ContactMessage $message): string => (string) $message->email)->width(26),
            ExportColumn::make('Telefon', static fn (ContactMessage $message): string => (string) $message->phone)->width(14),
            ExportColumn::make('Konu', static fn (ContactMessage $message): string => (string) $message->subject)->width(26),
            ExportColumn::make('Mesaj', static fn (ContactMessage $message): string => (string) $message->message)->width(40),
            ExportColumn::make('Durum', static fn (ContactMessage $message): string => match (true) {
                $message->trashed()      => 'Silinmiş',
                (bool) $message->is_read => 'Okundu',
                default                  => 'Okunmadı',
            })->width(10),
            ExportColumn::make('Tarih', static fn (ContactMessage $message): ?\DateTimeInterface => $message->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
