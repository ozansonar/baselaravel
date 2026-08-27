<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscriber;
use App\Models\SubscriberList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Abone listelerinin yönetimi.
 *
 * Listeler kampanyanın hedefini belirliyor; bu yüzden "kaç üyesi var" sorusu
 * her ekranda soruluyor ve üye sayısı gerçekten mail alabilecek kişilere göre
 * hesaplanıyor — ayrılmış bir adres üyelikte kalsa da gönderime girmiyor.
 */
final class SubscriberListService
{
    /**
     * Ekranlarda gösterilen liste, mail alabilecek üye sayısıyla birlikte.
     *
     * @return Collection<int, SubscriberList>
     */
    public function all(): Collection
    {
        return SubscriberList::query()
            ->ordered()
            ->withCount([
                'subscribers as members_count',
                'subscribers as active_members_count' => fn ($query) => $query->subscribed(),
            ])
            ->get();
    }

    /**
     * Site formundan gelenlerin düştüğü liste.
     *
     * İşaretli liste silinmişse ilk liste devralıyor: bülten formu bir yere
     * yazamadığı için sessizce kaybolmamalı.
     */
    public function default(): ?SubscriberList
    {
        return SubscriberList::query()->where('is_default', true)->first()
            ?? SubscriberList::query()->ordered()->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): SubscriberList
    {
        return DB::transaction(function () use ($data): SubscriberList {
            $list = SubscriberList::create([
                'name'        => $data['name'],
                'slug'        => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'is_default'  => (bool) ($data['is_default'] ?? false),
                'sort_order'  => (int) ($data['sort_order'] ?? 0),
            ]);

            if ($list->is_default) {
                $this->clearOtherDefaults($list);
            }

            return $list;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(SubscriberList $list, array $data): SubscriberList
    {
        return DB::transaction(function () use ($list, $data): SubscriberList {
            $name = $data['name'] ?? $list->name;

            $list->update([
                'name'        => $name,
                // Ad değiştiyse slug da yenileniyor; adres satırındaki süzgeç
                // bağlantıları listenin kimliğiyle kurulu.
                'slug'        => $name !== $list->name ? $this->uniqueSlug($name, $list->id) : $list->slug,
                'description' => $data['description'] ?? null,
                'is_default'  => (bool) ($data['is_default'] ?? false),
                'sort_order'  => (int) ($data['sort_order'] ?? $list->sort_order),
            ]);

            if ($list->is_default) {
                $this->clearOtherDefaults($list);
            }

            return $list->refresh();
        });
    }

    /**
     * Liste silinir, üyeleri silinmez — yalnızca üyelikleri kalkar.
     *
     * Son liste silinemiyor: site formundan gelen abonenin yazılacağı bir yer
     * kalmaz ve kayıt sessizce kaybolur.
     */
    public function delete(SubscriberList $list): void
    {
        if (SubscriberList::query()->count() < 2) {
            throw new RuntimeException('Son liste silinemez; site formundan gelen aboneler bir listeye yazılmak zorunda.');
        }

        DB::transaction(function () use ($list): void {
            $wasDefault = $list->is_default;

            $list->subscribers()->detach();
            $list->delete();

            if ($wasDefault) {
                // Varsayılan boşta kalmasın: sıradaki liste devralıyor.
                SubscriberList::query()->ordered()->first()?->update(['is_default' => true]);
            }
        });
    }

    /**
     * Seçilen aboneleri bir listeye ekler; zaten üye olanlar yinelenmez.
     *
     * @param array<int, int> $subscriberIds
     */
    public function addMany(SubscriberList $list, array $subscriberIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $subscriberIds)));

        if ($ids === []) {
            return 0;
        }

        $existing = $list->subscribers()->whereIn('subscribers.id', $ids)->pluck('subscribers.id')->all();
        $fresh = array_values(array_diff($ids, $existing));

        $list->subscribers()->attach($fresh);

        return count($fresh);
    }

    /**
     * @param array<int, int> $subscriberIds
     */
    public function removeMany(SubscriberList $list, array $subscriberIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $subscriberIds)));

        return $ids === [] ? 0 : $list->subscribers()->detach($ids);
    }

    /**
     * Bir abonenin listelerini belirtilenlerle değiştirir.
     *
     * @param array<int, int> $listIds
     */
    public function syncFor(Subscriber $subscriber, array $listIds): void
    {
        $subscriber->lists()->sync(array_map('intval', $listIds));
    }

    private function clearOtherDefaults(SubscriberList $list): void
    {
        SubscriberList::query()
            ->whereKeyNot($list->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'liste';
        $slug = $base;
        $suffix = 2;

        while (
            SubscriberList::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
