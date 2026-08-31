<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes leftover e-commerce wording from the seeded mail templates.
 *
 * The base kit dropped its product/order modules, but the welcome template
 * seeded before that still advertised a product catalogue. Replacement is
 * targeted at the exact stale snippet so any admin customisation survives.
 */
return new class extends Migration
{
    private const STALE = '<td class="em-feature-icon-td">&#127793;</td>
                    <td class="em-feature-text-td"><strong>Taze ürünlere</strong> göz atın</td>';

    private const FIXED = '<td class="em-feature-icon-td">&#128100;</td>
                    <td class="em-feature-text-td"><strong>Profil bilgilerinizi</strong> yönetin</td>';

    public function up(): void
    {
        $this->replaceInWelcomeBody(self::STALE, self::FIXED);

        // Order templates were never seeded, but drop them if a stale install has them.
        DB::table('mail_templates')
            ->whereIn('key', ['order_confirmation', 'order_status_updated'])
            ->delete();
    }

    public function down(): void
    {
        $this->replaceInWelcomeBody(self::FIXED, self::STALE);
    }

    private function replaceInWelcomeBody(string $search, string $replace): void
    {
        $template = DB::table('mail_templates')->where('key', 'welcome')->first();

        if ($template === null || ! str_contains((string) $template->body, $search)) {
            return;
        }

        DB::table('mail_templates')
            ->where('key', 'welcome')
            ->update([
                'body'       => str_replace($search, $replace, (string) $template->body),
                'updated_at' => now(),
            ]);
    }
};
