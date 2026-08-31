<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SearchType;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\Api\V1\SearchResultResource;
use App\Http\Responses\ApiResponse;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;

/**
 * Site geneli arama.
 *
 * Ön yüzdeki arama sayfasıyla aynı servisi çağırıyor, yani aynı terim iki
 * tarafta aynı sonucu ve aynı sırayı veriyor.
 */
final class SearchController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private readonly SearchService $search,
    ) {}

    /**
     * GET /api/v1/search?q=laravel&type=blog
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $term = (string) $this->search->normalize($request->string('q')->value());

        $type = SearchType::tryFrom((string) $request->query('type'));

        // Yapılandırmada kapalı bir tür yok sayılıyor: uydurma bir değer boş
        // liste değil "tümü" görünümü veriyor — ön yüzdeki kuralın aynısı.
        if ($type !== null && ! in_array($type, SearchType::enabled(), true)) {
            $type = null;
        }

        $results = $this->search->search($term, $type, $this->perPage($request));

        // Sunum katmanı ortak: adres kurma ve özet kırpma iki tarafta ayrı
        // yazılsaydı web'de görünen metin ile mobilde görünen ayrışırdı.
        $results->setCollection(
            $results->getCollection()->map(fn (object $row): array => $this->search->present($row)),
        );

        return ApiResponse::paginated($results, SearchResultResource::class, extra: [
            'counts' => $this->search->countsByType($term),
        ]);
    }
}
