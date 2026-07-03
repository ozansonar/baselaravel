<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library_images', function (Blueprint $table): void {
            // Visibility: 'private' = sadece bulk import için, 'public' = halka açık paylaşım da OK
            $table->enum('visibility', ['private', 'public'])->default('private')->after('alt_text');
            $table->index('visibility', 'mli_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::table('media_library_images', function (Blueprint $table): void {
            $table->dropIndex('mli_visibility_idx');
            $table->dropColumn('visibility');
        });
    }
};
