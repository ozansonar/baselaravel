<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use App\Services\SubscriberListService;
use App\Services\SubscriberService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bir e-posta adresi tek bir abone kaydına karşılık gelir.
 *
 * Tekillik yalnızca "önce ara sonra yaz" biçiminde uygulanıyordu; iki istek
 * aynı anda gelirse ikisi de aramada boş bulup satırı açabiliyor ve aynı adres
 * aynı listede iki kez görünüyordu. Kısıt artık veritabanında.
 */
class SubscriberUniqueEmailTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriberService
    {
        return app(SubscriberService::class);
    }

    /**
     * Kısıtın kendisi: aynı adresle ikinci bir satır açılamaz.
     */
    public function test_the_database_refuses_a_second_row_for_the_same_address(): void
    {
        Subscriber::create(['email' => 'ahmet@ornek.com', 'status' => SubscriberStatus::Subscribed]);

        $this->expectException(UniqueConstraintViolationException::class);

        Subscriber::create(['email' => 'ahmet@ornek.com', 'status' => SubscriberStatus::Subscribed]);
    }

    /**
     * Aynı adres iki kez kaydolduğunda kayıt çoğalmaz, var olan güncellenir.
     */
    public function test_subscribing_twice_keeps_one_row(): void
    {
        $lists = app(SubscriberListService::class);
        $suppliers = $lists->create(['name' => 'Tedarikçiler']);
        $newsletter = $lists->default();

        $first = $this->service()->subscribe('ahmet@ornek.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$suppliers->id]);
        $second = $this->service()->subscribe('ahmet@ornek.com', null, null, 'tr', 'form', [$newsletter->id]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Subscriber::where('email', 'ahmet@ornek.com')->count());
    }

    /**
     * Büyük harfle yazılan adres ayrı bir kayıt açmamalı.
     */
    public function test_the_address_case_does_not_create_a_second_row(): void
    {
        $this->service()->subscribe('Ahmet@Ornek.com');
        $this->service()->subscribe('ahmet@ornek.com');

        $this->assertSame(1, Subscriber::count());
        $this->assertSame('ahmet@ornek.com', Subscriber::first()->email);
    }

    /**
     * Bir listeye aynı kişi iki kez eklenemez.
     */
    public function test_a_subscriber_cannot_be_added_to_the_same_list_twice(): void
    {
        $list = app(SubscriberListService::class)->create(['name' => 'Tedarikçiler']);
        $subscriber = $this->service()->subscribe('ahmet@ornek.com', 'Ahmet', 'Yılmaz', 'tr', 'panel', [$list->id]);

        // Aynı üyelik üç ayrı yoldan tekrar deneniyor.
        $this->service()->subscribe('ahmet@ornek.com', null, null, null, 'form', [$list->id]);
        app(SubscriberListService::class)->addMany($list, [$subscriber->id]);
        app(SubscriberListService::class)->addMany($list, [$subscriber->id, $subscriber->id]);

        $this->assertSame(1, DB::table('subscriber_list_subscriber')
            ->where('subscriber_id', $subscriber->id)
            ->where('subscriber_list_id', $list->id)
            ->count());
        $this->assertSame(1, $list->subscribers()->count());
    }

    /**
     * Aynı adres bir dosyada iki kez geçerse bir kez eklenmeli.
     */
    public function test_an_import_with_a_repeated_address_adds_it_once(): void
    {
        $list = app(SubscriberListService::class)->create(['name' => 'Tedarikçiler']);

        $this->service()->importMany([
            ['email' => 'ahmet@ornek.com', 'first_name' => 'Ahmet', 'last_name' => 'Yılmaz'],
            ['email' => 'AHMET@ornek.com', 'first_name' => 'Ahmet', 'last_name' => 'Yılmaz'],
            ['email' => 'ayse@ornek.com', 'first_name' => 'Ayşe', 'last_name' => 'Demir'],
        ], 'tr', 'import', [$list->id]);

        $this->assertSame(2, Subscriber::count());
        $this->assertSame(2, $list->subscribers()->count());
    }

    /**
     * Yarış hâli: arama boş dönerken satır başkası tarafından açılmışsa,
     * ikinci yazma hata vermek yerine var olan kaydı okumalı.
     */
    public function test_a_row_created_between_the_lookup_and_the_write_is_reused(): void
    {
        $existing = Subscriber::create([
            'email'  => 'ayni@ornek.com',
            'status' => SubscriberStatus::Subscribed,
        ]);

        // subscribe() içindeki arama, kaydı görmemiş gibi davransın diye
        // doğrudan oluşturma yolu sınanıyor.
        $method = new \ReflectionMethod(SubscriberService::class, 'createOrLoad');
        $result = $method->invoke($this->service(), 'ayni@ornek.com', [
            'status' => SubscriberStatus::Subscribed,
        ]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(1, Subscriber::count());
    }

    /**
     * Taşımadan önce açılmış kopyalar tek kayda birleşmeli; listelerin
     * birleşimi korunur.
     */
    public function test_the_migration_merges_pre_existing_duplicates(): void
    {
        $lists = app(SubscriberListService::class);
        $suppliers = $lists->create(['name' => 'Tedarikçiler']);
        $marketers = $lists->create(['name' => 'Pazarlamacılar']);

        // Kısıt kaldırılıp kopya kayıtlar elle yazılıyor, sonra taşıma
        // yeniden çalıştırılıyor.
        $this->dropUniqueIndex();

        $first = $this->rawSubscriber('kopya@ornek.com', SubscriberStatus::Subscribed);
        $second = $this->rawSubscriber('kopya@ornek.com', SubscriberStatus::Subscribed);

        DB::table('subscriber_list_subscriber')->insert([
            ['subscriber_id' => $first, 'subscriber_list_id' => $suppliers->id, 'created_at' => now(), 'updated_at' => now()],
            ['subscriber_id' => $second, 'subscriber_list_id' => $marketers->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->runUniqueMigration();

        $rows = Subscriber::withTrashed()->where('email', 'kopya@ornek.com')->get();

        $this->assertCount(1, $rows);
        $this->assertEqualsCanonicalizing(
            [$suppliers->id, $marketers->id],
            $rows->first()->lists()->pluck('subscriber_lists.id')->all(),
        );
    }

    /**
     * Birleştirme çıkmış birini yeniden abone yapmamalı: kopyalardan biri
     * ayrıldıysa kalan kayıt da ayrılmış olur.
     */
    public function test_merging_keeps_the_strictest_status(): void
    {
        $this->dropUniqueIndex();

        $this->rawSubscriber('kopya@ornek.com', SubscriberStatus::Subscribed);
        $this->rawSubscriber('kopya@ornek.com', SubscriberStatus::Unsubscribed);

        $this->runUniqueMigration();

        $this->assertSame(
            SubscriberStatus::Unsubscribed,
            Subscriber::where('email', 'kopya@ornek.com')->firstOrFail()->status,
        );
    }

    private function dropUniqueIndex(): void
    {
        \Illuminate\Support\Facades\Schema::table('subscribers', function ($table): void {
            $table->dropUnique(['email']);
        });
    }

    private function runUniqueMigration(): void
    {
        (require database_path('migrations/2026_08_27_160000_make_subscriber_emails_unique.php'))->up();
    }

    private function rawSubscriber(string $email, SubscriberStatus $status): int
    {
        return DB::table('subscribers')->insertGetId([
            'email'             => $email,
            'status'            => $status->value,
            'unsubscribe_token' => Subscriber::newToken(),
            'subscribed_at'     => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
