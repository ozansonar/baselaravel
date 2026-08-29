<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\EmailAlreadyTakenException;
use App\Models\Role;
use App\Models\User;
use App\Rules\UserEmail;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * E-posta benzersizliği yalnız yaşayan kullanıcılar arasında geçerli.
 *
 * users.email düz bir UNIQUE taşıyordu ve kısıt silinmiş satırları da
 * sayıyordu: silinen bir kullanıcı aynı adresle geri dönemiyordu. Doğrulama
 * "zaten kayıtlı" diyordu, doğrulamayı atlatan bir yol bulunsa veritabanı
 * "Duplicate entry" ile düşüyordu. Oysa soft delete satırı kayıtta tutmak
 * demek, adresi sonsuza dek işgal etmek değil.
 *
 * Kısıt e-postanın kendisinden alınıp yalnız yaşayan satırlarda dolu olan
 * üretilmiş bir sütuna taşındı; iki katman da (veritabanı ve doğrulama)
 * burada ayrı ayrı sınanıyor.
 */
final class UserEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    // ── Veritabanı katmanı ──

    public function test_two_live_users_cannot_share_an_address(): void
    {
        User::factory()->create(['email' => 'ayni@ornek.com']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        User::factory()->create(['email' => 'ayni@ornek.com']);
    }

    public function test_a_deleted_user_releases_their_address(): void
    {
        $eski = User::factory()->create(['email' => 'serbest@ornek.com']);
        $eski->delete();

        $yeni = User::factory()->create(['email' => 'serbest@ornek.com']);

        $this->assertTrue($yeni->exists);
        $this->assertSame(2, User::withTrashed()->where('email', 'serbest@ornek.com')->count());
    }

    /** Aynı adres birden çok kez silinmiş olabilir; çöp bunu taşımalı. */
    public function test_many_deleted_rows_may_hold_the_same_address(): void
    {
        foreach (range(1, 3) as $i) {
            User::factory()->create(['email' => 'tekrar@ornek.com'])->delete();
        }

        $this->assertSame(3, User::onlyTrashed()->where('email', 'tekrar@ornek.com')->count());
    }

    public function test_the_index_only_watches_live_rows(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'email_active'),
            'Kısıtı taşıyan üretilmiş sütun yok',
        );
    }

    // ── Doğrulama katmanı ──

    public function test_validation_lets_a_deleted_users_address_through(): void
    {
        User::factory()->create(['email' => 'geri@ornek.com'])->delete();

        $validator = Validator::make(
            ['email' => 'geri@ornek.com'],
            ['email' => [UserEmail::unique()]],
        );

        $this->assertFalse($validator->fails(), 'Silinmiş kullanıcının adresi hâlâ reddediliyor');
    }

    public function test_validation_still_blocks_a_live_users_address(): void
    {
        User::factory()->create(['email' => 'dolu@ornek.com']);

        $validator = Validator::make(
            ['email' => 'dolu@ornek.com'],
            ['email' => [UserEmail::unique()]],
        );

        $this->assertTrue($validator->fails(), 'Yaşayan kullanıcının adresi kabul edildi');
    }

    public function test_a_user_keeps_their_own_address_when_editing(): void
    {
        $user = User::factory()->create(['email' => 'kendi@ornek.com']);

        $validator = Validator::make(
            ['email' => 'kendi@ornek.com'],
            ['email' => [UserEmail::unique($user->id)]],
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * Sütun 191 karakter (Schema::defaultStringLength). Kural 255 diyordu:
     * 200 karakterlik bir adres doğrulamadan geçip veritabanında düşüyordu,
     * yani kullanıcı doğrulama hatası değil 500 görüyordu.
     */
    public function test_the_length_rule_matches_the_column_width(): void
    {
        $this->assertSame(191, UserEmail::MAX_LENGTH);

        $uzun = str_repeat('a', 190) . '@ornek.com';

        $validator = Validator::make(
            ['email' => $uzun],
            ['email' => ['max:' . UserEmail::MAX_LENGTH]],
        );

        $this->assertTrue($validator->fails(), 'Sütuna sığmayan adres doğrulamadan geçti');
    }

    /** Formdaki sınır sunucudakiyle birebir aynı olmalı; gevşek olamaz. */
    public function test_the_forms_carry_the_same_limit_as_the_server(): void
    {
        foreach ([
            'auth/register.blade.php',
            'admin/users/_form.blade.php',
            'admin/profile/edit.blade.php',
            'account/profile.blade.php',
        ] as $view) {
            $html = (string) file_get_contents(resource_path('views/' . $view));

            $this->assertStringContainsString(
                'custom[email],maxSize[' . UserEmail::MAX_LENGTH . ']',
                $html,
                "{$view} → e-posta alanının sınırı sunucudakinden farklı",
            );
        }
    }

    // ── Kayıt ekranı ──

    public function test_someone_can_register_again_after_their_account_was_deleted(): void
    {
        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        User::factory()->create(['email' => 'donen@ornek.com'])->delete();

        $this->post(route('register', ['locale' => 'tr']), [
            'first_name'            => 'Ali',
            'last_name'             => 'Veli',
            'email'                 => 'donen@ornek.com',
            'password'              => 'GucluParola123!',
            'password_confirmation' => 'GucluParola123!',
            'terms'                 => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'donen@ornek.com', 'deleted_at' => null]);
    }

    // ── Geri yükleme ──

    public function test_restoring_a_user_whose_address_was_taken_is_refused_with_a_readable_message(): void
    {
        $eski = User::factory()->create(['email' => 'devredilen@ornek.com']);
        $eski->delete();

        User::factory()->create(['email' => 'devredilen@ornek.com']);

        $this->expectException(EmailAlreadyTakenException::class);

        app(UserService::class)->restore($eski->fresh());
    }

    public function test_restoring_is_fine_while_the_address_is_still_free(): void
    {
        $user = User::factory()->create(['email' => 'bos@ornek.com']);
        $user->delete();

        app(UserService::class)->restore($user->fresh());

        $this->assertNotSoftDeleted($user);
    }

    /**
     * Toplu geri yükleme bir çakışma yüzünden tamamen düşmemeli: çakışan
     * atlanır, gerisi geri yüklenir.
     */
    public function test_a_bulk_restore_skips_the_conflicting_one_and_finishes_the_rest(): void
    {
        $catisan = User::factory()->create(['email' => 'catisan@ornek.com']);
        $temiz = User::factory()->create(['email' => 'temiz@ornek.com']);

        $catisan->delete();
        $temiz->delete();

        User::factory()->create(['email' => 'catisan@ornek.com']);

        $geriYuklenen = app(UserService::class)->restoreMany([$catisan->id, $temiz->id]);

        $this->assertSame(1, $geriYuklenen);
        $this->assertNotSoftDeleted($temiz);
        $this->assertSoftDeleted($catisan);
    }

    /**
     * Çöpte aynı adresi taşıyan iki kayıt birden geri yüklenirse bu kez kendi
     * aralarında çakışırlar; her adresten yalnız biri geçmeli.
     */
    public function test_two_deleted_rows_with_the_same_address_do_not_both_come_back(): void
    {
        $ilk = User::factory()->create(['email' => 'ikiz@ornek.com']);
        $ilk->delete();

        $ikinci = User::factory()->create(['email' => 'ikiz@ornek.com']);
        $ikinci->delete();

        $geriYuklenen = app(UserService::class)->restoreMany([$ilk->id, $ikinci->id]);

        $this->assertSame(1, $geriYuklenen);
        $this->assertSame(1, User::where('email', 'ikiz@ornek.com')->count());
    }

    /** Yönetici ham SQL hatası değil, ne yapacağını söyleyen bir uyarı görmeli. */
    public function test_the_admin_screen_explains_why_a_restore_was_refused(): void
    {
        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        $eski = User::factory()->create(['email' => 'iade@ornek.com']);
        $eski->delete();
        User::factory()->create(['email' => 'iade@ornek.com']);

        $this->actingAs($admin)
            ->patch(route('admin.users.restore', $eski->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($eski);
    }
}
