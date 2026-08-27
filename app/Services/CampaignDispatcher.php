<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Drains campaign_recipients a few rows at a time, on a schedule.
 *
 * Two limits work together:
 *
 *  - The hourly limit is the hard ceiling. It is enforced by counting what
 *    actually went out in the last 60 minutes, not by trusting the clock, so a
 *    missed or doubled cron run can never push past it.
 *  - The per-run quota only smooths delivery. With the cron on five minutes
 *    there are twelve runs an hour, so a limit of 100 sends about eight per
 *    run instead of emptying the list the moment the campaign starts — which
 *    is what gets a mail account throttled or blacklisted.
 *
 * A campaign marked as not throttled skips the smoothing quota but still obeys
 * the hourly ceiling.
 */
final class CampaignDispatcher
{
    /**
     * Minutes between cron runs. The per-run quota is derived from this, so
     * changing the schedule keeps the hourly total correct.
     */
    public const RUN_INTERVAL_MINUTES = 5;

    private const DEFAULT_HOURLY_LIMIT = 100;

    private const DEFAULT_MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly MailLogService $mailLogService,
    ) {}

    /**
     * One cron tick: promote due campaigns, then send what the limits allow.
     *
     * @return array{sent: int, failed: int, retrying: int, budget: int, campaigns: int}
     */
    public function tick(): array
    {
        $this->startDueCampaigns();

        $budget = $this->remainingBudget();
        $sent = 0;
        $failed = 0;
        $retrying = 0;
        $touched = 0;

        if ($budget < 1) {
            return ['sent' => 0, 'failed' => 0, 'retrying' => 0, 'budget' => 0, 'campaigns' => 0];
        }

        // Oldest first, so a campaign that started earlier finishes earlier
        // instead of every campaign crawling forward together.
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Sending)
            ->orderBy('started_at')
            ->get();

        foreach ($campaigns as $campaign) {
            if ($budget < 1) {
                break;
            }

            $result = $this->sendBatch($campaign, $budget);

            $attempted = $result['sent'] + $result['failed'] + $result['retrying'];

            $sent += $result['sent'];
            $failed += $result['failed'];
            $retrying += $result['retrying'];
            // A retry still cost an SMTP conversation, so it spends budget.
            $budget -= $attempted;

            if ($attempted > 0) {
                $touched++;
            }
        }

        return [
            'sent'      => $sent,
            'failed'    => $failed,
            'retrying'  => $retrying,
            'budget'    => max(0, $budget),
            'campaigns' => $touched,
        ];
    }

    /**
     * Move campaigns whose time has come into the sending state and freeze
     * their audience into recipient rows.
     */
    public function startDueCampaigns(): int
    {
        $due = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->get();

        foreach ($due as $campaign) {
            try {
                $this->campaigns->start($campaign);
            } catch (Throwable $e) {
                Log::error('Kampanya başlatılamadı', [
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $due->count();
    }

    /**
     * Send at most one batch for a single campaign.
     *
     * @return array{sent: int, failed: int, retrying: int}
     */
    /**
     * Bir sonraki turda sırada olan alıcılar.
     *
     * Kampanya ekranı "önümüzdeki turda hangi adresler gidecek" sorusunu bu
     * metotla yanıtlıyor ve gönderim de aynı metodu kullanıyor: seçim iki yerde
     * ayrı yazılsaydı ekranda görünen liste ile gerçekte gidenler zamanla
     * birbirinden ayrılırdı.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CampaignRecipient>
     */
    public function nextBatch(Campaign $campaign, ?int $budget = null): \Illuminate\Database\Eloquent\Collection
    {
        $budget ??= $this->remainingBudget();

        $take = $campaign->throttled
            ? min($budget, $this->perRunQuota())
            : $budget;

        return $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Pending)
            ->orderBy('id')
            ->limit(max(1, $take))
            ->get();
    }

    public function sendBatch(Campaign $campaign, ?int $budget = null): array
    {
        $budget ??= $this->remainingBudget();

        if ($budget < 1 || $campaign->status !== CampaignStatus::Sending) {
            return ['sent' => 0, 'failed' => 0, 'retrying' => 0];
        }

        $recipients = $this->nextBatch($campaign, $budget);

        if ($recipients->isEmpty()) {
            $this->completeIfDrained($campaign);

            return ['sent' => 0, 'failed' => 0, 'retrying' => 0];
        }

        $sent = 0;
        $failed = 0;
        $retrying = 0;

        foreach ($recipients as $recipient) {
            match ($this->deliver($campaign, $recipient)) {
                CampaignRecipientStatus::Sent   => $sent++,
                CampaignRecipientStatus::Failed => $failed++,
                default                         => $retrying++,
            };
        }

        $campaign->increment('sent_count', $sent);

        // Only recipients that ran out of attempts count as failed. A delivery
        // that will be retried is not a failure yet, and counting it as one
        // would push the progress bar past what actually happened.
        if ($failed > 0) {
            $campaign->increment('failed_count', $failed);
        }

        $this->completeIfDrained($campaign->refresh());

        return ['sent' => $sent, 'failed' => $failed, 'retrying' => $retrying];
    }

    /**
     * Deliver to one recipient and report what became of them.
     *
     * A failure is recorded on the row, never thrown: one bad address must not
     * stop the rest of the list. Pending means it will be tried again.
     */
    private function deliver(Campaign $campaign, CampaignRecipient $recipient): CampaignRecipientStatus
    {
        $mailable = new CampaignMail($campaign, $recipient);

        try {
            Mail::to($recipient->email, $recipient->name)->send($mailable);

            $recipient->update([
                'status'   => CampaignRecipientStatus::Sent,
                'sent_at'  => now(),
                'attempts' => $recipient->attempts + 1,
                'error'    => null,
            ]);

            $this->log($campaign, $recipient, $mailable, true, null);

            return CampaignRecipientStatus::Sent;
        } catch (Throwable $e) {
            $attempts = $recipient->attempts + 1;
            $exhausted = $attempts >= $this->maxAttempts();
            $status = $exhausted ? CampaignRecipientStatus::Failed : CampaignRecipientStatus::Pending;

            $recipient->update([
                'status'   => $status,
                'attempts' => $attempts,
                'error'    => $e->getMessage(),
            ]);

            Log::warning('Kampanya maili gönderilemedi', [
                'campaign_id' => $campaign->id,
                'email'       => $recipient->email,
                'attempt'     => $attempts,
                'error'       => $e->getMessage(),
            ]);

            // Only counts against the campaign totals once it has really given up.
            if ($exhausted) {
                $this->log($campaign, $recipient, $mailable, false, $e->getMessage());
            }

            return $status;
        }
    }

    private function log(Campaign $campaign, CampaignRecipient $recipient, CampaignMail $mailable, bool $success, ?string $error): void
    {
        try {
            $this->mailLogService->logMail(
                to: $recipient->email,
                mailable: $mailable,
                subject: $campaign->subject,
                from: $campaign->senderAddress(),
                success: $success,
                error: $error,
                metadata: ['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id],
            );
        } catch (Throwable $e) {
            // Logging must never take the send down with it.
            Log::warning('Kampanya mail logu yazılamadı', ['error' => $e->getMessage()]);
        }
    }

    /**
     * A campaign with nothing pending left is done.
     */
    private function completeIfDrained(Campaign $campaign): void
    {
        if ($campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->exists()) {
            return;
        }

        $campaign->update([
            'status'       => CampaignStatus::Sent,
            'completed_at' => now(),
        ]);
    }

    /**
     * How many mails may still go out this hour.
     *
     * Counted from what was actually delivered in the last sixty minutes, so
     * the ceiling holds even if the scheduler misfires or runs twice.
     */
    public function remainingBudget(): int
    {
        $limit = $this->hourlyLimit();

        if ($limit < 1) {
            return 0;
        }

        $sentLastHour = CampaignRecipient::query()
            ->where('status', CampaignRecipientStatus::Sent)
            ->where('sent_at', '>=', now()->subHour())
            ->count();

        return max(0, $limit - $sentLastHour);
    }

    /**
     * The smoothing quota: the hourly limit divided across the runs in an hour.
     *
     * Rounded up so the hour's full allowance is reachable — the rolling
     * budget above is what actually stops it going over.
     */
    public function perRunQuota(): int
    {
        $configured = (int) Setting::getValue('mail_batch_max', '0');

        if ($configured > 0) {
            return $configured;
        }

        $runsPerHour = max(1, (int) (60 / self::RUN_INTERVAL_MINUTES));

        return max(1, (int) ceil($this->hourlyLimit() / $runsPerHour));
    }

    public function hourlyLimit(): int
    {
        $limit = (int) Setting::getValue('mail_hourly_limit', (string) self::DEFAULT_HOURLY_LIMIT);

        return max(0, $limit);
    }

    private function maxAttempts(): int
    {
        return max(1, (int) Setting::getValue('mail_max_attempts', (string) self::DEFAULT_MAX_ATTEMPTS));
    }

    /**
     * When the scheduler will next run this command.
     *
     * The cron fires on every fifth minute of the hour, so the next run is the
     * next such boundary. Shown in the panel so nobody wonders whether a queued
     * campaign is stuck.
     */
    public function nextRunAt(): \Illuminate\Support\Carbon
    {
        $now = now();
        $interval = self::RUN_INTERVAL_MINUTES;
        $minutesPast = $now->minute % $interval;

        return $now->copy()
            ->addMinutes($interval - $minutesPast)
            ->setSecond(0);
    }

    /**
     * Seconds until the next scheduled run, for a countdown in the panel.
     */
    public function secondsUntilNextRun(): int
    {
        return (int) max(0, now()->diffInSeconds($this->nextRunAt(), false));
    }

    /**
     * Mails delivered in the last hour, for the dashboard.
     */
    public function sentLastHour(): int
    {
        return CampaignRecipient::query()
            ->where('status', CampaignRecipientStatus::Sent)
            ->where('sent_at', '>=', now()->subHour())
            ->count();
    }
}
