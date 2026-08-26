<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ContactMessageNotification;
use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ContactMessageService
{
    public function __construct(
        private readonly MailService $mailService,
    ) {}
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = ContactMessage::withTrashed()->recent();

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'unread' => $query->whereNull('deleted_at')->where('is_read', false),
                'read' => $query->whereNull('deleted_at')->where('is_read', true),
                'trashed' => $query->onlyTrashed(),
                default => $query->whereNull('deleted_at'),
            };
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ContactMessage
    {
        return ContactMessage::findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ContactMessage
    {
        $data['ip_address'] = request()->ip();

        $message = ContactMessage::create($data);

        $this->clearCache();

        $adminEmail = config('mail.admin_address', config('mail.from.address'));
        if ($adminEmail) {
            try {
                $this->mailService->queue($adminEmail, new ContactMessageNotification($message));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('İletişim bildirim maili kuyruğa eklenemedi', [
                    'message_id' => $message->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $message;
    }

    public function markAsRead(ContactMessage $message): void
    {
        if (!$message->is_read) {
            $message->markAsRead();
            $this->clearCache();
        }
    }

    public function markAllAsRead(): int
    {
        $affected = ContactMessage::unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->clearCache();

        return $affected;
    }

    public function delete(ContactMessage $message): void
    {
        $message->delete();
        $this->clearCache();
    }

    public function restore(ContactMessage $message): void
    {
        $message->restore();
        $this->clearCache();
    }

    public function unreadCount(): int
    {
        return ContactMessage::unread()->count();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.contact_messages.stats', 300, function (): array {
            $counts = ContactMessage::withTrashed()
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and is_read = 0 then 1 else 0 end) as unread')
                ->selectRaw('sum(case when deleted_at is null and is_read = 1 then 1 else 0 end) as `read`')
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'   => (int) $counts->total,
                'unread'  => (int) $counts->unread,
                'read'    => (int) $counts->read,
                'trashed' => (int) $counts->trashed,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return [
            'unread' => ContactMessage::unread()->count(),
            'read' => ContactMessage::where('is_read', true)->count(),
            'trashed' => ContactMessage::onlyTrashed()->count(),
        ];
    }

    public function reply(ContactMessage $message, string $body): void
    {
        DB::transaction(function () use ($message, $body): void {
            $message->update([
                'replied_at' => now(),
                'reply_text' => $body,
            ]);

            if (!$message->is_read) {
                $message->markAsRead();
            }

            $this->mailService->queue(
                $message->email,
                new ContactMessageReplyMail($message, $body),
            );
        });

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget('admin.contact_messages.stats');
    }
}
