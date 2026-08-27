<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationLevel;
use App\Models\AdminNotification;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bildirim merkezi — liste, süzgeçler ve toplu işlemler.
 *
 * Bildirimler ya herkese açık (user_id null) ya da tek bir yöneticiye özeldir;
 * buradaki asıl mesele, kimsenin başkasına yazılmış bildirimi görmemesi ve
 * silememesidir.
 */
class NotificationCenterPageTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $slug, string $email): User
    {
        $this->seedAuthorization();

        $user = User::create([
            'first_name' => 'Test',
            'last_name'  => ucfirst($slug),
            'email'      => $email,
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $user->roles()->attach(Role::where('slug', $slug)->firstOrFail());

        return $user;
    }

    private function notify(array $attributes = []): AdminNotification
    {
        return AdminNotification::create(array_merge([
            'user_id'    => null,
            'type'       => 'backup_completed',
            'level'      => NotificationLevel::Success,
            'title'      => 'Yedek alındı',
            'message'    => 'Toplam 12 MB',
            'created_at' => now(),
        ], $attributes));
    }

    public function test_the_page_counts_what_is_in_the_list(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $this->notify(['read_at' => null]);
        $this->notify(['read_at' => now(), 'level' => NotificationLevel::Info]);
        $this->notify(['level' => NotificationLevel::Critical, 'created_at' => now()->subDays(3)]);

        $response = $this->actingAs($admin)->get(route('admin.notifications.index'))->assertOk();

        $stats = $response->viewData('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['unread']);
        $this->assertSame(2, $stats['today']);
        $this->assertSame(1, $stats['critical']);
    }

    public function test_the_level_tab_narrows_the_list(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $this->notify(['title' => 'Kritik olay', 'level' => NotificationLevel::Critical]);
        $this->notify(['title' => 'Sıradan bilgi', 'level' => NotificationLevel::Info]);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', ['level' => 'critical']))
            ->assertOk()
            ->assertSee('Kritik olay')
            ->assertDontSee('Sıradan bilgi');
    }

    public function test_the_search_looks_inside_the_title_and_the_message(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $this->notify(['title' => 'Yedek alındı', 'message' => 'Disk üzerinde 91 MB']);
        $this->notify(['title' => 'İçerik yayınlandı', 'message' => 'Kampanya yazısı']);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', ['q' => 'kampanya']))
            ->assertOk()
            ->assertSee('İçerik yayınlandı')
            ->assertDontSee('Yedek alındı');
    }

    public function test_another_admins_notification_is_not_listed(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');
        $other = $this->userWithRole('admin', 'nt-other@example.com');

        $this->notify(['user_id' => $other->id, 'title' => 'Sadece diğerine']);
        $this->notify(['title' => 'Herkese açık']);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Herkese açık')
            ->assertDontSee('Sadece diğerine');
    }

    public function test_reading_can_be_undone(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');
        $notification = $this->notify(['read_at' => null]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.mark-read', $notification->id))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($admin)
            ->post(route('admin.notifications.mark-unread', $notification->id))
            ->assertOk();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_the_selected_ones_are_marked_read(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $first = $this->notify(['read_at' => null]);
        $second = $this->notify(['read_at' => null]);
        $untouched = $this->notify(['read_at' => null]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.bulk-mark-read'), ['ids' => [$first->id, $second->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '2 bildirim okundu olarak işaretlendi.');

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($untouched->fresh()->read_at);
    }

    public function test_the_selected_ones_are_deleted(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $doomed = $this->notify(['title' => 'Silinecek']);
        $kept = $this->notify(['title' => 'Kalacak']);

        $this->actingAs($admin)
            ->delete(route('admin.notifications.bulk-destroy'), ['ids' => [$doomed->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '1 bildirim silindi.');

        $this->assertSoftDeleted('admin_notifications', ['id' => $doomed->id]);
        $this->assertNotSoftDeleted('admin_notifications', ['id' => $kept->id]);
    }

    /**
     * Seçim listeden yapılıyor ama id doğrudan istekten geliyor; başkasının
     * bildirimi seçilmiş gibi gönderilse bile silinmemeli.
     */
    public function test_a_bulk_delete_cannot_reach_another_admins_notification(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');
        $other = $this->userWithRole('admin', 'nt-other@example.com');

        $foreign = $this->notify(['user_id' => $other->id, 'title' => 'Diğerinin bildirimi']);

        $this->actingAs($admin)
            ->delete(route('admin.notifications.bulk-destroy'), ['ids' => [$foreign->id]])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('admin_notifications', ['id' => $foreign->id]);
    }

    public function test_the_whole_list_can_be_cleared(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $this->notify();
        $this->notify();

        $this->actingAs($admin)
            ->delete(route('admin.notifications.destroy-all'))
            ->assertRedirect(route('admin.notifications.index'))
            ->assertSessionHas('success', '2 bildirim silindi.');

        $this->assertSame(0, AdminNotification::query()->count());
    }

    /**
     * Editör bildirimi okundu işaretleyebilir ama silemez.
     */
    public function test_a_role_without_the_delete_permission_cannot_bulk_delete(): void
    {
        $editor = $this->userWithRole('editor', 'nt-editor@example.com');
        $notification = $this->notify();

        $this->actingAs($editor)
            ->delete(route('admin.notifications.bulk-destroy'), ['ids' => [$notification->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted('admin_notifications', ['id' => $notification->id]);

        $this->actingAs($editor)
            ->post(route('admin.notifications.bulk-mark-read'), ['ids' => [$notification->id]])
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_bulk_action_without_a_selection_is_rejected(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $this->actingAs($admin)
            ->from(route('admin.notifications.index'))
            ->delete(route('admin.notifications.bulk-destroy'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    /**
     * Sayfalama panelin kendi bileşeniyle çiziliyor; Laravel'in varsayılan
     * görünümü panele yabancı düşüyor ve çeviri anahtarlarını ham gösteriyor.
     */
    public function test_the_pager_is_the_panels_own(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        foreach (range(1, 35) as $i) {
            $this->notify(['title' => "Bildirim {$i}"]);
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('cl-page-btn', $html);
        $this->assertStringNotContainsString('pagination.previous', $html);
    }

    /**
     * Aynı işin başarılı ve başarısız hâli tek satırda toplanmalı: okuyan için
     * ikisi de "Yedekleme".
     */
    public function test_the_summary_merges_types_that_share_a_label(): void
    {
        $admin = $this->userWithRole('admin', 'nt-admin@example.com');

        $this->notify(['type' => 'backup_completed']);
        $this->notify(['type' => 'backup_failed', 'level' => NotificationLevel::Error]);
        $this->notify(['type' => 'post_failed', 'level' => NotificationLevel::Info]);

        $summary = NotificationCenter::typeSummary($admin->id);

        $labels = array_column($summary, 'label');

        $this->assertSame(['Yedekleme', 'İçerik'], $labels);
        $this->assertSame(2, $summary[0]['count']);
        $this->assertSame(100, $summary[0]['percent']);
    }
}
