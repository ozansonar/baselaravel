<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vertex AI çoklu referans görsel desteği:
     *  vertex_prompts.reference_image_path/mime → reference_images (JSON list)
     *  vertex_generations.reference_image_path  → reference_images  (JSON list)
     *
     * Her item: {"path": "vertex-prompts/foo.png", "mime": "image/png"}
     */
    public function up(): void
    {
        // 1) Yeni JSON kolonlarını ekle
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->json('reference_images')->nullable()->after('prompt');
        });
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->json('reference_images')->nullable()->after('prompt_used');
        });

        // 2) Mevcut tek görsel datayı yeni array kolonuna taşı
        $prompts = DB::table('vertex_prompts')
            ->whereNotNull('reference_image_path')
            ->get(['id', 'reference_image_path', 'reference_image_mime']);
        foreach ($prompts as $row) {
            DB::table('vertex_prompts')
                ->where('id', $row->id)
                ->update([
                    'reference_images' => json_encode([[
                        'path' => $row->reference_image_path,
                        'mime' => $row->reference_image_mime ?: 'image/png',
                    ]]),
                ]);
        }

        $generations = DB::table('vertex_generations')
            ->whereNotNull('reference_image_path')
            ->get(['id', 'reference_image_path']);
        foreach ($generations as $row) {
            DB::table('vertex_generations')
                ->where('id', $row->id)
                ->update([
                    'reference_images' => json_encode([[
                        'path' => $row->reference_image_path,
                        'mime' => 'image/png',
                    ]]),
                ]);
        }

        // 3) Eski tek-görsel kolonlarını sil
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->dropColumn(['reference_image_path', 'reference_image_mime']);
        });
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->dropColumn(['reference_image_path']);
        });
    }

    public function down(): void
    {
        // 1) Eski kolonları geri ekle
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->string('reference_image_path', 255)->nullable()->after('prompt');
            $table->string('reference_image_mime', 60)->nullable()->after('reference_image_path');
        });
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->string('reference_image_path', 255)->nullable()->after('prompt_used');
        });

        // 2) İlk array elemanını eski kolona geri yaz
        $prompts = DB::table('vertex_prompts')->whereNotNull('reference_images')->get(['id', 'reference_images']);
        foreach ($prompts as $row) {
            $list = json_decode((string) $row->reference_images, true);
            if (is_array($list) && isset($list[0]['path'])) {
                DB::table('vertex_prompts')->where('id', $row->id)->update([
                    'reference_image_path' => $list[0]['path'],
                    'reference_image_mime' => $list[0]['mime'] ?? null,
                ]);
            }
        }
        $generations = DB::table('vertex_generations')->whereNotNull('reference_images')->get(['id', 'reference_images']);
        foreach ($generations as $row) {
            $list = json_decode((string) $row->reference_images, true);
            if (is_array($list) && isset($list[0]['path'])) {
                DB::table('vertex_generations')->where('id', $row->id)->update([
                    'reference_image_path' => $list[0]['path'],
                ]);
            }
        }

        // 3) Yeni JSON kolonlarını sil
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->dropColumn('reference_images');
        });
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->dropColumn('reference_images');
        });
    }
};
