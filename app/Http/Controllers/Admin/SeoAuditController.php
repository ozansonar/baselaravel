<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeoAuditRequest;
use App\Services\Seo\SeoAuditor;
use Illuminate\Http\JsonResponse;

/**
 * Form ekranının denetim ucu.
 *
 * Yazar yazarken buraya soruyor: "şu an kaydetsem SEO açısından ne olurdu?"
 * Cevap kaydedilmiş kayıttan değil, formun o anki hâlinden üretiliyor — asıl
 * değeri bu. Kaydettikten sonra söylenen bir uyarı, düzeltmesi bir tur daha
 * gerektiren bir uyarıdır.
 */
final class SeoAuditController extends Controller
{
    public function __construct(
        private readonly SeoAuditor $auditor,
    ) {}

    public function __invoke(SeoAuditRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'report'  => $this->auditor->audit($request->subject())->toArray(),
        ]);
    }
}
