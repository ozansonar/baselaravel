<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * API hatalarını tek bir zarfa çevirir.
 *
 * Laravel'in kendi JSON hataları üç ayrı şekilde geliyor: doğrulamada
 * `{message, errors}`, kimlikte `{message}`, 500'de hata ayıklama açıkken
 * yığın izi. Mobil istemcinin bunların hepsini ayrı ayrı tanıması gerekirdi —
 * ve biri değiştiğinde uygulamanın mağazadan güncellenmesi.
 *
 * Burada hepsi `{success:false, message, errors}` oluyor: istemci tek bir
 * ayrıştırıcıyla, `success` alanına bakarak karar veriyor.
 *
 * Durum kodu korunuyor. 401 ile 422'yi aynı gövdeye sokup ayrımı sadece metne
 * bırakmak, "şifreni yenile" ile "e-posta alanı boş" arasındaki farkı
 * istemcinin metin karşılaştırmasıyla bulmasını istemek olurdu.
 */
final class ApiExceptionRenderer
{
    /**
     * İstek API'ye mi geldi?
     *
     * Yalnız /api/v1 altı: web tarafındaki /api/analytics/track de "api" ile
     * başlıyor ama o ön yüzün kendi ucu ve kendi yanıt şekli var.
     */
    public static function handles(Request $request): bool
    {
        $prefix = trim((string) config('api.prefix', 'api/v1'), '/');

        return $request->is($prefix) || $request->is($prefix . '/*');
    }

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::handles($request)) {
            return null;
        }

        // Çerçeve, kapanışlarımız çalışmadan ÖNCE bazı istisnaları HTTP
        // istisnasına çeviriyor (Handler::prepareException): yetkilendirme
        // hatası AccessDeniedHttpException oluyor, model bulunamadı
        // NotFoundHttpException. Aslını `getPrevious()` içinde taşıyor.
        //
        // Sınıflandırma bu yüzden asıl istisna üzerinden yapılıyor; yalnız
        // sarmalayana bakılsaydı bütün bu durumlar aşağıdaki genel dala düşer
        // ve çerçevenin İngilizce sabit metni ("Invalid ability provided.")
        // ziyaretçiye olduğu gibi giderdi.
        $original = $e instanceof HttpExceptionInterface && $e->getPrevious() !== null
            ? $e->getPrevious()
            : $e;

        $response = match (true) {
            $original instanceof ValidationException => ApiResponse::error(
                // Zarfın `message` alanı kullanıcıya gösterilebilecek metin:
                // ilk hatayı oraya koymak, istemcinin `errors` içine hiç
                // bakmadan bir uyarı gösterebilmesini sağlıyor.
                (string) (collect($original->errors())->flatten()->first() ?? __('api.common.validation_failed')),
                $original->errors(),
                $original->status,
            ),

            $original instanceof AuthenticationException => ApiResponse::error(
                __('api.auth.unauthenticated'),
                status: 401,
            ),

            $original instanceof AccountDeactivatedException => ApiResponse::error(
                __('site.login.deactivated'),
                status: 403,
            ),

            // Sosyal sağlayıcı adresi doğrulamamış ve aynı adresle bir hesap
            // zaten var. Bağlamak hesap devralma, yeni hesap açmak da yanlış
            // olurdu; istemcinin yapması gereken kişiyi şifreyle giriş
            // ekranına yönlendirmek — o yüzden 401 değil, kendi kodu var.
            $original instanceof EmailNotVerifiedBySocialProviderException => ApiResponse::error(
                __('api.social.email_unverified', ['provider' => $original->provider->label()]),
                ['id_token' => ['email_unverified']],
                409,
            ),

            // Şifre doğru, ikinci adım eksik. 401'den ayrı bir kod taşıyor
            // çünkü istemcinin yapacağı şey farklı: kişiyi giriş ekranına
            // geri göndermek değil, kod ekranını açıp aynı isteği `code` ile
            // tekrarlamak.
            $original instanceof TwoFactorRequiredException => ApiResponse::error(
                $original->invalidCode
                    ? __('site.two_factor.invalid_code')
                    : __('api.auth.two_factor_required'),
                [
                    'code' => ['two_factor_required'],
                    'two_factor_required' => [true],
                ],
                403,
            ),

            // AuthorizationException'ın alt türü, o yüzden ondan ÖNCE.
            // Genel 403'ten ayrılıyor çünkü istemcinin yapması gereken şey
            // farklı: yetkisi olan bir jetonla yeniden giriş yapmak.
            $original instanceof MissingAbilityException => ApiResponse::error(
                __('api.auth.missing_ability'),
                [
                    'code'      => ['missing_ability'],
                    'abilities' => $original->abilities(),
                ],
                403,
            ),

            $original instanceof AuthorizationException => ApiResponse::error(
                __('api.common.forbidden'),
                status: 403,
            ),

            // Rota model bağlama ile bulunamadığında bu tür geliyor; dışarıya
            // hangi modelin aranmadığını söylemenin bir anlamı yok.
            $original instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error(
                __('api.common.not_found'),
                status: 404,
            ),

            $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                __('api.common.method_not_allowed'),
                status: 405,
            ),

            // Çerçevenin kendi metni İngilizce sabit ("Too Many Attempts.");
            // ziyaretçinin dilinde bir uyarı görmesi gerekiyor.
            $e instanceof ThrottleRequestsException => ApiResponse::error(
                __('api.common.too_many_requests'),
                status: 429,
            ),

            $e instanceof HttpExceptionInterface => ApiResponse::error(
                self::httpMessage($e),
                status: $e->getStatusCode(),
            ),

            // Beklenmedik hata. Mesaj yalnız hata ayıklama açıkken dışarı
            // çıkıyor: canlıda bir istisna metni veritabanı adını, dosya
            // yolunu ve sorgunun kendisini taşıyabiliyor.
            default => ApiResponse::error(
                config('app.debug') === true
                    ? $e->getMessage()
                    : __('api.common.server_error'),
                status: 500,
            ),
        };

        // 429'un `Retry-After`ı ve 405'in `Allow`ı yanıtın parçası: istemci ne
        // zaman yeniden deneyeceğini bunlardan öğreniyor.
        if ($e instanceof HttpExceptionInterface) {
            $response->withHeaders($e->getHeaders());
        }

        return $response;
    }

    /**
     * Genel bir HTTP istisnasının dışarı çıkacak metni.
     *
     * Kendi metni yalnız istisna BİLEREK fırlatıldıysa yansıtılıyor — yani
     * `abort(403, 'Şu yüzden olmaz')` gibi, bir insanın ziyaretçi için yazdığı
     * cümle. Sarmalanmış bir istisnada (`getPrevious()` dolu) metin çerçevenin
     * ya da bir kütüphanenin iç metnidir: İngilizce sabit, çoğu zaman anlamsız,
     * bazen sınıf adı ya da sorgu taşır. Onun yerine duruma göre bizim
     * metnimiz gidiyor.
     */
    private static function httpMessage(HttpExceptionInterface&Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message !== '' && $e->getPrevious() === null) {
            return $message;
        }

        return match ($e->getStatusCode()) {
            403 => __('api.common.forbidden'),
            404 => __('api.common.not_found'),
            429 => __('api.common.too_many_requests'),
            503 => __('api.common.unavailable'),
            default => __('api.common.error'),
        };
    }
}
