<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->text('ig_caption_template')->nullable()->after('tags');
            $table->string('ig_title_template', 500)->nullable()->after('ig_caption_template');
            $table->text('ig_hashtags_template')->nullable()->after('ig_title_template');
        });

        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->text('ig_caption')->nullable()->after('output_image_path');
            $table->string('ig_title', 500)->nullable()->after('ig_caption');
            $table->text('ig_hashtags')->nullable()->after('ig_title');
        });
    }

    public function down(): void
    {
        Schema::table('vertex_generations', function (Blueprint $table) {
            $table->dropColumn(['ig_caption', 'ig_title', 'ig_hashtags']);
        });

        Schema::table('vertex_prompts', function (Blueprint $table) {
            $table->dropColumn(['ig_caption_template', 'ig_title_template', 'ig_hashtags_template']);
        });
    }
};
