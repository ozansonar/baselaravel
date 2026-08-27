<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MailLogStatus;
use App\Mail\BaseMail;
use App\Models\MailLog;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class MailService
{
    public function __construct(
        private readonly MailLogService $mailLogService,
    ) {}

    /**
     * Send a mailable synchronously.
     */
    public function send(string|array $to, Mailable $mailable): bool
    {
        return $this->attempt(
            fn () => Mail::to($to)->send($mailable),
            $to,
            $mailable,
        );
    }

    /**
     * Queue a mailable for async delivery.
     * Logs as "pending" – actual status is updated via MessageSent/Failed listeners.
     */
    public function queue(string|array $to, Mailable $mailable): bool
    {
        // Log as pending before queuing
        $mailLog = $this->logMailableDetails($to, $mailable, pending: true);

        try {
            // Store log ID on mailable so the listener can update it after real delivery
            if ($mailable instanceof BaseMail) {
                $mailable->mailLogId = $mailLog->id;
            }

            Mail::to($to)->queue($mailable);

            // Render body AFTER queue serialization to avoid closure issues
            $body = $this->extractMailBody($mailable);
            if ($body !== null) {
                $mailLog->update(['body' => $body]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Mail kuyruğa eklenemedi', [
                'to'       => $to,
                'mailable' => $mailable::class,
                'error'    => $e->getMessage(),
            ]);

            // Update existing pending log → failed (don't create a new record)
            $mailLog->update([
                'status'        => MailLogStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            Cache::forget('admin.mail_logs.stats');

            return false;
        }
    }

    /**
     * Send a raw text email (test emails, simple notifications).
     */
    public function sendRaw(string $to, string $subject, string $body): void
    {
        $this->purgeMailer();

        try {
            Mail::raw($body, function (Message $message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });

            $this->mailLogService->logMail($to, null, $subject, null, true, null, null, $body);
        } catch (\Throwable $e) {
            Log::error('Raw mail gönderilemedi', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            $this->mailLogService->logMail($to, null, $subject, null, false, $e->getMessage(), null, $body);

            throw $e;
        }
    }

    /**
     * Resend an email using the stored HTML body from a mail log.
     */
    public function resendFromLog(MailLog $mailLog): bool
    {
        if (empty($mailLog->body)) {
            throw new \RuntimeException('Bu mailin içeriği kayıtlı değil, yeniden gönderilemez.');
        }

        $this->purgeMailer();

        try {
            Mail::html($mailLog->body, fn (Message $message) => $this->applyRecipients($message, $mailLog));

            $this->mailLogService->logMail(
                to: $mailLog->to,
                subject: '[Yeniden] ' . $mailLog->subject,
                body: $mailLog->body,
                cc: $mailLog->cc,
                bcc: $mailLog->bcc,
                replyTo: $mailLog->reply_to,
                success: true,
                metadata: ['resent_from' => $mailLog->id],
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Mail yeniden gönderilemedi', [
                'mail_log_id' => $mailLog->id,
                'error'       => $e->getMessage(),
            ]);

            $this->mailLogService->logMail(
                to: $mailLog->to,
                subject: '[Yeniden] ' . $mailLog->subject,
                body: $mailLog->body,
                success: false,
                error: $e->getMessage(),
                metadata: ['resent_from' => $mailLog->id],
            );

            return false;
        }
    }

    /**
     * Kuyrukta bekleyen bir maili şimdi gönderir.
     *
     * Yeniden gönderme değil: yeni bir kayıt açmaz, bekleyen kaydın kendisini
     * kapatır. Kuyruktaki iş hâlâ duruyorsa çift gönderim olurdu; bu yüzden
     * yalnızca kuyruk boşaldıktan sonra çağrılır (MailLogController).
     */
    public function sendPendingNow(MailLog $mailLog): bool
    {
        if ($mailLog->status !== MailLogStatus::Pending) {
            throw new \RuntimeException('Bu mail zaten işlenmiş.');
        }

        if (empty($mailLog->body)) {
            throw new \RuntimeException('Mailin içeriği kayıtlı değil, elle gönderilemez.');
        }

        $this->purgeMailer();

        try {
            Mail::html($mailLog->body, fn (Message $message) => $this->applyRecipients($message, $mailLog));

            $mailLog->update([
                'status'        => MailLogStatus::Sent,
                'sent_at'       => now(),
                'error_message' => null,
            ]);

            Cache::forget('admin.mail_logs.stats');

            return true;
        } catch (\Throwable $e) {
            Log::error('Bekleyen mail elle gönderilemedi', [
                'mail_log_id' => $mailLog->id,
                'error'       => $e->getMessage(),
            ]);

            $mailLog->update([
                'status'        => MailLogStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            Cache::forget('admin.mail_logs.stats');

            throw $e;
        }
    }

    /**
     * Kayıttaki gönderen, alıcı ve yanıt adreslerini mesaja uygular.
     */
    private function applyRecipients(Message $message, MailLog $mailLog): void
    {
        $message->to($mailLog->to)->subject((string) $mailLog->subject);

        if ($mailLog->from) {
            $message->from($mailLog->from);
        }

        foreach (['cc' => 'cc', 'bcc' => 'bcc'] as $field => $method) {
            if (! $mailLog->{$field}) {
                continue;
            }

            foreach (array_filter(array_map('trim', explode(',', (string) $mailLog->{$field}))) as $address) {
                $message->{$method}($address);
            }
        }

        if ($mailLog->reply_to) {
            $message->replyTo($mailLog->reply_to);
        }
    }

    /**
     * Purge cached mailer instance to apply fresh config.
     */
    public function purgeMailer(): void
    {
        Mail::purge('smtp');
    }

    /**
     * Execute a mail action with logging and error handling.
     */
    private function attempt(callable $action, string|array $to, Mailable $mailable): bool
    {
        try {
            $action();

            $this->logMailableDetails($to, $mailable, true);

            return true;
        } catch (\Throwable $e) {
            Log::error('Mail gönderilemedi', [
                'to'       => $to,
                'mailable' => $mailable::class,
                'error'    => $e->getMessage(),
            ]);

            $this->logMailableDetails($to, $mailable, false, $e->getMessage());

            return false;
        }
    }

    /**
     * Extract details from a Mailable and log them.
     * When $pending is true (queued mails), body rendering is skipped here
     * to avoid attaching closures that break queue serialization.
     * Body is rendered separately after successful queue dispatch.
     */
    private function logMailableDetails(
        string|array $to,
        Mailable $mailable,
        bool $success = true,
        ?string $error = null,
        bool $pending = false,
    ): \App\Models\MailLog {
        $body = $pending ? null : $this->extractMailBody($mailable);
        $cc = $this->extractRecipients($mailable, 'cc');
        $bcc = $this->extractRecipients($mailable, 'bcc');
        $replyTo = $this->extractRecipients($mailable, 'replyTo');

        return $this->mailLogService->logMail(
            to: $to,
            mailable: $mailable,
            body: $body,
            cc: $cc,
            bcc: $bcc,
            replyTo: $replyTo,
            success: $success,
            error: $error,
            pending: $pending,
        );
    }

    /**
     * Try to render the mailable body as HTML.
     */
    private function extractMailBody(Mailable $mailable): ?string
    {
        try {
            return $mailable->render();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract recipient addresses from a mailable (cc, bcc, replyTo).
     */
    private function extractRecipients(Mailable $mailable, string $type): ?string
    {
        try {
            $addresses = $mailable->{$type} ?? [];

            if (empty($addresses)) {
                return null;
            }

            $emails = array_map(
                fn ($addr) => is_array($addr) ? ($addr['address'] ?? '') : (is_object($addr) ? $addr->address : (string) $addr),
                $addresses,
            );

            $emails = array_filter($emails);

            return !empty($emails) ? implode(', ', $emails) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
