<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ErrorLogService;
use App\Services\ExceptionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sunucu hataları panelden okunabiliyor.
 *
 * Eskiden hata üç yere gidiyordu ve üçü de eksikti: Telegram ile bildirim
 * merkezi on dakikalık kısma yüzünden **tekrarları hiç göstermiyor**,
 * `storage/logs/laravel.log` ise panelden okunamıyor. Yönetici "bir hata
 * olmuş" bilgisine ulaşıyor ama kaç kez olduğunu, nereden geldiğini ve
 * düzelip düzelmediğini göremiyordu.
 *
 * @see \Tests\Feature\ExceptionNotificationTest bildirimin kendisi orada
 */
final class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    private function service(): ErrorLogService
    {
        return app(ErrorLogService::class);
    }

    private function admin(): User
    {
        $this->seedAuthorization();

        $user = User::create([
            'first_name' => 'Hata', 'last_name' => 'Yoneticisi',
            'email' => 'hata@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }

    // ── Kayıt ──

    public function test_an_error_is_recorded(): void
    {
        $this->service()->record(new \RuntimeException('Sipariş bulunamadı'));

        $this->assertDatabaseHas('error_logs', [
            'exception'   => \RuntimeException::class,
            'message'     => 'Sipariş bulunamadı',
            'occurrences' => 1,
        ]);
    }

    /**
     * Asıl kazanç bu: aynı hata tek satırda toplanıyor ve sayaç artıyor.
     * Satır başına bir kayıt yazılsaydı sıcak bir sayfadaki döngüsel hata
     * tabloyu tek başına doldururdu.
     */
    public function test_the_same_error_increments_one_row(): void
    {
        $throw = static fn (): \RuntimeException => new \RuntimeException('Aynı kusur');

        // Aynı satırdan üretilmesi şart: parmak izi tür + dosya + satır.
        for ($i = 0; $i < 3; $i++) {
            $this->service()->record($throw());
        }

        $this->assertSame(1, ErrorLog::query()->count());
        $this->assertSame(3, (int) ErrorLog::query()->value('occurrences'));
    }

    public function test_different_errors_get_their_own_rows(): void
    {
        $this->service()->record(new \RuntimeException('Bir'));
        $this->service()->record(new \InvalidArgumentException('İki'));

        $this->assertSame(2, ErrorLog::query()->count());
    }

    /**
     * Mesaj parmak izine kasten girmiyor: aynı kusur her istekte farklı mesaj
     * üretebiliyor ("User 41 not found", "User 87 not found") ve mesaja bakan
     * bir parmak izi listeyi aynı hatanın binlerce kopyasıyla doldururdu.
     */
    public function test_the_message_does_not_split_the_row(): void
    {
        $make = static fn (int $id): \RuntimeException => new \RuntimeException("Kullanıcı {$id} bulunamadı");

        $this->service()->record($make(41));
        $this->service()->record($make(87));

        $this->assertSame(1, ErrorLog::query()->count());
        // Son görülen mesaj saklanıyor; eskisi değil.
        $this->assertSame('Kullanıcı 87 bulunamadı', ErrorLog::query()->value('message'));
    }

    /**
     * Yığın izi zincirin tamamını taşımalı: "bağlantı kurulamadı" hatasının
     * gerçek sebebi çoğu zaman iki katman aşağıda duruyor.
     */
    public function test_the_trace_keeps_the_previous_exception(): void
    {
        $this->service()->record(
            new \RuntimeException('Dış katman', 0, new \LogicException('Asıl sebep')),
        );

        $trace = (string) ErrorLog::query()->value('trace');

        $this->assertStringContainsString('Asıl sebep', $trace);
        $this->assertStringContainsString('Önceki hata', $trace);
    }

    /**
     * Yığın izi mutlak yol taşımamalı: paylaşımlı hosting'in yolu
     * (`/home/musteri123/public_html/…`) hosting kullanıcı adını ekrana
     * taşıyor ve satırlar ekrana sığmıyor.
     */
    public function test_the_trace_is_relative_to_the_project_root(): void
    {
        $this->service()->record(new \RuntimeException('Yol sınaması'));

        $trace = (string) ErrorLog::query()->value('trace');

        $this->assertStringNotContainsString(base_path() . DIRECTORY_SEPARATOR, $trace);
        // Kırpma izi boşaltmamalı: kareler proje köküne göre yazılı duruyor.
        $this->assertStringContainsString('vendor' . DIRECTORY_SEPARATOR . 'phpunit', $trace);
    }

    /**
     * Buraya hata işlemenin ortasından geliniyor. Veritabanı düştüğünde
     * —500'lerin en sık sebebi bu— kayıt sessizce başarısız olmalı; buradan
     * fırlayan bir istisna asıl hatanın yerini alır ve yönetici yanlış şeye
     * bakar.
     */
    public function test_recording_never_throws(): void
    {
        // Yazmanın gerçekten düştüğü hâl. `DB::shouldReceive` burada işe
        // yaramıyor: Eloquent bağlantıyı cepheden değil çözümleyiciden alıyor
        // ve sahte cephe sessizce atlanıyordu — sınav yeşil görünüp hiçbir şey
        // kanıtlamıyordu.
        Schema::drop('error_logs');

        $this->assertNull($this->service()->record(new \RuntimeException('Bir şey')));
    }

    // ── Bildirimle ilişkisi ──

    /**
     * Bildirim kısılıyor, kayıt kısılmıyor. Kaybedilen bilgi tam olarak buydu.
     */
    public function test_the_notification_throttle_does_not_swallow_the_count(): void
    {
        $notifier = app(ExceptionNotifier::class);
        $throw = static fn (): \RuntimeException => new \RuntimeException('Döngüdeki hata');

        for ($i = 0; $i < 5; $i++) {
            $notifier->notify($throw());
        }

        // Bildirim merkezine bir kez düşüyor…
        $this->assertSame(1, DB::table('admin_notifications')->where('type', 'exception')->count());

        // …ama beş tekrarın hepsi sayıldı.
        $this->assertSame(5, (int) ErrorLog::query()->value('occurrences'));
    }

    public function test_the_notification_links_to_the_error(): void
    {
        Cache::flush();

        app(ExceptionNotifier::class)->notify(new \RuntimeException('Bağlantı sınaması'));

        $log = ErrorLog::query()->firstOrFail();
        $url = (string) DB::table('admin_notifications')->where('type', 'exception')->value('action_url');

        $this->assertStringContainsString('hata-kayitlari/' . $log->id, $url);
    }

    // ── Çözüldü / yeniden açıldı ──

    public function test_a_resolved_error_reopens_when_it_happens_again(): void
    {
        $throw = static fn (): \RuntimeException => new \RuntimeException('Geri dönen kusur');

        $admin = $this->admin();

        $log = $this->service()->record($throw());
        $this->service()->resolve($log, $admin->id);

        $this->assertNotNull($log->fresh()?->resolved_at);

        $this->service()->record($throw());

        // Düzeldiği sanılan bir kusur geri döndüyse listede görünmeli.
        $this->assertNull($log->fresh()?->resolved_at);
    }

    /**
     * Silinen bir kayıt, hata devam ediyorsa geri gelmeli — yoksa yönetici
     * satırı temizledikten sonra aynı hata görünmez olurdu.
     */
    public function test_a_deleted_row_comes_back_when_the_error_repeats(): void
    {
        $throw = static fn (): \RuntimeException => new \RuntimeException('Silinip geri gelen');

        $log = $this->service()->record($throw());
        $log->delete();

        $this->service()->record($throw());

        $this->assertSame(1, ErrorLog::query()->count());
        $this->assertNull(ErrorLog::withTrashed()->find($log->id)?->deleted_at);
    }

    // ── Ekran ──

    public function test_the_screen_lists_open_errors_first(): void
    {
        ErrorLog::factory()->create(['message' => 'Açık kusur']);
        ErrorLog::factory()->resolved()->create(['message' => 'Kapanmış kusur']);

        $this->actingAs($this->admin())
            ->get(route('admin.error-logs.index'))
            ->assertOk()
            ->assertSee('Açık kusur')
            // Varsayılan sekme "açık": ekranı açanın sorusu "şu an neyim bozuk".
            ->assertDontSee('Kapanmış kusur');
    }

    public function test_the_resolved_tab_shows_resolved_errors(): void
    {
        ErrorLog::factory()->resolved()->create(['message' => 'Kapanmış kusur']);

        $this->actingAs($this->admin())
            ->get(route('admin.error-logs.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('Kapanmış kusur');
    }

    /**
     * Paket içinde patlayan hatanın çözümü çoğu zaman onu çağıran kendi
     * kodumuzda; ayrım listeyi hızlı daraltıyor.
     */
    public function test_the_source_filter_separates_project_code_from_packages(): void
    {
        ErrorLog::factory()->create([
            'fingerprint' => md5('proje'),
            'file'        => base_path('app/Services/OrderService.php'),
            'message'     => 'Proje kodundan',
        ]);
        ErrorLog::factory()->create([
            'fingerprint' => md5('paket'),
            'file'        => base_path('vendor/laravel/framework/src/Foo.php'),
            'message'     => 'Paketten',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.error-logs.index', ['source' => 'app']))
            ->assertOk()
            ->assertSee('Proje kodundan')
            ->assertDontSee('Paketten');

        $this->actingAs($admin)
            ->get(route('admin.error-logs.index', ['source' => 'vendor']))
            ->assertOk()
            ->assertSee('Paketten')
            ->assertDontSee('Proje kodundan');
    }

    public function test_the_detail_screen_shows_the_trace(): void
    {
        $log = ErrorLog::factory()->create(['trace' => 'YIGIN-IZI-ISARETI']);

        $this->actingAs($this->admin())
            ->get(route('admin.error-logs.show', $log->id))
            ->assertOk()
            ->assertSee('YIGIN-IZI-ISARETI');
    }

    public function test_an_admin_can_resolve_and_reopen(): void
    {
        $admin = $this->admin();
        $log = ErrorLog::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.error-logs.resolve', $log->id))
            ->assertRedirect();

        $this->assertSame($admin->id, $log->fresh()?->resolved_by);

        $this->actingAs($admin)
            ->patch(route('admin.error-logs.reopen', $log->id))
            ->assertRedirect();

        $this->assertNull($log->fresh()?->resolved_at);
    }

    public function test_purging_only_removes_resolved_rows(): void
    {
        ErrorLog::factory()->create(['message' => 'Açık kalmalı']);
        ErrorLog::factory()->resolved()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.error-logs.purge'))
            ->assertRedirect();

        $this->assertSame(1, ErrorLog::query()->count());
        $this->assertSame('Açık kalmalı', ErrorLog::query()->value('message'));
    }

    // ── Temizlik ──

    /**
     * Ölçüt son görülme: aylardır tekrar eden bir hata "eski" değil, açık.
     * İlk görülmeye baksaydı en inatçı kusurlar sessizce listeden düşerdi.
     */
    public function test_pruning_keeps_an_old_error_that_still_happens(): void
    {
        ErrorLog::factory()->create([
            'first_seen_at' => now()->subDays(300),
            'last_seen_at'  => now()->subDay(),
        ]);
        ErrorLog::factory()->create([
            'first_seen_at' => now()->subDays(300),
            'last_seen_at'  => now()->subDays(200),
        ]);

        $this->assertSame(1, $this->service()->prune());
        $this->assertSame(1, ErrorLog::query()->count());
    }
}
