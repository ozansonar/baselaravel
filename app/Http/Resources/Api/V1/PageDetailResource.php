<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Page;
use Illuminate\Http\Request;

/**
 * Sayfanın tam hâli.
 *
 * `content` zengin metin editöründen geliyor, yani HTML. Mobil istemci onu bir
 * HTML görüntüleyicide basmak zorunda — düz metne çevirmek başlıkları, listeleri
 * ve bağlantıları düşürür. Yasal metinlerde (KVKK, gizlilik) bu bir biçim
 * sorunu değil, içerik kaybıdır.
 *
 * `sections` panelde tanımlanan serbest bölümler; sayfa şablonu onları
 * kullanmıyorsa boş gelir.
 *
 * @mixin Page
 */
final class PageDetailResource extends PageResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'content_format' => 'html',
            'sections' => $this->sections ?? [],
            'image'    => $this->imageUrls($this->image),
            'meta'     => [
                'title'       => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: $this->excerpt,
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'attachments'  => ContentFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
