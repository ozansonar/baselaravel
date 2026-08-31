<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ResolvesMediaUrls;
use App\Models\GalleryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Galerideki tek öğe — fotoğraf ya da video.
 *
 * `video_url` yalnız video türünde dolu; fotoğrafta null. `image` her ikisinde
 * de var: videonun kapak görseli de aynı sütunda duruyor.
 *
 * @mixin GalleryItem
 */
final class GalleryItemResource extends JsonResource
{
    use ResolvesMediaUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => [
                'value' => $this->type?->value,
                'label' => $this->type?->label(),
            ],
            'image'      => $this->imageUrls($this->image),
            'video_url'  => $this->video_url,
            'duration'   => $this->duration,
            'view_count' => $this->view_count,
            'sort_order' => $this->sort_order,
            'locale'     => $this->locale,
            'category'   => GalleryCategoryResource::make($this->whenLoaded('galleryCategory')),
        ];
    }
}
