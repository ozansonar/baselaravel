<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YouTubeVideo;
use App\Services\YouTubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class YouTubeVideoController extends Controller
{
    public function __construct(
        private readonly YouTubeService $service,
    ) {}

    public function index(): View
    {
        return view('admin.youtube-videos.index', [
            'videos' => $this->service->allForAdmin(),
            'stats'  => $this->service->getStats(),
        ]);
    }

    public function toggleVisibility(YouTubeVideo $youtubeVideo): RedirectResponse
    {
        $this->service->toggleVisibility($youtubeVideo);
        $label = $youtubeVideo->is_visible ? 'görünür' : 'gizli';

        return redirect()->route('admin.youtube-videos.index')
            ->with('success', "Video {$label} yapıldı.");
    }

    public function sync(): RedirectResponse
    {
        $count = $this->service->fetchAndSync();

        return redirect()->route('admin.youtube-videos.index')
            ->with('success', "{$count} video YouTube'dan senkronize edildi.");
    }

    public function destroy(YouTubeVideo $youtubeVideo): RedirectResponse
    {
        $youtubeVideo->delete();

        return redirect()->route('admin.youtube-videos.index')
            ->with('success', 'Video silindi.');
    }

    public function restore(YouTubeVideo $youtubeVideo): RedirectResponse
    {
        $youtubeVideo->restore();

        return redirect()->route('admin.youtube-videos.index')
            ->with('success', 'Video geri yüklendi.');
    }
}
