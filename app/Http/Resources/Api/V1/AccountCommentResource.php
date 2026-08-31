<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kişinin kendi yorumu.
 *
 * Herkese açık yorum kaynağından (BlogCommentResource) ayrı, çünkü burada
 * fazladan iki şey var: durum ve hangi yazıya yapıldığı. Durum genel kaynağa
 * konamaz — onay bekleyen yorumların varlığını ziyaretçiye söylemek, sitede
 * görünmeyen içeriği duyurmak olurdu.
 *
 * @mixin \App\Models\BlogComment
 */
final class AccountCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'body'   => $this->body,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'post'   => $this->whenLoaded('post', fn (): ?array => $this->post === null ? null : [
                'id'    => $this->post->id,
                'title' => $this->post->title,
                'slug'  => $this->post->slug,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
