<?php

declare(strict_types=1);

use App\Support\MailTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mail şablonları dile bağlanıyor.
 *
 * Tablo anahtar başına tek satır tutuyordu ve satırların hepsi Türkçeydi.
 * BaseMail bir şablon bulduğunda Blade karşılığını hiç çizmediği için, ön yüzü
 * İngilizce gezen bir ziyaretçi kaydolduğunda karşılama mailini Türkçe alıyordu
 * — şablonlar çevrilebilir değildi, tek dilliydi.
 *
 * Artık birincil anahtar (key, locale). Var olan satırlar varsayılan dile
 * yazılıyor, öteki etkin diller için MailTemplateDefaults'tan yeni satırlar
 * açılıyor; o dilin karşılığı yoksa satır varsayılan dilin içeriğiyle açılıyor
 * ki panelde çevrilecek bir şey bulunsun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $default = $this->defaultLocale();

        Schema::table('mail_templates', function (Blueprint $table) use ($default): void {
            $table->string('locale', 5)->default($default)->after('key')->index();
        });

        // Anahtar tek başına benzersiz olamaz: aynı şablonun her dilde bir
        // satırı olacak. Eski dizin adı Laravel'in ürettiği ad.
        Schema::table('mail_templates', function (Blueprint $table): void {
            $table->dropUnique('mail_templates_key_unique');
            $table->unique(['key', 'locale']);
        });

        DB::table('mail_templates')->update(['locale' => $default]);

        $this->seedOtherLocales($default);
    }

    public function down(): void
    {
        $default = $this->defaultLocale();

        // Geri dönüşte anahtar yeniden tek başına benzersiz olacak; öteki
        // dillerin satırları kalırsa dizin kurulamaz.
        DB::table('mail_templates')->where('locale', '!=', $default)->delete();

        Schema::table('mail_templates', function (Blueprint $table): void {
            $table->dropUnique(['key', 'locale']);
        });

        Schema::table('mail_templates', function (Blueprint $table): void {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });

        Schema::table('mail_templates', function (Blueprint $table): void {
            $table->unique('key');
        });
    }

    /**
     * Etkin dillerin her biri için eksik şablon satırlarını açar.
     */
    private function seedOtherLocales(string $default): void
    {
        $now = now();

        /** @var array<int, object{key: string, name: string, description: ?string, variables: string, is_active: bool}> $rows */
        $rows = DB::table('mail_templates')
            ->where('locale', $default)
            ->whereNull('deleted_at')
            ->get();

        foreach ($this->targetLocales($default) as $locale) {
            $content = MailTemplateDefaults::forLocale($locale);

            $insert = [];

            foreach ($rows as $row) {
                $insert[] = [
                    'key'         => $row->key,
                    'locale'      => $locale,
                    // Ad, açıklama ve değişken listesi şablonu panelde
                    // etiketliyor; panel tek dilli, o yüzden diller arasında
                    // aynı kalıyor.
                    'name'        => $row->name,
                    'description' => $row->description,
                    'subject'     => $content[$row->key]['subject'] ?? $row->subject,
                    'body'        => $content[$row->key]['body'] ?? $row->body,
                    'variables'   => $row->variables,
                    'is_active'   => $row->is_active,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                    'deleted_at'  => null,
                ];
            }

            if ($insert !== []) {
                DB::table('mail_templates')->insert($insert);
            }
        }
    }

    /**
     * Şablon açılacak diller: varsayılan dışındaki etkin diller.
     *
     * Diller tablosu henüz yoksa (taze bir kurulumda göç sırası) yalnız bu
     * dosyanın içerik taşıdığı diller kullanılıyor.
     *
     * @return list<string>
     */
    private function targetLocales(string $default): array
    {
        $codes = Schema::hasTable('languages')
            ? DB::table('languages')->whereNull('deleted_at')->pluck('code')->all()
            : [];

        if ($codes === []) {
            $codes = MailTemplateDefaults::locales();
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($code): string => (string) $code, $codes),
            static fn (string $code): bool => $code !== '' && $code !== $default,
        )));
    }

    private function defaultLocale(): string
    {
        if (Schema::hasTable('languages')) {
            $code = DB::table('languages')
                ->where('is_default', true)
                ->whereNull('deleted_at')
                ->value('code');

            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        return (string) config('app.locale', 'tr');
    }
};
