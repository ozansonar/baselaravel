<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Toplu işlemden sonra kullanıcıyı bıraktığı yere döndürür.
 *
 * Listenin başına düşmek uzun listede yeri kaybettiriyor: 4. sayfada
 * "silinmiş" sekmesinde çalışan biri, işlemden sonra süzgeçsiz ilk sayfaya
 * atılıyordu. Süzgeç ve sayfa numarası istekte zaten var, geri dönüşe
 * olduğu gibi taşınıyor.
 */
trait ReturnsToList
{
    /**
     * Geri dönüşte korunan süzgeç anahtarları.
     *
     * @return list<string>
     */
    protected function listQueryKeys(): array
    {
        return ['status', 'type', 'category', 'search', 'per_page', 'page', 'view', 'q', 'locale'];
    }

    protected function backToList(Request $request, string $routeName): RedirectResponse
    {
        $query = array_filter(
            $request->only($this->listQueryKeys()),
            static fn ($value): bool => $value !== null && $value !== '',
        );

        return redirect()->route($routeName, $query);
    }
}
