<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReturnsToList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkFaqRequest;
use App\Http\Requests\Admin\StoreTranslatedFaqRequest;
use App\Http\Requests\StoreFaqRequest;
use App\Http\Requests\UpdateFaqRequest;
use App\Models\Faq;
use App\Services\FaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FaqController extends Controller
{
    use ReturnsToList;

    public function __construct(
        private readonly FaqService $faqService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Faq::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only($this->faqService->filterKeys());

        return view('admin.faqs.index', [
            'faqs'         => $this->faqService->paginate($perPage, $filters),
            'stats'        => $this->faqService->getAdminStats(),
            'statusCounts' => $this->faqService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Faq::class);

        return view('admin.faqs.create', ['formLanguages' => $this->faqService->formLanguages()]);
    }

    public function store(StoreTranslatedFaqRequest $request): RedirectResponse
    {
        $this->authorize('create', Faq::class);

        $this->faqService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'SSS başarıyla oluşturuldu.');
    }

    public function edit(Faq $faq): View
    {
        $this->authorize('update', $faq);

        return view('admin.faqs.edit', [
            'faq'           => $faq,
            'formLanguages' => $this->faqService->formLanguages(),
        ]);
    }

    public function update(StoreTranslatedFaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->authorize('update', $faq);

        $this->faqService->updateTranslated($faq, $request->validated('translations'));

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'SSS başarıyla güncellendi.');
    }

    public function destroy(Request $request, Faq $faq): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $faq);

        $this->faqService->delete($faq);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'SSS silindi.']);
        }

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'SSS başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $faq = Faq::withTrashed()->findOrFail($id);
        $this->authorize('restore', $faq);

        $this->faqService->restore($id);

        return redirect()
            ->route('admin.faqs.index')
            ->with('success', 'SSS başarıyla geri yüklendi.');
    }

    /**
     * Listede seçilen soruleri tek seferde siler.
     *
     * Liste ekranındaki seçim kutuları buraya bağlı; önceden yalnız arayüz
     * vardı, "Sil" kutuları temizliyor ama sunucuya istek gitmiyordu.
     */
    public function bulkDestroy(BulkFaqRequest $request): RedirectResponse
    {
        $this->authorize('delete', new Faq());

        $silinen = $this->faqService->deleteMany($request->ids());

        return $this->backToList($request, 'admin.faqs.index')->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} kayıt silindi." : 'Hiçbir kayıt silinemedi.',
        );
    }

    /**
     * Çöpteki soruleri tek seferde geri yükler.
     *
     * Silinmişler sekmesinde toplu silmenin karşılığı bu.
     */
    public function bulkRestore(BulkFaqRequest $request): RedirectResponse
    {
        $this->authorize('restore', new Faq());

        $geriYuklenen = $this->faqService->restoreMany($request->ids());

        return $this->backToList($request, 'admin.faqs.index')->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $geriYuklenen > 0 ? "{$geriYuklenen} kayıt geri yüklendi." : 'Hiçbir kayıt geri yüklenemedi.',
        );
    }
}
