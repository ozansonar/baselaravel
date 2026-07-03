<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->after('order_number');
            $table->index('tracking_token');
        });

        // Generate tracking tokens for existing orders
        $orders = \App\Models\Order::whereNull('tracking_token')->get();
        foreach ($orders as $order) {
            $order->update(['tracking_token' => bin2hex(random_bytes(32))]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tracking_token']);
            $table->dropColumn('tracking_token');
        });
    }
};
