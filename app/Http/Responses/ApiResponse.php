<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Her API yanıtının aynı zarfı taşıması için tek yer.
 *
 * Zarf üç anahtardan ibaret: `success` istemcinin tek bakışta karar vermesi
 * için, `message` kullanıcıya gösterilebilecek metin, `data` ise yükün kendisi.
 * Hata tarafında `data` yerine `errors` var — alan bazlı doğrulama hataları
 * oraya düşer.
 *
 * `data` başarıda, `errors` hatada HER ZAMAN bulunur (içi boşken bile). Mobil
 * istemci tipli bir modele ayrıştırırken "bazen olan" anahtar, olmayan
 * anahtardan pahalıdır: null denetimi tek yerde kalsın diye şekil sabit.
 */
final class ApiResponse
{
    /**
     * @param array<string, mixed> $extra Zarfa eklenecek üst düzey anahtarlar
     *                                    (sayfalama `meta`/`links` gibi).
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $extra = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('api.common.ok'),
            'data'    => $data,
            ...$extra,
        ], $status);
    }

    /**
     * 201 — yeni bir kayıt oluştu.
     */
    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function error(
        string $message,
        array $errors = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            // Boş dizi JSON'da `[]` olur, doluyken `{}`. İstemci aynı alanı iki
            // ayrı tipte görmesin diye nesneye zorlanıyor.
            'errors'  => (object) $errors,
        ], $status);
    }

    /**
     * Sayfalı liste — veri, sayaçlar ve gezinme bağlantıları ayrı anahtarlarda.
     *
     * Laravel'in kendi sayfalama zarfı (`links` içinde her sayfa için bir satır,
     * `meta` içinde `path`) mobil istemciye yaramıyor: onun ihtiyacı "kaçıncı
     * sayfadayım, daha var mı" ve bir sonraki adresten ibaret.
     *
     * @param LengthAwarePaginator<int, covariant mixed> $paginator
     * @param class-string<JsonResource> $resource
     * @param array<string, mixed> $extra Zarfa eklenecek üst düzey anahtarlar
     *                                    (arama ucunun tür sayaçları gibi).
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $resource,
        ?string $message = null,
        array $extra = [],
    ): JsonResponse {
        return self::success(
            $resource::collection($paginator->getCollection()),
            $message,
            200,
            [
                ...$extra,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                    'has_more'     => $paginator->hasMorePages(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last'  => $paginator->url($paginator->lastPage()),
                    'prev'  => $paginator->previousPageUrl(),
                    'next'  => $paginator->nextPageUrl(),
                ],
            ],
        );
    }
}
