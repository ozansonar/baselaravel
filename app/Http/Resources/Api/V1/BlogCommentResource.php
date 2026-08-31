<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BlogComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Onaylanmış tek yorum.
 *
 * E-posta adresi dışarı çıkmıyor: form yorumcuya "e-posta adresiniz
 * yayınlanmayacaktır" diyor ve bu söz API'de de tutulmak zorunda. `ip_address`
 * ve `status` de yönetim tarafına ait — buradan dönen her yorum zaten onaylı.
 *
 * @mixin BlogComment
 */
final class BlogCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'body'       => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            // Yanıtlar ağaç olarak iniyor; düz liste gelseydi istemci
            // parent_id'lere bakıp ağacı kendisi kurmak zorunda kalırdı.
            'replies' => self::collection($this->whenLoaded('approvedReplies')),
        ];
    }
}
