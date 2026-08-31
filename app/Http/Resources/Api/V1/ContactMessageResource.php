<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Gönderilen iletişim mesajının makbuzu.
 *
 * Yalnız gönderenin kendi yazdıkları geri dönüyor. `ip_address`, `is_read`,
 * `reply_text` gibi alanlar yönetim tarafına ait: gönderen onları görmemeli,
 * `id` ise takip için yeterli.
 *
 * @mixin ContactMessage
 */
final class ContactMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'subject'    => $this->subject,
            'message'    => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
