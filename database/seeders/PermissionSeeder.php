<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionKey as P;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the permission rows from the PermissionKey enum and hands each role the
 * abilities it had under the previous policy-based matrix, so switching to
 * database permissions does not change what anyone can do.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPermissions();
        $this->assignToRoles();
    }

    private function syncPermissions(): void
    {
        foreach (P::cases() as $index => $case) {
            Permission::updateOrCreate(
                ['key' => $case->value],
                [
                    'name'       => $case->label(),
                    'group'      => $case->group()->value,
                    'sort_order' => $index,
                ],
            );
        }
    }

    private function assignToRoles(): void
    {
        foreach ($this->matrix() as $roleSlug => $keys) {
            $role = Role::where('slug', $roleSlug)->first();

            if ($role === null) {
                continue;
            }

            $ids = Permission::whereIn('key', array_map(
                static fn (P $case): string => $case->value,
                $keys,
            ))->pluck('id');

            $role->permissions()->sync($ids);
        }
    }

    /**
     * @return array<string, array<int, P>>
     */
    private function matrix(): array
    {
        $editor = [
            P::PagesView, P::PagesManage,
            P::BlogPostsView, P::BlogPostsManage,
            P::BlogCategoriesView, P::BlogCategoriesManage,
            P::GalleryView, P::GalleryManage,
            P::FaqsView, P::FaqsManage,
            P::SlidersView, P::SlidersManage,
            P::PopupsView, P::PopupsManage,
            P::MenusView, P::MenusManage,
            P::FilesView, P::FilesManage, P::EditorUpload,
            P::MessagesView, P::MessagesReply,
            P::CommentsView, P::CommentsModerate,
            P::NotificationsView, P::NotificationsManage,
            P::AnalyticsView,
        ];

        $moderator = [
            P::MessagesView, P::MessagesReply,
            P::CommentsView, P::CommentsModerate,
            P::NotificationsView, P::NotificationsManage,
        ];

        return [
            // The admin keeps everything, including anything added later.
            UserRole::Admin->value     => P::cases(),
            UserRole::Editor->value    => $editor,
            UserRole::Moderator->value => $moderator,
            UserRole::User->value      => [],
            UserRole::Viewer->value    => [],
        ];
    }
}
