<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmPasswordRequest;
use App\Http\Requests\Api\V1\ConfirmTwoFactorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * İki adımlı doğrulamanın kurulumu — API tarafı.
 *
 * Girişin ikinci adımı zaten API'de vardı ({@see AuthController::login}), ama
 * **kurulum** yalnız web'deydi: yalnız mobil uygulamadan giren bir kullanıcı
 * 2FA'yı hiç açamıyordu, açmak için tarayıcı bulup siteye girmesi gerekiyordu.
 * Bu sınıf o boşluğu kapatıyor.
 *
 * Akış web'dekiyle birebir aynı ve aynı servisten geçiyor
 * ({@see \App\Services\TwoFactorService}) — iki yüz ayrı mantık yazsaydı biri
 * ötekinden sapardı ve sapma ancak bir kullanıcı kilitlendiğinde görünürdü.
 *
 * Kurulum **iki isteğe** bölünmüş: önce anahtar üretilip QR veriliyor, sonra
 * kullanıcının girdiği ilk kod doğrulanınca açılıyor. Tek istekte açılsaydı
 * QR'ı okutmayı beceremeyen kişi kendi hesabından kilitlenirdi.
 */
final class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    /**
     * GET /api/v1/account/two-factor
     *
     * Uygulamanın güvenlik ekranını çizmesi için durum. Anahtarın kendisi
     * burada dönmüyor: yalnız kurulumu başlatan istek onu görüyor.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'enabled' => $user->hasTwoFactorEnabled(),
            // Kurulum yarıda kalmış olabilir: anahtar var ama ilk kod
            // girilmemiş. Uygulama bunu bilmezse kullanıcıyı baştan
            // başlatır ve okuttuğu QR geçersiz olur.
            'pending'      => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
            // Kaç kurtarma kodu kaldığı; kullanıcı tükenmeden yenileyebilsin.
            'recovery_codes_remaining' => count($user->two_factor_recovery_codes ?? []),
            // Zorunluluk açıkken kapatma ucu reddediyor; uygulama düğmeyi
            // baştan göstermesin diye burada da söyleniyor.
            'required' => $this->twoFactor->requiredForAdmins()
                && $user->hasAnyRole(['admin', 'editor', 'moderator']),
        ]);
    }

    /**
     * POST /api/v1/account/two-factor
     *
     * Kurulumu başlatır: anahtar üretilir ama doğrulama **açılmaz**.
     *
     * Yanıt üç biçim birden taşıyor. `otpauth_uri` uygulamanın kimlik
     * doğrulayıcıyı doğrudan açması için, `secret` kareyi okutamayan
     * kullanıcının elle girmesi için, `qr_svg` ise ekranda kare gösterebilmek
     * için — kullanıcı çoğu zaman kodu *başka* bir cihazdaki uygulamayla
     * okutuyor ve o durumda uygulamanın kareyi kendisi çizmesi gerekiyor.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('api.two_factor.already_enabled'), status: 409);
        }

        // Yarıda kalmış bir kurulum varsa anahtar yenileniyor: eski QR'ı
        // okutmuş olan kişi zaten onu doğrulayamamış demektir ve iki
        // anahtardan hangisinin geçerli olduğu belirsiz kalmamalı.
        $secret = $this->twoFactor->beginSetup($user);

        return ApiResponse::success([
            'secret'      => $secret,
            'otpauth_uri' => $this->twoFactor->otpauthUri($user, $secret),
            'qr_svg'      => $this->twoFactor->qrCodeSvg($user, $secret),
        ], __('api.two_factor.setup_started'));
    }

    /**
     * POST /api/v1/account/two-factor/confirm
     *
     * Kurulumu tamamlar. Kod yanlışsa hiçbir şey değişmiyor ve kullanıcı
     * yeniden deneyebiliyor.
     *
     * Kurtarma kodları **yalnız burada** ve yenileme ucunda dönüyor; her
     * istekte dönselerdi ele geçirilen bir jeton onları istediği zaman
     * okuyabilirdi.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('api.two_factor.already_enabled'), status: 409);
        }

        if ($user->two_factor_secret === null) {
            return ApiResponse::error(__('api.two_factor.not_started'), status: 409);
        }

        $codes = $this->twoFactor->confirm($user, $request->string('code')->value());

        if ($codes === null) {
            return ApiResponse::error(
                __('site.two_factor.invalid_code'),
                ['code' => [__('site.two_factor.invalid_code')]],
                422,
            );
        }

        return ApiResponse::success(
            ['recovery_codes' => $codes],
            __('site.two_factor.enabled'),
        );
    }

    /**
     * DELETE /api/v1/account/two-factor
     *
     * Kapatma — şifre onaylı.
     */
    public function destroy(ConfirmPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('api.two_factor.not_enabled'), status: 409);
        }

        // Zorunluluk açıkken yönetici kendi ikinci adımını kaldıramıyor:
        // kaldırabilseydi ayar bir kural değil, bir öneri olurdu. Web tarafı
        // da aynı yerde duruyor.
        if ($this->twoFactor->requiredForAdmins() && $user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return ApiResponse::error(
                __('site.two_factor.required_by_admin'),
                ['password' => [__('site.two_factor.required_by_admin')]],
                422,
            );
        }

        $this->twoFactor->disable($user);

        return ApiResponse::success(null, __('site.two_factor.disabled'));
    }

    /**
     * POST /api/v1/account/two-factor/recovery-codes
     *
     * Kurtarma kodlarını yeniler — şifre onaylı. Eski liste geçersiz oluyor.
     */
    public function regenerateRecoveryCodes(ConfirmPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('api.two_factor.not_enabled'), status: 409);
        }

        return ApiResponse::success(
            ['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)],
            __('site.two_factor.codes_regenerated'),
        );
    }
}
