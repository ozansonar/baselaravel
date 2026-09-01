<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SocialLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Google / Apple ile giriş.
 *
 * Akış mobilin akışı: uygulama sağlayıcının kendi SDK'sıyla bir kimlik jetonu
 * alıyor ve buraya gönderiyor; sunucu jetonu sağlayıcının açık anahtarıyla
 * doğruluyor. Tarayıcı yönlendirmesi yok — mobilde yönlendirme akışı hem
 * kötü bir deneyim hem de Apple'ın mağaza kuralına aykırı.
 */
final class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $social,
    ) {}

    /**
     * POST /api/v1/auth/social/{provider}
     */
    public function store(SocialLoginRequest $request, string $provider): JsonResponse
    {
        $resolved = SocialProvider::tryFrom($provider);

        if ($resolved === null || ! $resolved->isConfigured()) {
            // Tanınmayan ve yapılandırılmamış sağlayıcı aynı cevabı alıyor:
            // hangi sağlayıcıların açık olduğu bilgisi tek tek denenerek
            // toplanabilecek bir şey olmasın.
            return ApiResponse::error(__('api.social.unsupported'), status: 404);
        }

        $result = $this->social->login(
            $resolved,
            $request->string('id_token')->value(),
            $request->string('device_name')->value() ?: null,
            (array) $request->input('abilities', []),
            $request->string('first_name')->value() ?: null,
            $request->string('last_name')->value() ?: null,
        );

        if ($result === null) {
            return ApiResponse::error(__('api.social.token_rejected'), status: 401);
        }

        return ApiResponse::success([
            'user'       => UserResource::make($result['user']),
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'abilities'  => $result['abilities'],
        ], __('api.auth.logged_in'));
    }

    /**
     * GET /api/v1/account/social-accounts
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'accounts' => $user->socialAccounts()->get()->map(static fn (SocialAccount $account): array => [
                'provider'      => $account->provider->value,
                'label'         => $account->provider->label(),
                'email'         => $account->email,
                'linked_at'     => $account->created_at?->toIso8601String(),
                'last_login_at' => $account->last_login_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * DELETE /api/v1/account/social-accounts/{provider}
     */
    public function destroy(Request $request, string $provider): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $resolved = SocialProvider::tryFrom($provider);

        if ($resolved === null) {
            return ApiResponse::error(__('api.social.unsupported'), status: 404);
        }

        $account = $user->socialAccounts()->where('provider', $resolved)->first();

        if ($account === null) {
            return ApiResponse::error(__('api.social.not_linked'), status: 404);
        }

        // Son bağlantıyı koparmak hesabı erişilemez bırakabilir: sosyal
        // girişle açılan hesabın şifresi rastgele ve kimse onu bilmiyor.
        //
        // Gerçek bir adresi olan kişi "şifremi unuttum" ile kendi şifresini
        // kurabildiği için kilitlenmiyor. Sağlayıcı adres vermediyse (Apple'ın
        // "adresimi gizle"si) yer tutucu bir adres üretilmiş oluyor, o adrese
        // mail gitmiyor ve kişinin başka kapısı kalmıyor — o durumda son
        // bağlantı koparılmıyor.
        $lastLink = $user->socialAccounts()->count() === 1;

        if ($lastLink && SocialAuthService::isPlaceholderEmail((string) $user->email)) {
            return ApiResponse::error(__('api.social.last_link'), status: 422);
        }

        $account->delete();

        return ApiResponse::success(null, __('api.social.unlinked'));
    }
}
