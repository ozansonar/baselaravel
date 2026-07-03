<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoogleReviewRequest;
use App\Models\GoogleReview;
use App\Services\GoogleReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class GoogleReviewController extends Controller
{
    public function __construct(
        private readonly GoogleReviewService $service,
    ) {}

    public function index(): View
    {
        return view('admin.google-reviews.index', [
            'reviews' => $this->service->allForAdmin(),
            'stats'   => $this->service->getStats(),
        ]);
    }

    public function store(StoreGoogleReviewRequest $request): RedirectResponse
    {
        GoogleReview::create([
            'author_name'    => $request->validated('author_name'),
            'text'           => $request->validated('text'),
            'rating'         => $request->validated('rating'),
            'review_time'    => $request->validated('review_time'),
            'language'       => 'tr',
            'is_visible'     => true,
            'google_review_id' => 'manual_' . uniqid(),
        ]);

        return redirect()->route('admin.google-reviews.index')
            ->with('success', 'Yorum başarıyla eklendi.');
    }

    public function toggleVisibility(GoogleReview $googleReview): RedirectResponse
    {
        $this->service->toggleVisibility($googleReview);
        $label = $googleReview->is_visible ? 'görünür' : 'gizli';

        return redirect()->route('admin.google-reviews.index')
            ->with('success', "Yorum {$label} yapıldı.");
    }

    public function sync(): RedirectResponse
    {
        $count = $this->service->fetchAndSync();

        return redirect()->route('admin.google-reviews.index')
            ->with('success', "{$count} yorum Google'dan senkronize edildi.");
    }

    public function destroy(GoogleReview $googleReview): RedirectResponse
    {
        $googleReview->delete();

        return redirect()->route('admin.google-reviews.index')
            ->with('success', 'Yorum silindi.');
    }

    public function restore(GoogleReview $googleReview): RedirectResponse
    {
        $googleReview->restore();

        return redirect()->route('admin.google-reviews.index')
            ->with('success', 'Yorum geri yüklendi.');
    }
}
