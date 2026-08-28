<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\ContentFile;
use App\Services\ContentFileService;

/**
 * Ek bölümünün ekrana ihtiyaç duyduğu iki şey: boyut tavanı ve doğrulama
 * hatasından sonra geri dönen bekleyen yüklemeler.
 *
 * Blog yazısı ile sayfa formu aynısını istiyor; iki controller'da ayrı
 * yazılsaydı biri düzelen bir davranışı öteki taşımaya devam ederdi.
 */
trait ProvidesContentFileForm
{
    /**
     * Ek bölümünün değişkenleri.
     *
     * @return array{fileLimits: array{per_file: int, post_max: int, max_files: int}, pendingFiles: array<string, \Illuminate\Support\Collection<int, ContentFile>>}
     */
    protected function contentFileFormData(): array
    {
        return [
            // Ekranda yazan tavan sunucununkiyle aynı olmalı; iki yerde ayrı
            // yazılsaydı gösterilen sayı ile kabul edilen boyut ayrışırdı.
            'fileLimits'   => app(ContentFileService::class)->limits(),
            'pendingFiles' => $this->pendingFilesFromOldInput(),
        ];
    }

    /**
     * Doğrulama hatasından sonra forma geri dönen bekleyen ekler.
     *
     * Yüklenen dosyanın satırını JS çiziyor; sayfa yeniden yüklenince o satır
     * kayboluyor. Başlığı boş bırakıp kaydeden kullanıcı, hatayı düzeltip
     * tekrar kaydettiğinde az önce yüklediği beş dosyayı listede bulamıyor ve
     * dosyalar bir gün sonra temizliğe takılana kadar sahipsiz bekliyordu.
     *
     * Bekleyenler sahibiyle aranıyor: belirteç uydurulsa bile başkasının
     * dosyası forma geri gelmiyor.
     *
     * @return array<string, \Illuminate\Support\Collection<int, ContentFile>> locale => files
     */
    protected function pendingFilesFromOldInput(): array
    {
        $userId = auth()->id();
        $restored = [];

        foreach ((array) old('translations', []) as $locale => $fields) {
            $tokens = is_array($fields) ? ($fields['file_tokens'] ?? null) : null;

            if (! is_array($tokens) || $tokens === []) {
                continue;
            }

            $restored[$locale] = ContentFile::query()
                ->pending($userId)
                ->whereIn('token', $tokens)
                ->sorted()
                ->get();
        }

        return $restored;
    }
}
