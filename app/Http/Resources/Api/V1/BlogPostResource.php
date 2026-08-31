<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ResolvesMediaUrls;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Liste görünümündeki blog yazısı.
 *
 * Gövde (`body`) bilinçli olarak yok: yirmi yazılık bir sayfada yirmi tam metin
 * taşımak, listenin kendisinden kat kat büyük bir yanıt demek. Tam metin detay
 * ucundan geliyor — {@see BlogPostDetailResource}.
 *
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    use ResolvesMediaUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'title'   => $this->title,
            'slug'    => $this->slug,
            'excerpt' => $this->excerpt,
            'image'   => $this->imageUrls($this->image),
            'status'  => $this->status?->value,
            'locale'  => $this->locale,
            'views'   => $this->views,
            'published_at' => $this->published_at?->toIso8601String(),
            'category'     => BlogCategoryResource::make($this->whenLoaded('category')),
            'author'       => $this->whenLoaded('author', fn (): ?array => $this->author === null ? null : [
                'id'        => $this->author->id,
                'full_name' => $this->author->full_name,
                'avatar'    => $this->imageUrls($this->author->avatar),
            ]),
            // Web sürümündeki karşılığı: uygulamadan paylaşılan bağlantı
            // tarayıcıda da açılabilsin.
            'url' => $this->relationLoaded('category') && $this->category !== null
                ? route('blog.show', ['categorySlug' => $this->category->slug, 'slug' => $this->slug])
                : null,
        ];
    }
}
