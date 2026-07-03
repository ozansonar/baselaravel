<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Google;

use App\Models\GoogleApiLog;
use App\Models\GscPageMetric;
use App\Models\GscQueryMetric;
use App\Models\Setting;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class SearchConsoleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_enabled_returns_false_when_setting_is_off(): void
    {
        Setting::setValue('google_gsc_enabled', '0');

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);

        $service = new SearchConsoleService($auth);

        $this->assertFalse($service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_service_account_missing(): void
    {
        Setting::setValue('google_gsc_enabled', '1');

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(false);

        $service = new SearchConsoleService($auth);

        $this->assertFalse($service->isEnabled());
    }

    public function test_fetch_and_store_queries_persists_response_rows(): void
    {
        Setting::setValue('google_gsc_enabled', '1');
        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
            'searchconsole.googleapis.com/*' => Http::response([
                'rows' => [
                    ['keys' => ['köy tereyağı'], 'clicks' => 127, 'impressions' => 4200, 'ctr' => 0.0302, 'position' => 4.2],
                    ['keys' => ['doğal peynir'], 'clicks' => 89,  'impressions' => 2100, 'ctr' => 0.0424, 'position' => 6.8],
                ],
            ], 200),
        ]);

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);
        $auth->shouldReceive('accessToken')->andReturn('fake-token');

        $service = new SearchConsoleService($auth);

        $result = $service->fetchAndStoreQueries(days: 28);

        $this->assertSame(2, $result['rows']);
        $this->assertSame(2, GscQueryMetric::count());

        $top = GscQueryMetric::orderByDesc('clicks')->first();
        $this->assertSame('köy tereyağı', $top->query);
        $this->assertSame(127, $top->clicks);
        $this->assertEquals(3.02, (float) $top->ctr);
        $this->assertEquals(4.2, (float) $top->position);
    }

    public function test_fetch_and_store_pages_resolves_page_types(): void
    {
        Setting::setValue('google_gsc_enabled', '1');
        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake', 'expires_in' => 3600], 200),
            'searchconsole.googleapis.com/*' => Http::response([
                'rows' => [
                    ['keys' => ['https://example.com/'],                        'clicks' => 342, 'impressions' => 12000, 'ctr' => 0.0285, 'position' => 5.2],
                    ['keys' => ['https://example.com/urun/koy-tereyagi'],       'clicks' => 189, 'impressions' => 4200,  'ctr' => 0.045,  'position' => 3.1],
                    ['keys' => ['https://example.com/istanbul-koy-urunleri'],   'clicks' => 87,  'impressions' => 2100,  'ctr' => 0.0414, 'position' => 8.4],
                    ['keys' => ['https://example.com/blog/genel/test-yazi'],    'clicks' => 54,  'impressions' => 1800,  'ctr' => 0.030,  'position' => 11.2],
                ],
            ], 200),
        ]);

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);
        $auth->shouldReceive('accessToken')->andReturn('fake');

        $service = new SearchConsoleService($auth);

        $result = $service->fetchAndStorePages(days: 28);

        $this->assertSame(4, $result['rows']);
        $this->assertSame(4, GscPageMetric::count());

        $home = GscPageMetric::where('page_path', '/')->first();
        $this->assertSame(GscPageMetric::TYPE_HOME, $home->page_type);

        $product = GscPageMetric::where('page_path', '/urun/koy-tereyagi')->first();
        $this->assertSame(GscPageMetric::TYPE_PRODUCT, $product->page_type);

        $city = GscPageMetric::where('page_path', '/istanbul-koy-urunleri')->first();
        $this->assertSame(GscPageMetric::TYPE_CITY, $city->page_type);

        $blog = GscPageMetric::where('page_path', '/blog/genel/test-yazi')->first();
        $this->assertSame(GscPageMetric::TYPE_BLOG, $blog->page_type);
    }

    public function test_non_retriable_error_writes_to_google_api_logs(): void
    {
        Setting::setValue('google_gsc_enabled', '1');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake', 'expires_in' => 3600], 200),
            'searchconsole.googleapis.com/*' => Http::response('Forbidden', 403),
        ]);

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);
        $auth->shouldReceive('accessToken')->andReturn('fake');

        $service = new SearchConsoleService($auth);

        $this->expectException(\RuntimeException::class);

        try {
            $service->fetchAndStoreQueries(days: 1, limit: 5);
        } finally {
            $this->assertGreaterThanOrEqual(1, GoogleApiLog::where('status', 'failed')->count());
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
