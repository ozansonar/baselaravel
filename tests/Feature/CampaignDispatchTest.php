<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignAudience;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\SubscriberStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * The sending engine.
 *
 * Bulk mail fails in ways nothing shouts about: a burst that gets the account
 * throttled, a list sent twice, one bad address stopping the rest. Everything
 * here is a guard against one of those.
 */
class CampaignDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Setting::setValue('mail_hourly_limit', '100');
        Setting::setValue('mail_batch_max', '0');
    }

    private function campaignWith(int $recipientCount, array $attributes = []): Campaign
    {
        $campaign = Campaign::factory()->create(array_merge([
            'status'           => CampaignStatus::Sending,
            'started_at'       => now(),
            'total_recipients' => $recipientCount,
        ], $attributes));

        for ($i = 1; $i <= $recipientCount; $i++) {
            CampaignRecipient::factory()->create([
                'campaign_id' => $campaign->id,
                'email'       => "kisi{$i}@ornek.com",
                'first_name'  => 'Kişi',
                'last_name'   => (string) $i,
            ]);
        }

        return $campaign->refresh();
    }

    // ── Kota matematiği ──

    /**
     * The rule the whole feature exists for: 100 an hour, twelve runs an hour,
     * so a run sends about eight — not all hundred the moment it starts.
     */
    public function test_a_run_sends_the_hourly_limit_divided_by_the_runs_in_an_hour(): void
    {
        $dispatcher = app(CampaignDispatcher::class);

        $this->assertSame(100, $dispatcher->hourlyLimit());
        $this->assertSame(9, $dispatcher->perRunQuota(), '100 / (60/5) yukarı yuvarlanmalı');

        Setting::setValue('mail_hourly_limit', '120');
        $this->assertSame(10, app(CampaignDispatcher::class)->perRunQuota());

        Setting::setValue('mail_hourly_limit', '6');
        $this->assertSame(1, app(CampaignDispatcher::class)->perRunQuota(), 'Kota hiç sıfıra düşmemeli');
    }

    public function test_one_run_does_not_empty_the_whole_list(): void
    {
        $campaign = $this->campaignWith(30);

        $result = app(CampaignDispatcher::class)->tick();

        $this->assertSame(9, $result['sent']);
        $this->assertSame(21, $campaign->refresh()->pendingCount(), 'Tek turda liste boşaltıldı');
        Mail::assertSentCount(9);
    }

    public function test_successive_runs_drain_the_list(): void
    {
        $campaign = $this->campaignWith(30);
        $dispatcher = app(CampaignDispatcher::class);

        $dispatcher->tick();
        $dispatcher->tick();
        $dispatcher->tick();

        // Three runs at nine each.
        $this->assertSame(27, $campaign->refresh()->sent_count);
        $this->assertSame(3, $campaign->pendingCount());
    }

    /**
     * The ceiling is enforced from what actually went out, so a cron that fires
     * twice in the same minute cannot double the hour's volume.
     */
    public function test_the_hourly_ceiling_holds_however_often_the_cron_fires(): void
    {
        Setting::setValue('mail_hourly_limit', '10');
        $campaign = $this->campaignWith(50);
        $dispatcher = app(CampaignDispatcher::class);

        for ($i = 0; $i < 20; $i++) {
            $dispatcher->tick();
        }

        $this->assertSame(10, $campaign->refresh()->sent_count, 'Saatlik limit aşıldı');
        $this->assertSame(0, $dispatcher->remainingBudget());
    }

    public function test_the_budget_frees_up_as_the_hour_rolls_forward(): void
    {
        Setting::setValue('mail_hourly_limit', '10');
        // A batch size equal to the limit isolates the rolling window as the
        // only thing under test; otherwise the per-run quota (1) dominates.
        Setting::setValue('mail_batch_max', '10');

        $campaign = $this->campaignWith(30);
        $dispatcher = app(CampaignDispatcher::class);

        $dispatcher->tick();
        $dispatcher->tick();
        $this->assertSame(10, $campaign->refresh()->sent_count, 'İkinci tur limiti aştı');

        // Push the delivered rows out of the rolling window.
        CampaignRecipient::where('status', CampaignRecipientStatus::Sent)
            ->update(['sent_at' => now()->subHours(2)]);

        $this->assertSame(10, $dispatcher->remainingBudget(), 'Pencere kayınca kota serbest kalmalı');
        $dispatcher->tick();
        $this->assertSame(20, $campaign->refresh()->sent_count);
    }

    public function test_an_unthrottled_campaign_skips_the_smoothing_but_not_the_ceiling(): void
    {
        Setting::setValue('mail_hourly_limit', '25');
        $campaign = $this->campaignWith(40, ['throttled' => false]);

        $result = app(CampaignDispatcher::class)->tick();

        $this->assertSame(25, $result['sent'], 'Yayma kapalıyken tur kotası uygulanmamalı');
        $this->assertSame(15, $campaign->refresh()->pendingCount(), 'Saatlik limit yine de geçerli');
    }

    public function test_a_zero_limit_stops_sending_entirely(): void
    {
        Setting::setValue('mail_hourly_limit', '0');
        $this->campaignWith(10);

        $this->assertSame(
            ['sent' => 0, 'failed' => 0, 'retrying' => 0, 'budget' => 0, 'campaigns' => 0],
            app(CampaignDispatcher::class)->tick(),
        );
        Mail::assertNothingSent();
    }

    public function test_an_explicit_batch_size_overrides_the_computed_quota(): void
    {
        Setting::setValue('mail_batch_max', '3');
        $campaign = $this->campaignWith(20);

        app(CampaignDispatcher::class)->tick();

        $this->assertSame(3, $campaign->refresh()->sent_count);
    }

    // ── Durum akışı ──

    public function test_a_drained_campaign_is_marked_sent(): void
    {
        Setting::setValue('mail_hourly_limit', '100');
        $campaign = $this->campaignWith(5);

        app(CampaignDispatcher::class)->tick();

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Sent, $campaign->status);
        $this->assertNotNull($campaign->completed_at);
    }

    public function test_a_paused_campaign_sends_nothing(): void
    {
        $campaign = $this->campaignWith(10);
        app(CampaignService::class)->pause($campaign);

        app(CampaignDispatcher::class)->tick();

        Mail::assertNothingSent();
        $this->assertSame(10, $campaign->refresh()->pendingCount());
    }

    public function test_resuming_continues_where_it_left_off(): void
    {
        $campaign = $this->campaignWith(20);
        $service = app(CampaignService::class);
        $dispatcher = app(CampaignDispatcher::class);

        $dispatcher->tick();
        $service->pause($campaign->refresh());
        $dispatcher->tick();

        $this->assertSame(9, $campaign->refresh()->sent_count);

        $service->resume($campaign);
        $dispatcher->tick();

        $this->assertSame(18, $campaign->refresh()->sent_count);
    }

    public function test_cancelling_skips_everyone_still_waiting(): void
    {
        $campaign = $this->campaignWith(20);
        app(CampaignDispatcher::class)->tick();

        app(CampaignService::class)->cancel($campaign->refresh());

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Cancelled, $campaign->status);
        $this->assertSame(0, $campaign->pendingCount());
        $this->assertSame(11, $campaign->recipients()->where('status', CampaignRecipientStatus::Skipped)->count());
        $this->assertSame(9, $campaign->sent_count, 'Gönderilmiş mailler iptalden etkilenmemeli');
    }

    public function test_a_scheduled_campaign_waits_for_its_time(): void
    {
        $campaign = Campaign::factory()->manual([['first_name' => 'A', 'last_name' => null, 'email' => 'a@ornek.com']])->create([
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);

        app(CampaignDispatcher::class)->tick();

        Mail::assertNothingSent();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->refresh()->status);
    }

    public function test_a_due_campaign_starts_and_freezes_its_audience(): void
    {
        Subscriber::factory()->count(3)->create();

        $campaign = Campaign::factory()->create([
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);

        app(CampaignDispatcher::class)->tick();

        $campaign->refresh();
        $this->assertSame(3, $campaign->total_recipients);
        $this->assertSame(3, $campaign->sent_count);
        $this->assertSame(CampaignStatus::Sent, $campaign->status);
    }

    /**
     * Someone subscribing mid-send must not be added to a campaign that is
     * already going out — the list is what it was when it started.
     */
    public function test_the_audience_is_frozen_when_the_campaign_starts(): void
    {
        Subscriber::factory()->count(2)->create();

        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Scheduled]);
        app(CampaignService::class)->start($campaign);

        Subscriber::factory()->count(5)->create();
        app(CampaignDispatcher::class)->tick();

        $this->assertSame(2, $campaign->refresh()->total_recipients);
    }

    // ── Hata dayanıklılığı ──

    /**
     * One bad address must not take the rest of the list down with it.
     */
    public function test_a_failing_address_does_not_stop_the_others(): void
    {
        $campaign = $this->campaignWith(5);

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('550 mailbox unavailable'));
        Mail::shouldReceive('to')->andReturnUsing(function () {
            return new class {
                public function send($mailable): void {}
            };
        });

        $result = app(CampaignDispatcher::class)->sendBatch($campaign);

        // maxAttempts defaults to 3, so a first failure is a retry, not a
        // failure — but it must not stop the four that follow.
        $this->assertSame(1, $result['retrying']);
        $this->assertSame(4, $result['sent'], 'İlk hata kalan alıcıları durdurdu');
    }

    public function test_a_failure_is_retried_until_the_attempt_limit(): void
    {
        Setting::setValue('mail_max_attempts', '2');
        $campaign = $this->campaignWith(1);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('geçici hata'));

        $dispatcher = app(CampaignDispatcher::class);

        $dispatcher->sendBatch($campaign);
        $recipient = $campaign->recipients()->first();
        $this->assertSame(CampaignRecipientStatus::Pending, $recipient->status, 'İlk hatada hemen vazgeçilmemeli');
        $this->assertSame(1, $recipient->attempts);

        $dispatcher->sendBatch($campaign->refresh());
        $recipient->refresh();
        $this->assertSame(CampaignRecipientStatus::Failed, $recipient->status);
        $this->assertSame(2, $recipient->attempts);
        $this->assertStringContainsString('geçici hata', (string) $recipient->error);
    }

    /**
     * A delivery that will be retried is not a failure yet.
     *
     * Counting it as one inflated failed_count on every attempt and pushed the
     * progress bar past what had actually happened — a campaign that recovered
     * on the second try still looked broken.
     */
    public function test_a_retry_is_not_counted_as_a_failure(): void
    {
        Setting::setValue('mail_max_attempts', '3');
        $campaign = $this->campaignWith(2);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP kapalı'));

        $result = app(CampaignDispatcher::class)->sendBatch($campaign);

        $this->assertSame(2, $result['retrying']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $campaign->refresh()->failed_count, 'Tekrar denenecek alıcı başarısız sayıldı');
        $this->assertSame(0, $campaign->progress(), 'İlerleme çubuğu olmayan bir ilerlemeyi gösteriyor');
        $this->assertSame(2, $campaign->pendingCount());
    }

    public function test_the_failure_counter_moves_only_when_attempts_run_out(): void
    {
        Setting::setValue('mail_max_attempts', '2');
        $campaign = $this->campaignWith(1);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('kalıcı hata'));

        $dispatcher = app(CampaignDispatcher::class);

        $dispatcher->sendBatch($campaign);
        $this->assertSame(0, $campaign->refresh()->failed_count);

        $dispatcher->sendBatch($campaign);
        $this->assertSame(1, $campaign->refresh()->failed_count);
        $this->assertSame(100, $campaign->progress());
    }

    // ── Alıcı listesi ──

    public function test_an_unsubscribed_address_is_never_included(): void
    {
        Subscriber::factory()->create(['email' => 'kalan@ornek.com']);
        Subscriber::factory()->unsubscribed()->create(['email' => 'ayrilan@ornek.com']);

        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Scheduled]);
        app(CampaignService::class)->start($campaign);

        $emails = $campaign->recipients()->pluck('email')->all();
        $this->assertSame(['kalan@ornek.com'], $emails);
    }

    /**
     * A hand-typed list and the mailing list can hold the same person; they
     * must still receive one mail, not two.
     */
    public function test_a_duplicate_address_is_queued_once(): void
    {
        $campaign = Campaign::factory()->manual([
            ['first_name' => 'A', 'last_name' => null, 'email' => 'ayni@ornek.com'],
            ['first_name' => 'B', 'last_name' => null, 'email' => 'AYNI@ornek.com'],
            ['first_name' => 'C', 'last_name' => null, 'email' => 'baska@ornek.com'],
        ])->create(['status' => CampaignStatus::Scheduled]);

        app(CampaignService::class)->start($campaign);

        $this->assertSame(2, $campaign->refresh()->total_recipients);
    }

    public function test_site_members_can_be_the_audience(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $campaign = Campaign::factory()->create([
            'status'          => CampaignStatus::Scheduled,
            'audience'        => CampaignAudience::Users,
            'audience_filter' => ['active_only' => true],
        ]);

        app(CampaignService::class)->start($campaign);

        $this->assertSame(3, $campaign->refresh()->total_recipients, 'Pasif üye listeye girdi');
    }

    public function test_starting_with_an_empty_audience_is_refused(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Scheduled]);

        $this->expectException(RuntimeException::class);
        app(CampaignService::class)->start($campaign);
    }

    // ── İçerik ──

    public function test_the_content_cannot_change_once_sending_has_started(): void
    {
        $campaign = $this->campaignWith(3);

        $this->expectException(RuntimeException::class);
        app(CampaignService::class)->update($campaign, [
            'name'     => 'Yeni',
            'subject'  => 'Yeni konu',
            'body'     => '<p>Değişti</p>',
            'audience' => CampaignAudience::Subscribers,
        ]);
    }

    /**
     * Ad ve soyad ayrı tutuluyor: metin yalnızca adla ya da yalnızca soyadla
     * seslenebilmeli, {name} ise ikisinin birleşimi olarak durmalı.
     */
    public function test_each_recipient_gets_their_own_name_in_the_mail(): void
    {
        $campaign = $this->campaignWith(1, ['subject' => 'Merhaba {first_name}']);
        $campaign->update(['body' => '<p>Selam {name}, sayın {last_name}, adresin {email}.</p>']);

        app(CampaignDispatcher::class)->sendBatch($campaign->refresh());

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'Selam Kişi 1')
                && str_contains($html, 'sayın 1')
                && str_contains($html, 'kisi1@ornek.com')
                && $mail->envelope()->subject === 'Merhaba Kişi';
        });
    }

    public function test_every_mail_carries_its_own_unsubscribe_link(): void
    {
        $campaign = $this->campaignWith(1);
        $campaign->recipients()->update(['unsubscribe_token' => str_repeat('a', 64)]);

        app(CampaignDispatcher::class)->sendBatch($campaign->refresh());

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail): bool {
            $expected = route('newsletter.unsubscribe', str_repeat('a', 64));

            return str_contains($mail->render(), $expected)
                && ($mail->headers()->text['List-Unsubscribe'] ?? '') === '<' . $expected . '>';
        });
    }

    public function test_a_test_send_is_marked_and_reaches_only_the_tester(): void
    {
        $campaign = Campaign::factory()->create(['subject' => 'Ağustos Bülteni']);

        app(CampaignService::class)->sendTest($campaign, 'ben@ornek.com');

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail): bool {
            return $mail->isTest
                && $mail->hasTo('ben@ornek.com')
                && str_starts_with($mail->envelope()->subject, '[TEST]')
                // A test must not offer a real opt-out; there is no list entry.
                && ! isset($mail->headers()->text['List-Unsubscribe']);
        });
    }

    // ── Zamanlama bilgisi ──

    public function test_the_next_cron_time_lands_on_the_interval_boundary(): void
    {
        $next = app(CampaignDispatcher::class)->nextRunAt();

        $this->assertSame(0, $next->minute % CampaignDispatcher::RUN_INTERVAL_MINUTES);
        $this->assertSame(0, $next->second);
        $this->assertTrue($next->greaterThan(now()));
    }
}
