<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiPromptSetting;
use Illuminate\Support\Facades\DB;

/**
 * Round-robin city selector for the daily blog rotation.
 *
 * Each call to `nextCity()` advances the rotation index atomically and returns
 * either a target city name or `null` (signalling that the next post should be
 * a generic, non-city-targeted SEO piece — for example, "köy tereyağı nasıl
 * yapılır").
 *
 * Generic posts are scheduled every Nth run (configurable in admin) so the
 * blog stream stays diverse and avoids being purely location-keyworded.
 */
final class CityRotationService
{
    /**
     * Pick the next city for blog generation and persist the advance.
     *
     * Returns null when:
     *  - target_cities is empty,
     *  - the current run lands on a "generic post" slot (every Nth run).
     *
     * Wrapped in a transaction with `lockForUpdate` so two cron workers running
     * in parallel cannot observe the same rotation index. In practice the four
     * blog cron slots are minutes apart and Schedule::withoutOverlapping is
     * already in place — this is defense-in-depth for shared-hosting setups
     * where clock skew or queue retries could overlap two runs.
     */
    public function nextCity(): ?string
    {
        // Make sure the singleton row exists before we lock it.
        $setting = AiPromptSetting::current();

        return DB::transaction(function () use ($setting): ?string {
            $row = DB::table('ai_prompt_settings')
                ->where('id', $setting->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            $cities = $this->parseCities($row->target_cities ?? null);
            $index  = (int) $row->city_rotation_index;
            $every  = max(0, (int) $row->general_post_every_n_runs);

            // Always advance the index, even on generic slots — keeps the
            // rotation moving so the next run picks the next city.
            DB::table('ai_prompt_settings')
                ->where('id', $setting->id)
                ->update([
                    'city_rotation_index' => $index + 1,
                ]);

            if ($cities === []) {
                return null;
            }

            // Generic-post slot.
            if ($every > 0 && $index > 0 && $index % $every === 0) {
                return null;
            }

            return $cities[$index % count($cities)];
        });
    }

    /**
     * Reset the rotation pointer back to 0 — used after the admin reorders
     * the target city list and wants the next run to start from "İstanbul" again.
     */
    public function resetIndex(): void
    {
        AiPromptSetting::query()
            ->where('id', 1)
            ->update(['city_rotation_index' => 0]);
    }

    /**
     * Decode and sanitize the target_cities column. Accepts the raw DB value
     * (JSON string from query builder, or already-cast array from Eloquent).
     *
     * @return list<string>
     */
    private function parseCities(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            $decoded,
            static fn ($v): bool => is_string($v) && trim($v) !== '',
        ));
    }
}
