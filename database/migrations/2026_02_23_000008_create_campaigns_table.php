<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 30);
            $table->text('description')->nullable();

            // Discount config
            $table->decimal('discount_value', 10, 2);
            $table->string('discount_type', 15)->default('percentage');
            $table->decimal('min_order_amount', 10, 2)->default(0);

            // Coupon code (nullable, only for coupon type)
            $table->string('code', 50)->nullable()->unique();

            // Date range
            $table->dateTime('start_date');
            $table->dateTime('end_date');

            // Limits
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
