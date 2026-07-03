<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk import (toplu Excel paylaşım) ile oluşturulan IG postlarının izlenmesi.
 * - null = manuel oluşturulmuş veya blog auto-share kaynaklı post
 * - dolu = bulk_imports.id'den geldi → bulk durum sayfasında listelenir
 *
 * NOT: ->index() çağrısı KASTEN yok — constrained() InnoDB için zorunlu FK
 * index'ini zaten otomatik oluşturuyor. Ek bir ->index() Laravel'in auto-naming
 * çakışmasına yol açıp 'Duplicate key name 1' hatasına neden oluyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // İdempotent: önceki başarısız deneme yarım kolon bırakmış olabilir
        if (! Schema::hasColumn('instagram_posts', 'bulk_import_id')) {
            Schema::table('instagram_posts', function (Blueprint $table): void {
                $table->foreignId('bulk_import_id')
                    ->nullable()
                    ->after('blog_post_id')
                    ->constrained('bulk_imports')
                    ->nullOnDelete();
            });

            return;
        }

        // Kolon var ama FK eksik (yarım kalan durum) → sadece FK kur
        if (! $this->hasForeignKey('instagram_posts', 'bulk_import_id')) {
            Schema::table('instagram_posts', function (Blueprint $table): void {
                $table->foreign('bulk_import_id')
                    ->references('id')
                    ->on('bulk_imports')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table): void {
            if ($this->hasForeignKey('instagram_posts', 'bulk_import_id')) {
                $table->dropForeign(['bulk_import_id']);
            }
            if (Schema::hasColumn('instagram_posts', 'bulk_import_id')) {
                $table->dropColumn('bulk_import_id');
            }
        });
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $database = DB::connection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column],
        );

        return (int) ($row->cnt ?? 0) > 0;
    }
};
