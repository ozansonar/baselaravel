<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MailLogStatus;
use App\Models\MailLog;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class MailLogService
{
    /**
     * Log a mail attempt (mailable or raw).
     */
    public function logMail(
        string|array $to,
        ?Mailable $mailable = null,
        ?string $subject = null,
        ?string $from = null,
        bool $success = true,
        ?string $error = null,
        ?array $metadata = null,
        ?string $body = null,
        string|array|null $cc = null,
        string|array|null $bcc = null,
        ?string $replyTo = null,
        ?string $ipAddress = null,
        bool $pending = false,
    ): MailLog {
        $resolvedSubject = $mailable?->subject ?? $subject ?? class_basename($mailable ?? 'Raw Mail');
        $status = $pending ? MailLogStatus::Pending : ($error ? MailLogStatus::Failed : ($success ? MailLogStatus::Sent : MailLogStatus::Pending));

        $resolvedIp = $ipAddress ?? request()?->ip();

        $mailLog = MailLog::create([
            'to'             => is_array($to) ? implode(', ', $to) : $to,
            'cc'             => $cc ? (is_array($cc) ? implode(', ', $cc) : $cc) : null,
            'bcc'            => $bcc ? (is_array($bcc) ? implode(', ', $bcc) : $bcc) : null,
            'from'           => $from ?? config('mail.from.address'),
            'reply_to'       => $replyTo,
            'subject'        => $resolvedSubject,
            'body'           => $body,
            'mailable_class' => $mailable !== null ? $mailable::class : null,
            'status'         => $status,
            'error_message'  => $error,
            'sent_at'        => $status === MailLogStatus::Sent ? now() : null,
            'metadata'       => $metadata,
            'ip_address'     => $resolvedIp,
            'user_id'        => Auth::id(),
        ]);

        Cache::forget('admin.mail_logs.stats');

        return $mailLog;
    }

    /**
     * Paginate mail logs with filters.
     */
    public function paginate(int $perPage = 25, ?array $filters = null): LengthAwarePaginator
    {
        $query = MailLog::with('user');

        if ($filters) {
            if (!empty($filters['status'])) {
                $status = MailLogStatus::tryFrom($filters['status']);
                if ($status) {
                    $query->byStatus($status);
                }
            }

            if (!empty($filters['search'])) {
                $query->search($filters['search']);
            }

            if (!empty($filters['date_filter'])) {
                $query = match ($filters['date_filter']) {
                    'today'   => $query->whereDate('created_at', today()),
                    'week'    => $query->where('created_at', '>=', now()->startOfWeek()),
                    'month'   => $query->where('created_at', '>=', now()->startOfMonth()),
                    'quarter' => $query->where('created_at', '>=', now()->subMonths(3)),
                    default   => $query,
                };
            }
        }

        return $query->recent()->paginate($perPage);
    }

    /**
     * Get status counts for tabs.
     */
    public function statusCounts(): array
    {
        return MailLog::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get admin stats for stat cards.
     *
     * @return array{total: int, sent: int, failed: int, pending: int, today: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.mail_logs.stats', 300, function (): array {
            $counts = MailLog::query()
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as sent', [MailLogStatus::Sent->value])
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [MailLogStatus::Failed->value])
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as pending', [MailLogStatus::Pending->value])
                ->selectRaw('sum(case when date(created_at) = ? then 1 else 0 end) as today', [today()->toDateString()])
                ->first();

            return [
                'total'   => (int) $counts->total,
                'sent'    => (int) $counts->sent,
                'failed'  => (int) $counts->failed,
                'pending' => (int) $counts->pending,
                'today'   => (int) $counts->today,
            ];
        });
    }
}
