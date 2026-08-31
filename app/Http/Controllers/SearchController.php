<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Site geneli arama sayfası.
 *
 * Süzgeç adları ön yüzün geri kalanıyla aynı düzende: galeri `?kategori` ve
 * `?tur` kullanıyor, blog `?arama`. Burada da `?arama` ve `?tur` — tek bir
 * sayfa için İngilizce `?q` kullanmak adres düzenini ikiye bölerdi.
 */
final class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
    ) {}

    public function __invoke(Request $request): View
    {
        $term = $this->search->normalize($request->query('arama'));
        $type = $this->requestedType($request);

        // Çok kısa terim aranmış sayılmıyor: tek harf pratikte bütün siteyi
        // döndürür ve ziyaretçiye hiçbir şey anlatmaz.
        $searchable = $this->search->isSearchable($term);

        return view('search', [
            'term'       => $term,
            'type'       => $type,
            'searchable' => $searchable,
            'tooShort'   => $term !== null && ! $searchable,
            'results'    => $searchable ? $this->search->search((string) $term, $type) : null,
            'counts'     => $searchable ? $this->search->countsByType((string) $term) : [],
            'types'      => SearchType::enabled(),
            'presenter'  => $this->search,
        ]);
    }

    /**
     * Adres çubuğundaki tür süzgeci.
     *
     * Tanınmayan ya da yapılandırmada kapalı bir tür yok sayılıyor: uydurma
     * bir değer boş bir liste değil "tümü" görünümünü veriyor ve süzgeç
     * çubuğu hiçbir zaman ekranda olmayan bir seçimi işaretlemiyor — galeri
     * sayfasındaki kuralın aynısı.
     */
    private function requestedType(Request $request): ?SearchType
    {
        $type = SearchType::tryFrom((string) $request->query('tur'));

        return $type !== null && in_array($type, SearchType::enabled(), true) ? $type : null;
    }
}
