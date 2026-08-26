<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriberStatus;
use App\Models\CampaignRecipient;
use App\Models\Subscriber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class SubscriberService
{
    /**
     * Add an address to the list, or bring a former subscriber back.
     *
     * Signing up again after unsubscribing has to work: the row is kept for its
     * history, so it is revived rather than duplicated.
     */
    public function subscribe(string $email, ?string $name = null, ?string $locale = null, string $source = 'form'): Subscriber
    {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($email, $name, $locale, $source): Subscriber {
            $existing = Subscriber::withTrashed()->where('email', $email)->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'name'            => $name ?: $existing->name,
                    'locale'          => $locale ?: $existing->locale,
                    'status'          => SubscriberStatus::Subscribed,
                    'subscribed_at'   => now(),
                    'unsubscribed_at' => null,
                ]);

                return $existing->refresh();
            }

            return Subscriber::create([
                'email'         => $email,
                'name'          => $name,
                'locale'        => $locale,
                'status'        => SubscriberStatus::Subscribed,
                'source'        => $source,
                'subscribed_at' => now(),
            ]);
        });
    }

    /**
     * Opting out never deletes the row — the address has to stay on record so a
     * later import cannot quietly add the person back.
     */
    public function unsubscribeByToken(string $token): ?Subscriber
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        // A campaign can go to people who were never on the mailing list —
        // typed in by hand or imported from a spreadsheet. Their token belongs
        // to the campaign row, and opting out has to work for them too.
        if ($subscriber === null) {
            $subscriber = $this->suppressFromCampaignToken($token);
        }

        if ($subscriber === null) {
            return null;
        }

        $subscriber->update([
            'status'          => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);

        return $subscriber->refresh();
    }

    /**
     * Record an opt-out for an address that has no mailing list entry.
     *
     * The subscribers table doubles as the suppression list — CampaignService
     * excludes every unsubscribed address from future audiences — so the row is
     * created here rather than the opt-out silently doing nothing.
     */
    private function suppressFromCampaignToken(string $token): ?Subscriber
    {
        $recipient = CampaignRecipient::where('unsubscribe_token', $token)->first();

        if ($recipient === null) {
            return null;
        }

        $email = mb_strtolower(trim($recipient->email));

        $existing = Subscriber::withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return Subscriber::create([
            'email'         => $email,
            'name'          => $recipient->name,
            'locale'        => $recipient->locale,
            'status'        => SubscriberStatus::Unsubscribed,
            'source'        => 'campaign',
            'subscribed_at' => null,
        ]);
    }

    public function unsubscribeByEmail(string $email): ?Subscriber
    {
        $subscriber = Subscriber::where('email', mb_strtolower(trim($email)))->first();

        return $subscriber === null ? null : $this->unsubscribeByToken($subscriber->unsubscribe_token);
    }

    /**
     * Bulk add, used by the import screen.
     *
     * @param array<int, array{email: string, name?: ?string}> $rows
     * @return array{added: int, updated: int, skipped: int}
     */
    public function importMany(array $rows, ?string $locale = null, string $source = 'import'): array
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $existed = Subscriber::withTrashed()->where('email', $email)->exists();

            $this->subscribe($email, $row['name'] ?? null, $locale, $source);

            $existed ? $updated++ : $added++;
        }

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return LengthAwarePaginator<int, Subscriber>
     */
    public function paginate(int $perPage = 25, ?array $filters = null): LengthAwarePaginator
    {
        $query = Subscriber::query()->latest('id');

        if (! empty($filters['status']) && ($status = SubscriberStatus::tryFrom($filters['status'])) !== null) {
            $query->where('status', $status);
        }

        if (! empty($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('email', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'total'        => Subscriber::count(),
            'subscribed'   => Subscriber::where('status', SubscriberStatus::Subscribed)->count(),
            'unsubscribed' => Subscriber::where('status', SubscriberStatus::Unsubscribed)->count(),
            'bounced'      => Subscriber::where('status', SubscriberStatus::Bounced)->count(),
        ];
    }
}
