<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $perPage = in_array($request->query('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->query('per_page')
            : 25;

        $filters = $request->only(['status', 'type', 'search']);

        return view('admin.campaigns.index', [
            'campaigns'    => $this->campaignService->paginate($perPage, $filters),
            'stats'        => $this->campaignService->getAdminStats(),
            'statusCounts' => $this->campaignService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('admin.campaigns.create');
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        $this->campaignService->create($request->validated());

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Kampanya başarıyla oluşturuldu.');
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('admin.campaigns.edit', [
            'campaign' => $campaign,
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $this->campaignService->update($campaign, $request->validated());

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Kampanya başarıyla güncellendi.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $this->campaignService->delete($campaign);

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Kampanya başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $campaign = Campaign::withTrashed()->findOrFail($id);
        $this->authorize('restore', $campaign);

        $this->campaignService->restore($id);

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Kampanya başarıyla geri yüklendi.');
    }
}
