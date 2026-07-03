<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Google\GoogleMerchantFeedService;
use Illuminate\Http\Response;

/**
 * Public endpoint: GET /feeds/google-merchant.xml
 * Google Merchant Center "Scheduled Fetch" bu URL'i günde 1-2 kez çeker.
 * Cache 6 saat — sunucu yükü minimal.
 */
final class GoogleMerchantFeedController extends Controller
{
    public function __construct(
        private readonly GoogleMerchantFeedService $service,
    ) {}

    public function __invoke(): Response
    {
        $xml = $this->service->getOrBuildXml();

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=21600', // 6 saat (CDN/Google için)
            'X-Robots-Tag'  => 'noindex',               // Feed kendisi index'lenmesin
        ]);
    }
}
