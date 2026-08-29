<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Slug → çeviri grubu araması, istek başına bir kez.
 *
 * Aynı arama iki ayrı yerde yazılıydı: adresleri çeviren LocalizedUrlService
 * ve menü bağlantılarını çeviren ResolvesLocalizedSlugs. İkisi de kendi
 * içinde saklıyordu ama birbirinden habersizdi, dolayısıyla her sayfada aynı
 * "hangi gruptan" sorgusu iki kez gidiyordu — alt bilgideki "Hakkımızda"
 * bağlantısı yüzünden blog, galeri, iletişim dahil her ekranda.
 *
 * Tek yerde toplanınca hem sorgu tekilleşiyor hem de kural (aynı slug iki
 * dilde varsa hangisinin kazanacağı) tek yerde duruyor.
 *
 * Singleton olarak bağlı; olmasaydı her çözücü yine kendi kopyasını tutardı.
 */
final class TranslationGroupResolver
{
    /**
     * "model|dil|slug" → grup kimliği.
     *
     * @var array<string, string|null>
     */
    private array $memo = [];

    /**
     * Slug'ın çeviri grubu.
     *
     * Dil ipucu sıralamada kullanılıyor: aynı slug iki dilde birden varsa
     * (çeviri sırasında olabiliyor) aranan dilin satırı öne alınıyor. İpucu
     * verilmezse isteğin kendi dili geçerli.
     *
     * @param class-string<Model> $modelClass
     */
    public function resolve(string $modelClass, string $slug, ?string $localeHint = null): ?string
    {
        $localeHint ??= app()->getLocale();
        $key = $modelClass . '|' . $localeHint . '|' . $slug;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $group = $modelClass::query()
            ->where('slug', $slug)
            ->orderByRaw('case when locale = ? then 0 else 1 end', [$localeHint])
            ->value('lang_group_id');

        return $this->memo[$key] = is_string($group) ? $group : null;
    }

    /** Testlerde ve aynı istekte içerik değiştiğinde saklananı düşürür. */
    public function flush(): void
    {
        $this->memo = [];
    }
}
