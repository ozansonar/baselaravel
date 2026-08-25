<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the ON DELETE CASCADE rules so deletion is driven by the observers
 * instead, which is what makes soft delete and restore travel down the tree.
 *
 * The create-table migrations were corrected too, so a fresh install already
 * gets the right schema. This migration only matters for a database that was
 * built before that change.
 *
 * SQLite stores foreign keys inside the table definition and cannot alter
 * them, so it is skipped; on SQLite the fix comes from migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->canAlterForeignKeys()) {
            return;
        }

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropForeign(['blog_category_id']);
            $table->dropForeign(['user_id']);
        });

        // An author may now be removed without taking their posts along.
        DB::statement('ALTER TABLE blog_posts MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreign('blog_category_id')->references('id')->on('blog_categories')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('blog_comments', function (Blueprint $table): void {
            $table->dropForeign(['blog_post_id']);
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->restrictOnDelete();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['menu_id']);
            $table->foreign('menu_id')->references('id')->on('menus')->restrictOnDelete();
        });

        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->canAlterForeignKeys()) {
            return;
        }

        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['menu_id']);
            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
        });

        Schema::table('blog_comments', function (Blueprint $table): void {
            $table->dropForeign(['blog_post_id']);
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->cascadeOnDelete();
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropForeign(['blog_category_id']);
            $table->dropForeign(['user_id']);
        });

        DB::table('blog_posts')->whereNull('user_id')->delete();
        DB::statement('ALTER TABLE blog_posts MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreign('blog_category_id')->references('id')->on('blog_categories')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function canAlterForeignKeys(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
