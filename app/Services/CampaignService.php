<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignAudience;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\SubscriberStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Subscriber;
use App\Support\PersonName;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CampaignService
{
    private const UPLOAD_FOLDER = 'campaigns';

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign
    {
        return DB::transaction(function () use ($data): Campaign {
            $campaign = Campaign::create($this->fields($data) + [
                'status'  => CampaignStatus::Draft,
                'user_id' => auth()->id(),
            ]);

            $this->syncAttachments($campaign, $data['attachments'] ?? []);

            return $campaign;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        if (! $campaign->isEditable()) {
            throw new RuntimeException('Gönderimi başlamış bir kampanyanın içeriği değiştirilemez.');
        }

        return DB::transaction(function () use ($campaign, $data): Campaign {
            $campaign->update($this->fields($data));

            $this->syncAttachments($campaign, $data['attachments'] ?? []);

            return $campaign->refresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            'name'            => $data['name'],
            'subject'         => $data['subject'],
            'body'            => $data['body'],
            'from_name'       => $data['from_name'] ?? null,
            'from_email'      => $data['from_email'] ?? null,
            'reply_to'        => $data['reply_to'] ?? null,
            'locale'          => $data['locale'] ?? null,
            'audience'        => $data['audience'],
            'audience_filter' => $data['audience_filter'] ?? null,
            'throttled'       => (bool) ($data['throttled'] ?? true),
        ];
    }

    /**
     * Queue a campaign for the dispatcher.
     *
     * "Send now" is the same path with no scheduled time — the next cron run
     * picks it up, which keeps one code path for both cases.
     */
    public function schedule(Campaign $campaign, ?\DateTimeInterface $at = null): Campaign
    {
        if (! $campaign->isEditable()) {
            throw new RuntimeException('Bu kampanya zaten gönderime alınmış.');
        }

        $campaign->update([
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => $at,
        ]);

        return $campaign->refresh();
    }

    /**
     * Freeze the audience into recipient rows and open the campaign for sending.
     *
     * The list is resolved once, here — not on every batch. Someone signing up
     * (or leaving) mid-send must not change who this campaign goes to.
     */
    public function start(Campaign $campaign): Campaign
    {
        if ($campaign->status === CampaignStatus::Sending) {
            return $campaign;
        }

        if (! $campaign->status->isDispatchable()) {
            throw new RuntimeException('Bu kampanya gönderime hazır değil.');
        }

        return DB::transaction(function () use ($campaign): Campaign {
            if ($campaign->recipients()->doesntExist()) {
                $this->buildRecipients($campaign);
            }

            $campaign->update([
                'status'           => CampaignStatus::Sending,
                'started_at'       => $campaign->started_at ?? now(),
                'total_recipients' => $campaign->recipients()->count(),
            ]);

            return $campaign->refresh();
        });
    }

    public function pause(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Sending) {
            throw new RuntimeException('Yalnızca gönderimi süren bir kampanya duraklatılabilir.');
        }

        $campaign->update(['status' => CampaignStatus::Paused]);

        return $campaign->refresh();
    }

    public function resume(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Paused) {
            throw new RuntimeException('Yalnızca duraklatılmış bir kampanya sürdürülebilir.');
        }

        $campaign->update(['status' => CampaignStatus::Sending]);

        return $campaign->refresh();
    }

    /**
     * Stop for good. Whatever already went out stays sent; the rest is dropped.
     */
    public function cancel(Campaign $campaign): Campaign
    {
        if (in_array($campaign->status, [CampaignStatus::Sent, CampaignStatus::Cancelled], true)) {
            throw new RuntimeException('Bu kampanya zaten sonlanmış.');
        }

        DB::transaction(function () use ($campaign): void {
            $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Pending)
                ->update(['status' => CampaignRecipientStatus::Skipped]);

            $campaign->update([
                'status'       => CampaignStatus::Cancelled,
                'completed_at' => now(),
            ]);
        });

        return $campaign->refresh();
    }

    /**
     * Resolve the audience into recipient rows.
     *
     * @return int number of recipients queued
     */
    public function buildRecipients(Campaign $campaign): int
    {
        $rows = $this->resolveAudience($campaign);

        if ($rows === []) {
            throw new RuntimeException('Bu kampanya için gönderilecek alıcı bulunamadı.');
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $payload[] = [
                'campaign_id'       => $campaign->id,
                'email'             => $row['email'],
                'first_name'        => $row['first_name'] ?? null,
                'last_name'         => $row['last_name'] ?? null,
                'locale'            => $row['locale'] ?? $campaign->locale,
                'status'            => CampaignRecipientStatus::Pending->value,
                // Every recipient gets one, not just people already on the
                // mailing list: someone added from a spreadsheet must be able
                // to opt out of the mail they just received.
                'unsubscribe_token' => $row['unsubscribe_token'] ?? Str::lower(Str::random(64)),
                'attempts'          => 0,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            CampaignRecipient::insert($chunk);
        }

        return count($payload);
    }

    /**
     * @return array<int, array{email: string, first_name: ?string, last_name: ?string, locale: ?string, unsubscribe_token?: ?string}>
     */
    public function resolveAudience(Campaign $campaign): array
    {
        $rows = match ($campaign->audience) {
            CampaignAudience::Users       => $this->fromUsers($campaign),
            CampaignAudience::Subscribers => $this->fromSubscribers($campaign),
            CampaignAudience::Import,
            CampaignAudience::Manual      => $this->fromStoredList($campaign),
        };

        return $this->dedupe($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fromUsers(Campaign $campaign): array
    {
        $query = User::query()->whereNotNull('email');

        if ($campaign->audience_filter['active_only'] ?? true) {
            $query->where('is_active', true);
        }

        if ($campaign->audience_filter['verified_only'] ?? false) {
            $query->whereNotNull('email_verified_at');
        }

        $roleIds = $campaign->audience_filter['role_ids'] ?? [];

        if ($roleIds !== []) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds));
        }

        return $query->get(['email', 'first_name', 'last_name'])
            ->map(fn (User $user): array => [
                'email'      => $user->email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'locale'     => $campaign->locale,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fromSubscribers(Campaign $campaign): array
    {
        $query = Subscriber::query()->where('status', SubscriberStatus::Subscribed);

        // Hangi listelere gideceği seçilmişse yalnızca o listelerin üyeleri.
        // Boş bırakılırsa liste ayrımı yapılmaz, tüm aboneler hedeflenir.
        $listIds = array_filter(array_map('intval', $campaign->audience_filter['list_ids'] ?? []));

        if ($listIds !== []) {
            // İki listede birden olan kişi tek kez alsın diye whereHas; sonraki
            // dedupe zaten adresi tekilleştiriyor ama sorgu da satırı çoğaltmıyor.
            $query->whereHas('lists', fn ($sub) => $sub->whereIn('subscriber_lists.id', $listIds));
        }

        // A campaign written in one language should not land in the inbox of
        // someone who signed up in another.
        if (($campaign->audience_filter['match_locale'] ?? false) && $campaign->locale) {
            $query->where('locale', $campaign->locale);
        }

        return $query->get(['email', 'first_name', 'last_name', 'locale', 'unsubscribe_token'])
            ->map(fn (Subscriber $subscriber): array => [
                'email'             => $subscriber->email,
                'first_name'        => $subscriber->first_name,
                'last_name'         => $subscriber->last_name,
                'locale'            => $subscriber->locale ?? $campaign->locale,
                'unsubscribe_token' => $subscriber->unsubscribe_token,
            ])
            ->all();
    }

    /**
     * Imported and hand-typed lists are kept on the campaign itself: they
     * belong to this send, not to the subscriber list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fromStoredList(Campaign $campaign): array
    {
        $list = $campaign->audience_filter['recipients'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        $rows = [];

        foreach ($list as $entry) {
            $email = is_array($entry) ? ($entry['email'] ?? null) : $entry;

            if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Ad ve soyad ayrı tutuluyor; daha önce kaydedilmiş kampanyalarda
            // tek parça "name" var, o da bölünerek okunuyor.
            $names = is_array($entry) && (isset($entry['first_name']) || isset($entry['last_name']))
                ? ['first_name' => $entry['first_name'] ?? null, 'last_name' => $entry['last_name'] ?? null]
                : PersonName::split(is_array($entry) ? ($entry['name'] ?? null) : null);

            $rows[] = $names + [
                'email'  => mb_strtolower(trim($email)),
                'locale' => $campaign->locale,
            ];
        }

        return $rows;
    }

    /**
     * One address gets one mail, and people who opted out never do.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $rows): array
    {
        $optedOut = Subscriber::query()
            ->whereIn('status', [SubscriberStatus::Unsubscribed, SubscriberStatus::Bounced])
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->flip();

        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) $row['email']));

            if ($email === '' || isset($seen[$email]) || $optedOut->has($email)) {
                continue;
            }

            $seen[$email] = true;
            $row['email'] = $email;
            $result[] = $row;
        }

        return $result;
    }

    /**
     * What the audience resolves to right now, without writing anything.
     *
     * Shown on the review screen so the campaign is approved against a real
     * number and a real sample rather than a description of a filter.
     *
     * @return array{count: int, sample: array<int, array<string, mixed>>, error: ?string}
     */
    public function previewAudience(Campaign $campaign): array
    {
        // Once it has started the frozen list is the truth, not the filter.
        if ($campaign->recipients()->exists()) {
            return [
                'count'  => $campaign->recipients()->count(),
                'sample' => $campaign->recipients()
                    ->orderBy('id')
                    ->limit(10)
                    ->get(['first_name', 'last_name', 'email'])
                    ->map(fn ($row): array => [
                        'first_name' => $row->first_name,
                        'last_name'  => $row->last_name,
                        'email'      => $row->email,
                    ])
                    ->all(),
                'error'  => null,
            ];
        }

        try {
            $rows = $this->resolveAudience($campaign);
        } catch (Throwable $e) {
            return ['count' => 0, 'sample' => [], 'error' => $e->getMessage()];
        }

        return [
            'count'  => count($rows),
            'sample' => array_slice($rows, 0, 10),
            'error'  => $rows === [] ? 'Bu seçime uyan alıcı bulunamadı.' : null,
        ];
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function syncAttachments(Campaign $campaign, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'ek';
            $path = $this->uploadService->uploadFile($file, self::UPLOAD_FOLDER, $name);

            $campaign->attachments()->create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize() ?: 0,
            ]);
        }
    }

    public function deleteAttachment(Campaign $campaign, int $attachmentId): void
    {
        $attachment = $campaign->attachments()->findOrFail($attachmentId);

        $this->uploadService->deleteFile($attachment->path);
        $attachment->delete();
    }

    /**
     * Elle girilen alıcı satırlarını kayıt biçimine çevirir.
     *
     * Form artık serbest metin yerine satır satır alan gönderiyor: her satırda
     * e-posta, ad ve soyad ayrı. Boş satırlar (kullanıcı "ekle"ye basıp
     * doldurmadan bıraktığında oluşur) ve aynı adres iki kez atlanır.
     *
     * @param  array<int, array<string, string|null>> $rows
     * @return array<int, array{first_name: ?string, last_name: ?string, email: string}>
     */
    public function parseManualRows(array $rows): array
    {
        $parsed = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            $first = trim((string) ($row['first_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));

            $parsed[] = [
                'first_name' => $first !== '' ? $first : null,
                'last_name'  => $last !== '' ? $last : null,
                'email'      => $email,
            ];
        }

        return $parsed;
    }

    /**
     * A one-off preview send, so the wording can be checked in a real inbox
     * before the list gets it.
     */
    public function sendTest(Campaign $campaign, string $email): void
    {
        $recipient = new CampaignRecipient([
            'campaign_id'       => $campaign->id,
            'email'             => $email,
            'first_name'        => 'Test',
            'last_name'         => 'Gönderimi',
            'locale'            => $campaign->locale,
            'unsubscribe_token' => Str::lower(Str::random(64)),
        ]);
        $recipient->setRelation('campaign', $campaign);

        \Illuminate\Support\Facades\Mail::to($email)
            ->send(new \App\Mail\CampaignMail($campaign, $recipient, isTest: true));
    }
}
