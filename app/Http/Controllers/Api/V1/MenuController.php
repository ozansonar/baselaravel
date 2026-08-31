<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MenuResource;
use App\Http\Responses\ApiResponse;
use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;

/**
 * Site gezinmesi.
 *
 * Menüler dil bazında ayrı satırlar; istemcinin dili {@see \App\Http\Middleware\SetApiLocale}
 * ile çözülüyor ve o dilde menüsü olmayan konum varsayılan dile düşüyor —
 * yeni açılan bir dil siteyi gezinmesiz bırakmasın diye.
 */
final class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $menus,
    ) {}

    /**
     * GET /api/v1/menus
     *
     * Bütün konumlar tek istekte: uygulama açılışında üst ve alt menüyü ayrı
     * ayrı sormak iki gidiş dönüş demekti.
     */
    public function index(): JsonResponse
    {
        $menus = collect($this->menus->activeLocations())
            ->map(fn (string $location): ?Menu => $this->menus->getByLocation($location))
            ->filter()
            ->values();

        return ApiResponse::success(MenuResource::collection($menus));
    }

    /**
     * GET /api/v1/menus/{location}
     */
    public function show(string $location): JsonResponse
    {
        $menu = $this->menus->getByLocation($location);

        if ($menu === null) {
            return ApiResponse::error(__('api.menus.not_found'), status: 404);
        }

        return ApiResponse::success(MenuResource::make($menu));
    }
}
