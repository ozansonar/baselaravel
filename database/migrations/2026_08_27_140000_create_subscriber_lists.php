<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abone listeleri: tedarikçiler, pazarlamacılar, bülten…
 *
 * Aboneler tek düz bir listede duruyordu; kime yazdığını ayırmanın tek yolu
 * her seferinde Excel hazırlamaktı. Segment tutabilecek tek alan "source"
 * sütunuydu ama o "nereden geldi" bilgisi — panelden eklenen tedarikçiyle
 * panelden eklenen bülten abonesi aynı değeri taşıyor.
 *
 * Üyelik çoklu: bir tedarikçi aynı zamanda bültene de kaydolabilir. Tek bir
 * list_id sütunu olsaydı ya ikinci bir kayıt açmak (e-posta tekilliğiyle
 * çakışır) ya da kişiyi bir listeden çıkarmak gerekirdi.
 *
 * Abonelikten çıkma listeye değil kişiye bağlı kalıyor: durumu "ayrıldı" olan
 * bir adrese hangi listede olursa olsun mail gitmiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            // Site formundan gelen aboneler bu listeye düşer; tek bir liste
            // işaretli olabilir.
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_default');
            $table->index('sort_order');
        });

        Schema::create('subscriber_list_subscriber', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_list_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Aynı kişi bir listeye iki kez giremez.
            $table->unique(['subscriber_id', 'subscriber_list_id'], 'subscriber_list_unique');
            // Listenin üyelerini saymanın ve kampanyanın hedefini çözmenin
            // sıcak sorgusu.
            $table->index('subscriber_list_id');
        });

        $this->seedDefaultList();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_list_subscriber');
        Schema::dropIfExists('subscriber_lists');
    }

    /**
     * Var olan aboneler bir listeye girmeli, yoksa taşımadan sonra hiçbir
     * kampanyanın hedefinde görünmezler.
     */
    private function seedDefaultList(): void
    {
        $now = now();

        $listId = DB::table('subscriber_lists')->insertGetId([
            'name'        => 'Bülten',
            'slug'        => 'bulten',
            'description' => 'Site üzerinden bültene kaydolan kişiler.',
            'is_default'  => true,
            'sort_order'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        DB::table('subscribers')
            ->select('id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($listId, $now): void {
                DB::table('subscriber_list_subscriber')->insert(
                    collect($rows)->map(static fn ($row): array => [
                        'subscriber_id'      => $row->id,
                        'subscriber_list_id' => $listId,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ])->all(),
                );
            });
    }
};
