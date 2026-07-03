<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MailLogStatus;
use App\Mail\BaseMail;
use App\Models\MailLog;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class UpdateMailLogOnFailed
{
    /**
     * Handle MessageFailed event (Laravel 11+).
     */
    public function handleMessageFailed(MessageFailed $event): void
    {
        try {
            $header = $event->message->getHeaders()->get('X-Mail-Log-Id');

            if (! $header) {
                return;
            }

            $mailLogId = (int) $header->getBodyAsString();

            if ($mailLogId > 0) {
                $this->markAsFailed($mailLogId, 'Mail gönderim hatası');
            }
        } catch (\Throwable $e) {
            Log::warning('Mail log durumu güncellenemedi (message failed)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle queue JobFailed event for queued mailables.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        try {
            $payload = $event->job->payload();
            $data = unserialize($payload['data']['command'] ?? '');

            if (! $data instanceof SendQueuedMailable) {
                return;
            }

            $mailable = $data->mailable ?? null;

            if ($mailable instanceof BaseMail && $mailable->mailLogId) {
                $this->markAsFailed($mailable->mailLogId, $event->exception->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('Mail log durumu güncellenemedi (job failed)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markAsFailed(int $mailLogId, string $error): void
    {
        $updated = MailLog::where('id', $mailLogId)
            ->where('status', MailLogStatus::Pending)
            ->update([
                'status'        => MailLogStatus::Failed,
                'error_message' => mb_substr($error, 0, 500),
            ]);

        if ($updated) {
            Cache::forget('admin.mail_logs.stats');
        }
    }
}
