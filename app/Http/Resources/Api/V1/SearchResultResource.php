<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\SearchType;
use App\Http\Resources\Api\V1\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tek bir arama sonucu.
 *
 * Dört ayrı türden gelen kayıtlar tek bir şekle indirgeniyor: istemci sonuç
 * listesini çizerken türe göre dallanmak zorunda kalmasın. Tür bilgisi yine de
 * var (`type`) — rozet ve ikon için gerekiyor.
 *
 * `url` çözülmüş geliyor; SSS'nin kendi sayfası olmadığı için o tür SSS
 * sayfasının kendisine gider.
 */
final class SearchResultResource extends JsonResource
{
    use ResolvesMediaUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $item */
        $item = $this->resource;

        /** @var SearchType $type */
        $type = $item['type'];

        return [
            'type' => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            'id'      => $item['id'],
            'title'   => $item['title'],
            'snippet' => $item['snippet'],
            'image'   => $this->imageUrls($item['image'] ?? null),
            'url'     => $item['url'],
            'date'    => $item['date'] === null
                ? null
                : \Illuminate\Support\Carbon::parse($item['date'])->toIso8601String(),
        ];
    }
}
