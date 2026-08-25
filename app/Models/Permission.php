<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PermissionGroup;
use App\Enums\PermissionKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'group',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'group'      => PermissionGroup::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * The enum case behind this row, or null if the row is stale.
     */
    public function enum(): ?PermissionKey
    {
        return PermissionKey::tryFrom($this->key);
    }
}
