<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\GalleryCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GalleryCategory
 */
final class GalleryCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'sort_order'  => $this->sort_order,
            'locale'      => $this->locale,
            'items_count' => $this->whenCounted('galleryItems'),
        ];
    }
}
