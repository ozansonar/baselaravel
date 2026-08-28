<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\User;
use App\Services\UserService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Kullanıcı listesinin dışa aktarma tanımı. */
final class UserExport extends ListExport
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function title(): string
    {
        return 'Kullanıcı Listesi';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->users->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->users->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Ad Soyad', static fn (User $user): string => $user->full_name)->width(22),
            ExportColumn::make('E-posta', static fn (User $user): string => (string) $user->email)->width(28),
            ExportColumn::make(
                'Rol',
                static fn (User $user): string => $user->roles->pluck('name')->implode(', '),
            )->width(18),
            ExportColumn::make('Durum', static fn (User $user): string => match (true) {
                $user->trashed() => 'Silinmiş',
                $user->is_active => 'Aktif',
                default          => 'Pasif',
            })->width(10),
            ExportColumn::make('Kayıt Tarihi', static fn (User $user): ?\DateTimeInterface => $user->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
