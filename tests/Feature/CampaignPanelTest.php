<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignAudience;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\PermissionKey;
use App\Enums\SubscriberStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CampaignDispatcher;
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

    private function admin(): User
    {
        return $this->sender();
    }

    /**
     * Gönderimi başlamış, alıcıları hazır bir kampanya.
     *
     * Alıcı yönetimi ancak liste dondurulduktan sonra anlamlı: taslakta
     * gönderilecek satır henüz yok.
     */
    private function sendingCampaign(string $name = 'Gönderimdeki Kampanya'): Campaign
    {
        $campaign = Campaign::create([
            'name'      => $name,
            'subject'   => 'Konu',
            'body'      => '<p>Gövde</p>',
            'audience'  => CampaignAudience::Subscribers->value,
            'status'    => CampaignStatus::Sending,
            'user_id'   => $this->admin()->id,
            'throttled' => true,
            'started_at' => now(),
        ]);

        foreach (range(1, 5) as $i) {
            $campaign->recipients()->create([
                'email'             => "alici{$i}-" . uniqid() . '@ornek.com',
                'first_name'        => 'Alici',
                'last_name'         => "Kisi{$i}",
                'status'            => CampaignRecipientStatus::Pending,
                'attempts'          => 0,
                'unsubscribe_token' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(64)),
            ]);
        }

        $campaign->update(['total_recipients' => $campaign->recipients()->count()]);

        return $campaign;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Ağustos Bülteni',
            'subject'     => 'Merhaba {name}',
            'body'        => '<p>Selam {name}</p>',
            'audience'    => CampaignAudience::Manual->value,
            // Elle giriş artık satır satır alan gönderiyor.
            'manual_rows' => [
                ['email' => 'ahmet@ornek.com', 'first_name' => 'Ahmet', 'last_name' => 'Yılmaz'],
                ['email' => 'ayse@ornek.com',  'first_name' => 'Ayşe',  'last_name' => 'Demir'],
                ['email' => 'bilgi@ornek.com', 'first_name' => '',      'last_name' => ''],
            ],
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

    /**
     * Ad ve soyad ayrı alan; kayıtta tek bir isim olarak birleşiyor, ikisi de
     * boşsa isim yazılmıyor.
     */
    public function test_a_hand_typed_list_joins_first_and_last_name(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());

        $recipients = Campaign::firstOrFail()->audience_filter['recipients'];

        $this->assertSame([
            ['first_name' => 'Ahmet', 'last_name' => 'Yılmaz', 'email' => 'ahmet@ornek.com'],
            ['first_name' => 'Ayşe',  'last_name' => 'Demir',  'email' => 'ayse@ornek.com'],
            ['first_name' => null,    'last_name' => null,     'email' => 'bilgi@ornek.com'],
        ], $recipients);
    }

    /**
     * Kullanıcı "ekle"ye basıp doldurmadan bırakabiliyor; boş satırlar ve aynı
     * adres listeyi bozmamalı.
     */
    public function test_a_hand_typed_list_drops_empty_rows_and_duplicates(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload([
            'manual_rows' => [
                ['email' => 'ahmet@ornek.com', 'first_name' => 'Ahmet', 'last_name' => 'Yılmaz'],
                ['email' => '', 'first_name' => '', 'last_name' => ''],
                ['email' => 'AHMET@ornek.com', 'first_name' => 'Başka', 'last_name' => 'Kayıt'],
            ],
        ]));

        $this->assertSame(
            [['first_name' => 'Ahmet', 'last_name' => 'Yılmaz', 'email' => 'ahmet@ornek.com']],
            Campaign::firstOrFail()->audience_filter['recipients'],
        );
    }

    public function test_a_hand_typed_list_with_no_valid_address_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload([
                'manual_rows' => [['email' => 'bu bir mail değil', 'first_name' => '', 'last_name' => '']],
            ]))
            ->assertSessionHasErrors('manual_rows');

        $this->assertSame(0, Campaign::count());
    }

    /**
     * Yayarak gönderim kullanıcı tercihi değil: form ne gönderirse göndersin
     * kampanya yayarak gider.
     */
    public function test_sending_is_always_throttled(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload(['throttled' => '0']));

        $this->assertTrue(Campaign::firstOrFail()->throttled);
    }

    public function test_an_import_without_a_file_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload([
                'audience'    => CampaignAudience::Import->value,
                'manual_rows' => null,
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
                'manual_rows'       => null,
                'recipient_file'    => new UploadedFile($path, 'liste.xlsx', null, null, true),
            ]))
            ->assertSessionHasNoErrors();

        $recipients = Campaign::firstOrFail()->audience_filter['recipients'];

        $this->assertCount(3, $recipients);
        $this->assertSame('ahmet@ornek.com', $recipients[0]['email']);
        $this->assertSame('Ahmet', $recipients[0]['first_name']);
        $this->assertSame('Yılmaz', $recipients[0]['last_name']);
    }

    /**
     * Yükleme önizlemesi: dosya kaydedilmeden okunuyor, kaç alıcı bulunduğu ve
     * kaç satırın atlandığı kampanya kaydedilmeden görülebilmeli.
     */
    public function test_the_upload_preview_reports_what_it_found(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'liste') . '.csv';
        file_put_contents($path, "Ad Soyad;E-posta\nAhmet Yılmaz;ahmet@ornek.com\nBozuk;bu-adres-degil\nAyşe Demir;ayse@ornek.com\n");

        $response = $this->actingAs($this->editor())
            ->post(route('admin.campaigns.recipients.preview'), [
                'recipient_file' => new UploadedFile($path, 'liste.csv', null, null, true),
            ])
            ->assertOk();

        $this->assertSame(2, $response->json('total'));
        $this->assertSame(1, $response->json('invalid'));
        $this->assertSame('ahmet@ornek.com', $response->json('sample.0.email'));
        $this->assertSame('Ahmet', $response->json('sample.0.first_name'));
        $this->assertSame('Yılmaz', $response->json('sample.0.last_name'));

        // Önizleme yalnızca okur; ortada kampanya kalmamalı.
        $this->assertSame(0, Campaign::count());
    }

    public function test_the_upload_preview_explains_an_unreadable_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'liste') . '.csv';
        file_put_contents($path, "Başlık\nburada adres yok\n");

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.recipients.preview'), [
                'recipient_file' => new UploadedFile($path, 'liste.csv', null, null, true),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_a_file_that_is_not_a_spreadsheet_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.store'), $this->payload([
                'audience'          => CampaignAudience::Import->value,
                'manual_rows'       => null,
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

    /**
     * Ekler forma binmiyor: her dosya kendi isteğiyle önden yükleniyor, kampanya
     * kaydedilince belirtecinden bağlanıyor. Hepsi tek POST'ta gitseydi birkaç
     * dosya post_max_size'ı aşar, PHP gövdeyi atar ve form 419 ile kaybolurdu.
     */
    public function test_an_attachment_uploaded_up_front_is_linked_when_the_campaign_is_saved(): void
    {
        $editor = $this->editor();

        $token = $this->actingAs($editor)
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Katalog.pdf', 20, 'application/pdf'),
            ])
            ->assertOk()
            ->json('token');

        $this->assertNotEmpty($token);

        $this->actingAs($editor)->post(route('admin.campaigns.store'), $this->payload([
            'attachment_tokens' => [$token],
        ]));

        $attachment = Campaign::firstOrFail()->attachments()->firstOrFail();

        $this->assertSame('Katalog.pdf', $attachment->original_name);
        $this->assertFileExists(UploadService::basePath($attachment->path));
    }

    /**
     * On dosya seçilince on istek aynı anda gidiyor.
     *
     * Bekleyen ekler oturumda tutulurken her istek oturumu baştan okuyup sonunda
     * geri yazıyordu: en son biten diğerlerinin kaydını eziyor, on dosyanın
     * yüklendiğini gören kullanıcı kampanyada üç ek buluyordu. Kayıt artık
     * satır başına ayrı.
     */
    public function test_every_uploaded_attachment_survives_until_the_campaign_is_saved(): void
    {
        $editor = $this->editor();
        $tokens = [];

        foreach (range(1, 10) as $i) {
            $tokens[] = $this->actingAs($editor)
                ->postJson(route('admin.campaigns.attachments.upload'), [
                    'file' => UploadedFile::fake()->create($i . '.jpg', 20, 'image/jpeg'),
                ])
                ->assertOk()
                ->json('token');
        }

        $this->actingAs($editor)->post(route('admin.campaigns.store'), $this->payload([
            'attachment_tokens' => $tokens,
        ]));

        $attachments = Campaign::firstOrFail()->attachments()->orderBy('id')->get();

        $this->assertCount(10, $attachments, 'Yüklenen her ek kampanyaya bağlanmalı');
        $this->assertSame(
            ['1.jpg', '2.jpg', '3.jpg', '4.jpg', '5.jpg', '6.jpg', '7.jpg', '8.jpg', '9.jpg', '10.jpg'],
            $attachments->pluck('original_name')->all(),
            'Ekler yükleme sırasını korumalı',
        );
    }

    /**
     * Bekleyen ek oturumdan bağımsız: oturum tazelense de yükleme kaybolmamalı.
     * Eşzamanlı isteklerin oturumu birbirine ezmesi tam olarak bu etkiyi
     * yaratıyordu.
     */
    public function test_a_pending_attachment_outlives_the_session(): void
    {
        $editor = $this->editor();

        $token = $this->actingAs($editor)
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Katalog.pdf', 20, 'application/pdf'),
            ])
            ->assertOk()
            ->json('token');

        $this->flushSession();

        $this->actingAs($editor)->post(route('admin.campaigns.store'), $this->payload([
            'attachment_tokens' => [$token],
        ]));

        $this->assertSame(1, Campaign::firstOrFail()->attachments()->count());
    }

    /**
     * Belirteç sahibine bağlı: başkasının bekleyen dosyası iliştirilemez.
     */
    public function test_someone_elses_pending_attachment_is_not_attached(): void
    {
        $token = $this->actingAs($this->editor())
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Gizli.pdf', 20, 'application/pdf'),
            ])
            ->assertOk()
            ->json('token');

        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload([
            'attachment_tokens' => [$token],
        ]));

        $this->assertSame(0, Campaign::firstOrFail()->attachments()->count());
    }

    /**
     * Form terk edilince dosya diskte kalıyor; temizliği cron yapıyor. Taze
     * bekleyene dokunulmamalı, kullanıcı hâlâ formda olabilir.
     */
    public function test_stale_pending_attachments_are_purged_but_fresh_ones_stay(): void
    {
        $editor = $this->editor();

        $eski = $this->actingAs($editor)
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Unutulmus.pdf', 20, 'application/pdf'),
            ])->json('token');

        $this->actingAs($editor)
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Taze.pdf', 20, 'application/pdf'),
            ])->assertOk();

        $eskiKayit = \App\Models\CampaignAttachment::where('token', $eski)->firstOrFail();
        $eskiKayit->forceFill(['created_at' => now()->subDays(2)])->save();
        $yol = UploadService::basePath($eskiKayit->path);

        $this->artisan('campaigns:purge-attachments')->assertSuccessful();

        $this->assertSame(0, \App\Models\CampaignAttachment::where('token', $eski)->count());
        $this->assertFileDoesNotExist($yol);
        $this->assertSame(
            1,
            \App\Models\CampaignAttachment::whereNull('campaign_id')->count(),
            'Taze bekleyen ek durmalı',
        );
    }

    /**
     * Belirteç yalnızca oturumda gerçek yola çevriliyor. Uydurulmuş bir belirteç
     * sessizce atlanmalı — istemciye yol verilseydi, kaydederken başka bir yol
     * göndererek sunucudaki herhangi bir dosya kampanyaya iliştirilebilirdi.
     */
    public function test_a_made_up_attachment_token_attaches_nothing(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload([
            'attachment_tokens' => ['3f2504e0-4f89-41d3-9a0c-0305e82c3301'],
        ]));

        $this->assertSame(0, Campaign::firstOrFail()->attachments()->count());
    }

    /**
     * Kaydetmeden vazgeçilen ek diskten de silinmeli, yoksa public/uploads
     * altında sahipsiz dosya birikir.
     */
    public function test_discarding_a_pending_attachment_removes_the_file(): void
    {
        $editor = $this->editor();

        $token = $this->actingAs($editor)
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Vazgectim.pdf', 12, 'application/pdf'),
            ])
            ->assertOk()
            ->json('token');

        $this->actingAs($editor)
            ->deleteJson(route('admin.campaigns.attachments.discard', $token))
            ->assertOk()
            ->assertJson(['removed' => true]);

        $this->actingAs($editor)->post(route('admin.campaigns.store'), $this->payload([
            'attachment_tokens' => [$token],
        ]));

        $this->assertSame(0, Campaign::firstOrFail()->attachments()->count());
    }

    /**
     * İstemci sınırı atlatılsa bile sunucu son söz: tavan, uygulamanın kendi
     * sınırı ile php.ini'nin izin verdiğinden hangisi düşükse odur.
     */
    public function test_an_oversized_attachment_is_refused_by_the_server(): void
    {
        $limits = app(\App\Services\CampaignService::class)->attachmentLimits();
        $tooBigKb = (int) floor($limits['per_file'] / 1024) + 64;

        $this->actingAs($this->editor())
            ->postJson(route('admin.campaigns.attachments.upload'), [
                'file' => UploadedFile::fake()->create('Devasa.pdf', $tooBigKb, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    // ── Alıcı yönetimi ──

    /**
     * Kampanya ekranından bir adres gönderim dışında bırakılabilmeli: liste
     * onaylandıktan sonra yanlış bir adres fark edildiğinde tek çare kampanyayı
     * iptal etmek olmamalı.
     */
    public function test_a_recipient_can_be_excluded_from_the_send(): void
    {
        $campaign = $this->sendingCampaign();
        $recipient = $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.exclude', [$campaign, $recipient]))
            ->assertRedirect();

        $this->assertSame(CampaignRecipientStatus::Skipped, $recipient->refresh()->status);
    }

    /**
     * Çıkarılan adres bir sonraki turda da sırada görünmemeli — ekranda
     * "gidecekler" diye gösterilen liste ile gerçekte gidenler aynı olmalı.
     */
    public function test_an_excluded_recipient_leaves_the_next_batch(): void
    {
        $campaign = $this->sendingCampaign();
        $recipient = $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->firstOrFail();

        $dispatcher = app(CampaignDispatcher::class);
        $this->assertTrue($dispatcher->nextBatch($campaign)->contains('id', $recipient->id));

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.exclude', [$campaign, $recipient]));

        $this->assertFalse($dispatcher->nextBatch($campaign)->contains('id', $recipient->id));
    }

    /**
     * Gönderilmiş bir adres listeden çıkarılamaz: mail çoktan yola çıktı,
     * "çıkardım" demek kullanıcıyı yanıltırdı.
     */
    public function test_an_already_sent_recipient_cannot_be_excluded(): void
    {
        $campaign = $this->sendingCampaign();
        $recipient = $campaign->recipients()->firstOrFail();
        $recipient->update(['status' => CampaignRecipientStatus::Sent, 'sent_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.exclude', [$campaign, $recipient]))
            ->assertRedirect();

        $this->assertSame(CampaignRecipientStatus::Sent, $recipient->refresh()->status);
    }

    public function test_an_excluded_recipient_can_be_put_back_in_the_queue(): void
    {
        $campaign = $this->sendingCampaign();
        $recipient = $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->firstOrFail();
        $recipient->update(['status' => CampaignRecipientStatus::Skipped]);

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.restore', [$campaign, $recipient]))
            ->assertRedirect();

        $this->assertSame(CampaignRecipientStatus::Pending, $recipient->refresh()->status);
    }

    /**
     * Alıcı başka bir kampanyaya aitse istek reddedilmeli; yoksa kimlik
     * numarasını değiştiren biri başka kampanyanın listesini bozabilir.
     */
    public function test_a_recipient_of_another_campaign_is_refused(): void
    {
        $campaign = $this->sendingCampaign();
        $other = $this->sendingCampaign('Başka Kampanya');
        $recipient = $other->recipients()->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.exclude', [$campaign, $recipient]))
            ->assertNotFound();
    }

    public function test_the_screen_lists_recipients_and_filters_them_by_status(): void
    {
        $campaign = $this->sendingCampaign();
        $campaign->recipients()->firstOrFail()->update([
            'status' => CampaignRecipientStatus::Failed,
            'error'  => 'SMTP reddetti',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.campaigns.show', [$campaign, 'rstatus' => CampaignRecipientStatus::Failed->value]))
            ->assertOk()
            ->assertSee('SMTP reddetti');
    }

    // ── Toplu işlemler ──

    public function test_selected_recipients_can_be_excluded_in_bulk(): void
    {
        $campaign = $this->sendingCampaign();
        $ids = $campaign->recipients()->limit(3)->pluck('id')->all();

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.bulk', $campaign), [
                'action'        => 'exclude',
                'recipient_ids' => $ids,
            ])
            ->assertRedirect();

        $this->assertSame(3, $campaign->recipients()
            ->whereIn('id', $ids)
            ->where('status', CampaignRecipientStatus::Skipped)
            ->count());
    }

    /**
     * Toplu çıkarmada gönderilmiş satır sessizce dışarıda kalmalı: seçim
     * kabaca yapılıyor, mail gitmiş bir adres geri alınamaz.
     */
    public function test_bulk_exclude_leaves_already_sent_recipients_alone(): void
    {
        $campaign = $this->sendingCampaign();
        $sent = $campaign->recipients()->firstOrFail();
        $sent->update(['status' => CampaignRecipientStatus::Sent, 'sent_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.bulk', $campaign), [
                'action'        => 'exclude',
                'recipient_ids' => $campaign->recipients()->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->assertSame(CampaignRecipientStatus::Sent, $sent->refresh()->status);
    }

    /**
     * Yeniden denemede durumu değiştirmek yetmez: satır zaten deneme tavanına
     * ulaştığı için başarısız, sayaç sıfırlanmazsa anında yine başarısız olur.
     */
    public function test_retrying_a_failure_resets_its_attempt_counter(): void
    {
        $campaign = $this->sendingCampaign();
        $campaign->update(['failed_count' => 1]);
        $recipient = $campaign->recipients()->firstOrFail();
        $recipient->update([
            'status'   => CampaignRecipientStatus::Failed,
            'attempts' => 5,
            'error'    => 'SMTP reddetti',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.retry', $campaign))
            ->assertRedirect();

        $recipient->refresh();

        $this->assertSame(CampaignRecipientStatus::Pending, $recipient->status);
        $this->assertSame(0, $recipient->attempts);
        $this->assertNull($recipient->error);
        $this->assertSame(0, (int) $campaign->refresh()->failed_count);
    }

    /**
     * Tamamlanmış kampanyaya satır geri konduğunda durum "gönderildi" kalırsa
     * zamanlanmış görev kampanyayı hiç eline almaz, satır sonsuza dek bekler.
     */
    public function test_retrying_reopens_a_completed_campaign(): void
    {
        $campaign = $this->sendingCampaign();
        $campaign->recipients()->update(['status' => CampaignRecipientStatus::Failed, 'attempts' => 3]);
        $campaign->update(['status' => CampaignStatus::Sent, 'completed_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.campaigns.recipients.retry', $campaign));

        $campaign->refresh();

        $this->assertSame(CampaignStatus::Sending, $campaign->status);
        $this->assertNull($campaign->completed_at);
    }

    public function test_the_recipient_list_can_be_exported_as_csv(): void
    {
        $campaign = $this->sendingCampaign();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.campaigns.recipients.export', $campaign))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // Excel'in Türkçe karakterleri doğru okuması BOM'a bağlı.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('E-posta', $csv);
        $this->assertSame(6, substr_count(trim($csv), "\n") + 1, 'Başlık + 5 alıcı bekleniyor');
    }

    /**
     * Dışa aktarma ekrandaki süzgeci taşımalı: "başarısızları ver" diyen biri
     * dosyada tüm listeyi bulmamalı.
     */
    public function test_the_export_honours_the_status_filter(): void
    {
        $campaign = $this->sendingCampaign();
        $campaign->recipients()->firstOrFail()->update(['status' => CampaignRecipientStatus::Failed]);

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.campaigns.recipients.export', [
                $campaign,
                'rstatus' => CampaignRecipientStatus::Failed->value,
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertSame(2, substr_count(trim($csv), "\n") + 1, 'Başlık + 1 başarısız bekleniyor');
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

    // ── Gönderim öncesi alıcı listesi ──

    /**
     * Kitle formda seçildi; listeyi görmek için ayrıca bir düğmeye basmak
     * gerekmemeli. Detay ekranı açıldığında adresler ve satır işlemleri orada.
     */
    public function test_the_draft_screen_shows_the_recipient_list_without_being_asked(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertDontSee('Alıcı Listesini Hazırla')
            ->assertSee('Listeyi yenile')
            ->assertSee('ahmet@ornek.com')
            ->assertSee('ayse@ornek.com')
            ->assertSee('Gönderimden çıkar');
    }

    /**
     * Bu özellik gelmeden açılmış taslakların listesi yok; ekran açılırken
     * kuruluyor, kullanıcıdan bir şey beklenmiyor.
     */
    public function test_an_older_draft_gets_its_list_when_the_screen_opens(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $campaign->recipients()->forceDelete();
        $this->assertSame(0, $campaign->recipients()->count());

        $this->actingAs($this->sender())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('ahmet@ornek.com');

        $this->assertSame(3, $campaign->recipients()->count());
    }

    public function test_the_recipient_list_is_built_when_the_campaign_is_saved(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();
        $this->assertSame(CampaignStatus::Draft, $campaign->status, 'Liste hazırlamak gönderimi başlatmamalı');
        $this->assertSame(3, $campaign->recipients()->count());
        $this->assertSame(3, $campaign->pendingCount());
        Mail::assertNothingSent();
    }

    public function test_an_address_excluded_before_approval_is_not_sent(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $disari = $campaign->recipients()->where('email', 'ayse@ornek.com')->firstOrFail();

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.recipients.exclude', [$campaign, $disari]))
            ->assertRedirect();

        // Onay ekranı ayıklamadan sonraki gerçek sayıyı söylemeli.
        $this->actingAs($this->sender())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('2 kişiye');

        $this->actingAs($this->sender())->post(route('admin.campaigns.send', $campaign));

        $campaign->refresh();
        $this->assertSame(2, $campaign->total_recipients, 'Çıkarılan adres toplama girmemeli');
        $this->assertSame(
            CampaignRecipientStatus::Skipped,
            $disari->refresh()->status,
            'Onay, ayıklanmış adresi sıraya geri almamalı',
        );
    }

    public function test_refreshing_the_prepared_list_rebuilds_it_from_the_source(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $disari = $campaign->recipients()->where('email', 'ayse@ornek.com')->firstOrFail();
        $this->actingAs($this->editor())->post(route('admin.campaigns.recipients.exclude', [$campaign, $disari]));

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.recipients.prepare', $campaign), ['refresh' => 1])
            ->assertRedirect();

        // Liste baştan kuruluyor: ne eski satırlar birikiyor ne de ayıklama kalıyor.
        $this->assertSame(3, $campaign->recipients()->count());
        $this->assertSame(3, $campaign->pendingCount());
    }

    public function test_changing_the_audience_rebuilds_the_list(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->assertSame(3, $campaign->recipients()->count());

        Subscriber::create([
            'email'             => 'abone@ornek.com',
            'status'            => SubscriberStatus::Subscribed,
            'unsubscribe_token' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(64)),
            'subscribed_at'     => now(),
        ]);

        $this->actingAs($this->editor())->put(route('admin.campaigns.update', $campaign), $this->payload([
            'audience'    => CampaignAudience::Subscribers->value,
            'manual_rows' => [],
        ]));

        $this->assertSame(
            ['abone@ornek.com'],
            $campaign->recipients()->pluck('email')->all(),
            'Kitle değişince liste yeni seçimi yansıtmalı; eskisi kalırsa kampanya başka adreslere gider',
        );
    }

    public function test_a_started_campaign_cannot_have_its_list_rebuilt(): void
    {
        $campaign = $this->sendingCampaign();

        $this->actingAs($this->editor())
            ->post(route('admin.campaigns.recipients.prepare', $campaign), ['refresh' => 1])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(5, $campaign->recipients()->count());
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
        $this->assertSame(3, $campaign->recipients()->count(), 'Liste kayıtla birlikte kurulur');
        $this->assertSame(0, $campaign->sent_count, 'Zamanlanmış kampanyadan mail çıkmamalı');
    }

    /**
     * Zamanlanmış kampanya henüz sıraya girmedi.
     *
     * Gönderim mesajı iki durumda da aynıydı: liste ancak gönderim başlarken
     * sayıldığı için ekranda "0 alıcı sıraya alındı" yazıyordu.
     */
    public function test_scheduling_reports_the_plan_not_a_queue(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $an = now()->addDay()->setTime(9, 30);

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign), ['scheduled_at' => $an->format('Y-m-d\TH:i')])
            ->assertSessionHas('success', fn (string $mesaj): bool => str_contains($mesaj, $an->format('d.m.Y H:i'))
                && str_contains($mesaj, '3 alıcıya')
                && ! str_contains($mesaj, '0 alıcı'));
    }

    /**
     * Zamanlanmış kampanyada ekran plana göre konuşmalı: tarih düğmenin
     * üstünde görünmeli ve kullanıcı planı değiştirebilmeli.
     */
    public function test_the_screen_shows_the_plan_and_offers_to_change_it(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $an = now()->addDay()->setTime(9, 30);

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign), ['scheduled_at' => $an->format('Y-m-d\TH:i')]);

        $this->actingAs($this->sender())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Gönderim Zamanlandı')
            ->assertSee($an->format('d.m.Y H:i'))
            ->assertSee('Zamanı değiştir')
            ->assertSee('Planı İptal Et, Hemen Gönder')
            ->assertDontSee('Onayla ve Gönderime Al');
    }

    /**
     * Plan bağlayıcı değil: yeni bir saat verilince eskisinin yerini alır.
     */
    public function test_a_scheduled_campaign_can_be_rescheduled(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $ilk = now()->addDay()->setTime(9, 30);
        $yeni = now()->addDays(3)->setTime(14, 0);

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign), ['scheduled_at' => $ilk->format('Y-m-d\TH:i')]);

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign), ['scheduled_at' => $yeni->format('Y-m-d\TH:i')])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
        $this->assertSame($yeni->format('Y-m-d H:i'), $campaign->scheduled_at?->format('Y-m-d H:i'));
        $this->assertSame(0, $campaign->sent_count, 'Yeniden planlamak gönderim başlatmamalı');
    }

    /**
     * Plandan vazgeçip hemen göndermek: aynı uçtan, tarih verilmeden.
     */
    public function test_a_scheduled_campaign_can_be_sent_right_away(): void
    {
        $this->actingAs($this->editor())->post(route('admin.campaigns.store'), $this->payload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($this->sender())->post(route('admin.campaigns.send', $campaign), [
            'scheduled_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ]);

        $this->actingAs($this->sender())
            ->post(route('admin.campaigns.send', $campaign))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Sending, $campaign->status);
        $this->assertNull($campaign->scheduled_at, 'Plan iptal edilmeli');
        $this->assertSame(3, $campaign->total_recipients);
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
        // Test maili listeye dokunmamalı: kimse "gönderildi" sayılmamalı.
        $this->assertSame(3, $campaign->refresh()->pendingCount());
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
        $this->assertDatabaseHas('subscribers', [
            'email'      => 'zeynep@ornek.com',
            'first_name' => 'Zeynep',
            'last_name'  => 'Ak',
            'locale'     => 'tr',
        ]);
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
