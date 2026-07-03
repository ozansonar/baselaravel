<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HashtagPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HashtagPoolController extends Controller
{
    public function index(Request $request): View
    {
        $category = (string) $request->query('category', '');
        $search   = trim((string) $request->query('search', ''));

        $query = HashtagPool::query();

        if ($category !== '') {
            $query->where('category', $category);
        }
        if ($search !== '') {
            $query->where('tag', 'like', '%' . $search . '%');
        }

        $hashtags = $query->orderBy('sort_order')->orderBy('tag')->paginate(50)->withQueryString();

        $stats = [
            'total'      => HashtagPool::count(),
            'active'     => HashtagPool::where('is_active', true)->count(),
            'most_used'  => HashtagPool::orderByDesc('usage_count')->first(),
            'never_used' => HashtagPool::where('usage_count', 0)->count(),
        ];

        return view('admin.hashtag-pool.index', [
            'hashtags' => $hashtags,
            'stats'    => $stats,
            'category' => $category,
            'search'   => $search,
            'categoryLabels' => HashtagPool::categoryLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tag'      => 'required|string|max:100',
            'category' => 'required|string|in:genel,urun,lokasyon,trend,sezon',
        ]);

        $normalized = HashtagPool::normalize($validated['tag']);
        if ($normalized === '') {
            return back()->with('error', 'Geçersiz hashtag.');
        }

        HashtagPool::updateOrCreate(
            ['tag' => $normalized],
            [
                'category'  => $validated['category'],
                'is_active' => true,
            ],
        );

        return back()->with('success', "#{$normalized} havuza eklendi.");
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tags'     => 'required|string|max:5000',
            'category' => 'required|string|in:genel,urun,lokasyon,trend,sezon',
        ]);

        $parts = preg_split('/[\s,]+/u', $validated['tags']) ?: [];
        $count = 0;
        foreach ($parts as $part) {
            $normalized = HashtagPool::normalize($part);
            if ($normalized === '') {
                continue;
            }
            HashtagPool::updateOrCreate(
                ['tag' => $normalized],
                ['category' => $validated['category'], 'is_active' => true],
            );
            $count++;
        }

        return back()->with('success', "{$count} hashtag havuza eklendi/güncellendi.");
    }

    public function update(Request $request, HashtagPool $hashtagPool): RedirectResponse
    {
        $validated = $request->validate([
            'category'   => 'sometimes|string|in:genel,urun,lokasyon,trend,sezon',
            'is_active'  => 'sometimes|in:0,1',
            'sort_order' => 'sometimes|integer|min:0|max:9999',
        ]);

        $hashtagPool->update($validated);

        return back()->with('success', "#{$hashtagPool->tag} güncellendi.");
    }

    public function destroy(HashtagPool $hashtagPool): RedirectResponse
    {
        $tag = $hashtagPool->tag;
        $hashtagPool->delete();

        return back()->with('success', "#{$tag} silindi.");
    }

    public function toggle(HashtagPool $hashtagPool): RedirectResponse
    {
        $hashtagPool->update(['is_active' => ! $hashtagPool->is_active]);

        return back()->with('success', "#{$hashtagPool->tag} " . ($hashtagPool->is_active ? 'aktifleştirildi' : 'pasifleştirildi'));
    }
}
