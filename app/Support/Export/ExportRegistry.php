<?php

declare(strict_types=1);

namespace App\Support\Export;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adres satırındaki anahtarı dışa aktarma tanımına çevirir.
 *
 * Harita config/export.php'de tutulur; yeni bir liste eklemek için oraya tek
 * satır yazmak yeterli, ayrı bir rota ya da denetleyici gerekmez.
 */
final class ExportRegistry
{
    public function get(string $key): ListExport
    {
        /** @var array<string, class-string<ListExport>> $lists */
        $lists = config('export.lists', []);

        $class = $lists[$key] ?? null;

        if ($class === null || !class_exists($class) || !is_subclass_of($class, ListExport::class)) {
            throw new NotFoundHttpException("Tanımsız dışa aktarma anahtarı: {$key}");
        }

        return app($class);
    }

    /** @return list<string> */
    public function keys(): array
    {
        /** @var array<string, class-string<ListExport>> $lists */
        $lists = config('export.lists', []);

        return array_keys($lists);
    }
}
