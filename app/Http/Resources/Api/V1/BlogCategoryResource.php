<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogCategory
 */
final class BlogCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'icon'       => $this->icon,
            'sort_order' => $this->sort_order,
            'locale'     => $this->locale,
            // withCount ile sayılmadıysa alan hiç çıkmıyor — sıfır yazmak,
            // "hiç yazısı yok" demek olurdu, oysa cevap "sayılmadı".
            'posts_count' => $this->whenCounted('posts'),
            'url'         => route('blog.category', ['categorySlug' => $this->slug]),
        ];
    }
}
