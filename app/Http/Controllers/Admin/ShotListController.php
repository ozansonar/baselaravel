<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaLibraryImage;
use App\Models\ShotListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/cek-listesi — Fotoğraf/video çekim yapılacaklar listesi.
 *
 * Gap analizdeki AI önerileri buraya kaydedilir, kullanıcı "Yapıldı" işaretler.
 */
final class ShotListController extends Controller
{
    public function index(Request $request): View
    {
        $query = ShotListItem::query()->with('creator', 'doer');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('media_type')) {
            $query->where('media_type', $request->string('media_type')->toString());
        }

        $items = $query->byPriority()->paginate(50)->withQueryString();

        $stats = [
            'pending' => ShotListItem::where('status', 'pending')->count(),
            'planned' => ShotListItem::where('status', 'planned')->count(),
            'done'    => ShotListItem::where('status', 'done')->count(),
            'high_priority_pending' => ShotListItem::where('status', 'pending')->where('priority', 'high')->count(),
        ];

        return view('admin.shot-list.index', [
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic'       => ['required', 'string', 'min:3', 'max:250'],
            'media_type'  => ['required', 'string', 'in:feed,reels,story'],
            'theme'       => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::THEMES)],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'priority'    => ['nullable', 'string', 'in:' . implode(',', ShotListItem::PRIORITIES)],
        ]);

        $item = ShotListItem::create([
            'topic'        => $validated['topic'],
            'media_type'   => $validated['media_type'],
            'theme'        => $validated['theme']    ?? null,
            'notes'        => $validated['notes']    ?? null,
            'priority'     => $validated['priority'] ?? 'normal',
            'status'       => 'pending',
            'ai_generated' => false,
            'created_by'   => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Çekim listesine eklendi.', 'id' => $item->id]);
    }

    /**
     * Gap analizden gelen AI önerilerini bulk olarak çek listesine ekler.
     */
    public function bulkAddFromAi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'                => ['required', 'array', 'min:1', 'max:50'],
            'items.*.topic'        => ['required', 'string', 'min:3', 'max:250'],
            'items.*.media_type'   => ['required', 'string', 'in:feed,reels,story'],
            'items.*.theme'        => ['nullable', 'string'],
        ]);

        $count = 0;
        foreach ($validated['items'] as $row) {
            // Aynı topic + media_type kombinasyonu zaten varsa atla
            $exists = ShotListItem::where('topic', $row['topic'])
                ->where('media_type', $row['media_type'])
                ->whereIn('status', ['pending', 'planned'])
                ->exists();

            if ($exists) continue;

            ShotListItem::create([
                'topic'        => $row['topic'],
                'media_type'   => $row['media_type'],
                'theme'        => $row['theme'] ?? null,
                'priority'     => 'normal',
                'status'       => 'pending',
                'ai_generated' => true,
                'created_by'   => $request->user()?->id,
            ]);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} öneri çek listesine eklendi (zaten olan öneriler atlandı).",
            'count'   => $count,
        ]);
    }

    public function update(Request $request, ShotListItem $item): JsonResponse
    {
        $validated = $request->validate([
            'topic'      => ['nullable', 'string', 'min:3', 'max:250'],
            'media_type' => ['nullable', 'string', 'in:feed,reels,story'],
            'theme'      => ['nullable', 'string'],
            'notes'      => ['nullable', 'string', 'max:2000'],
            'priority'   => ['nullable', 'string', 'in:' . implode(',', ShotListItem::PRIORITIES)],
            'status'     => ['nullable', 'string', 'in:' . implode(',', ShotListItem::STATUSES)],
        ]);

        $payload = array_filter($validated, static fn ($v) => $v !== null);

        // Status 'done'a geçtiyse done_at + done_by işaretle
        if (($payload['status'] ?? null) === 'done' && $item->status !== 'done') {
            $payload['done_at'] = now();
            $payload['done_by'] = $request->user()?->id;
        }

        $item->update($payload);

        return response()->json(['success' => true, 'message' => 'Güncellendi.']);
    }

    public function destroy(ShotListItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(['success' => true, 'message' => 'Silindi.']);
    }
}
