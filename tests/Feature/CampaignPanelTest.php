<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignAudience;
use App\Enums\CampaignStatus;
use App\Enums\PermissionKey;
use App\Enums\SubscriberStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The panel side: creating a campaign, approving it, and the safety rails
 * around who may do what.
 *
 * A campaign is never sent straight from the form. It is saved as a draft, the
 * review screen shows the real recipient count, and only an explicit approval
 * starts it — because a send cannot be taken back.
 */
class CampaignPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param array<int, PermissionKey> $permissions
     */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Test Rolü', 'slug' => 'test-' . uniqid()]);

        $ids = [];
        foreach ($permissions as $key) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $key->value],
                ['name' => $key->label(), 'group' => $key->group()],
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function editor(): User
    {
        return $this->userWith([PermissionKey::CampaignsView, PermissionKey::CampaignsManage]);
    }

    private function sender(): User
    {
        return $this->userWith([
            PermissionKey::CampaignsView,
            PermissionKey::CampaignsManage,
            PermissionKey::CampaignsSend,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'              => 'Ağustos Bülteni',
            'subject'           => 'Merhaba {name}',
            'body'              => '<p>Selam {name}</p>',
            'audience'          => CampaignAudience::Manual->value,
            'manual_recipients' => "Ahmet Yılmaz <ahmet@ornek.com>\nAyşe Demir;ayse@ornek.com\nbilgi@ornek.com",
            'throttled'         => '1',
        ], $overrides);
    }

    // ── Oluşturma ──

    public function test_a_new_campaign_is_saved_as_a_draft_and_opens_the_review_screen(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload());

        $campaign = Campaign::firstOrFail();

        $response->assertRedirect(route('admin.campaigns.show', $campaign));
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame(0, $campaign->total_recipients, 'Taslak kaydedilirken alıcı sıraya alınmamalı');
        Mail::assertNothingSent();
    }

    public function test_a_hand_typed_list_accepts_all_three_line_formats(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());

        $recipients = Campaign::firstOrFail()->audience_filter['recipients'];

        $this->assertSame([
            ['name' => 'Ahmet Yılmaz', 'email' => 'ahmet@ornek.com'],
            ['name' => 'Ayşe Demir',   'email' => 'ayse@ornek.com'],
            ['name' => null,           'email' => 'bilgi@ornek.com'],
        ], $recipients);
    }

    public function test_a_hand_typed_list_with_no_valid_address_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload(['manual_recipients' => "bu bir mail değil\nbu da değil"]))
            ->assertSessionHasErrors('manual_recipients');

        $this->assertSame(0, Campaign::count());
    }

    public function test_an_import_without_a_file_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload([
                'audience'          => CampaignAudience::Import->value,
                'manual_recipients' => null,
            ]))
            ->assertSessionHasErrors('recipient_file');
    }

    public function test_an_excel_upload_becomes_the_recipient_list(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'liste') . '.xlsx';
        app(\App\Services\RecipientImportService::class)->writeTemplate($path);

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload([
                'audience'          => CampaignAudience::Import->value,
                'manual_recipients' => null,
                'recipient_file'    => new UploadedFile($path, 'liste.xlsx', null, null, true),
            ]))
            ->assertSessionHasNoErrors();

        $recipients = Campaign::firstOrFail()->audience_filter['recipients'];

        $this->assertCount(3, $recipients);
        $this->assertSame('ahmet@ornek.com', $recipients[0]['email']);
        $this->assertSame('Ahmet Yılmaz', $recipients[0]['name']);
    }

    public function test_a_file_that_is_not_a_spreadsheet_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload([
                'audience'          => CampaignAudience::Import->value,
                'manual_recipients' => null,
                'recipient_file'    => UploadedFile::fake()->create('zararli.exe', 8),
            ]))
            ->assertSessionHasErrors('recipient_file');
    }

    public function test_an_attachment_is_stored_and_travels_with_the_mail(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload([
            'attachments' => [UploadedFile::fake()->create('Fiyat Listesi.pdf', 20, 'application/pdf')],
        ]));

        $campaign = Campaign::firstOrFail();
        $attachment = $campaign->attachments()->firstOrFail();

        $this->assertSame('Fiyat Listesi.pdf', $attachment->original_name);
        $this->assertFileExists(UploadService::basePath($attachment->path));

        $mail = new CampaignMail($campaign->load('attachments'), $campaign->recipients()->make([
            'email' => 'x@ornek.com',
        ]));

        $this->assertCount(1, $mail->attachments());
    }

    // ── Onay akışı ──

    public function test_the_review_screen_shows_the_real_recipient_count(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('3 kişiye')
            ->assertSee('Onayla ve Gönderime Al');
    }

    public function test_approving_queues_the_recipients(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Sending, $campaign->status);
        $this->assertSame(3, $campaign->total_recipients);
        $this->assertSame(3, $campaign->pendingCount(), 'Onay anında mail gitmemeli, sıraya girmeli');
        Mail::assertNothingSent();
    }

    public function test_scheduling_keeps_the_campaign_waiting(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())->post(route('admin.campaigns.send', $campaign), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertNotNull($campaign->scheduled_at);
        $this->assertSame(0, $campaign->recipients()->count(), 'Liste zamanı gelince dondurulmalı');
    }

    public function test_a_past_schedule_is_rejected(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign), ['scheduled_at' => now()->subDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_approving_an_empty_audience_reports_the_problem(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Draft]);

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(CampaignStatus::Draft, $campaign->refresh()->status);
    }

    public function test_a_started_campaign_cannot_be_edited(): void
    {
        $campaign = Campaign::factory()->sending()->create();

        $this->actingAs($this->editor())
            ->get(route('admin.campaigns.edit', $campaign))
            ->assertForbidden();
    }

    // ── Yetkiler ──

    /**
     * Drafting and sending are separate abilities: reaching the whole list is
     * not the same decision as writing the copy.
     */
    public function test_an_editor_may_draft_but_not_send(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.send', $campaign))
            ->assertForbidden();

        $this->assertSame(CampaignStatus::Draft, $campaign->refresh()->status);
    }

    public function test_a_user_without_permission_sees_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.campaigns.create'))->assertForbidden();
    }

    public function test_the_excel_template_can_be_downloaded(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.campaigns.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=alici-listesi-sablonu.xlsx');
    }

    // ── Test gönderimi ──

    public function test_a_test_mail_goes_only_to_the_given_address(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.test', $campaign), ['test_email' => 'ben@ornek.com'])
            ->assertRedirect();

        Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail): bool => $mail->hasTo('ben@ornek.com') && $mail->isTest);
        $this->assertSame(0, $campaign->refresh()->recipients()->count());
    }

    // ── Abone listesi ──

    public function test_subscribers_can_be_imported_from_a_file(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'abone') . '.csv';
        file_put_contents($csv, "Ad Soyad;E-posta\nZeynep Ak;zeynep@ornek.com\nbozuk;degil-mail\nCan Su;can@ornek.com\n");

        $this->actingAs($this->userWith([PermissionKey::SubscribersView, PermissionKey::SubscribersManage]))
            ->post(route('admin.subscribers.import'), [
                'file'   => new UploadedFile($csv, 'abone.csv', null, null, true),
                'locale' => 'tr',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Subscriber::count());
        $this->assertDatabaseHas('subscribers', ['email' => 'zeynep@ornek.com', 'name' => 'Zeynep Ak', 'locale' => 'tr']);
    }

    public function test_the_front_form_adds_a_subscriber(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'Yeni@Ornek.com'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscribers', ['email' => 'yeni@ornek.com', 'source' => 'form']);
    }

    public function test_subscribing_twice_does_not_duplicate_the_row(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'ayni@ornek.com'])->assertOk();
        $this->postJson(route('newsletter.subscribe'), ['email' => 'ayni@ornek.com'])->assertOk();

        $this->assertSame(1, Subscriber::where('email', 'ayni@ornek.com')->count());
    }

    /**
     * Opting out has to work from a mail, with no login — otherwise the only
     * way out is marking the mail as spam.
     */
    public function test_the_unsubscribe_link_works_without_signing_in(): void
    {
        $subscriber = Subscriber::factory()->create(['email' => 'cikan@ornek.com']);

        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))
            ->assertOk()
            ->assertSee('cikan@ornek.com');

        $subscriber->refresh();
        $this->assertSame(SubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    /**
     * A campaign can reach people who were never on the mailing list — typed in
     * by hand or imported. Without a token of their own the mail would carry no
     * way out, which is both rude and, for commercial mail, not allowed.
     */
    public function test_a_hand_typed_recipient_can_also_unsubscribe(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())->post(route('admin.campaigns.send', $campaign));

        $recipient = $campaign->refresh()->recipients()->where('email', 'ahmet@ornek.com')->firstOrFail();
        $this->assertNotNull($recipient->unsubscribe_token, 'Elle girilen alıcıya çıkış anahtarı verilmedi');

        $this->get(route('newsletter.unsubscribe', $recipient->unsubscribe_token))
            ->assertOk()
            ->assertSee('ahmet@ornek.com');

        $this->assertDatabaseHas('subscribers', [
            'email'  => 'ahmet@ornek.com',
            'status' => SubscriberStatus::Unsubscribed->value,
            'source' => 'campaign',
        ]);
    }

    /**
     * The subscribers table doubles as the suppression list, so opting out of
     * one campaign has to keep the address out of the next one.
     */
    public function test_opting_out_keeps_the_address_out_of_later_campaigns(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $first = Campaign::firstOrFail();
        $this->actingAs($this->sender())->post(route('admin.campaigns.send', $first));

        $recipient = $first->refresh()->recipients()->where('email', 'ahmet@ornek.com')->firstOrFail();
        $this->get(route('newsletter.unsubscribe', $recipient->unsubscribe_token))->assertOk();

        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload(['name' => 'İkinci Bülten']));
        $second = Campaign::where('name', 'İkinci Bülten')->firstOrFail();
        $this->actingAs($this->sender())->post(route('admin.campaigns.send', $second));

        $emails = $second->refresh()->recipients()->pluck('email')->all();

        $this->assertNotContains('ahmet@ornek.com', $emails, 'Çıkan kişi sonraki kampanyaya yine eklendi');
        $this->assertCount(2, $emails);
    }

    public function test_an_unknown_unsubscribe_token_is_handled_gracefully(): void
    {
        $this->get(route('newsletter.unsubscribe', str_repeat('z', 64)))
            ->assertOk()
            ->assertSee('geçersiz', false);
    }

    /**
     * Someone who left and comes back should be revived, not duplicated — and
     * the row has to stay on file so an import cannot quietly re-add them.
     */
    public function test_resubscribing_revives_the_existing_row(): void
    {
        $subscriber = Subscriber::factory()->unsubscribed()->create(['email' => 'geri@ornek.com']);

        $this->postJson(route('newsletter.subscribe'), ['email' => 'geri@ornek.com'])->assertOk();

        $this->assertSame(1, Subscriber::where('email', 'geri@ornek.com')->count());
        $this->assertSame(SubscriberStatus::Subscribed, $subscriber->refresh()->status);
    }
}
