<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Services\UploadService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ekler yalnız blog yazısının değil, sayfanın da olabiliyor.
 *
 * Tablo `blog_post_files` adıyla ve tek bir yabancı anahtarla doğmuştu. Sayfalar
 * da ek taşıyınca ikinci bir tablo açmak, ikinci bir servis, ikinci bir denetim
 * ve ikinci bir arayüz demekti; ikisi zamanla birbirinden ayrışırdı. Bağ
 * polimorfik hale getiriliyor: aynı tablo, aynı servis, aynı ekran.
 *
 * Bağı boş olan satır yine "henüz kaydedilmedi" demek — belirteçle bekleyen
 * yükleme, hangi içeriğe iliştirileceğini kaydedilirken öğreniyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Yabancı anahtar tablo adından önce düşürülüyor: kısıtın adı eski
        // tabloya göre üretildiği için yeniden adlandırmadan sonra
        // bulunamıyordu.
        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->dropForeign(['blog_post_id']);
            $table->dropIndex(['blog_post_id', 'sort_order']);
        });

        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->string('attachable_type')->nullable()->after('id');
            $table->unsignedBigInteger('attachable_id')->nullable()->after('attachable_type');
        });

        // Mevcut ekler blog yazısının; bağları olduğu gibi taşınıyor.
        DB::table('blog_post_files')
            ->whereNotNull('blog_post_id')
            ->update([
                'attachable_type' => BlogPost::class,
                'attachable_id'   => DB::raw('blog_post_id'),
            ]);

        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->dropColumn('blog_post_id');
            $table->index(['attachable_type', 'attachable_id', 'sort_order'], 'content_files_attachable_index');
        });

        Schema::rename('blog_post_files', 'content_files');
    }

    public function down(): void
    {
        Schema::rename('content_files', 'blog_post_files');

        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('blog_post_id')->nullable()->after('id');
        });

        DB::table('blog_post_files')
            ->where('attachable_type', BlogPost::class)
            ->update(['blog_post_id' => DB::raw('attachable_id')]);

        // Blog yazısı dışındaki eklerin (sayfa ekleri) tek sütunlu düzende
        // duracak yeri yok; sütunlar kalkarken onlar da gitmeli. Kayıt yalnızca
        // dosyanın adresi olduğu için dosya da gidiyor: satır silinip dosya
        // bırakılsaydı public/uploads altında sahipsiz birikirdi.
        $dusenler = DB::table('blog_post_files')
            ->whereNotNull('attachable_type')
            ->where('attachable_type', '!=', BlogPost::class)
            ->get(['id', 'path']);

        foreach ($dusenler as $satir) {
            $dosya = UploadService::basePath((string) $satir->path);

            if (is_file($dosya)) {
                @unlink($dosya);
            }
        }

        DB::table('blog_post_files')
            ->whereIn('id', $dusenler->pluck('id'))
            ->delete();

        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->dropIndex('content_files_attachable_index');
            $table->dropColumn(['attachable_type', 'attachable_id']);
            $table->index(['blog_post_id', 'sort_order']);
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->restrictOnDelete();
        });
    }
};
