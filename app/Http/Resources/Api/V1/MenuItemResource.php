<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\MenuItem;
use App\Services\MenuItemService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Menüdeki tek bağlantı.
 *
 * `url` hazır çözülmüş geliyor: kayıt bir rota adı da tutabiliyor
 * (`blog.index`), site içi bir yol da, harici bir adres de. Hangisi olduğunu
 * istemciye anlatıp çözümü ona bırakmak, aynı mantığın Flutter'da bir daha
 * yazılması demekti — ve panelden bir sayfanın adresi değiştiğinde mobil
 * uygulamanın da güncellenmesi.
 *
 * @mixin MenuItem
 */
final class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'icon'         => $this->icon,
            'url'          => app(MenuItemService::class)->resolveUrl($this->resource),
            'link_type'    => $this->link_type,
            'route_name'   => $this->route_name,
            'target'       => $this->target,
            'display_type' => $this->display_type,
            'sort_order'   => $this->sort_order,
            // Alt menüler ağaç olarak iniyor; düz liste gelseydi istemci
            // parent_id'lere bakıp ağacı kendisi kurmak zorunda kalırdı.
            'children' => self::collection($this->whenLoaded('activeChildren')),
        ];
    }
}
