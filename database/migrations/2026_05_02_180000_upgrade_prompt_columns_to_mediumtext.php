<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt kolonlarını MEDIUMTEXT'e yükselt — defansif kapasite.
 *
 * TEXT     = 65,535 char (~65 KB)
 * MEDIUMTEXT = 16,777,215 char (~16 MB)
 *
 * Mevcut prompt'lar 4-7k char arasında; validation 20k. TEXT teorik olarak
 * yeterli (3x marj) ama özel gün/markalı/CTA gibi uzun template'ler büyürse
 * darboğaz olmasın. MEDIUMTEXT 250x daha geniş alan.
 *
 * Etkilenen kolonlar:
 *  - ai_image_prompts.prompt
 *  - ai_generated_images.final_prompt
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite TEXT zaten unbounded, ALTER COLUMN gerekmez. MySQL/MariaDB
        // için raw SQL — Schema::table doctrine/dbal gerektiriyor, raw daha
        // güvenli ve bağımsız.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE ai_image_prompts MODIFY prompt MEDIUMTEXT NOT NULL');
            DB::statement('ALTER TABLE ai_generated_images MODIFY final_prompt MEDIUMTEXT NOT NULL');
        }
        // Diğer driver'larda no-op
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Geri dönüş: TEXT'e indir (eğer içerikte 65k+ char varsa truncate olur)
            DB::statement('ALTER TABLE ai_image_prompts MODIFY prompt TEXT NOT NULL');
            DB::statement('ALTER TABLE ai_generated_images MODIFY final_prompt TEXT NOT NULL');
        }
    }
};
