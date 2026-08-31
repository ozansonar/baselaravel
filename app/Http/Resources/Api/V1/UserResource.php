<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ResolvesMediaUrls;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Giriş yapmış kullanıcının dışarı açılan yüzü.
 *
 * Modelin `$hidden` listesi zaten parolayı ve remember_token'ı düşürüyor ama
 * buradaki liste beyaz: yarın modele bir sütun eklendiğinde API onu
 * kendiliğinden yayınlamaz. Karartma listesi unutulur, seçme listesi unutulmaz.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    use ResolvesMediaUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'full_name'  => $this->full_name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'avatar'     => $this->imageUrls($this->avatar),
            'bio'        => $this->bio,
            'location'   => $this->location,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender'     => $this->gender === null ? null : [
                'value' => $this->gender->value,
                'label' => $this->gender->label(),
            ],
            'department' => $this->department === null ? null : [
                'value' => $this->department->value,
                'label' => $this->department->label(),
            ],
            'is_active'         => $this->is_active,
            // Uygulama güvenlik ekranını buna bakarak çiziyor. Anahtarın
            // kendisi asla çıkmıyor (modelde $hidden), yalnız açık mı kapalı
            // mı bilgisi.
            'two_factor_enabled' => $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null,
            'email_verified'    => $this->email_verified_at !== null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            // Rol listesi yalnızca çağıran onu yükleyerek istediğinde çıkar;
            // her /me isteğinde iki sorgu daha atmanın anlamı yok.
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles
                ->map(fn (Role $role): array => ['slug' => $role->slug, 'name' => $role->name])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
