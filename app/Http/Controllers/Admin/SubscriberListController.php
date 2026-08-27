<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\SubscriberList;
use App\Services\SubscriberListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Abone listelerinin yönetimi — kendi ekranı yok, mail listesi sayfasından
 * açılan pencerelerle yönetiliyor.
 *
 * Yetki abonelerin yetkisiyle aynı: listeyi yönetebilen kişi zaten aboneleri
 * de yönetiyor, ayrı bir izin çifti açmak izin listesini gereksiz kalabalık
 * yapardı.
 */
final class SubscriberListController extends Controller
{
    public function __construct(
        private readonly SubscriberListService $lists,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageLists', Subscriber::class);

        $this->lists->create($this->validated($request));

        return back()->with('success', 'Liste oluşturuldu.');
    }

    public function update(Request $request, SubscriberList $subscriberList): RedirectResponse
    {
        $this->authorize('manageLists', Subscriber::class);

        $this->lists->update($subscriberList, $this->validated($request));

        return back()->with('success', 'Liste güncellendi.');
    }

    public function destroy(SubscriberList $subscriberList): RedirectResponse
    {
        $this->authorize('manageLists', Subscriber::class);

        try {
            $this->lists->delete($subscriberList);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Liste silindi, aboneler listede değil ama kayıtlı kaldı.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default'  => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.required' => 'Listeye bir ad verin.',
        ]);
    }
}
