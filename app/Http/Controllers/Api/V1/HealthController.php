<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uygulamanın sunucuya ilk sorduğu soru.
 *
 * Üç şeyi birden söylüyor ve üçü de olmadığında mobil uygulamanın elinde
 * hiçbir yol kalmıyordu:
 *
 *   - Bakım var mı? Yoksa uygulama, bakım penceresinde her isteği hata
 *     sanıyor ve kullanıcıya "bir şeyler ters gitti" diyor.
 *   - En eski desteklenen sürüm hangisi? Mağazadaki eski bir sürümü
 *     güncellemeye zorlamanın başka yolu yok; sunucu sözleşmeyi değiştirdiğinde
 *     eski uygulama sessizce bozuluyordu.
 *   - Sunucu ayakta mı? İzleme araçları için tek satırlık cevap.
 *
 * Jeton istemiyor: uygulamanın bunu giriş ekranından önce sorması gerekiyor.
 */
final class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $minimum = (string) Setting::getValue('api_minimum_client_version', '');
        $client = (string) $request->header('X-Client-Version', '');

        // Karşılaştırma yalnız ikisi de doluysa yapılıyor: sürüm bildirmeyen
        // eski bir istemciyi "güncelle" diye geri çevirmek, onu tamamen
        // kullanılamaz hâle getirirdi — oysa yapması gereken tek şey
        // güncellenmek ve bunu ancak uygulamayı açabilirse öğrenir.
        $updateRequired = $minimum !== '' && $client !== '' && version_compare($client, $minimum, '<');

        return ApiResponse::success([
            'status'      => 'ok',
            'api_version' => (string) config('api.version'),
            'maintenance' => Setting::getValue('maintenance_mode') === '1',
            'minimum_client_version' => $minimum !== '' ? $minimum : null,
            'update_required' => $updateRequired,
            'server_time'     => now()->toIso8601String(),
        ]);
    }
}
