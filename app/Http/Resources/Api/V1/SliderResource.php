<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ResolvesMediaUrls;
use App\Models\Slider;
use App\Services\LocalizedUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ana sayfa görsel şeridi.
 *
 * `button_url` çözülmüş geliyor: panelde site içi bir yol da (`/iletisim`)
 * harici bir adres de yazılabiliyor. Ham hâliyle verilseydi istemci hangisi
 * olduğunu anlayıp eksik olanı tamamlamak zorunda kalırdı — menü öğelerinde
 * çözülen aynı sorun.
 *
 * @mixin Slider
 */
final class SliderResource extends JsonResource
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
            'subtitle'    => $this->subtitle,
            'image'       => $this->imageUrls($this->image),
            'button_text' => $this->button_text,
            'button_url'  => $this->button_url === null || $this->button_url === ''
                ? null
                : app(LocalizedUrlService::class)->fromInput($this->button_url),
            'sort_order' => $this->sort_order,
            'locale'     => $this->locale,
        ];
    }
}
