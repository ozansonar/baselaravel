<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Düzenleme, ekleme ile aynı kuralları kullanıyor; tek fark kendi kaydını
 * benzersizlik denetiminin dışında tutması — o zaten üst sınıfta.
 */
final class UpdateCustomRouteRequest extends StoreCustomRouteRequest
{
}
