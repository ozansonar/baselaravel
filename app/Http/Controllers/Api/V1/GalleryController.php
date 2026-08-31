<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\GalleryType;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GalleryItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\GalleryCategoryService;
use App\Services\GalleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Galeri — fotoğraf ve videolar.
 *
 * Kategori süzgeci slug ile çalışıyor, kimlikle değil: kategorinin her dilde
 * ayrı bir satırı var ve kimliğe göre süzülseydi o dile çevrilmemiş olduğu için
 * varsayılan dilden düşen öğeler süzgecin dışında kalırdı
 * ({@see GalleryService::paginateActive()}).
 */
final class GalleryController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private readonly GalleryService $gallery,
        private readonly GalleryCategoryService $categories,
    ) {}

    /**
     * GET /api/v1/gallery
     * GET /api/v1/gallery?category=etkinlikler&type=photo
     */
    public function index(Request $request): JsonResponse
    {
        $categorySlug = $request->query('category');

        if (is_string($categorySlug) && $categorySlug !== ''
            && ! $this->categories->allActive()->contains('slug', $categorySlug)) {
            return ApiResponse::error(__('api.gallery.category_not_found'), status: 404);
        }

        $type = $request->query('type');

        if (is_string($type) && $type !== '' && GalleryType::tryFrom($type) === null) {
            return ApiResponse::error(
                __('api.gallery.invalid_type'),
                ['type' => [__('api.gallery.invalid_type')]],
                422,
            );
        }

        return ApiResponse::paginated(
            $this->gallery->paginateActive(
                is_string($categorySlug) ? $categorySlug : null,
                is_string($type) ? $type : null,
                $this->perPage($request),
            ),
            GalleryItemResource::class,
        );
    }
}
