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
 *
 * Her adım "zaten yapılmış mı" diye bakıyor. Sebep: yarıda kalan bir çalıştırma
 * migrations tablosuna yazılmıyor, dolayısıyla ikinci deneme baştan başlıyor ve
 * ilk denemede düşürülmüş bir yabancı anahtarı yeniden düşürmeye kalkıp
 * "Can't DROP FOREIGN KEY ... check that it exists" ile ölüyordu. Kısıt adları
 * da tahmin edilmiyor, şemadan okunuyor: elle kurulmuş ya da farklı adlandırılmış
 * bir kısıt tahminle bulunamaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tablo zaten dönüştürülmüş: yarıda kalan bir çalıştırmanın ardından
        // gelen ikinci deneme buradan sessizce çıkıyor.
        if (Schema::hasTable('content_files')) {
            return;
        }

        // Yabancı anahtar tablo adından önce düşürülüyor: kısıtın adı eski
        // tabloya göre üretildiği için yeniden adlandırmadan sonra
        // bulunamıyordu.
        $this->dropForeignKeyOn('blog_post_files', 'blog_post_id');
        $this->dropIndexOn('blog_post_files', ['blog_post_id', 'sort_order']);

        Schema::table('blog_post_files', function (Blueprint $table): void {
            if (! Schema::hasColumn('blog_post_files', 'attachable_type')) {
                $table->string('attachable_type')->nullable()->after('id');
            }

            if (! Schema::hasColumn('blog_post_files', 'attachable_id')) {
                $table->unsignedBigInteger('attachable_id')->nullable()->after('attachable_type');
            }
        });

        // Mevcut ekler blog yazısının; bağları olduğu gibi taşınıyor.
        if (Schema::hasColumn('blog_post_files', 'blog_post_id')) {
            DB::table('blog_post_files')
                ->whereNotNull('blog_post_id')
                ->update([
                    'attachable_type' => BlogPost::class,
                    'attachable_id'   => DB::raw('blog_post_id'),
                ]);

            Schema::table('blog_post_files', function (Blueprint $table): void {
                $table->dropColumn('blog_post_id');
            });
        }

        if (! $this->hasIndexNamed('blog_post_files', 'content_files_attachable_index')) {
            Schema::table('blog_post_files', function (Blueprint $table): void {
                $table->index(['attachable_type', 'attachable_id', 'sort_order'], 'content_files_attachable_index');
            });
        }

        Schema::rename('blog_post_files', 'content_files');
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_files')) {
            return;
        }

        Schema::rename('content_files', 'blog_post_files');

        if (! Schema::hasColumn('blog_post_files', 'blog_post_id')) {
            Schema::table('blog_post_files', function (Blueprint $table): void {
                $table->unsignedBigInteger('blog_post_id')->nullable()->after('id');
            });
        }

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

        $this->dropIndexNamed('blog_post_files', 'content_files_attachable_index');

        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->dropColumn(['attachable_type', 'attachable_id']);
        });

        if (! $this->hasIndexOn('blog_post_files', ['blog_post_id', 'sort_order'])) {
            Schema::table('blog_post_files', function (Blueprint $table): void {
                $table->index(['blog_post_id', 'sort_order']);
            });
        }

        Schema::table('blog_post_files', function (Blueprint $table): void {
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->restrictOnDelete();
        });
    }

    // ──────────────────────────────────────────────
    //  Şemadan okuyan yardımcılar
    //
    //  Adı tahmin etmek yerine şemaya bakılıyor. SQLite yabancı anahtarları
    //  adlandırmıyor, elle kurulmuş bir kısıt Laravel'in kalıbına uymuyor ve
    //  hiç kurulmamış bir kısıt zaten düşürülemiyor.
    // ──────────────────────────────────────────────

    private function dropForeignKeyOn(string $table, string $column): void
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array($column, $foreignKey['columns'], true)) {
                continue;
            }

            $name = $foreignKey['name'];
            $named = is_string($name) && $name !== '';

            // SQLite kısıtları adsız döner; orada sütun biçimi kullanılıyor —
            // Laravel bunu tabloyu yeniden kurarak hallediyor. MySQL'de ise ad
            // şart ve tahmin edilmiyor, şemadan okunuyor.
            Schema::table($table, fn (Blueprint $blueprint) => $named
                ? $blueprint->dropForeign($name)
                : $blueprint->dropForeign([$column]));
        }
    }

    /**
     * @param list<string> $columns
     */
    private function dropIndexOn(string $table, array $columns): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns && ! $index['primary']) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index['name']));
            }
        }
    }

    private function dropIndexNamed(string $table, string $name): void
    {
        if ($this->hasIndexNamed($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
        }
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        return in_array($name, array_column(Schema::getIndexes($table), 'name'), true);
    }

    /**
     * @param list<string> $columns
     */
    private function hasIndexOn(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }
};
