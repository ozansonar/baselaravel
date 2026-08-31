<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deactivating an account has to end the sessions it already has.
 *
 * is_active used to be read at one moment only — the login attempt — so
 * pressing "deactivate" on someone who was already signed in did nothing until
 * their session expired, and nothing at all while a "remember me" cookie was
 * alive. Permissions never had this problem because AdminMiddleware asks the
 * database on every request; is_active was the one flag nothing rechecked.
 *
 * Two mechanisms cover it and both are asserted here: the middleware turns the
 * session away on the way in, and the observer removes the session rows and the
 * remember token at the source.
 */
class InactiveUserSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::where('slug', 'admin')->value('id'));

        return $user;
    }

    /**
     * Sign in the way a visitor does, so the session carries the user id and
     * every later request has to resolve the account again.
     */
    private function signIn(User $user): void
    {
        $this->post('/tr/giris', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /**
     * A real HTTP request builds the guard from scratch; inside one test the
     * guard is a singleton and keeps the user it already resolved. Forgetting
     * it is what makes the next call read the database again, exactly as the
     * next request would in production.
     */
    private function asANewRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** Deactivate without touching the model, so nothing but the flag changes. */
    private function deactivateQuietly(User $user): void
    {
        User::where('id', $user->id)->update(['is_active' => false]);
    }

    public function test_an_active_user_keeps_their_session(): void
    {
        $admin = $this->admin();

        $this->signIn($admin);
        $this->asANewRequest();

        $this->get('/admin')->assertOk();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_deactivated_user_is_signed_out_on_their_next_panel_request(): void
    {
        $admin = $this->admin();

        $this->signIn($admin);
        $this->deactivateQuietly($admin);
        $this->asANewRequest();

        $this->get('/admin')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_front_end_is_covered_by_the_same_check(): void
    {
        $user = User::factory()->create();

        $this->signIn($user);
        $this->deactivateQuietly($user);
        $this->asANewRequest();

        $this->get('/tr')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_json_request_is_refused_rather_than_redirected(): void
    {
        $admin = $this->admin();

        $this->signIn($admin);
        $this->deactivateQuietly($admin);
        $this->asANewRequest();

        $this->getJson('/admin')->assertForbidden();

        $this->assertGuest();
    }

    public function test_a_guest_request_is_left_alone(): void
    {
        $this->get('/tr')->assertOk();
    }

    public function test_deactivating_a_user_drops_their_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'stale-token']);

        $user->update(['is_active' => false]);

        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_a_user_who_stays_active_keeps_their_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'still-valid']);

        $user->update(['first_name' => 'Değişti']);

        $this->assertSame('still-valid', $user->fresh()->remember_token);
    }

    public function test_deactivating_a_user_deletes_the_sessions_they_are_holding(): void
    {
        config(['session.driver' => 'database']);

        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->fakeSessionRow('kept-alive', $other);
        $this->fakeSessionRow('to-be-cut', $user);

        $user->update(['is_active' => false]);

        $this->assertDatabaseMissing('sessions', ['id' => 'to-be-cut']);
        $this->assertDatabaseHas('sessions', ['id' => 'kept-alive']);
    }

    public function test_deleting_a_user_deletes_the_sessions_they_are_holding(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $this->fakeSessionRow('deleted-user', $user);

        $user->delete();

        $this->assertSoftDeleted($user);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user']);
    }

    /**
     * Bulk delete goes through the query builder, so no model event fires and
     * the observer cannot help — the service has to do it itself.
     */
    public function test_bulk_delete_revokes_the_sessions_of_every_selected_user(): void
    {
        config(['session.driver' => 'database']);

        $first  = User::factory()->create(['remember_token' => 'first-token']);
        $second = User::factory()->create(['remember_token' => 'second-token']);
        $spared = User::factory()->create(['remember_token' => 'spared-token']);

        $this->fakeSessionRow('first-session', $first);
        $this->fakeSessionRow('second-session', $second);
        $this->fakeSessionRow('spared-session', $spared);

        app(UserService::class)->deleteMany([$first->id, $second->id]);

        $this->assertDatabaseMissing('sessions', ['id' => 'first-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'second-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'spared-session']);

        $this->assertNull(User::withTrashed()->find($first->id)->remember_token);
        $this->assertNull(User::withTrashed()->find($second->id)->remember_token);
        $this->assertSame('spared-token', $spared->fresh()->remember_token);
    }

    /**
     * Force deleting must not resurrect the row it just removed — writing the
     * remember token back would insert the user again.
     */
    public function test_force_deleting_a_user_does_not_bring_them_back(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $this->fakeSessionRow('gone-for-good', $user);

        $user->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'gone-for-good']);
    }

    private function fakeSessionRow(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id'            => $id,
            'user_id'       => $user->id,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'phpunit',
            'payload'       => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
