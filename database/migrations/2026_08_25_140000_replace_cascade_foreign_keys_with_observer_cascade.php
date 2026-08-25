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
 * arrives with the right rules and this migration finds nothing to do. It only
 * matters for a database built before that change.
 *
 * Because of that split it must never assume a particular starting state: the
 * constraint may already be correct, may carry the old CASCADE rule, may have
 * been created under a different name, or may not exist at all. Every change is
 * therefore driven by what information_schema actually reports rather than by
 * the name Laravel would have generated.
 *
 * SQLite stores foreign keys inside the table definition and cannot alter them,
 * so it is skipped; there the fix comes from migrate:fresh.
 */
return new class extends Migration
{
    /**
     * table => [column, referenced table, desired delete rule]
     *
     * @var array<int, array{string, string, string, string}>
     */
    private const CONSTRAINTS = [
        ['blog_posts',          'blog_category_id', 'blog_categories', 'RESTRICT'],
        ['blog_posts',          'user_id',          'users',           'SET NULL'],
        ['blog_comments',       'blog_post_id',     'blog_posts',      'RESTRICT'],
        ['menu_items',          'menu_id',          'menus',           'RESTRICT'],
        ['admin_notifications', 'user_id',          'users',           'RESTRICT'],
    ];

    public function up(): void
    {
        if (! $this->canAlterForeignKeys()) {
            return;
        }

        // An author may now be removed without taking their posts along, so the
        // column has to accept null before the SET NULL rule can be applied.
        if (Schema::hasTable('blog_posts') && ! $this->isNullable('blog_posts', 'user_id')) {
            DB::statement('ALTER TABLE `blog_posts` MODIFY `user_id` BIGINT UNSIGNED NULL');
        }

        foreach (self::CONSTRAINTS as [$table, $column, $references, $rule]) {
            $this->applyRule($table, $column, $references, $rule);
        }
    }

    public function down(): void
    {
        if (! $this->canAlterForeignKeys()) {
            return;
        }

        foreach (self::CONSTRAINTS as [$table, $column, $references, $rule]) {
            $this->applyRule($table, $column, $references, 'CASCADE');
        }

        if (Schema::hasTable('blog_posts') && $this->isNullable('blog_posts', 'user_id')) {
            // CASCADE cannot express "no author", so those rows go.
            DB::table('blog_posts')->whereNull('user_id')->delete();

            $this->dropConstraint('blog_posts', 'user_id');
            DB::statement('ALTER TABLE `blog_posts` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
            $this->addConstraint('blog_posts', 'user_id', 'users', 'CASCADE');
        }
    }

    /**
     * Bring one constraint to the wanted delete rule, whatever state it is in.
     */
    private function applyRule(string $table, string $column, string $references, string $rule): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $existing = $this->existingConstraint($table, $column);

        if ($existing !== null && strtoupper((string) $existing->delete_rule) === $rule) {
            return; // already what we want
        }

        if ($existing !== null) {
            $this->dropConstraint($table, $column, (string) $existing->name);
        }

        $this->addConstraint($table, $column, $references, $rule);
    }

    /**
     * The constraint on a column as the database actually knows it — its real
     * name and its current delete rule — or null when there is none.
     */
    private function existingConstraint(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT kcu.CONSTRAINT_NAME AS name, rc.DELETE_RULE AS delete_rule
               FROM information_schema.KEY_COLUMN_USAGE kcu
               JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                 ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
              WHERE kcu.TABLE_SCHEMA = DATABASE()
                AND kcu.TABLE_NAME   = ?
                AND kcu.COLUMN_NAME  = ?
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
              LIMIT 1',
            [$table, $column],
        );
    }

    private function dropConstraint(string $table, string $column, ?string $name = null): void
    {
        $name ??= $this->existingConstraint($table, $column)?->name;

        if ($name === null) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
    }

    private function addConstraint(string $table, string $column, string $references, string $rule): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $rule): void {
            $foreign = $blueprint->foreign($column)->references('id')->on($references);

            match ($rule) {
                'SET NULL' => $foreign->nullOnDelete(),
                'CASCADE'  => $foreign->cascadeOnDelete(),
                default    => $foreign->restrictOnDelete(),
            };
        });
    }

    private function isNullable(string $table, string $column): bool
    {
        $result = DB::selectOne(
            'SELECT IS_NULLABLE AS nullable
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        return $result !== null && strtoupper((string) $result->nullable) === 'YES';
    }

    private function canAlterForeignKeys(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
