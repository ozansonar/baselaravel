<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MailLogStatus;
use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class UpdateMailLogOnSent
{
    public function handle(MessageSent $event): void
    {
        try {
            $mailLogId = $this->extractMailLogId($event);

            if (! $mailLogId) {
                return;
            }

            $updateData = [
                'status'  => MailLogStatus::Sent,
                'sent_at' => now(),
            ];

            // Capture rendered HTML body from the sent message
            $htmlBody = $event->sent->getOriginalMessage()->getHtmlBody();
            if ($htmlBody) {
                $updateData['body'] = $htmlBody;
            }

            $updated = MailLog::where('id', $mailLogId)
                ->where('status', MailLogStatus::Pending)
                ->update($updateData);

            if ($updated) {
                Cache::forget('admin.mail_logs.stats');
            }
        } catch (\Throwable $e) {
            Log::warning('Mail log durumu güncellenemedi (sent)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractMailLogId(MessageSent $event): ?int
    {
        $header = $event->sent->getOriginalMessage()
            ->getHeaders()
            ->get('X-Mail-Log-Id');

        if (! $header) {
            return null;
        }

        $value = (int) $header->getBodyAsString();

        return $value > 0 ? $value : null;
    }
}
