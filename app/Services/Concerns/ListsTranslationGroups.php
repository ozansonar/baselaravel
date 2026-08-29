<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Admin lists show one row per record, not one per translation.
 *
 * Every edit form in the panel is group based: opening any translation of a
 * record brings up the same tabbed form. A list that printed raw rows showed
 * the same record several times, with nothing saying which language each row
 * belonged to — and the row count grew with every language added.
 */
trait ListsTranslationGroups
{
    /**
     * The one row that stands for its group: the default language's
     * translation when it exists, otherwise the one created first.
     *
     * @param class-string<Model> $modelClass
     * @return Builder<Model>
     */
    protected function groupRepresentatives(string $modelClass): Builder
    {
        $default = app(LanguageService::class)->defaultCode();

        return $modelClass::withTrashed()
            ->selectRaw('coalesce(min(case when locale = ? then id end), min(id))', [$default])
            ->groupBy('lang_group_id');
    }

    /**
     * Narrows a list query down to one row per group.
     *
     * @param Builder<Model> $query
     * @param class-string<Model> $modelClass
     * @return Builder<Model>
     */
    protected function onlyGroupRepresentatives(Builder $query, string $modelClass): Builder
    {
        return $query->whereIn($query->getModel()->getTable() . '.id', $this->groupRepresentatives($modelClass));
    }

    /**
     * Widens a search: matching any translation surfaces the whole group, so
     * an English title still finds the record while the Turkish row is shown.
     *
     * @param Builder<Model> $query
     * @param class-string<Model> $modelClass
     * @param callable(Builder<Model>): void $match
     * @return Builder<Model>
     */
    protected function whereGroupMatches(Builder $query, string $modelClass, callable $match): Builder
    {
        $matching = $modelClass::withTrashed()
            ->select('lang_group_id')
            ->where($match);

        return $query->whereIn('lang_group_id', $matching);
    }

    /**
     * Hangs each group's language list on its row in one extra query, so the
     * table can show which translations exist without an N+1.
     *
     * @param class-string<Model> $modelClass
     */
    protected function attachGroupLocales(LengthAwarePaginator $rows, string $modelClass): LengthAwarePaginator
    {
        $groupIds = collect($rows->items())->pluck('lang_group_id')->filter()->unique();

        if ($groupIds->isEmpty()) {
            return $rows;
        }

        $locales = $modelClass::withTrashed()
            ->whereIn('lang_group_id', $groupIds)
            ->get(['lang_group_id', 'locale'])
            ->groupBy('lang_group_id')
            ->map(fn ($group) => $group->pluck('locale')->unique()->values()->all());

        foreach ($rows->items() as $row) {
            $row->setAttribute('group_locales', $locales[$row->lang_group_id] ?? []);
        }

        return $rows;
    }

    /**
     * Counts records rather than translations.
     *
     * The count follows the same row the list shows, so a group whose English
     * translation is passive while its Turkish one is active is not counted in
     * both places.
     *
     * @param Builder<Model> $query
     */
    protected function countGroups(Builder $query): int
    {
        return $this->onlyGroupRepresentatives($query, $query->getModel()::class)->count();
    }

    /**
     * Deleting from the list removes the record in every language.
     *
     * Leaving the siblings behind would look like the delete silently failed,
     * because the row would still be there. A single translation is removed by
     * emptying its tab in the form instead.
     */
    protected function deleteTranslationGroup(Model $row): void
    {
        DB::transaction(function () use ($row): void {
            $row::query()
                ->where('lang_group_id', $row->lang_group_id)
                ->get()
                ->each(fn (Model $sibling) => $sibling->delete());
        });
    }

    /**
     * Listede seçilen satırları tek seferde siler.
     *
     * Listede her grup tek satırla duruyor, silme de grup grup işliyor —
     * tekil silmeyle aynı kural. Aynı gruba ait iki satır seçilse bile grup
     * bir kez siliniyor; dönen sayı bu yüzden seçilen satır sayısı değil,
     * gerçekten silinen kayıt sayısı: ekranda "3 seçildi" deyip "5 silindi"
     * yazmak kullanıcıya ne olduğunu yanlış anlatırdı.
     *
     * @param  class-string<Model> $modelClass
     * @param  list<int>           $ids
     * @return int                 silinen kayıt sayısı
     */
    protected function deleteGroupsById(string $modelClass, array $ids): int
    {
        return $this->applyToGroups($modelClass::query()->whereIn('id', $ids)->get(), $this->deleteTranslationGroup(...));
    }

    /**
     * Seçilen satırları çöpten tek seferde çıkarır.
     *
     * @param  class-string<Model> $modelClass
     * @param  list<int>           $ids
     * @return int                 geri yüklenen kayıt sayısı
     */
    protected function restoreGroupsById(string $modelClass, array $ids): int
    {
        return $this->applyToGroups($modelClass::onlyTrashed()->whereIn('id', $ids)->get(), $this->restoreTranslationGroup(...));
    }

    /**
     * Her grubu bir kez işler; hepsi tek işlemde.
     *
     * @param  \Illuminate\Support\Collection<int, Model> $rows
     * @return int                                        işlenen grup sayısı
     */
    private function applyToGroups($rows, callable $action): int
    {
        if ($rows->isEmpty()) {
            return 0;
        }

        $islenen = 0;

        DB::transaction(function () use ($rows, $action, &$islenen): void {
            $gorulen = [];

            foreach ($rows as $row) {
                if (in_array($row->lang_group_id, $gorulen, true)) {
                    continue;
                }

                $gorulen[] = $row->lang_group_id;
                $action($row);
                $islenen++;
            }
        });

        return $islenen;
    }

    /**
     * Brings a whole group back out of the bin.
     */
    protected function restoreTranslationGroup(Model $row): void
    {
        DB::transaction(function () use ($row): void {
            $row::query()
                ->onlyTrashed()
                ->where('lang_group_id', $row->lang_group_id)
                ->get()
                ->each(fn (Model $sibling) => $sibling->restore());
        });
    }
}
