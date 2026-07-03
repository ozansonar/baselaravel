<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductTopic;
use Illuminate\Support\Facades\DB;

/**
 * Modül 4 Faz 2 — Cron blog otomasyonu için topic havuz seçici.
 *
 * Her cron çalıştırmasında (BlogGenerationService) henüz blog yazısına
 * çevrilmemiş bir ProductTopic seçer. Manuel "AI ile İçerik Üret" akışı
 * bu servisten etkilenmez (orada kullanıcı kendi topic'ini geçer).
 *
 * Seçim mantığı:
 *   1. blog_post_id IS NULL + is_active=true topic'lerden rastgele biri
 *   2. Aynı satır iki cron worker tarafından eşzamanlı seçilemesin diye
 *      DB::transaction + lockForUpdate kullanılır (defense-in-depth —
 *      cron schedule'ları zaten withoutOverlapping ile korunur, bu paylaşımlı
 *      hosting'lerdeki saat kayması/queue retry edge-case'leri için).
 *   3. Tüm topic'ler kullanılmış / hiç topic yoksa null döner — caller
 *      eski "rastgele ürün adı" davranışına geçer.
 */
final class TopicRotationService
{
    /**
     * Pick a random unused product topic.
     * Returns null when the pool is exhausted (or never seeded).
     */
    public function pickNextTopic(): ?ProductTopic
    {
        return DB::transaction(function (): ?ProductTopic {
            $topic = ProductTopic::query()
                ->active()
                ->unconverted()
                ->whereHas('product', fn ($q) => $q->active())
                ->inRandomOrder()
                ->lockForUpdate()
                ->first();

            return $topic; // null when pool is empty
        });
    }

    /**
     * Mark a topic as converted to a blog post — bu topic bir daha
     * pickNextTopic ile seçilmez. BlogGenerationService cron başarıyla
     * blog post oluşturduktan sonra çağırır.
     */
    public function markConverted(ProductTopic $topic, int $blogPostId): void
    {
        $topic->update([
            'blog_post_id' => $blogPostId,
            'converted_at' => now(),
        ]);
    }

    /**
     * Havuzdaki kullanılmamış topic sayısı — admin panelinde "havuz tükeniyor"
     * uyarısı için.
     */
    public function poolSize(): int
    {
        return ProductTopic::query()
            ->active()
            ->unconverted()
            ->whereHas('product', fn ($q) => $q->active())
            ->count();
    }
}
