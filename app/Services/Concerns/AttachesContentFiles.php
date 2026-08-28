<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Services\ContentFileService;
use Illuminate\Database\Eloquent\Model;

/**
 * Dil bloklarıyla gelen bekleyen ek belirteçlerini kaydedilen satırlara bağlar.
 *
 * Çevirisi zaten olan bir dilde ek yüklenirken bu yol hiç kullanılmıyor; dosya
 * o an doğrudan satıra bağlanıyor. Buraya yalnızca satırı olmayan dilin ekleri
 * düşüyor — yeni içerik ya da hiç çevrilmemiş sekme.
 *
 * Blog yazısı ile sayfa aynı akışı paylaşıyor; iki serviste ayrı yazılsaydı
 * biri düzelen bir hatayı öteki taşımaya devam ederdi.
 */
trait AttachesContentFiles
{
    /**
     * Dil bloklarındaki bekleyen ek belirteçlerini ayırır.
     *
     * Belirteç içeriğin sütunu değil; blokta bırakılsaydı satırı yazmaya
     * çalışırken bilinmeyen alan hatası verirdi. Ayrıca yalnızca dosya taşıyan
     * bir blok, çeviri yazılmış sayılmamalı: alan çıkınca blok yeniden boş
     * görünüyor ve o dilde boş bir satır doğmuyor.
     *
     * @param  array<string, array<string, mixed>> $translations locale => fields
     * @return array<string, array<int, mixed>>                  locale => tokens
     */
    protected function extractFileTokens(array &$translations): array
    {
        $tokens = [];

        foreach ($translations as $locale => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $blockTokens = $fields['file_tokens'] ?? null;
            $tokens[$locale] = is_array($blockTokens) ? array_values($blockTokens) : [];

            unset($translations[$locale]['file_tokens']);
        }

        return $tokens;
    }

    /**
     * Peşin yüklenmiş ekleri doğdukları dilin satırına bağlar.
     *
     * Blok boş bırakıldıysa o dilde satır doğmuyor: bağlanacak yer olmadığı için
     * dosya diskten de siliniyor, yoksa public/uploads altında sahipsiz
     * birikirdi.
     *
     * @param class-string<Model>               $modelClass
     * @param array<string, array<int, mixed>>  $tokensByLocale
     */
    protected function syncPendingFiles(string $modelClass, string $groupId, array $tokensByLocale): void
    {
        $files = app(ContentFileService::class);
        $userId = auth()->id();

        foreach ($tokensByLocale as $locale => $tokens) {
            if ($tokens === []) {
                continue;
            }

            $row = $modelClass::query()
                ->where('lang_group_id', $groupId)
                ->where('locale', $locale)
                ->first();

            if ($row === null) {
                $files->discardTokens($tokens, $userId);

                continue;
            }

            $files->attachPending($row, $tokens, $userId);
        }
    }
}
