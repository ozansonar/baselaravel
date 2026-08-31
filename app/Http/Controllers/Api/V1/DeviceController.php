<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ApiAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Cihazlarım" — kullanıcının açık oturumları.
 *
 * Jeton, oturum çerezinden farklı olarak kendiliğinden sona ermiyor ve sahibi
 * hangi cihazlarda açık olduğunu göremiyordu. Telefonunu kaybeden ya da ortak
 * bir bilgisayarda giriş yaptığını hatırlayan kişinin elinde tek seçenek
 * şifresini değiştirmekti; artık ilgili oturumu tek başına kapatabiliyor.
 *
 * Doğrulanmış e-posta şartı yok: hesabına şüpheli bir erişim olduğunu düşünen
 * kişi, doğrulama adımını tamamlayamamış olsa bile oturumları kapatabilmeli.
 */
final class DeviceController extends Controller
{
    public function __construct(
        private readonly ApiAuthService $apiAuth,
    ) {}

    /**
     * GET /api/v1/auth/devices
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $currentId = $this->apiAuth->currentTokenId($user);

        return ApiResponse::success(
            $this->apiAuth->devices($user)
                ->map(fn (PersonalAccessToken $token): DeviceResource => new DeviceResource($token, $currentId))
                ->all(),
        );
    }

    /**
     * DELETE /api/v1/auth/devices/{device}
     */
    public function destroy(Request $request, int $device): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->apiAuth->revokeDevice($user, $device)) {
            return ApiResponse::error(__('api.devices.not_found'), status: 404);
        }

        return ApiResponse::success(null, __('api.devices.revoked'));
    }

    /**
     * DELETE /api/v1/auth/devices
     *
     * Bu cihaz hariç hepsi. Mevcut oturumu da kapatsaydı kullanıcı düğmeye
     * bastığı anda kendi uygulamasından atılırdı — beklediği şey bu değil.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $this->apiAuth->revokeOtherDevices($user);

        return ApiResponse::success(
            ['revoked' => $count],
            __('api.devices.others_revoked', ['count' => $count]),
        );
    }
}
