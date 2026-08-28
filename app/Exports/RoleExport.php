<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Role;
use App\Services\RoleService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Rol listesinin dışa aktarma tanımı. */
final class RoleExport extends ListExport
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function title(): string
    {
        return 'Roller';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    /**
     * Rol ekranında süzgeç yok: liste olduğu gibi iniyor.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return [];
    }

    public function query(array $filters): Builder
    {
        return $this->roles->listQuery();
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        $roles = $this->roles;

        return [
            ExportColumn::make('Rol', static fn (Role $role): string => (string) $role->name)->width(22),
            ExportColumn::make('Anahtar', static fn (Role $role): string => (string) $role->slug)->width(18),
            ExportColumn::make(
                'Tür',
                static fn (Role $role): string => $roles->isSystemRole($role) ? 'Sistem Rolü' : 'Özel Rol',
            )->width(14),
            ExportColumn::make('Açıklama', static fn (Role $role): string => (string) $role->description)->width(34),
            ExportColumn::make('Kullanıcı', static fn (Role $role): int => (int) $role->users_count)
                ->asNumber()
                ->width(10),
            ExportColumn::make('İzin', static fn (Role $role): int => (int) $role->permissions_count)
                ->asNumber()
                ->width(8),
        ];
    }
}
