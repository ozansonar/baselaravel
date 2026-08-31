<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Site ayarları — yalnız dışarı açılması onaylananlar.
 *
 * Hangi grupların ve anahtarların yayınlandığı config/api.php'de; süzgecin
 * kendisi {@see SettingService::publicValues()} içinde. Buradaki tek karar,
 * istemcinin tek bir grup isteyip istemediği.
 */
final class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
    ) {}

    /**
     * GET /api/v1/settings
     * GET /api/v1/settings?group=contact
     */
    public function index(Request $request): JsonResponse
    {
        $all = $this->settings->publicValues();

        $group = $request->query('group');

        if (! is_string($group) || $group === '') {
            return ApiResponse::success($all);
        }

        // Var olmayan ya da yayınlanmayan bir grup boş nesne değil 404 dönüyor:
        // istemci yanlış grup adı yazdığını "hiç ayar yok" sanmamalı.
        if (! array_key_exists($group, $all)) {
            return ApiResponse::error(__('api.settings.group_not_found'), status: 404);
        }

        return ApiResponse::success([$group => $all[$group]]);
    }
}
