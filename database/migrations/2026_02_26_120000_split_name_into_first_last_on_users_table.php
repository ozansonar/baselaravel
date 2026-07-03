<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->after('id')->default('');
            $table->string('last_name')->after('first_name')->default('');
        });

        // Migrate existing data: split "name" into first_name and last_name
        DB::table('users')->orderBy('id')->chunk(100, function ($users): void {
            foreach ($users as $user) {
                $parts = explode(' ', trim($user->name), 2);
                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $parts[0] ?? '',
                    'last_name'  => $parts[1] ?? '',
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->after('id')->default('');
        });

        DB::table('users')->orderBy('id')->chunk(100, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
