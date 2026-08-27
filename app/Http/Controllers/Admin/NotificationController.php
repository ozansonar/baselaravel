<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkNotificationRequest;
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

        $filters = [
            'level'       => $request->string('level')->value(),
            'unread_only' => $request->boolean('unread_only'),
            'q'           => $request->string('q')->trim()->value(),
        ];

        $notifications = NotificationCenter::listQuery($userId, $filters)
            ->paginate(30)
            ->withQueryString();

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'unreadCount'   => NotificationCenter::unreadCount($userId),
            'stats'         => NotificationCenter::stats($userId),
            'levelCounts'   => NotificationCenter::levelCounts($userId),
            'typeSummary'   => NotificationCenter::typeSummary($userId),
            'filters'       => $filters,
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
                'level'       => $n->level?->value,
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

    /**
     * Okundu işaretini geri al — listede yanlışlıkla okunan bildirim kaybolmasın.
     */
    public function markUnread(Request $request, AdminNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        NotificationCenter::markUnread($notification->id, $request->user()?->id);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdminNotification::class);

        $count = NotificationCenter::markAllRead($request->user()?->id);
        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Seçilenleri okundu işaretle.
     */
    public function bulkMarkRead(BulkNotificationRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', AdminNotification::class);

        $count = NotificationCenter::markManyRead($request->ids(), $request->user()?->id);

        return $this->backToList($request)->with(
            $count > 0 ? 'success' : 'info',
            $count > 0 ? "{$count} bildirim okundu olarak işaretlendi." : 'İşaretlenecek okunmamış bildirim yoktu.',
        );
    }

    /**
     * Seçilenleri sil.
     */
    public function bulkDestroy(BulkNotificationRequest $request): RedirectResponse
    {
        $this->authorize('delete', new AdminNotification());

        $count = NotificationCenter::deleteMany($request->ids(), $request->user()?->id);

        return $this->backToList($request)->with(
            $count > 0 ? 'success' : 'error',
            $count > 0 ? "{$count} bildirim silindi." : 'Hiçbir bildirim silinemedi.',
        );
    }

    /**
     * Listeyi tamamen boşalt.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        $this->authorize('delete', new AdminNotification());

        $count = NotificationCenter::deleteAll($request->user()?->id);

        return redirect()->route('admin.notifications.index')->with(
            $count > 0 ? 'success' : 'info',
            $count > 0 ? "{$count} bildirim silindi." : 'Silinecek bildirim yoktu.',
        );
    }

    /**
     * Kullanıcı hangi filtreye bakıyorsa oraya döndürür.
     */
    private function backToList(Request $request): RedirectResponse
    {
        return redirect()->route('admin.notifications.index', $request->only(['level', 'unread_only', 'q', 'page']));
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
