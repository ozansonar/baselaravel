<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BlogPost;
use Illuminate\Http\Request;

/**
 * Tek yazının tam hâli: gövde, SEO alanları ve ekler.
 *
 * @mixin BlogPost
 */
final class BlogPostDetailResource extends BlogPostResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'body' => $this->body,
            'meta' => [
                'title'       => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: $this->excerpt,
            ],
            // Yorumların kendisi ayrı uçtan geliyor; burada yalnız sayısı var ki
            // istemci "12 Yorum" başlığını istek atmadan çizebilsin.
            //
            // Ön yüzdeki sayı gibi yalnız üst düzey yorumları sayıyor,
            // yanıtları değil: aynı yazı web'de ve uygulamada farklı sayı
            // göstermemeli.
            'comment_count' => $this->whenCounted('approvedComments'),
            'attachments'   => ContentFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
