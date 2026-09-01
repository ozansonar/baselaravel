<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\Seo\SeoContentAuditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Toplu SEO denetimi.
 *
 * Form paneli tek bir içeriğe bakıyor; bu ekran "sitede genel olarak ne
 * durumdayız" sorusunu yanıtlıyor ve en kötüyü başa alıyor — listenin işi
 * hangi içeriğe önce bakılacağını söylemek.
 */
final class SeoController extends Controller
{
    /** Sayfa başına gösterilebilecek kayıt sayıları. */
    private const PER_PAGE = [25, 50, 100];

    public function __construct(
        private readonly SeoContentAuditor $auditor,
    ) {}

    public function index(Request $request): View
    {
        // Denetim içerikleri okuyor ve içerik hakkında konuşuyor; kapısı da
        // içerik yetkisi. Ayrı bir izin açmak, var olan matrisi karmaşıklaştırır
        // ve yeni bir şey korumazdı.
        Gate::authorize('viewAny', \App\Models\Page::class);

        $filters = array_filter(
            $request->only($this->auditor->filterKeys()),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 25;

        return view('admin.seo.index', [
            'rows'           => $this->auditor->paginate($perPage, $filters, (int) $request->integer('page', 1)),
            'summary'        => $this->auditor->summary($filters),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE,
            'languages'      => Language::active()->sorted()->get(),
        ]);
    }
}
