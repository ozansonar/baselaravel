<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vertex_prompt_versions')) {
            Schema::create('vertex_prompt_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vertex_prompt_id')->constrained('vertex_prompts')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->json('data');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->softDeletes();

                $table->index(['vertex_prompt_id', 'version_number']);
            });

            return;
        }

        Schema::table('vertex_prompt_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('vertex_prompt_versions', 'version_number')) {
                $table->unsignedInteger('version_number')->default(0)->after('vertex_prompt_id');
            }

            if (! Schema::hasColumn('vertex_prompt_versions', 'data')) {
                $table->json('data')->nullable()->after('version_number');
            }

            if (! Schema::hasColumn('vertex_prompt_versions', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('data');
            }

            if (! Schema::hasColumn('vertex_prompt_versions', 'created_at')) {
                $table->timestamp('created_at')->useCurrent()->after('created_by');
            }

            if (! Schema::hasColumn('vertex_prompt_versions', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasColumn('vertex_prompt_versions', 'version_number')) {
            return;
        }

        $hasIndex = collect(\Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM vertex_prompt_versions WHERE Key_name = 'vertex_prompt_versions_vertex_prompt_id_version_number_index'"
        ))->isNotEmpty();

        if (! $hasIndex) {
            Schema::table('vertex_prompt_versions', function (Blueprint $table) {
                $table->index(['vertex_prompt_id', 'version_number']);
            });
        }
    }

    public function down(): void
    {
        // Sütun kaldırmak riskli — down boş bırakılıyor
    }
};
