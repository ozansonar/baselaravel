<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blog posts move from an is_published boolean to the ContentStatus the rest
 * of the panel already speaks — pages have used it from the start.
 *
 * A published post with no date is repaired on the way: the front only shows
 * posts whose date has arrived, so those rows were invisible on the site while
 * the panel reported them as live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('status', 20)
                ->default(ContentStatus::Draft->value)
                ->after('image');

            $table->index('status');
        });

        DB::table('blog_posts')
            ->where('is_published', true)
            ->update(['status' => ContentStatus::Published->value]);

        DB::table('blog_posts')
            ->where('status', ContentStatus::Published->value)
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('created_at')]);

        Schema::table('blog_posts', function (Blueprint $table): void {
            // The index has to go first; SQLite refuses to drop a column an
            // index still points at.
            $table->dropIndex(['is_published']);
            $table->dropColumn('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->boolean('is_published')->default(false)->after('image');
            $table->index('is_published');
        });

        DB::table('blog_posts')
            ->where('status', ContentStatus::Published->value)
            ->update(['is_published' => true]);

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
