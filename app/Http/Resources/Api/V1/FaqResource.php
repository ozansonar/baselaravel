<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Faq
 */
final class FaqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'question'   => $this->question,
            'answer'     => $this->answer,
            'sort_order' => $this->sort_order,
            'locale'     => $this->locale,
        ];
    }
}
