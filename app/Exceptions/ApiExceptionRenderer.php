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

        $response = match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                // Zarfın `message` alanı kullanıcıya gösterilebilecek metin:
                // ilk hatayı oraya koymak, istemcinin `errors` içine hiç
                // bakmadan bir uyarı gösterebilmesini sağlıyor.
                (string) (collect($e->errors())->flatten()->first() ?? __('api.common.validation_failed')),
                $e->errors(),
                $e->status,
            ),

            $e instanceof AuthenticationException => ApiResponse::error(
                __('api.auth.unauthenticated'),
                status: 401,
            ),

            $e instanceof AccountDeactivatedException => ApiResponse::error(
                __('site.login.deactivated'),
                status: 403,
            ),

            $e instanceof AuthorizationException => ApiResponse::error(
                __('api.common.forbidden'),
                status: 403,
            ),

            // Rota model bağlama ile bulunamadığında bu tür geliyor; dışarıya
            // hangi modelin aranmadığını söylemenin bir anlamı yok.
            $e instanceof ModelNotFoundException,
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
     * HTTP istisnasının kendi metni yoksa duruma göre bir karşılık.
     */
    private static function httpMessage(HttpExceptionInterface&Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message !== '') {
            return $message;
        }

        return match ($e->getStatusCode()) {
            429 => __('api.common.too_many_requests'),
            503 => __('api.common.unavailable'),
            default => __('api.common.error'),
        };
    }
}
