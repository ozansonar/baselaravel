<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApiAuthService;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

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
        private readonly PasswordResetCodeService $passwordReset,
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
            (array) $request->input('abilities', []),
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
            (array) $request->input('abilities', []),
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

        // Jetonun yetkileri de burada: uygulama yeniden açıldığında elindeki
        // jetonun neye yettiğini tek istekle öğreniyor.
        $token = $user->currentAccessToken();

        return ApiResponse::success(
            UserResource::make($user),
            extra: ['meta' => [
                'abilities' => $token instanceof PersonalAccessToken
                    ? $token->abilities
                    // Oturum çerezi ile gelen ön yüz isteğinde jeton yok;
                    // oturumun kendisi tam yetkili.
                    : ['*'],
            ]],
        );
    }

    /**
     * POST /api/v1/auth/password/forgot
     *
     * Altı haneli kod maille gidiyor. Web'deki bağlantılı akıştan farkı
     * {@see PasswordResetCodeService} içinde anlatılı.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordReset->sendCode($request->string('email')->value());

        // Adres kayıtlı olsa da olmasa da aynı yanıt. Ayırt edilebilseydi bu
        // uç, hangi adreslerin sistemde olduğunu öğrenmenin en kolay yolu
        // olurdu — üstelik giriş denemesi gerektirmeden.
        return ApiResponse::success(
            ['expires_in_minutes' => $this->passwordReset->expiresInMinutes()],
            __('api.password.code_sent'),
        );
    }

    /**
     * POST /api/v1/auth/password/reset
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $done = $this->passwordReset->reset(
            $request->string('email')->value(),
            $request->string('code')->value(),
            $request->string('password')->value(),
        );

        if (! $done) {
            // Kodun yanlış mı yoksa süresi dolmuş mu olduğu söylenmiyor:
            // ikisini ayırmak, geçerli bir kodun varlığını doğrulamaya yarar.
            return ApiResponse::error(
                __('api.password.code_invalid'),
                ['code' => [__('api.password.code_invalid')]],
                422,
            );
        }

        // Jeton dönülmüyor: şifresini sıfırlayan kullanıcı yeni şifresiyle
        // giriş yapmalı. Sıfırlama, o ana kadarki bütün oturum ve jetonları
        // düşürüyor — biri hesabı ele geçirdiyse elindekini de kaybetsin diye.
        return ApiResponse::success(null, __('api.password.reset'));
    }

    /**
     * POST /api/v1/auth/email/resend
     *
     * Doğrulama bağlantısını yeniden gönderir. Bağlantı tarayıcıda açılıyor ve
     * doğrulamayı orada tamamlıyor — mobil tarafta ek bir kurulum gerektirmeyen
     * tek adım bu.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, __('api.auth.already_verified'));
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(null, __('api.auth.verification_sent'));
    }

    /**
     * Giriş ve kayıt aynı gövdeyi dönüyor: istemcinin iki ayrı ayrıştırıcı
     * yazmasına gerek kalmıyor.
     *
     * @param array{user: User, token: string, expires_at: string|null, abilities: array<int, string>} $result
     * @return array<string, mixed>
     */
    private function authPayload(array $result): array
    {
        return [
            'user'       => UserResource::make($result['user']),
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            // İstemci ne yapabileceğini yanıttan öğreniyor: yapamayacağı bir
            // isteği hiç atmasın, ekranı ona göre çizsin.
            'abilities' => $result['abilities'],
        ];
    }
}
