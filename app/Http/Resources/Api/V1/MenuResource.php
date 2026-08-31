<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bir konumun menüsü (header, footer …).
 *
 * @mixin Menu
 */
final class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'location' => $this->location,
            'locale'   => $this->locale,
            'items'    => MenuItemResource::collection($this->whenLoaded('rootItems')),
        ];
    }
}
