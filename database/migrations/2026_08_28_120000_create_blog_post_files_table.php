<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İçeriklere iliştirilen ekler.
 *
 * Ek doğrudan blog_posts satırına bağlanıyor, gruba değil: her dil kendi
 * satırı olduğu için Türkçe yazının kırk eki varken İngilizcesinin hiç eki
 * olmaması kendiliğinden mümkün oluyor, ayrıca bir locale sütunu tutmaya
 * gerek kalmıyor.
 *
 * blog_post_id boş olan satır "içerik henüz kaydedilmedi" demek: yeni içerik
 * eklerken ya da hiç çevirisi olmayan bir dil sekmesinde dosya, kayıttan önce
 * yükleniyor. token onu forma bağlıyor, user_id başkasının bekleyen dosyasının
 * iliştirilmesini engelliyor — kampanya eklerindeki ile aynı düzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_files', function (Blueprint $table): void {
            $table->id();
            // Silme davranışı gözlemcide: yumuşak silinen içerik eklerini de
            // gizlesin, geri alındığında birlikte dönsünler diye.
            $table->foreignId('blog_post_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('token')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('extension', 20)->default('');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Ek listesi hep "şu içeriğin ekleri, sırasıyla" diye okunuyor.
            $table->index(['blog_post_id', 'sort_order']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_files');
    }
};
