<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessageReplyRequest;
use App\Models\ContactMessage;
use App\Services\ContactMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ContactMessageController extends Controller
{
    public function __construct(
        private readonly ContactMessageService $contactMessageService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ContactMessage::class);

        $perPage = in_array($request->query('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->query('per_page')
            : 25;

        $filters = $request->only(['status', 'search']);

        return view('admin.contact-messages.index', [
            'messages'     => $this->contactMessageService->paginate($perPage, $filters),
            'stats'        => $this->contactMessageService->getAdminStats(),
            'statusCounts' => $this->contactMessageService->statusCounts(),
            'unreadCount'  => $this->contactMessageService->unreadCount(),
            'perPage'      => $perPage,
        ]);
    }

    public function show(ContactMessage $contactMessage): View|JsonResponse
    {
        $this->authorize('view', $contactMessage);

        $this->contactMessageService->markAsRead($contactMessage);

        if (request()->wantsJson()) {
            return response()->json([
                'id'         => $contactMessage->id,
                'name'       => $contactMessage->name,
                'email'      => $contactMessage->email,
                'phone'      => $contactMessage->phone,
                'subject'    => $contactMessage->subject,
                'message'    => $contactMessage->message,
                'ip_address' => $contactMessage->ip_address,
                'is_read'    => $contactMessage->is_read,
                'replied_at' => $contactMessage->replied_at?->format('d.m.Y H:i'),
                'reply_text' => $contactMessage->reply_text,
                'created_at' => $contactMessage->created_at?->format('d.m.Y H:i'),
            ]);
        }

        return view('admin.contact-messages.show', [
            'message' => $contactMessage,
        ]);
    }

    public function markAllRead(): RedirectResponse
    {
        $this->authorize('viewAny', ContactMessage::class);

        $count = $this->contactMessageService->markAllAsRead();
        $this->contactMessageService->clearCache();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('success', "{$count} mesaj okundu olarak işaretlendi.");
    }

    public function restore(ContactMessage $contactMessage): RedirectResponse
    {
        $this->authorize('delete', $contactMessage);

        $this->contactMessageService->restore($contactMessage);
        $this->contactMessageService->clearCache();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('success', 'Mesaj başarıyla geri yüklendi.');
    }

    public function reply(ContactMessageReplyRequest $request, ContactMessage $contactMessage): JsonResponse
    {
        $this->authorize('reply', $contactMessage);

        try {
            $this->contactMessageService->reply($contactMessage, $request->validated('reply_body'));

            return response()->json([
                'success'    => true,
                'message'    => 'Yanıt başarıyla gönderildi.',
                'replied_at' => now()->format('d.m.Y H:i'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Yanıt gönderilemedi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $this->authorize('delete', $contactMessage);

        $this->contactMessageService->delete($contactMessage);
        $this->contactMessageService->clearCache();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('success', 'Mesaj başarıyla silindi.');
    }
}
