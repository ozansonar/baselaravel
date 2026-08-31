<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BlogPostResource;
use App\Http\Resources\Api\V1\GalleryItemResource;
use App\Http\Resources\Api\V1\SliderResource;
use App\Http\Responses\ApiResponse;
use App\Models\GalleryItem;
use App\Services\BlogService;
use App\Services\GalleryService;
use App\Services\SliderService;
use Illuminate\Http\JsonResponse;

/**
 * Açılış ekranının ihtiyacı olan her şey tek istekte.
 *
 * Parçalar ayrı ayrı da yayında (/sliders, /blog/posts, /gallery) ama uygulama
 * açılışında üçünü birden sormak üç gidiş dönüş demek — mobil bağlantıda bu,
 * ekranın gecikmesinin büyük kısmı. Ön yüzdeki HomeController da aynı üçlüyü
 * aynı servislerden alıyor, yani iki taraf aynı şeyi gösteriyor.
 */
final class HomeController extends Controller
{
    public function __construct(
        private readonly SliderService $sliders,
        private readonly BlogService $posts,
        private readonly GalleryService $gallery,
    ) {}

    /**
     * GET /api/v1/home
     */
    public function __invoke(): JsonResponse
    {
        $postLimit = (int) config('api.home.posts', 4);
        $photoLimit = (int) config('api.home.gallery_photos', 8);

        return ApiResponse::success([
            'sliders' => SliderResource::collection($this->sliders->allActive()),
            'posts'   => BlogPostResource::collection($this->posts->latestPublished($postLimit)),
            // Görseli olmayan öğe şeritte boşluk bırakır; ön yüzde de eleniyor.
            'gallery' => GalleryItemResource::collection(
                $this->gallery->activePhotos()
                    ->filter(fn (GalleryItem $photo): bool => (bool) $photo->image)
                    ->take($photoLimit)
                    ->values(),
            ),
        ]);
    }
}
