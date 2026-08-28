<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignAudience;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\SubscriberStatus;
use App\Models\Campaign;
use App\Models\CampaignAttachment;
use App\Models\CampaignRecipient;
use App\Models\Subscriber;
use App\Support\PersonName;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Illuminate\Database\Eloquent\Builder;

final class CampaignService
{
    /**
     * Alıcı listesinin tanıdığı süzgeç anahtarları.
     *
     * @return list<string>
     */
    public function recipientFilterKeys(): array
    {
        return ['rstatus', 'rsearch'];
    }

    /**
     * Bir kampanyanın süzülmüş alıcı sorgusu.
     *
     * Ekran, CSV indirmesi ve dışa aktarma aynı sorguyu kullanır: "başarısızları
     * ver" diyen biri üç ayrı yerde üç ayrı liste görmemeli.
     *
     * @param array<string, mixed> $filters
     * @return Builder<CampaignRecipient>
     */
    public function recipientQuery(Campaign $campaign, array $filters = []): Builder
    {
        $status = (string) ($filters['rstatus'] ?? '');
        $search = trim((string) ($filters['rsearch'] ?? ''));

        // İlişki yerine doğrudan sorgu: ilişki nesnesi sorgu arayüzünü
        // karşılamıyor, dışa aktarma ise sorgu üzerinden geziyor.
        return CampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->when(
                CampaignRecipientStatus::tryFrom($status) !== null,
                static fn (Builder $query) => $query->where('status', $status),
            )
            ->when($search !== '', static function (Builder $query) use ($search): void {
                // Joker karakterler düz metin sayılıyor, yoksa "%" tüm listeyi getirir.
                $term = '%' . addcslashes($search, '%_\\') . '%';

                $query->where(static function (Builder $inner) use ($term): void {
                    $inner->where('email', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->orderBy('id');
    }

    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * Ekran da dışa aktarma da bu listeyi okur; iki yerde ayrı yazılsaydı
     * dosyaya inen ile ekranda görünen zamanla ayrışırdı.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['search', 'status', 'audience', 'from', 'to', 'sort'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış kampanya sorgusu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<Campaign>
     */
    public function query(array $filters = []): Builder
    {
        $query = Campaign::query()->withCount('recipients')->with('author');

        if (($case = CampaignStatus::tryFrom((string) ($filters['status'] ?? ''))) !== null) {
            $query->where('status', $case);
        }

        if (($audience = CampaignAudience::tryFrom((string) ($filters['audience'] ?? ''))) !== null) {
            $query->where('audience', $audience);
        }

        if (($filters['search'] ?? '') !== '') {
            // Joker karakterler düz metin sayılıyor: "%" yazan biri tüm listeyi
            // getirmemeli.
            $term = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';

            $query->where(function (Builder $sub) use ($term): void {
                $sub->where('name', 'like', $term)->orWhere('subject', 'like', $term);
            });
        }

        // Tarih aralığı oluşturulma gününe göre; bitiş günü de dâhil.
        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        // Sıralama seçenekleri sabit: istekten gelen değer doğrudan sütun adı
        // olarak sorguya giremiyor.
        match ($filters['sort'] ?? '') {
            'oldest'     => $query->oldest('id'),
            'name'       => $query->orderBy('name'),
            'recipients' => $query->orderByDesc('total_recipients')->orderByDesc('id'),
            'sent'       => $query->orderByDesc('sent_count')->orderByDesc('id'),
            default      => $query->latest('id'),
        };

        return $query;
    }

    private const UPLOAD_FOLDER = 'campaigns';

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign
    {
        $campaign = DB::transaction(function () use ($data): Campaign {
            $campaign = Campaign::create($this->fields($data) + [
                'status'  => CampaignStatus::Draft,
                'user_id' => auth()->id(),
            ]);

            // Ekler artık forma değil kendi isteğine biniyor; doğrudan dosya
            // gönderen çağrılar (testler, programatik kullanım) için ikisi de açık.
            $this->syncAttachments($campaign, $data['attachments'] ?? []);
            $this->attachPending($campaign, $data['attachment_tokens'] ?? []);

            return $campaign;
        });

        // Kitle formda seçildi; listenin kurulması için ayrıca bir düğmeye
        // basılmasının kullanıcı açısından bir anlamı yok. Kayıtla birlikte
        // kuruluyor ki detay ekranı açıldığında adresler orada olsun.
        $this->prepareRecipientsQuietly($campaign);

        return $campaign;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        if (! $campaign->isEditable()) {
            throw new RuntimeException('Gönderimi başlamış bir kampanyanın içeriği değiştirilemez.');
        }

        DB::transaction(function () use ($campaign, $data): void {
            $onceki = [
                'audience' => $campaign->audience,
                'filter'   => $campaign->audience_filter,
            ];

            $campaign->update($this->fields($data));

            // Kitle değiştiyse önden hazırlanmış liste artık başka bir seçimi
            // anlatıyor; bırakılırsa kampanya, formda görünenden bambaşka bir
            // adres kümesine gider. Mail çıkmışsa dokunulmuyor.
            $kitleDegisti = $campaign->audience !== $onceki['audience']
                || $campaign->audience_filter != $onceki['filter'];

            if ($kitleDegisti && $campaign->recipients()->exists() && ! $this->hasDeliveries($campaign)) {
                $this->dropPreparedRecipients($campaign);
            }

            // Ekler artık forma değil kendi isteğine biniyor; doğrudan dosya
            // gönderen çağrılar (testler, programatik kullanım) için ikisi de açık.
            $this->syncAttachments($campaign, $data['attachments'] ?? []);
            $this->attachPending($campaign, $data['attachment_tokens'] ?? []);
        });

        // Kitle değiştiyse eski liste yukarıda düştü; yenisi hemen kuruluyor,
        // yoksa düzenlemeden dönen kullanıcı boş bir alıcı listesi bulurdu.
        $this->prepareRecipientsQuietly($campaign);

        return $campaign->refresh();
    }

    /**
     * Listeyi kurar, kuramıyorsa sessizce geçer.
     *
     * Kitle boş ya da okunamıyor olabilir (silinmiş liste, boş süzgeç). Bu,
     * onay ekranında zaten uyarı olarak görünüyor; kampanyanın kaydını
     * engellemesi için bir sebep değil.
     */
    public function prepareRecipientsQuietly(Campaign $campaign): void
    {
        try {
            $this->prepareRecipients($campaign);
        } catch (Throwable) {
            // Sessiz: alıcısız kampanya da taslak olarak kaydedilebilmeli.
        }
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
                // Gönderim dışında bırakılanlar toplama girmiyor: giremeyecek
                // adresler sayılırsa ilerleme çubuğu asla %100'e ulaşmaz.
                'total_recipients' => $campaign->recipients()
                    ->where('status', '!=', CampaignRecipientStatus::Skipped)
                    ->count(),
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
     * Gönderimden önce alıcı listesini oluşturur.
     *
     * Liste yalnızca gönderim onaylanınca donduğu için istenmeyen bir adresi
     * ayıklamanın tek yolu kampanyayı başlatmaktı; taslakta görünen tek şey on
     * kişilik bir örnekti. Liste onay öncesinde de kurulabiliyor: yönetici tam
     * listeyi süzgeçleyip çıkaracağını çıkarıyor, sonra onaylıyor. start()
     * hazır listeyi yeniden kurmadığı için bu ayıklama gönderime taşınıyor.
     *
     * @param  bool $refresh Kaynak liste değiştiyse listeyi baştan kurar
     * @return int hazırlanan alıcı sayısı
     */
    public function prepareRecipients(Campaign $campaign, bool $refresh = false): int
    {
        if (! $campaign->isEditable()) {
            throw new RuntimeException('Gönderimi başlamış bir kampanyanın alıcı listesi kurulamaz.');
        }

        return DB::transaction(function () use ($campaign, $refresh): int {
            $existing = $campaign->recipients()->count();

            if ($existing > 0 && ! $refresh) {
                return $existing;
            }

            if ($existing > 0) {
                $this->dropPreparedRecipients($campaign);
            }

            return $this->buildRecipients($campaign);
        });
    }

    /**
     * Henüz gönderime girmemiş alıcı satırlarını siler.
     *
     * Yumuşak silme değil kalıcı silme: yeniden kurulan liste aynı adresleri
     * içeriyor ve silinmiş satırlar durum sayımlarına takılmasa bile tablo her
     * yenilemede bir kat daha şişerdi. Mail çıkmışsa liste dokunulmaz kalır;
     * kime gittiğinin kaydı listenin tazeliğinden önce gelir.
     */
    private function dropPreparedRecipients(Campaign $campaign): void
    {
        if ($this->hasDeliveries($campaign)) {
            throw new RuntimeException('Bu kampanyadan mail çıkmış, alıcı listesi yeniden kurulamaz.');
        }

        $campaign->recipients()->forceDelete();
    }

    /**
     * Kampanyadan en az bir mail çıkmış mı?
     */
    private function hasDeliveries(Campaign $campaign): bool
    {
        return $campaign->recipients()
            ->whereIn('status', [CampaignRecipientStatus::Sent, CampaignRecipientStatus::Failed])
            ->exists();
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
        // Once it has been frozen the list is the truth, not the filter.
        if ($campaign->recipients()->exists()) {
            // Çıkarılan adresler sayıma girmiyor: onay ekranındaki sayı, gerçekte
            // kaç kişiye gideceğini söylemezse ayıklama yapmanın anlamı kalmaz.
            $gidecek = $campaign->recipients()
                ->where('status', '!=', CampaignRecipientStatus::Skipped);

            $count = (clone $gidecek)->count();

            return [
                'count'  => $count,
                'sample' => (clone $gidecek)
                    ->orderBy('id')
                    ->limit(10)
                    ->get(['first_name', 'last_name', 'email'])
                    ->map(fn ($row): array => [
                        'first_name' => $row->first_name,
                        'last_name'  => $row->last_name,
                        'email'      => $row->email,
                    ])
                    ->all(),
                'error'  => $count === 0 ? 'Listedeki her adres gönderim dışında bırakılmış.' : null,
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

    /**
     * Seçilen alıcıları gönderim dışında bırakır.
     *
     * Gönderilmiş adresler atlanıyor: mail yola çıktıktan sonra çıkarmak
     * mümkün değil, toplu işlemde sessizce dışarıda bırakılıyorlar ve kaç
     * tanesinin işlendiği geri dönüyor.
     *
     * @param  array<int, int> $recipientIds Boşsa süzgeçten geçen tüm uygun satırlar
     * @return int İşlenen satır sayısı
     */
    public function excludeRecipients(Campaign $campaign, array $recipientIds): int
    {
        return $campaign->recipients()
            ->whereIn('id', $recipientIds)
            ->whereIn('status', [CampaignRecipientStatus::Pending, CampaignRecipientStatus::Failed])
            ->update(['status' => CampaignRecipientStatus::Skipped]);
    }

    /**
     * Çıkarılmış alıcıları sıraya geri koyar.
     *
     * @param  array<int, int> $recipientIds
     * @return int
     */
    public function restoreRecipients(Campaign $campaign, array $recipientIds): int
    {
        $count = $campaign->recipients()
            ->whereIn('id', $recipientIds)
            ->where('status', CampaignRecipientStatus::Skipped)
            ->update(['status' => CampaignRecipientStatus::Pending]);

        if ($count > 0) {
            $this->reopenIfCompleted($campaign);
        }

        return $count;
    }

    /**
     * Başarısız alıcıları yeniden denemeye alır.
     *
     * Yalnızca durumu değiştirmek yetmez: satır zaten deneme tavanına ulaştığı
     * için başarısız sayılmış, sayaç sıfırlanmazsa bir sonraki turda anında
     * yine başarısız olur. Kampanyanın failed_count'u da geri alınıyor, yoksa
     * ekrandaki özet gerçeği söylemez.
     *
     * @param  array<int, int> $recipientIds Boşsa kampanyanın tüm başarısızları
     * @return int
     */
    public function retryFailed(Campaign $campaign, array $recipientIds = []): int
    {
        return DB::transaction(function () use ($campaign, $recipientIds): int {
            $query = $campaign->recipients()->where('status', CampaignRecipientStatus::Failed);

            if ($recipientIds !== []) {
                $query->whereIn('id', $recipientIds);
            }

            $count = $query->update([
                'status'   => CampaignRecipientStatus::Pending,
                'attempts' => 0,
                'error'    => null,
            ]);

            if ($count === 0) {
                return 0;
            }

            $campaign->decrement('failed_count', min($count, (int) $campaign->failed_count));
            $this->reopenIfCompleted($campaign);

            return $count;
        });
    }

    /**
     * Tamamlanmış kampanyayı yeniden gönderime açar.
     *
     * Sıraya yeni satır eklendiğinde durum "gönderildi" kalırsa zamanlanmış
     * görev kampanyayı hiç eline almaz ve satırlar sonsuza dek bekler.
     */
    private function reopenIfCompleted(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Sent) {
            return;
        }

        $campaign->update([
            'status'       => CampaignStatus::Sending,
            'completed_at' => null,
        ]);
    }

    public function deleteAttachment(Campaign $campaign, int $attachmentId): void
    {
        $attachment = $campaign->attachments()->findOrFail($attachmentId);

        $this->uploadService->deleteFile($attachment->path);
        $attachment->delete();
    }

    /**
     * Uygulamanın kendi ek sınırları; sunucununkiyle birlikte
     * UploadService::limits() içinde tartılır.
     */
    public const MAX_ATTACHMENTS = 10;

    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /**
     * @return array{per_file: int, post_max: int, max_files: int}
     */
    public function attachmentLimits(): array
    {
        return $this->uploadService->limits(self::MAX_ATTACHMENT_BYTES, self::MAX_ATTACHMENTS);
    }

    /**
     * Tek bir eki peşin yükler ve kampanya kaydedilene kadar kampanyasız bir
     * satırda bekletir.
     *
     * Ekler kampanya formuyla tek POST'ta gitseydi birkaç dosya post_max_size'ı
     * aşar, PHP gövdeyi komple atar ve CSRF alanı da onunla gittiği için istek
     * 419 dönerdi: kullanıcı yazdığı kampanyayı, alıcı listesini, her şeyi
     * kaybederdi. Her dosya kendi küçük isteğiyle gelince o tavana hiç
     * yaklaşılmıyor ve bir dosyanın başarısızlığı taslağı etkilemiyor.
     *
     * Bekleyen kayıt oturumda tutulurken bu kez başka bir şey kayboluyordu: on
     * dosya seçilince on istek aynı anda gidiyor, her biri oturumu baştan okuyup
     * sonunda geri yazıyor ve en son biten diğerlerinin kaydını eziyordu. On
     * dosyanın yüklendiğini gören kullanıcı kampanyada üçünü buluyordu. Her
     * yükleme artık kendi satırını yazıyor, kimse kimsenin kaydına dokunmuyor.
     *
     * Dönen belirteç dosya yolunu taşımaz. Yol istemciye verilseydi, kaydederken
     * başka bir yol gönderip sunucudaki herhangi bir dosyayı kampanyaya
     * iliştirmek mümkün olurdu.
     *
     * @return array{token: string, name: string, size: int}
     */
    public function storePendingAttachment(UploadedFile $file): array
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'ek';
        $original = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize() ?: 0;

        $path = $this->uploadService->uploadFile($file, self::UPLOAD_FOLDER, $name);
        $token = (string) Str::uuid();

        CampaignAttachment::create([
            'campaign_id'   => null,
            'token'         => $token,
            'user_id'       => auth()->id(),
            'path'          => $path,
            'original_name' => $original,
            'mime_type'     => $mime,
            'size'          => $size,
        ]);

        return ['token' => $token, 'name' => $original, 'size' => $size];
    }

    /**
     * Kullanıcı kaydetmeden vazgeçtiğinde dosyayı diskten de siler; aksi hâlde
     * public/uploads altında sahipsiz dosya birikir.
     */
    public function discardPendingAttachment(string $token): bool
    {
        $bekleyen = CampaignAttachment::query()
            ->pending(auth()->id())
            ->where('token', $token)
            ->first();

        if ($bekleyen === null) {
            return false;
        }

        $this->uploadService->deleteFile($bekleyen->path);
        // Yumuşak silme değil: kayıt yalnızca dosyanın adresi, dosya gittiyse
        // satırın saklanacak bir tarafı kalmıyor.
        $bekleyen->forceDelete();

        return true;
    }

    /**
     * Peşin yüklenmiş ekleri kampanyaya bağlar.
     *
     * Dosya zaten diskte, satır da: burada yalnızca kampanya sahipleniyor.
     * Tanınmayan bir belirteç sessizce atlanıyor — kullanıcı iki sekmede
     * çalışmış ya da eki kaldırmış olabilir, bu kaydı durduracak bir hata değil.
     *
     * Sıra kullanıcının yükleme sırası: forma hangi sırayla eklendiyse
     * kampanyada da o sırayla görünmeli.
     *
     * @param array<int, string> $tokens
     */
    public function attachPending(Campaign $campaign, array $tokens): void
    {
        $tokens = array_values(array_filter($tokens, 'is_string'));

        if ($tokens === []) {
            return;
        }

        $bekleyenler = CampaignAttachment::query()
            ->pending(auth()->id())
            ->whereIn('token', $tokens)
            ->get()
            ->keyBy('token');

        foreach ($tokens as $token) {
            $bekleyenler->get($token)?->update([
                'campaign_id' => $campaign->id,
                'token'       => null,
            ]);
        }
    }

    /**
     * Bağlanmadan kalan ekleri diskten temizler; form terk edildiğinde çağrılır.
     */
    public function discardAllPending(): void
    {
        CampaignAttachment::query()
            ->pending(auth()->id())
            ->get()
            ->each(fn (CampaignAttachment $ek) => $this->discardPendingAttachment((string) $ek->token));
    }

    /**
     * Sahipsiz kalmış bekleyen ekleri siler.
     *
     * Kullanıcı dosyayı yükleyip kampanyayı kaydetmeden çıkarsa satır da dosya
     * da kalıyor. Bunu kimse fark etmediği için temizliği zamanlanmış görev
     * yapıyor; taze bekleyenlere dokunulmuyor, kullanıcı hâlâ formda olabilir.
     *
     * @return int silinen ek sayısı
     */
    public function purgeStalePendingAttachments(int $hours = 24): int
    {
        $silinen = 0;

        CampaignAttachment::query()
            ->whereNull('campaign_id')
            ->where('created_at', '<', now()->subHours($hours))
            ->get()
            ->each(function (CampaignAttachment $ek) use (&$silinen): void {
                $this->uploadService->deleteFile($ek->path);
                $ek->forceDelete();
                $silinen++;
            });

        return $silinen;
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
