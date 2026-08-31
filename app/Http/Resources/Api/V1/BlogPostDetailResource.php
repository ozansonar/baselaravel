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
            'attachments' => ContentFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
