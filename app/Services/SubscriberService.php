<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\CampaignRecipient;
use App\Models\Subscriber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SubscriberService
{
    /**
     * Add an address to the list, or bring a former subscriber back.
     *
     * Signing up again after unsubscribing has to work: the row is kept for its
     * history, so it is revived rather than duplicated.
     *
     * @param array<int, int> $listIds Üyelik eklenecek listeler
     */
    public function subscribe(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $locale = null,
        string $source = 'form',
        array $listIds = [],
    ): Subscriber {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($email, $firstName, $lastName, $locale, $source, $listIds): Subscriber {
            $existing = Subscriber::withTrashed()->where('email', $email)->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    // Boş gelen ad kayıtlıyı silmemeli: abone formu yalnızca
                    // adres soruyor, isim başka bir yoldan girilmiş olabilir.
                    'first_name'      => $firstName ?: $existing->first_name,
                    'last_name'       => $lastName ?: $existing->last_name,
                    'locale'          => $locale ?: $existing->locale,
                    'status'          => SubscriberStatus::Subscribed,
                    'subscribed_at'   => now(),
                    'unsubscribed_at' => null,
                ]);

                // Üyelik ekleniyor, mevcutlar sökülmüyor: bültene yeniden
                // kaydolan bir tedarikçi tedarikçi listesinden düşmemeli.
                $this->attachLists($existing, $listIds);

                return $existing->refresh();
            }

            $subscriber = $this->createOrLoad($email, [
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'locale'        => $locale,
                'status'        => SubscriberStatus::Subscribed,
                'source'        => $source,
                'subscribed_at' => now(),
            ]);

            $this->attachLists($subscriber, $listIds);

            return $subscriber;
        });
    }

    /**
     * Adres için kaydı açar; aynı anda başkası açtıysa onu döndürür.
     *
     * Bir adres tek bir abone kaydına karşılık gelmeli ve bunu artık
     * veritabanı garanti ediyor. "Önce ara sonra yaz" iki eşzamanlı istekte
     * ikisine de boş sonuç verebiliyor; ikinci yazma kısıta takıldığında hata
     * vermek yerine ilkinin açtığı kayıt okunuyor.
     *
     * @param array<string, mixed> $attributes
     */
    private function createOrLoad(string $email, array $attributes): Subscriber
    {
        try {
            return Subscriber::create($attributes + ['email' => $email]);
        } catch (UniqueConstraintViolationException) {
            return Subscriber::withTrashed()->where('email', $email)->firstOrFail();
        }
    }

    /**
     * @param array<int, int> $listIds
     */
    private function attachLists(Subscriber $subscriber, array $listIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $listIds))));

        if ($ids === []) {
            return;
        }

        $subscriber->lists()->syncWithoutDetaching($ids);
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

        return $this->createOrLoad($email, [
            'first_name'    => $recipient->first_name,
            'last_name'     => $recipient->last_name,
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
     * @param array<int, array{email: string, first_name?: ?string, last_name?: ?string}> $rows
     * @param array<int, int> $listIds Yüklenen herkesin ekleneceği listeler
     * @return array{added: int, updated: int, skipped: int}
     */
    public function importMany(array $rows, ?string $locale = null, string $source = 'import', array $listIds = []): array
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

            $this->subscribe($email, $row['first_name'] ?? null, $row['last_name'] ?? null, $locale, $source, $listIds);

            $existed ? $updated++ : $added++;
        }

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Süzülmüş abone listesi.
     *
     * @param array<string, mixed>|null $filters
     * @return LengthAwarePaginator<int, Subscriber>
     */
    public function paginate(int $perPage = 25, ?array $filters = null): LengthAwarePaginator
    {
        $filters ??= [];

        $query = Subscriber::query()->with('lists:id,name');

        if (($filters['status'] ?? '') !== '' && ($status = SubscriberStatus::tryFrom((string) $filters['status'])) !== null) {
            $query->where('status', $status);
        }

        if (($filters['source'] ?? '') !== '' && SubscriberSource::tryFrom((string) $filters['source']) !== null) {
            $query->where('source', $filters['source']);
        }

        if (($filters['locale'] ?? '') !== '') {
            $query->where('locale', $filters['locale']);
        }

        // Liste süzgeci: "tedarikçilerim kimler" sorusunun cevabı.
        if (($filters['list_id'] ?? '') !== '') {
            $query->whereHas('lists', fn (Builder $sub) => $sub->whereKey((int) $filters['list_id']));
        }

        // Hiçbir listede olmayanlar: liste hedefli kampanyalarda bu adreslere
        // mail gitmiyor, gözden kaçmasınlar.
        if (! empty($filters['unlisted'])) {
            $query->whereDoesntHave('lists');
        }

        if (($filters['from'] ?? '') !== '') {
            $query->where('created_at', '>=', Carbon::parse((string) $filters['from'])->startOfDay());
        }

        if (($filters['to'] ?? '') !== '') {
            $query->where('created_at', '<=', Carbon::parse((string) $filters['to'])->endOfDay());
        }

        if (($filters['search'] ?? '') !== '') {
            $term = $this->likeTerm((string) $filters['search']);

            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw("email LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("first_name LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("last_name LIKE ? ESCAPE '!'", [$term]);
            });
        }

        $query = match ($filters['sort'] ?? '') {
            'oldest' => $query->oldest('id'),
            'email'  => $query->orderBy('email'),
            default  => $query->latest('id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Arama terimini LIKE kalıbına çevirir; % ve _ joker değil harf sayılır.
     */
    private function likeTerm(string $value): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
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
            // Hiçbir listede olmayan aktif aboneler: liste hedefli kampanyalarda
            // bunlara mail gitmiyor, sayfanın uyarması gereken tek durum bu.
            'unlisted'     => Subscriber::query()->subscribed()->whereDoesntHave('lists')->count(),
        ];
    }
}
