<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class DashboardService
{
    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return Cache::remember('admin.dashboard.stats', 300, function (): array {
            return [
                'total_users'     => User::count(),
                'total_posts'     => BlogPost::count(),
                'total_pages'     => Page::count(),
                'unread_messages' => ContactMessage::unread()->count(),
            ];
        });
    }

    /**
     * @return Collection<int, ContactMessage>
     */
    public function recentMessages(int $limit = 5): Collection
    {
        return ContactMessage::latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function recentPosts(int $limit = 5): Collection
    {
        return BlogPost::with(['category', 'author'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
