<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MailLogStatus;
use App\Models\MailLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mail logları listesindeki süzgeçler.
 *
 * Liste tek bir kutuya sığmayacak kadar büyüyor; hangi maile bakılacağı
 * alıcı, tür, tarih ve tetikleyen kullanıcı üzerinden daraltılabilmeli.
 * Buradaki testler her süzgecin gerçekten daralttığını ve sekme sayılarının
 * daraltılmış kümeyi gösterdiğini doğruluyor.
 */
class MailLogFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Mail',
            'last_name'  => 'Yöneticisi',
            'email'      => 'mail-filter-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    public function test_recipient_filter_narrows_the_list(): void
    {
        MailLog::factory()->create(['to' => 'ayse@example.com', 'subject' => 'Ayşe maili']);
        MailLog::factory()->create(['to' => 'mehmet@example.com', 'subject' => 'Mehmet maili']);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', ['recipient' => 'ayse@example.com']))
            ->assertOk()
            ->assertSee('Ayşe maili', false)
            ->assertDontSee('Mehmet maili');
    }

    public function test_mailable_filter_narrows_the_list(): void
    {
        MailLog::factory()->create(['mailable_class' => 'App\\Mail\\WelcomeMail', 'subject' => 'Hoş geldin']);
        MailLog::factory()->create(['mailable_class' => 'App\\Mail\\TestMail', 'subject' => 'Deneme maili']);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', ['mailable' => 'App\\Mail\\WelcomeMail']))
            ->assertOk()
            ->assertSee('Hoş geldin', false)
            ->assertDontSee('Deneme maili');
    }

    /**
     * Mailable sınıfı olmayan ham mailler de seçilebilmeli; boş değer
     * "süzgeç kapalı" demek olduğu için kendi anahtarları var.
     */
    public function test_raw_mails_can_be_filtered(): void
    {
        MailLog::factory()->create(['mailable_class' => null, 'subject' => 'Ham mail']);
        MailLog::factory()->create(['subject' => 'Sınıflı mail']);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', ['mailable' => 'raw']))
            ->assertOk()
            ->assertSee('Ham mail', false)
            ->assertDontSee('Sınıflı mail');
    }

    public function test_date_range_narrows_the_list(): void
    {
        MailLog::factory()->create(['subject' => 'Eski mail', 'created_at' => now()->subDays(10)]);
        MailLog::factory()->create(['subject' => 'Yeni mail', 'created_at' => now()->subDay()]);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', [
                'from' => now()->subDays(3)->toDateString(),
                'to'   => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Yeni mail', false)
            ->assertDontSee('Eski mail');
    }

    public function test_user_filter_separates_system_mails(): void
    {
        $admin = $this->admin();

        MailLog::factory()->create(['subject' => 'Kullanıcı maili', 'user_id' => $admin->id]);
        MailLog::factory()->create(['subject' => 'Sistem maili', 'user_id' => null]);

        $this->actingAs($admin)
            ->get(route('admin.mail-logs.index', ['user_id' => '0']))
            ->assertOk()
            ->assertSee('Sistem maili', false)
            ->assertDontSee('Kullanıcı maili');
    }

    /**
     * Arama joker karakter almamalı: "%" yazan biri tüm tabloyu değil,
     * içinde yüzde işareti geçen kayıtları görür.
     */
    public function test_search_treats_wildcards_as_plain_text(): void
    {
        MailLog::factory()->create(['subject' => 'Kampanya %50 indirim']);
        MailLog::factory()->create(['subject' => 'Sıradan bildirim']);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', ['search' => '%50']))
            ->assertOk()
            ->assertSee('Kampanya %50 indirim', false)
            ->assertDontSee('Sıradan bildirim');
    }

    public function test_search_also_looks_into_error_messages(): void
    {
        MailLog::factory()->failed()->create(['subject' => 'Giden mail']);
        MailLog::factory()->create(['subject' => 'Sorunsuz mail']);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', ['search' => 'SMTP']))
            ->assertOk()
            ->assertSee('Giden mail', false)
            ->assertDontSee('Sorunsuz mail');
    }

    /**
     * Sekme sayıları açık süzgeçle uyumlu olmalı: "Başarısız 1" yazıyorsa o
     * sekmeye basınca gerçekten 1 kayıt gelmeli.
     */
    public function test_status_counts_respect_the_other_filters(): void
    {
        MailLog::factory()->create(['to' => 'ayse@example.com']);
        MailLog::factory()->failed()->create(['to' => 'ayse@example.com']);
        MailLog::factory()->failed()->create(['to' => 'mehmet@example.com']);
        MailLog::factory()->failed()->create(['to' => 'mehmet@example.com']);

        $counts = app(\App\Services\MailLogService::class)
            ->statusCounts(['recipient' => 'ayse@example.com', 'status' => MailLogStatus::Failed->value]);

        $this->assertSame(1, $counts[MailLogStatus::Sent->value] ?? 0);
        $this->assertSame(1, $counts[MailLogStatus::Failed->value] ?? 0);
    }

    /**
     * Elle verilen aralık hazır aralığın önüne geçer; iki tarih süzgeci
     * birbirini daraltırsa kullanıcı hangisinin işlediğini bilemez.
     */
    public function test_explicit_range_overrides_the_quick_preset(): void
    {
        MailLog::factory()->create(['subject' => 'Geçen hafta', 'created_at' => now()->subDays(9)]);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', [
                'date_filter' => 'today',
                'from'        => now()->subDays(14)->toDateString(),
                'to'          => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Geçen hafta', false);
    }

    /**
     * Açık süzgeçler rozet olarak listelenir ve her rozetin çarpısı yalnızca
     * kendi süzgecini düşürür — diğerleri yerinde kalır.
     */
    public function test_active_filter_chips_remove_only_their_own_filter(): void
    {
        MailLog::factory()->create(['to' => 'ayse@example.com']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', [
                'recipient' => 'ayse@example.com',
                'status'    => MailLogStatus::Sent->value,
            ]))
            ->assertOk()
            ->assertSee('Açık süzgeçler')
            ->assertSee('Alıcı süzgecini kaldır')
            ->assertSee('Durum süzgecini kaldır')
            ->assertSee('Tümünü temizle');

        // Alıcı rozetinin bağlantısı durumu korumalı, alıcıyı düşürmeli.
        $response->assertSee(
            e(route('admin.mail-logs.index', ['status' => MailLogStatus::Sent->value])),
            false,
        );
    }

    /**
     * Tek süzgeç açıkken toplu temizlik rozeti gereksiz; alanın kendi
     * temizleme düğmesi zaten aynı işi yapıyor.
     */
    public function test_reset_chip_is_hidden_for_a_single_filter(): void
    {
        MailLog::factory()->create(['to' => 'ayse@example.com']);

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index', ['recipient' => 'ayse@example.com']))
            ->assertOk()
            ->assertSee('Alıcı süzgecini kaldır')
            ->assertDontSee('Tümünü temizle');
    }

    /**
     * Süzgeç kapalıyken ne rozet satırı ne de alan içi temizleme düğmeleri
     * çıkar; boş kutuların yanında ölü düğme durmaz.
     */
    public function test_no_chips_without_filters(): void
    {
        MailLog::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.mail-logs.index'))
            ->assertOk()
            ->assertDontSee('Açık süzgeçler')
            ->assertDontSee('Aramayı temizle')
            ->assertDontSee('Başlangıç tarihini temizle');
    }
}
