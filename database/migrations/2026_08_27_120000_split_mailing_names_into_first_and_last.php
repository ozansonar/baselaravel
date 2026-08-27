<?php

declare(strict_types=1);

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abone ve alıcı isimleri ada + soyada ayrılıyor.
 *
 * Tek bir "name" sütunu yalnızca "Ad Soyad" yazmaya yarıyordu: mailde "Sayın
 * Yılmaz" demek ya da listeyi soyada göre sıralamak mümkün değildi, dışarıdan
 * gelen Excel dosyalarında da ad ve soyad zaten ayrı sütunlarda geliyor.
 *
 * Var olan kayıtlar kaybolmasın diye taşıma sırasında son kelime soyad
 * sayılarak bölünüyor; geri alındığında ikisi yeniden birleştiriliyor.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const TABLES = ['subscribers', 'campaign_recipients'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('first_name')->nullable()->after('email');
                $blueprint->string('last_name')->nullable()->after('first_name');
            });

            $this->splitExistingNames($table);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('name');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('name')->nullable()->after('email');
            });

            DB::table($table)
                ->select('id', 'first_name', 'last_name')
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['name' => PersonName::full($row->first_name, $row->last_name)]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['first_name', 'last_name']);
            });
        }
    }

    /**
     * Tek parça ismi iki sütuna dağıtır. Soft delete edilmiş satırlar da
     * taşınıyor: geri yüklenen bir abone ismini kaybetmemeli.
     */
    private function splitExistingNames(string $table): void
    {
        DB::table($table)
            ->select('id', 'name')
            ->whereNotNull('name')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $parts = PersonName::split($row->name);

                    DB::table($table)->where('id', $row->id)->update([
                        'first_name' => $parts['first_name'],
                        'last_name'  => $parts['last_name'],
                    ]);
                }
            });
    }
};
