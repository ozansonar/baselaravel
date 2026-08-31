<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ResolvesMediaUrls;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Listedeki sayfa.
 *
 * İçerik (`content`) burada yok: sayfa listesi mobil uygulamada bir menü —
 * "Hakkımızda, Gizlilik Politikası, KVKK" — ve o menüyü çizmek için bütün
 * metinleri indirmenin anlamı yok. Tam metin detay ucundan geliyor.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    use ResolvesMediaUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'slug'       => $this->slug,
            'sort_order' => $this->sort_order,
            'locale'     => $this->locale,
            'url'        => route('pages.show', ['slug' => $this->slug]),
        ];
    }
}
