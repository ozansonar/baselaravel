<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vertex_generations')
            ->whereNotNull('request_payload')
            ->update(['request_payload' => null]);

        DB::table('vertex_generations')
            ->whereNotNull('response_payload')
            ->update(['response_payload' => null]);
    }

    public function down(): void
    {
        // Payload verileri geri getirilemez
    }
};
