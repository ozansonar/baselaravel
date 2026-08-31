<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationLevel;
use App\Models\AdminNotification;
use App\Models\Setting;
use App\Services\ExceptionNotifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * İşlenmeyen bir hata yöneticiye ulaşıyor mu?
 *
 * `withExceptions()` bloğu boştu: canlıda 500 veren bir sayfa yalnızca
 * `storage/logs` altına düşüyor, kimseye haber gitmiyordu. Projede çalışan bir
 * bildirim kanalı zaten vardı — yalnızca yedekleme ve birkaç servis onu
 * kullanıyordu.
 */
class ExceptionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function enableTelegram(): void
    {
        Setting::query()->upsert([
            ['key' => 'telegram_enabled', 'value' => '1'],
            ['key' => 'telegram_bot_token', 'value' => 'test-token'],
            ['key' => 'telegram_chat_id', 'value' => '12345'],
        ], ['key'], ['value']);

        Setting::clearSettingsCache();
    }

    private function notify(\Throwable $e): void
    {
        app(ExceptionNotifier::class)->notify($e);
    }

    public function test_an_unexpected_error_reaches_the_notification_centre(): void
    {
        $this->notify(new \RuntimeException('Veritabanı bağlantısı koptu'));

        $notification = AdminNotification::latest('id')->first();

        $this->assertNotNull($notification);
        $this->assertSame('exception', $notification->type);
        $this->assertSame(NotificationLevel::Critical, $notification->level);
        $this->assertStringContainsString('RuntimeException', $notification->title);
        $this->assertStringContainsString('Veritabanı bağlantısı koptu', (string) $notification->message);
    }

    public function test_the_message_says_where_the_error_happened(): void
    {
        $this->notify(new \RuntimeException('patladı'));

        $message = (string) AdminNotification::latest('id')->first()?->message;

        // Proje köküne göre yol: mutlak yol satıra sığmıyor ve hosting
        // kullanıcı adını mesaja taşıyor.
        $this->assertStringContainsString('tests/Feature/ExceptionNotificationTest.php:', $message);
        $this->assertStringNotContainsString(base_path(), $message);
    }

    public function test_telegram_is_told_when_it_is_switched_on(): void
    {
        $this->enableTelegram();

        $this->notify(new \RuntimeException('kuyruk düştü'));

        Http::assertSent(function ($request): bool {
            $body = (string) $request->body();

            return str_contains($request->url(), 'api.telegram.org')
                && str_contains($body, 'RuntimeException')
                && str_contains($body, 'kuyruk d');
        });
    }

    public function test_nothing_is_sent_to_telegram_while_it_is_switched_off(): void
    {
        $this->notify(new \RuntimeException('sessiz'));

        Http::assertNothingSent();
        $this->assertNotNull(AdminNotification::latest('id')->first());
    }

    /**
     * Sıcak bir sayfadaki döngüsel hata dakikada yüzlerce kez tekrar edebilir.
     */
    public function test_the_same_error_is_reported_once_per_window(): void
    {
        $error = new \RuntimeException('aynı hata');

        $this->notify($error);
        $this->notify($error);
        $this->notify($error);

        $this->assertSame(1, AdminNotification::where('type', 'exception')->count());
    }

    public function test_a_different_error_is_not_swallowed_by_the_throttle(): void
    {
        $this->notify(new \RuntimeException('birinci'));
        $this->notify(new \LogicException('ikinci'));

        $this->assertSame(2, AdminNotification::where('type', 'exception')->count());
    }

    /**
     * Bildirim yolu hata işlemenin içinde çalışıyor: buradan bir şey fırlarsa
     * asıl hatanın yerini alır ve loglanan şey yanlış olur.
     */
    public function test_a_failing_notification_channel_cannot_replace_the_original_error(): void
    {
        $this->enableTelegram();

        Http::fake([
            'api.telegram.org/*' => fn () => throw new \RuntimeException('Telegram ulaşılamıyor'),
        ]);

        $this->notify(new \RuntimeException('asıl hata'));

        $this->assertTrue(true, 'notify() sessizce döndü');
    }

    /**
     * Beklenen hatalar buraya hiç gelmemeli. Laravel bunları raporlamadan
     * önce eliyor; asıl doğrulanan şey bu eleme.
     */
    public function test_expected_http_exceptions_are_not_reported(): void
    {
        $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        foreach ([
            new NotFoundHttpException(),
            new AuthenticationException(),
            ValidationException::withMessages(['email' => 'geçersiz']),
        ] as $expected) {
            $handler->report($expected);
        }

        $this->assertSame(0, AdminNotification::where('type', 'exception')->count());
        Http::assertNothingSent();
    }

    /**
     * Uçtan uca: gerçek bir istek patladığında bildirim düşüyor mu?
     */
    public function test_a_request_that_blows_up_produces_a_notification(): void
    {
        Route::middleware('web')->get('/__boom', function (): void {
            throw new \RuntimeException('rota patladı');
        });

        $this->get('/__boom')->assertStatus(500);

        $notification = AdminNotification::where('type', 'exception')->latest('id')->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('rota patladı', (string) $notification->message);
    }

    /**
     * Bildirim logun yerine değil yanına geçiyor: raporlama kapanışı `false`
     * dönseydi hata dosyaya hiç yazılmazdı.
     */
    public function test_the_error_is_still_written_to_the_log(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'loga da yazılmalı'));

        app(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->report(new \RuntimeException('loga da yazılmalı'));
    }
}
