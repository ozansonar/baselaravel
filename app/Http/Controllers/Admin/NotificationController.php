<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\NotificationCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/bildirimler — Admin bildirim merkezi.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AdminNotification::class);

        $userId = $request->user()?->id;

        $query = AdminNotification::query()->forUser($userId);

        if ($request->filled('level')) {
            $query->where('level', $request->string('level')->toString());
        }
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'unreadCount'   => NotificationCenter::unreadCount($userId),
        ]);
    }

    /**
     * Header dropdown için en son 10 bildirim — JSON.
     */
    public function recent(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdminNotification::class);

        $userId = $request->user()?->id;

        $items = AdminNotification::query()
            ->forUser($userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'unread_count' => NotificationCenter::unreadCount($userId),
            'items' => $items->map(static fn ($n) => [
                'id'          => $n->id,
                'type'        => $n->type,
                'level'       => $n->level,
                'icon'        => $n->levelIcon(),
                'badge_class' => $n->levelBadgeClass(),
                'title'       => $n->title,
                'message'     => $n->message,
                'action_url'  => $n->action_url,
                'time'        => $n->created_at->diffForHumans(),
                'is_unread'   => $n->isUnread(),
            ])->all(),
        ]);
    }

    public function markRead(Request $request, AdminNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        NotificationCenter::markRead($notification->id, $request->user()?->id);
        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdminNotification::class);

        $count = NotificationCenter::markAllRead($request->user()?->id);
        return response()->json(['success' => true, 'count' => $count]);
    }

    public function destroy(Request $request, AdminNotification $notification): RedirectResponse
    {
        $this->authorize('delete', $notification);

        // Sadece kendi bildirimi veya broadcast olanı silebilir
        if ($notification->user_id !== null && $notification->user_id !== $request->user()?->id) {
            return redirect()->back()->with('error', 'Bu bildirimi silme yetkin yok.');
        }
        $notification->delete();

        return redirect()->route('admin.notifications.index')->with('success', 'Bildirim silindi.');
    }
}
