<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topic Cluster için her ürünün etrafına önceden planlanmış 7 farklı
 * "açı"yı (search intent) saklar. AI ürün adından önerir, admin onaylar
 * ve tek tıkla mevcut BlogGenerationService ile blog yazısına çevirir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('title', 200);
            $table->string('search_intent', 50)->nullable(); // how_to, comparison, storage, purchase, recipe, health, buying_guide
            $table->text('notes')->nullable();               // admin'in topic için ekstra notu (opsiyonel)

            // Bu topic blog'a çevrildi mi? Eğer evet, hangi yazı?
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('blog_post_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_topics');
    }
};
