<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignAudience;
use App\Enums\PermissionKey;
use App\Enums\SubscriberStatus;
use App\Models\Campaign;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\SubscriberList;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\SubscriberListService;
use App\Services\SubscriberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Abone listeleri: tedarikçiler, pazarlamacılar, bülten…
 *
 * Aboneler tek düz bir listede duruyordu; tedarikçilere mail atmanın tek yolu
 * her seferinde Excel hazırlamaktı. Üyelik çoklu — bir tedarikçi aynı zamanda
 * bültene de kayıtlı olabilir — ama abonelikten çıkma kişiye bağlı: ayrılan bir
 * adrese hangi listede olursa olsun mail gitmiyor.
 */
class SubscriberListTest extends TestCase
{
    use RefreshDatabase;

    private ?User $manager = null;

    /**
     * Aynı testte iki kez çağrılabiliyor; kullanıcı bir kez açılıyor.
     */
    private function manager(): User
    {
        if ($this->manager !== null) {
            return $this->manager;
        }

        $this->seedAuthorization();

        $user = User::create([
            'first_name' => 'Liste',
            'last_name'  => 'Yöneticisi',
            'email'      => 'list-manager@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $role = Role::create(['name' => 'Liste Rolü', 'slug' => 'list-' . uniqid()]);
        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('key', [
                PermissionKey::SubscribersView->value,
                PermissionKey::SubscribersManage->value,
                PermissionKey::CampaignsView->value,
                PermissionKey::CampaignsManage->value,
            ])->pluck('id')->all(),
        );

        $user->roles()->attach($role);

        return $this->manager = $user;
    }

    private function lists(): SubscriberListService
    {
        return app(SubscriberListService::class);
    }

    /**
     * Kurulum var olan aboneleri bir listeye taşımalı, yoksa taşımadan sonra
     * hiçbir kampanyanın hedefinde görünmezler.
     */
    public function test_the_installation_ships_a_default_list(): void
    {
        $default = $this->lists()->default();

        $this->assertNotNull($default);
        $this->assertTrue($default->is_default);
        $this->assertSame('Bülten', $default->name);
    }

    public function test_a_subscriber_can_belong_to_several_lists(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $newsletter = $this->lists()->default();

        $subscriber = app(SubscriberService::class)->subscribe(
            'zeynep@ornek.com', 'Zeynep', 'Ak', 'tr', 'panel', [$suppliers->id, $newsletter->id],
        );

        $this->assertEqualsCanonicalizing(
            [$suppliers->id, $newsletter->id],
            $subscriber->lists()->pluck('subscriber_lists.id')->all(),
        );
    }

    /**
     * Bültene yeniden kaydolan bir tedarikçi tedarikçi listesinden düşmemeli.
     */
    public function test_subscribing_again_adds_a_list_without_dropping_the_others(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $newsletter = $this->lists()->default();
        $service = app(SubscriberService::class);

        $service->subscribe('ahmet@tedarik.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);
        $subscriber = $service->subscribe('ahmet@tedarik.com', null, null, 'tr', 'form', [$newsletter->id]);

        $this->assertEqualsCanonicalizing(
            [$suppliers->id, $newsletter->id],
            $subscriber->lists()->pluck('subscriber_lists.id')->all(),
        );
    }

    /**
     * Asıl kazanç: Excel hazırlamadan tedarikçilere kampanya.
     */
    public function test_a_campaign_targets_only_the_chosen_lists(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $marketers = $this->lists()->create(['name' => 'Pazarlamacılar']);
        $service = app(SubscriberService::class);

        $service->subscribe('ahmet@tedarik.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);
        $service->subscribe('ayse@tedarik.com', 'Ayşe', 'Demir', 'tr', 'panel', [$suppliers->id]);
        $service->subscribe('mehmet@pazar.com', 'Mehmet', 'Kaya', 'tr', 'panel', [$marketers->id]);

        $campaign = Campaign::factory()->create([
            'audience'        => CampaignAudience::Subscribers,
            'audience_filter' => ['match_locale' => false, 'list_ids' => [$suppliers->id]],
        ]);

        $emails = collect(app(CampaignService::class)->resolveAudience($campaign))->pluck('email')->all();

        $this->assertEqualsCanonicalizing(['ahmet@tedarik.com', 'ayse@tedarik.com'], $emails);
    }

    /**
     * İki listede birden olan kişi maili bir kez almalı.
     */
    public function test_someone_in_two_targeted_lists_is_queued_once(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $marketers = $this->lists()->create(['name' => 'Pazarlamacılar']);

        app(SubscriberService::class)
            ->subscribe('ikisinde@ornek.com', 'İki', 'Listede', 'tr', 'panel', [$suppliers->id, $marketers->id]);

        $campaign = Campaign::factory()->create([
            'audience'        => CampaignAudience::Subscribers,
            'audience_filter' => ['list_ids' => [$suppliers->id, $marketers->id]],
        ]);

        $emails = collect(app(CampaignService::class)->resolveAudience($campaign))->pluck('email')->all();

        $this->assertSame(['ikisinde@ornek.com'], $emails);
    }

    public function test_no_chosen_list_means_every_subscriber(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);

        $service->subscribe('ahmet@tedarik.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);
        $service->subscribe('listesiz@ornek.com', 'Listesiz', 'Kişi', 'tr', 'panel');

        $campaign = Campaign::factory()->create([
            'audience'        => CampaignAudience::Subscribers,
            'audience_filter' => ['list_ids' => []],
        ]);

        $emails = collect(app(CampaignService::class)->resolveAudience($campaign))->pluck('email')->all();

        $this->assertEqualsCanonicalizing(['ahmet@tedarik.com', 'listesiz@ornek.com'], $emails);
    }

    /**
     * Abonelikten çıkma listeye değil kişiye bağlı: ayrılan biri listede kalsa
     * da gönderime girmemeli.
     */
    public function test_an_unsubscribed_member_is_never_queued(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);

        $service->subscribe('ahmet@tedarik.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);
        $ayrilan = $service->subscribe('ayrilan@tedarik.com', 'Ayrılan', 'Kişi', 'tr', 'panel', [$suppliers->id]);
        $service->unsubscribeByToken($ayrilan->unsubscribe_token);

        $campaign = Campaign::factory()->create([
            'audience'        => CampaignAudience::Subscribers,
            'audience_filter' => ['list_ids' => [$suppliers->id]],
        ]);

        $emails = collect(app(CampaignService::class)->resolveAudience($campaign))->pluck('email')->all();

        $this->assertSame(['ahmet@tedarik.com'], $emails);
        // Üyelik duruyor; geçmiş kaybolmamalı.
        $this->assertTrue($ayrilan->fresh()->lists()->whereKey($suppliers->id)->exists());
    }

    public function test_the_front_form_lands_in_the_default_list(): void
    {
        $this->lists()->create(['name' => 'Tedarikçiler']);
        $default = $this->lists()->default();

        $this->postJson(route('newsletter.subscribe'), ['email' => 'yeni@ornek.com'])
            ->assertOk();

        $subscriber = Subscriber::where('email', 'yeni@ornek.com')->firstOrFail();

        $this->assertSame([$default->id], $subscriber->lists()->pluck('subscriber_lists.id')->all());
    }

    public function test_the_panel_can_add_a_subscriber_straight_into_a_list(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);

        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.store'), [
                'email'      => 'yeni@tedarik.com',
                'first_name' => 'Yeni',
                'last_name'  => 'Tedarikçi',
                'list_ids'   => [$suppliers->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$suppliers->id],
            Subscriber::where('email', 'yeni@tedarik.com')->firstOrFail()->lists()->pluck('subscriber_lists.id')->all(),
        );
    }

    public function test_an_import_puts_everyone_into_the_chosen_lists(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);

        $path = tempnam(sys_get_temp_dir(), 'liste') . '.csv';
        file_put_contents($path, "Ad;Soyad;E-posta\nAhmet;Yılmaz;ahmet@tedarik.com\nAyşe;Demir;ayse@tedarik.com\n");

        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.import'), [
                'file'     => new UploadedFile($path, 'liste.csv', null, null, true),
                'list_ids' => [$suppliers->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, $suppliers->subscribers()->count());
    }

    /**
     * Listeye yanlış girilmiş bir ad ya da adres, kaydı silip yeniden eklemeden
     * düzeltilebilmeli — silmek kayıt tarihini ve liste üyeliklerini kaybettirir.
     */
    public function test_a_subscriber_can_be_corrected(): void
    {
        $liste = $this->lists()->create(['name' => 'Tedarikçiler']);
        $abone = app(SubscriberService::class)->subscribe('mehmt@ornek.com', 'Mehmt', 'Demirr', 'tr', 'panel', [$liste->id]);

        $this->actingAs($this->manager())
            ->put(route('admin.subscribers.update', $abone), [
                'email'      => 'mehmet@ornek.com',
                'first_name' => 'Mehmet',
                'last_name'  => 'Demir',
                'locale'     => 'tr',
                'status'     => SubscriberStatus::Subscribed->value,
                'list_ids'   => [$liste->id],
            ])
            ->assertRedirect();

        $abone->refresh();

        $this->assertSame('mehmet@ornek.com', $abone->email);
        $this->assertSame('Mehmet', $abone->first_name);
        $this->assertSame('Demir', $abone->last_name);
        $this->assertSame(1, $abone->lists()->count());
    }

    /**
     * Adresine dokunmadan yalnızca adını düzelten biri "bu e-posta zaten
     * kayıtlı" hatası almamalı: benzersizlik kendi kaydı hariç sınanıyor.
     */
    public function test_correcting_only_the_name_keeps_the_same_address(): void
    {
        $abone = app(SubscriberService::class)->subscribe('kisi@ornek.com', 'Yanlis', 'Isim');

        $this->actingAs($this->manager())
            ->put(route('admin.subscribers.update', $abone), [
                'email'      => 'kisi@ornek.com',
                'first_name' => 'Doğru',
                'last_name'  => 'İsim',
                'status'     => SubscriberStatus::Subscribed->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Doğru', $abone->refresh()->first_name);
    }

    public function test_a_subscriber_cannot_take_another_ones_address(): void
    {
        $service = app(SubscriberService::class);
        $service->subscribe('dolu@ornek.com', 'Dolu', 'Kayit');
        $abone = $service->subscribe('bos@ornek.com', 'Bos', 'Kayit');

        $this->actingAs($this->manager())
            ->put(route('admin.subscribers.update', $abone), [
                'email'      => 'dolu@ornek.com',
                'first_name' => 'Bos',
                'last_name'  => 'Kayit',
                'status'     => SubscriberStatus::Subscribed->value,
            ])
            ->assertSessionHasErrors('email');
    }

    /**
     * İşaretsiz onay kutusu istekte hiç yer almıyor; "hepsinin işareti
     * kaldırıldı" ile "listelere dokunulmadı" karışırsa abone hiçbir listeden
     * çıkarılamaz.
     */
    public function test_clearing_every_list_removes_the_subscriber_from_them(): void
    {
        $liste = $this->lists()->create(['name' => 'Tedarikçiler']);
        $abone = app(SubscriberService::class)->subscribe('cikan@ornek.com', 'Cikan', 'Kisi', null, 'panel', [$liste->id]);

        $this->actingAs($this->manager())
            ->put(route('admin.subscribers.update', $abone), [
                'email'      => 'cikan@ornek.com',
                'first_name' => 'Cikan',
                'last_name'  => 'Kisi',
                'status'     => SubscriberStatus::Subscribed->value,
                // list_ids gönderilmiyor: tarayıcıda tüm kutular boşken olan tam bu.
            ])
            ->assertRedirect();

        $this->assertSame(0, $abone->lists()->count());
    }

    /**
     * Önizleme, geçersiz satırı atmak yerine nedeniyle birlikte döndürüyor;
     * kullanıcı ekranda düzeltebilsin diye.
     */
    public function test_the_import_preview_reports_every_row_with_its_verdict(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'onizleme') . '.csv';
        file_put_contents($path, "Ad;Soyad;E-posta\n"
            . "Zeynep;Kaya;zeynep@ornek.com\n"
            . "Burak;Sahin;bozuk-adres\n"
            . "Elif;Aydin;elif@ornek.com\n"
            . "Deniz;Yildiz;elif@ornek.com\n");

        $veri = $this->actingAs($this->manager())
            ->post(route('admin.subscribers.import.preview'), [
                'file' => new UploadedFile($path, 'liste.csv', null, null, true),
            ])
            ->assertOk()
            ->json();

        $this->assertSame(4, $veri['total']);
        $this->assertSame(2, $veri['valid']);
        $this->assertSame(2, $veri['invalid']);
        $this->assertSame('Geçersiz e-posta biçimi', $veri['rows'][1]['reason']);
        $this->assertSame('Dosyada tekrar ediyor', $veri['rows'][3]['reason']);
    }

    /**
     * Önizlemede düzeltilen satırlar kaydedilmeli; dosya tekrar okunsaydı
     * kullanıcının emeği çöpe giderdi.
     */
    public function test_rows_corrected_in_the_preview_are_what_gets_saved(): void
    {
        $liste = $this->lists()->create(['name' => 'Tedarikçiler']);

        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.import'), [
                'rows' => [
                    ['email' => 'duzeltilmis@ornek.com', 'first_name' => 'Burak', 'last_name' => 'Şahin'],
                    ['email' => 'ikinci@ornek.com', 'first_name' => 'Elif', 'last_name' => 'Aydın'],
                ],
                'list_ids' => [$liste->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $liste->subscribers()->count());
        $this->assertDatabaseHas('subscribers', ['email' => 'duzeltilmis@ornek.com', 'first_name' => 'Burak']);
    }

    /**
     * İstemci denetimi atlatılsa bile sunucu son söz.
     */
    public function test_the_server_refuses_an_invalid_address_among_the_rows(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.import'), [
                'rows' => [
                    ['email' => 'gecerli@ornek.com', 'first_name' => 'Gecerli', 'last_name' => 'Kayit'],
                    ['email' => 'hala-bozuk', 'first_name' => 'Bozuk', 'last_name' => 'Kayit'],
                ],
            ])
            ->assertSessionHasErrors('rows.1.email');

        $this->assertDatabaseMissing('subscribers', ['email' => 'gecerli@ornek.com']);
    }

    /**
     * Listeye eklemek durumu değiştirmiyor — çıkış kaydı bilerek duruyor —
     * ama yanlışlıkla çıkarılan birini geri almanın bir yolu olmalı.
     */
    public function test_an_unsubscribed_person_can_be_brought_back(): void
    {
        $liste = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);
        $abone = $service->subscribe('geri@ornek.com', 'Geri', 'Gelen', null, 'panel', [$liste->id]);
        $service->unsubscribeByToken($abone->unsubscribe_token);

        $this->assertSame(SubscriberStatus::Unsubscribed, $abone->refresh()->status);

        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.resubscribe', $abone))
            ->assertRedirect();

        $abone->refresh();

        $this->assertSame(SubscriberStatus::Subscribed, $abone->status);
        $this->assertNull($abone->unsubscribed_at);
        // Liste üyeliği çıkışta da korunuyordu, geri alışta da bozulmamalı.
        $this->assertSame(1, $abone->lists()->count());
    }

    public function test_bulk_action_moves_selected_subscribers_into_a_list(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);

        $first = $service->subscribe('bir@ornek.com', 'Bir', 'Kişi');
        $second = $service->subscribe('iki@ornek.com', 'İki', 'Kişi');

        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.bulk-list'), [
                'list_id'        => $suppliers->id,
                'action'         => 'add',
                'subscriber_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, $suppliers->subscribers()->count());

        $this->actingAs($this->manager())
            ->post(route('admin.subscribers.bulk-list'), [
                'list_id'        => $suppliers->id,
                'action'         => 'remove',
                'subscriber_ids' => [$first->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $suppliers->subscribers()->count());
    }

    public function test_the_list_filter_narrows_the_subscriber_table(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);

        $service->subscribe('ahmet@tedarik.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);
        $service->subscribe('baskasi@ornek.com', 'Başka', 'Kişi');

        $emails = $this->actingAs($this->manager())
            ->get(route('admin.subscribers.index', ['list_id' => $suppliers->id]))
            ->assertOk()
            ->viewData('subscribers')
            ->pluck('email')
            ->all();

        $this->assertSame(['ahmet@tedarik.com'], $emails);
    }

    /**
     * Yalnızca bir liste kalmışsa silinemez: site formundan gelen abonenin
     * yazılacağı yer kalmaz ve kayıt sessizce kaybolur.
     */
    public function test_the_last_list_cannot_be_deleted(): void
    {
        $default = $this->lists()->default();

        $this->actingAs($this->manager())
            ->delete(route('admin.subscriber-lists.destroy', $default))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, SubscriberList::query()->count());
    }

    /**
     * Liste silinince aboneler değil yalnızca üyelikleri gider.
     */
    public function test_deleting_a_list_keeps_its_subscribers(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        app(SubscriberService::class)->subscribe('ahmet@tedarik.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);

        $this->actingAs($this->manager())
            ->delete(route('admin.subscriber-lists.destroy', $suppliers))
            ->assertRedirect();

        $this->assertDatabaseHas('subscribers', ['email' => 'ahmet@tedarik.com']);
        $this->assertSame(
            SubscriberStatus::Subscribed,
            Subscriber::where('email', 'ahmet@tedarik.com')->firstOrFail()->status,
        );
        $this->assertSame(0, Subscriber::where('email', 'ahmet@tedarik.com')->firstOrFail()->lists()->count());
    }

    /**
     * Varsayılan tek olmalı: yeni işaretlenen eskisini düşürür.
     */
    public function test_marking_a_list_as_default_clears_the_previous_one(): void
    {
        $previous = $this->lists()->default();
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler', 'is_default' => true]);

        $this->assertTrue($suppliers->fresh()->is_default);
        $this->assertFalse($previous->fresh()->is_default);
        $this->assertSame($suppliers->id, $this->lists()->default()->id);
    }

    public function test_list_names_get_unique_slugs(): void
    {
        $first = $this->lists()->create(['name' => 'Tedarikçiler']);
        $second = $this->lists()->create(['name' => 'Tedarikçiler']);

        $this->assertNotSame($first->slug, $second->slug);
    }

    // ── Süzgeçler ──

    /**
     * Kaynak, kişinin listeye nasıl girdiğini söyler: siteden mi geldi, ben mi
     * ekledim, Excel'den mi yüklendi.
     */
    public function test_the_source_filter_narrows_the_table(): void
    {
        $service = app(SubscriberService::class);
        $service->subscribe('formdan@ornek.com', 'Form', 'Kişi', 'tr', 'form');
        $service->subscribe('panelden@ornek.com', 'Panel', 'Kişi', 'tr', 'panel');

        $emails = $this->listedEmails(['source' => 'form']);

        $this->assertSame(['formdan@ornek.com'], $emails);
    }

    /**
     * Hiçbir listede olmayanlar liste hedefli kampanyalarda gözden kaçıyor;
     * sayfanın bunları gösterebilmesi gerekiyor.
     */
    public function test_the_unlisted_filter_finds_subscribers_outside_every_list(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);

        $service->subscribe('listede@ornek.com', 'Listede', 'Kişi', 'tr', 'panel', [$suppliers->id]);
        $service->subscribe('listesiz@ornek.com', 'Listesiz', 'Kişi', 'tr', 'panel');

        $this->assertSame(['listesiz@ornek.com'], $this->listedEmails(['unlisted' => 1]));
        $this->assertSame(1, app(SubscriberService::class)->stats()['unlisted']);
    }

    public function test_the_date_range_narrows_the_table(): void
    {
        $service = app(SubscriberService::class);

        $old = $service->subscribe('eski@ornek.com', 'Eski', 'Kayıt');
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $service->subscribe('yeni@ornek.com', 'Yeni', 'Kayıt');

        $emails = $this->listedEmails([
            'from' => now()->subDays(3)->toDateString(),
            'to'   => now()->toDateString(),
        ]);

        $this->assertSame(['yeni@ornek.com'], $emails);
    }

    public function test_the_search_looks_at_the_address_and_both_names(): void
    {
        $service = app(SubscriberService::class);
        $service->subscribe('ahmet@ornek.com', 'Ahmet', 'Yılmaz');
        $service->subscribe('baskasi@ornek.com', 'Başka', 'Kişi');

        $this->assertSame(['ahmet@ornek.com'], $this->listedEmails(['search' => 'Yılmaz']));
        $this->assertSame(['ahmet@ornek.com'], $this->listedEmails(['search' => 'ahmet@']));
    }

    /**
     * Arama joker karakter almamalı: "%" yazan biri tüm tabloyu değil, içinde
     * yüzde işareti geçen kayıtları görür.
     */
    public function test_the_search_treats_wildcards_as_plain_text(): void
    {
        app(SubscriberService::class)->subscribe('ahmet@ornek.com', 'Ahmet', 'Yılmaz');

        $this->assertSame([], $this->listedEmails(['search' => '%']));
    }

    public function test_sorting_by_address_orders_the_table(): void
    {
        $service = app(SubscriberService::class);
        $service->subscribe('zeynep@ornek.com', 'Zeynep', 'Ak');
        $service->subscribe('ahmet@ornek.com', 'Ahmet', 'Yılmaz');

        $this->assertSame(['ahmet@ornek.com', 'zeynep@ornek.com'], $this->listedEmails(['sort' => 'email']));
    }

    /**
     * Tanınmayan sıralama değeri sorguyu bozmamalı.
     */
    public function test_an_unknown_sort_falls_back_to_the_default(): void
    {
        $response = $this->actingAs($this->manager())
            ->get(route('admin.subscribers.index', ['sort' => 'drop table']))
            ->assertOk();

        $this->assertSame('', $response->viewData('filters')['sort']);
    }

    /**
     * Liste sekmesi seçiliyken süzgeç değiştirmek sekmeden düşürmemeli.
     */
    public function test_a_filter_composes_with_the_selected_list(): void
    {
        $suppliers = $this->lists()->create(['name' => 'Tedarikçiler']);
        $service = app(SubscriberService::class);

        $service->subscribe('elle@tedarik.com', 'Elle', 'Eklenen', 'tr', 'panel', [$suppliers->id]);
        $service->subscribe('excelden@tedarik.com', 'Excel', 'Yüklenen', 'tr', 'import', [$suppliers->id]);
        $service->subscribe('excelden@baska.com', 'Excel', 'Yüklenen', 'tr', 'import');

        $emails = $this->listedEmails(['list_id' => $suppliers->id, 'source' => 'import']);

        $this->assertSame(['excelden@tedarik.com'], $emails);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<int, string>
     */
    private function listedEmails(array $query): array
    {
        return $this->actingAs($this->manager())
            ->get(route('admin.subscribers.index', $query))
            ->assertOk()
            ->viewData('subscribers')
            ->pluck('email')
            ->all();
    }
}
