<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MailLogStatus;
use App\Mail\WelcomeMail;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailService;
use App\Services\MailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Mail is the part of the base kit that fails quietly.
 *
 * The panel owns the templates and the log, so a broken render or a log that
 * never leaves "pending" looks like nothing happened at all — no exception, no
 * failed request, just an email the user never received.
 */
class MailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function recipient(): User
    {
        return User::factory()->create([
            'first_name' => 'Ahmet',
            'last_name'  => 'Yılmaz',
            'email'      => 'ahmet@example.com',
        ]);
    }

    // ── Şablon render'ı ──

    public function test_a_template_replaces_its_variables(): void
    {
        MailTemplate::create([
            'key'       => 'testing',
            'name'      => 'Test',
            'subject'   => '{site_name} — Hoş geldiniz {user_name}',
            'body'      => '<p>Merhaba {user_name}, {site_name} ailesine katıldınız.</p>',
            'variables' => [],
            'is_active' => true,
        ]);

        $rendered = MailTemplate::render('testing', [
            'user_name' => 'Ahmet Yılmaz',
            'site_name' => 'Acme',
        ]);

        $this->assertNotNull($rendered);
        $this->assertSame('Acme — Hoş geldiniz Ahmet Yılmaz', $rendered['subject']);
        $this->assertStringContainsString('Merhaba Ahmet Yılmaz', $rendered['body']);
        $this->assertStringNotContainsString('{user_name}', $rendered['body'], 'Değiştirilmemiş yer tutucu kaldı');
    }

    /**
     * A missing variable must not leave "{verification_url}" in the body the
     * user reads.
     */
    public function test_a_missing_variable_is_not_left_as_a_placeholder(): void
    {
        MailTemplate::create([
            'key'       => 'testing',
            'name'      => 'Test',
            'subject'   => 'Konu',
            'body'      => '<p>{user_name} / {eksik_degisken}</p>',
            'variables' => [],
            'is_active' => true,
        ]);

        $rendered = MailTemplate::render('testing', ['user_name' => 'Ahmet', 'eksik_degisken' => null]);

        $this->assertNotNull($rendered);
        $this->assertStringNotContainsString('{eksik_degisken}', $rendered['body']);
    }

    public function test_an_inactive_template_does_not_render(): void
    {
        MailTemplate::create([
            'key'       => 'testing',
            'name'      => 'Test',
            'subject'   => 'Konu',
            'body'      => 'Gövde',
            'variables' => [],
            'is_active' => false,
        ]);

        $this->assertNull(MailTemplate::render('testing', []));
    }

    public function test_an_unknown_template_key_renders_nothing(): void
    {
        $this->assertNull(MailTemplate::render('boyle-bir-sablon-yok', []));
    }

    /**
     * The mail falls back to its Blade view when the panel has no template, so
     * a freshly cloned project still sends something readable.
     */
    public function test_a_mail_without_a_db_template_falls_back_to_its_blade_view(): void
    {
        MailTemplate::where('key', 'welcome')->delete();

        $html = (new WelcomeMail($this->recipient()))->render();

        $this->assertStringContainsString('Ahmet', $html);
    }

    public function test_a_mail_prefers_the_panel_template_over_the_blade_view(): void
    {
        MailTemplate::updateOrCreate(
            ['key' => 'welcome', 'locale' => app()->getLocale()],
            [
                'name'      => 'Hoş Geldiniz',
                'subject'   => 'Panelden gelen konu',
                'body'      => '<p>Panelden gelen gövde: {user_name}</p>',
                'variables' => [],
                'is_active' => true,
            ],
        );

        $html = (new WelcomeMail($this->recipient()))->render();

        $this->assertStringContainsString('Panelden gelen gövde: Ahmet Yılmaz', $html);
    }

    /**
     * Collapse whitespace so the comparison is about the words in the mail, not
     * how the HTML happens to be indented.
     */
    private function normalize(string $html): string
    {
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/>\s+/', '>', $html);
        $html = (string) preg_replace('/\s+</', '<', $html);

        return trim($html);
    }

    /**
     * The reset button regressed the welcome template to e-commerce copy once,
     * because the service defaults and the migration seed had drifted apart.
     * Every shipped template is checked, not just the one that broke.
     */
    #[DataProvider('shippedTemplateKeys')]
    public function test_resetting_a_template_restores_the_shipped_content(string $key): void
    {
        $template = MailTemplate::where('key', $key)->firstOrFail();
        $original = $template->body;
        $originalSubject = $template->subject;

        $template->update(['body' => '<p>Elle bozulmuş içerik</p>', 'subject' => 'Bozuk konu']);

        $restored = app(MailTemplateService::class)->resetToDefault($template->fresh());

        $this->assertSame($originalSubject, $restored->subject, "{$key}: varsayılana dönüş konuyu geri getirmedi");
        $this->assertSame(
            $this->normalize($original),
            $this->normalize($restored->body),
            "{$key}: varsayılana dönüş gövdeyi geri getirmedi",
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function shippedTemplateKeys(): array
    {
        $keys = ['test', 'welcome', 'verify_email', 'reset_password', 'contact_message', 'contact_reply'];

        return array_combine($keys, array_map(static fn (string $k): array => [$k], $keys));
    }

    /**
     * A mail that names a template the panel never seeded can never be worded
     * from the panel — it silently falls back to its Blade view forever. That
     * is how contact_reply went missing.
     */
    public function test_every_mail_template_key_has_a_row_in_the_panel(): void
    {
        $missing = [];

        foreach (glob(app_path('Mail') . '/*.php') ?: [] as $file) {
            $class = 'App\\Mail\\' . basename($file, '.php');

            if (! class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            // Instantiate the mail class itself, not wherever templateKey() is
            // declared — a mail that does not override it would resolve to the
            // abstract base.
            $method = new \ReflectionMethod($class, 'templateKey');
            $key = $method->invoke((new \ReflectionClass($class))->newInstanceWithoutConstructor());

            if ($key !== null && ! MailTemplate::where('key', $key)->exists()) {
                $missing[] = class_basename($class) . " → {$key}";
            }
        }

        $this->assertSame([], $missing, 'Panelde karşılığı olmayan mail şablonu: ' . implode(', ', $missing));
    }

    public function test_no_shipped_template_mentions_orders_or_products(): void
    {
        $offenders = [];

        foreach (MailTemplate::all() as $template) {
            $haystack = mb_strtolower($template->subject . ' ' . $template->body);

            foreach (['sipariş', 'ürün', 'kargo', 'sepet'] as $word) {
                if (str_contains($haystack, $word)) {
                    $offenders[] = "{$template->key}: {$word}";
                }
            }
        }

        $this->assertSame([], $offenders, 'E-ticaret metni kalmış: ' . implode(', ', $offenders));
    }

    // ── Gönderim ve loglama ──

    public function test_a_sent_mail_reaches_the_recipient(): void
    {
        Mail::fake();

        $user = $this->recipient();

        $this->assertTrue(app(MailService::class)->send($user->email, new WelcomeMail($user)));

        Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('ahmet@example.com'));
    }

    public function test_a_sent_mail_is_written_to_the_log(): void
    {
        Mail::fake();

        $user = $this->recipient();
        app(MailService::class)->send($user->email, new WelcomeMail($user));

        $log = MailLog::where('to', 'ahmet@example.com')->firstOrFail();

        $this->assertSame(MailLogStatus::Sent, $log->status);
        $this->assertSame(WelcomeMail::class, $log->mailable_class);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error_message);
    }

    /**
     * The log stores the rendered body so the panel can show and resend it.
     */
    public function test_the_log_keeps_the_rendered_body(): void
    {
        Mail::fake();

        $user = $this->recipient();
        app(MailService::class)->send($user->email, new WelcomeMail($user));

        $log = MailLog::where('to', 'ahmet@example.com')->firstOrFail();

        $this->assertNotNull($log->body);
        $this->assertStringContainsString('Ahmet', (string) $log->body);
    }

    /**
     * A transport failure must not bubble up into the request — the caller gets
     * false and the reason lands in the log.
     */
    public function test_a_failed_send_is_logged_rather_than_thrown(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP bağlantısı kurulamadı'));

        $user = $this->recipient();

        $this->assertFalse(app(MailService::class)->send($user->email, new WelcomeMail($user)));

        $log = MailLog::where('to', 'ahmet@example.com')->firstOrFail();

        $this->assertSame(MailLogStatus::Failed, $log->status);
        $this->assertStringContainsString('SMTP', (string) $log->error_message);
        $this->assertNull($log->sent_at);
    }

    public function test_a_queued_mail_is_logged_as_pending(): void
    {
        Mail::fake();

        $user = $this->recipient();

        $this->assertTrue(app(MailService::class)->queue($user->email, new WelcomeMail($user)));

        Mail::assertQueued(WelcomeMail::class);

        $log = MailLog::where('to', 'ahmet@example.com')->firstOrFail();
        $this->assertSame(MailLogStatus::Pending, $log->status);
        $this->assertNull($log->sent_at);
    }

    /**
     * The listener finds its row through a header on the message; without it a
     * queued mail would sit at "pending" forever even after being delivered.
     */
    public function test_a_queued_mail_carries_its_log_id_as_a_header(): void
    {
        Mail::fake();

        $user = $this->recipient();
        app(MailService::class)->queue($user->email, new WelcomeMail($user));

        $log = MailLog::where('to', 'ahmet@example.com')->firstOrFail();

        Mail::assertQueued(
            WelcomeMail::class,
            fn (WelcomeMail $mail): bool => $mail->mailLogId === $log->id,
        );
    }

    public function test_a_raw_mail_is_sent_and_logged(): void
    {
        Mail::fake();

        app(MailService::class)->sendRaw('test@example.com', 'Test Konusu', 'Test gövdesi');

        $log = MailLog::where('to', 'test@example.com')->firstOrFail();

        $this->assertSame(MailLogStatus::Sent, $log->status);
        $this->assertSame('Test Konusu', $log->subject);
        $this->assertSame('Test gövdesi', $log->body);
    }

    // ── Yeniden gönderim ──

    public function test_a_logged_mail_can_be_resent(): void
    {
        Mail::fake();

        $log = MailLog::create([
            'to'      => 'ahmet@example.com',
            'subject' => 'Orijinal Konu',
            'body'    => '<p>Kayıtlı gövde</p>',
            'status'  => MailLogStatus::Sent,
        ]);

        $this->assertTrue(app(MailService::class)->resendFromLog($log));

        $resent = MailLog::where('subject', '[Yeniden] Orijinal Konu')->firstOrFail();
        $this->assertSame(MailLogStatus::Sent, $resent->status);
        $this->assertSame(['resent_from' => $log->id], $resent->metadata);
    }

    public function test_a_log_without_a_body_cannot_be_resent(): void
    {
        $log = MailLog::create([
            'to'      => 'ahmet@example.com',
            'subject' => 'Gövdesiz',
            'body'    => null,
            'status'  => MailLogStatus::Sent,
        ]);

        $this->expectException(RuntimeException::class);

        app(MailService::class)->resendFromLog($log);
    }

    // ── Tema ayarları ──

    /**
     * Mail theming is a panel setting, which is the whole point of having it in
     * the base kit — it should not be rebuilt per project.
     */
    public function test_the_panel_theme_colour_reaches_the_rendered_mail(): void
    {
        Setting::setValue('mail_theme_primary_color', '#ff0066');
        MailTemplate::where('key', 'welcome')->delete();

        $html = (new WelcomeMail($this->recipient()))->render();

        $this->assertStringContainsString('#ff0066', $html);
    }

    public function test_the_site_name_setting_reaches_the_rendered_mail(): void
    {
        Setting::setValue('site_name', 'Acme Kurumsal');
        MailTemplate::where('key', 'welcome')->delete();

        $html = (new WelcomeMail($this->recipient()))->render();

        $this->assertStringContainsString('Acme Kurumsal', $html);
    }
}
