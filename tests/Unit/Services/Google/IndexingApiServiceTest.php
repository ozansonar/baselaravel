<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Google;

use App\Models\IndexingRequest;
use App\Models\Setting;
use App\Services\Google\GoogleAuthService;
use App\Services\Google\IndexingApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class IndexingApiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_creates_pending_request(): void
    {
        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);

        $service = new IndexingApiService($auth);

        $request = $service->queue(
            url: 'https://example.com/urun/test',
            type: IndexingRequest::TYPE_UPDATED,
            relatedType: 'App\\Models\\Product',
            relatedId: 1,
        );

        $this->assertSame('https://example.com/urun/test', $request->url);
        $this->assertSame(IndexingRequest::STATUS_PENDING, $request->status);
        $this->assertSame(IndexingRequest::TYPE_UPDATED, $request->type);
    }

    public function test_queue_is_idempotent_within_24h(): void
    {
        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);

        $service = new IndexingApiService($auth);

        $first  = $service->queue('https://example.com/u/test', IndexingRequest::TYPE_UPDATED);
        $second = $service->queue('https://example.com/u/test', IndexingRequest::TYPE_UPDATED);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, IndexingRequest::count());
    }

    public function test_submit_marks_success_on_200(): void
    {
        Setting::setValue('google_indexing_enabled', '1');

        Http::fake([
            'indexing.googleapis.com/*' => Http::response(['urlNotificationMetadata' => ['url' => 'https://example.com/u']], 200),
        ]);

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);
        $auth->shouldReceive('accessToken')->andReturn('fake');

        $service = new IndexingApiService($auth);
        $request = $service->queue('https://example.com/u', IndexingRequest::TYPE_UPDATED);

        $service->submit($request);
        $request->refresh();

        $this->assertSame(IndexingRequest::STATUS_SUCCESS, $request->status);
        $this->assertNotNull($request->sent_at);
    }

    public function test_submit_marks_failed_on_4xx(): void
    {
        Setting::setValue('google_indexing_enabled', '1');

        Http::fake([
            'indexing.googleapis.com/*' => Http::response('Forbidden', 403),
        ]);

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);
        $auth->shouldReceive('accessToken')->andReturn('fake');

        $service = new IndexingApiService($auth);
        $request = $service->queue('https://example.com/u', IndexingRequest::TYPE_UPDATED);

        $service->submit($request);
        $request->refresh();

        $this->assertSame(IndexingRequest::STATUS_FAILED, $request->status);
        $this->assertStringContainsString('403', (string) $request->error_message);
    }

    public function test_submit_keeps_pending_on_disabled(): void
    {
        Setting::setValue('google_indexing_enabled', '0');

        $auth = Mockery::mock(GoogleAuthService::class);
        $auth->shouldReceive('isConfigured')->andReturn(true);

        $service = new IndexingApiService($auth);
        $request = $service->queue('https://example.com/u', IndexingRequest::TYPE_UPDATED);

        $service->submit($request);
        $request->refresh();

        $this->assertSame(IndexingRequest::STATUS_FAILED, $request->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
