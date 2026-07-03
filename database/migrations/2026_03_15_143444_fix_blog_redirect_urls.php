<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * /blog/{slug} → /blog/{categorySlug}/{slug}
     */
    public function up(): void
    {
        DB::table('redirects')
            ->where('new_url', 'like', '/blog/%')
            ->update([
                'new_url' => DB::raw("
                    CONCAT(
                        '/blog/',
                        COALESCE(
                            (SELECT bc.slug
                             FROM blog_posts bp
                             JOIN blog_categories bc ON bc.id = bp.blog_category_id
                             WHERE bp.slug = SUBSTRING(redirects.new_url, 7)
                             AND bp.deleted_at IS NULL
                             LIMIT 1),
                            'genel'
                        ),
                        '/',
                        SUBSTRING(redirects.new_url, 7)
                    )
                "),
            ]);
    }

    /**
     * /blog/{categorySlug}/{slug} → /blog/{slug}
     */
    public function down(): void
    {
        DB::table('redirects')
            ->where('new_url', 'like', '/blog/%/%')
            ->update([
                'new_url' => DB::raw("
                    CONCAT('/blog/', SUBSTRING_INDEX(redirects.new_url, '/', -1))
                "),
            ]);
    }
};
