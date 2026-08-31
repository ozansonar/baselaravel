<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApiAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kayıt, giriş, çıkış ve "ben kimim".
 *
 * Yanıtta jetonun kendisi bir kez, düz metin olarak dönüyor — Sanctum onu
 * veritabanında hash'li tuttuğu için ikinci kez okunamaz. İstemci bu yüzden
 * jetonu güvenli depoya (Keychain / Keystore) yazmak zorunda.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly ApiAuthService $apiAuth,
    ) {}

    /**
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Kayıt panelden kapatılabiliyor. Ön yüzde bu durumda kayıt sayfası
        // 404 veriyor; API'de 403 daha doğru: adres var, işlem kapalı.
        if (Setting::getValue('registration_enabled', '1') !== '1') {
            return ApiResponse::error(__('api.auth.registration_disabled'), status: 403);
        }

        $result = $this->apiAuth->register(
            $request->validated(),
            $request->string('device_name')->value(),
        );

        return ApiResponse::created(
            $this->authPayload($result),
            __('api.auth.registered'),
        );
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->apiAuth->login(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->string('device_name')->value(),
        );

        if ($result === null) {
            // Hangi alanın yanlış olduğu bilerek söylenmiyor: "böyle bir
            // e-posta yok" cevabı, kayıtlı adresleri tek tek denemeye açık
            // kapı bırakır.
            return ApiResponse::error(
                __('site.login.failed'),
                ['email' => [__('site.login.failed')]],
                401,
            );
        }

        return ApiResponse::success($this->authPayload($result), __('api.auth.logged_in'));
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->apiAuth->logout($user);

        return ApiResponse::success(null, __('api.auth.logged_out'));
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Roller burada bilerek yükleniyor: mobil uygulama menüsünü buna göre
        // kuruyor ve ayrı bir uç için ikinci gidiş dönüş yapmak zorunda kalmasın.
        $user->loadMissing('roles');

        return ApiResponse::success(UserResource::make($user));
    }

    /**
     * Giriş ve kayıt aynı gövdeyi dönüyor: istemcinin iki ayrı ayrıştırıcı
     * yazmasına gerek kalmıyor.
     *
     * @param array{user: User, token: string, expires_at: string|null} $result
     * @return array<string, mixed>
     */
    private function authPayload(array $result): array
    {
        return [
            'user'       => UserResource::make($result['user']),
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
        ];
    }
}
