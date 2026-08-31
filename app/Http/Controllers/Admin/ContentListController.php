<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\ContentListService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Genel içerik listesi.
 *
 * Blog, sayfa, galeri ve SSS'nin tek ekranda görünen hâli. Düzenleme buradan
 * yapılmıyor: her kayıt kendi ekranına bağlanıyor. Aksi hâlde dört ayrı formun
 * beşinci bir kopyası doğar ve dördüyle birden ayrışırdı.
 */
final class ContentListController extends Controller
{
    /** Sayfa başına gösterilebilecek değerler; adres satırından gelen sayı bu kümeyle sınırlı. */
    private const PER_PAGE = [10, 25, 50, 100];

    public function __construct(
        private readonly ContentListService $contents,
    ) {}

    public function index(Request $request): View
    {
        // Yetki her tür için ayrı ayrı sorulmuyor: liste yalnız başlık ve
        // tarih gösteriyor, kaydın kendisine gitmek isteyen zaten o ekranın
        // kapısından geçiyor. Ama listeyi görmek için içerik okuma yetkisi
        // gerekiyor.
        $this->authorize('viewAny', \App\Models\BlogPost::class);

        $filters = array_filter(
            $request->only($this->contents->filterKeys()),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $perPage = (int) $request->query('per_page', '25');
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 25;

        return view('admin.content-list.index', [
            'items'     => $this->contents->paginate($perPage, $filters),
            'counts'    => $this->contents->counts($filters),
            'filters'   => $filters,
            'perPage'   => $perPage,
            'perPages'  => self::PER_PAGE,
            'types'     => ContentListService::TYPES,
            'routes'    => ContentListService::ROUTES,
            'languages' => Language::active()->sorted()->get(),
        ]);
    }
}
