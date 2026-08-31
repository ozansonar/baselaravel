<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Kullanıcının açık bir oturumu — yani bir jeton.
 *
 * Jetonun kendisi burada yok ve olamaz: Sanctum onu hash'li tutuyor, düz metni
 * yalnız üretildiği anda bir kez görülüyor. Kullanıcının bu ekranda ihtiyacı
 * olan şey zaten jeton değil, "hangi cihaz, en son ne zaman kullanılmış" —
 * tanımadığı bir satır görürse iptal edebilsin diye.
 *
 * @mixin PersonalAccessToken
 */
final class DeviceResource extends JsonResource
{
    public function __construct(
        PersonalAccessToken $resource,
        private readonly ?int $currentTokenId = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            // İstemci "bu cihaz" etiketini basabilsin ve kullanıcı yanlışlıkla
            // kendi oturumunu kapatmasın diye.
            'current'      => $this->id === $this->currentTokenId,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
            'expires_at'   => $this->expires_at?->toIso8601String(),
        ];
    }
}
