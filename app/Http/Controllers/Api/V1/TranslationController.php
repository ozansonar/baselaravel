<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Arayüz metinleri.
 *
 * Mobil uygulama kendi metinlerini gömmek yerine buradan okuyabiliyor: panelden
 * değiştirilen bir yazı mağaza güncellemesi beklemeden yerine oturuyor.
 *
 * Değerler dosya varsayılanının üstüne panel düzenlemeleri serilmiş hâliyle
 * geliyor ({@see TranslationService::effectiveLines()}) — yani ekranda ne
 * görünüyorsa o. Anahtarlar düz: "nav.home" => "Anasayfa".
 */
final class TranslationController extends Controller
{
    public function __construct(
        private readonly TranslationService $translations,
    ) {}

    /**
     * GET /api/v1/translations
     * GET /api/v1/translations?group=site
     */
    public function index(Request $request): JsonResponse
    {
        /** @var array<int, string> $allowed */
        $allowed = (array) config('api.public_translation_groups', []);

        $requested = $request->query('group');

        if (is_string($requested) && $requested !== '') {
            if (! in_array($requested, $allowed, true)) {
                return ApiResponse::error(__('api.translations.group_not_found'), status: 404);
            }

            $allowed = [$requested];
        }

        $locale = app()->getLocale();

        $lines = [];

        foreach ($allowed as $group) {
            $lines[$group] = $this->translations->effectiveLines($locale, $group);
        }

        return ApiResponse::success([
            'locale' => $locale,
            'groups' => $lines,
        ]);
    }
}
