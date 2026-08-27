<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bir e-posta adresi tek bir abone kaydına karşılık gelir.
 *
 * Tekillik yalnızca uygulama katmanında, "önce ara sonra yaz" biçiminde
 * uygulanıyordu. İki istek aynı anda gelirse (bülten formu ile içe aktarma
 * çakışırsa, ya da form iki kez gönderilirse) ikisi de aramada boş bulup satırı
 * açabiliyordu; sonuç, aynı adresin aynı listede iki kez görünmesiydi.
 *
 * Artık kısıt veritabanında. Öncesinde var olan kopyalar tek kayda birleşiyor:
 * listelerin birleşimi korunuyor ve ayrılmış bir kopya varsa kalan kaydın
 * durumu da "ayrıldı" oluyor — çıkmış birini birleştirme yüzünden geri
 * abone yapmak, düzeltilen hatadan daha kötü olurdu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeAddresses();
        $this->mergeDuplicates();

        Schema::table('subscribers', function (Blueprint $table): void {
            // Tekil indeks aramayı da karşılıyor, düz indeks gereksiz kalıyor.
            // Varlığı sınanıyor: elle kaldırılmış bir kurulumda taşıma
            // "böyle bir indeks yok" diye durmasın.
            if (Schema::hasIndex('subscribers', 'subscribers_email_index')) {
                $table->dropIndex(['email']);
            }

            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->dropUnique(['email']);

            if (! Schema::hasIndex('subscribers', 'subscribers_email_index')) {
                $table->index('email');
            }
        });
    }

    /**
     * Adresler her zaman küçük harfe çevrilerek yazılıyor ama kısıt konmadan
     * önce yazılmış bir kayıt farklı olabilir; "Ali@x.com" ile "ali@x.com"
     * SQLite'ta iki ayrı değer.
     */
    private function normalizeAddresses(): void
    {
        DB::table('subscribers')
            ->select('id', 'email')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $normalized = mb_strtolower(trim((string) $row->email));

                    if ($normalized !== $row->email) {
                        DB::table('subscribers')->where('id', $row->id)->update(['email' => $normalized]);
                    }
                }
            });
    }

    private function mergeDuplicates(): void
    {
        $duplicates = DB::table('subscribers')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('count(*) > 1')
            ->pluck('email');

        foreach ($duplicates as $email) {
            $rows = DB::table('subscribers')->where('email', $email)->orderBy('id')->get();

            // Silinmemiş bir kayıt varsa o kalır: silinmiş bir satırı ayakta
            // tutup canlısını atmak aboneyi listeden düşürürdü.
            $survivor = $rows->firstWhere('deleted_at', null) ?? $rows->first();
            $others = $rows->reject(static fn ($row): bool => $row->id === $survivor->id);

            DB::table('subscribers')->where('id', $survivor->id)->update([
                'status'          => $this->strictestStatus($rows->pluck('status')->all()),
                'first_name'      => $this->firstFilled($rows->pluck('first_name')->all()) ?? $survivor->first_name,
                'last_name'       => $this->firstFilled($rows->pluck('last_name')->all()) ?? $survivor->last_name,
                'locale'          => $this->firstFilled($rows->pluck('locale')->all()) ?? $survivor->locale,
                'subscribed_at'   => $rows->pluck('subscribed_at')->filter()->min() ?? $survivor->subscribed_at,
                'unsubscribed_at' => $rows->pluck('unsubscribed_at')->filter()->max() ?? $survivor->unsubscribed_at,
                'updated_at'      => now(),
            ]);

            $this->mergeListMemberships($survivor->id, $others->pluck('id')->all());

            DB::table('subscribers')->whereIn('id', $others->pluck('id'))->delete();
        }
    }

    /**
     * Listelerin birleşimi kalan kayda geçiyor; kopyalardan gelen üyelikler
     * kaybolmamalı.
     *
     * @param array<int, int> $removedIds
     */
    private function mergeListMemberships(int $survivorId, array $removedIds): void
    {
        if ($removedIds === []) {
            return;
        }

        $alreadyIn = DB::table('subscriber_list_subscriber')
            ->where('subscriber_id', $survivorId)
            ->pluck('subscriber_list_id')
            ->all();

        $incoming = DB::table('subscriber_list_subscriber')
            ->whereIn('subscriber_id', $removedIds)
            ->pluck('subscriber_list_id')
            ->unique()
            ->diff($alreadyIn);

        $now = now();

        foreach ($incoming as $listId) {
            DB::table('subscriber_list_subscriber')->insert([
                'subscriber_id'      => $survivorId,
                'subscriber_list_id' => $listId,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }

        DB::table('subscriber_list_subscriber')->whereIn('subscriber_id', $removedIds)->delete();
    }

    /**
     * Gönderime en kapalı durum kazanır: çıkmış ya da ulaşılamayan bir adresi
     * birleştirme yüzünden yeniden abone yapmak kabul edilemez.
     *
     * @param array<int, ?string> $statuses
     */
    private function strictestStatus(array $statuses): string
    {
        if (in_array('bounced', $statuses, true)) {
            return 'bounced';
        }

        return in_array('unsubscribed', $statuses, true) ? 'unsubscribed' : 'subscribed';
    }

    /**
     * @param array<int, ?string> $values
     */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
};
