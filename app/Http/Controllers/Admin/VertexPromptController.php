<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VertexPromptRequest;
use App\Models\VertexPrompt;
use App\Models\VertexPromptVersion;
use App\Services\VertexPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class VertexPromptController extends Controller
{
    public function __construct(
        private readonly VertexPromptService $promptService,
    ) {}

    public function index(): View
    {
        $prompts = VertexPrompt::query()
            ->withCount('versions')
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.vertex.prompts.index', [
            'prompts' => $prompts,
        ]);
    }

    public function create(): View
    {
        return view('admin.vertex.prompts.form', [
            'prompt'       => new VertexPrompt(),
            'mode'         => 'create',
            'versionCount' => 0,
        ]);
    }

    public function store(VertexPromptRequest $request): RedirectResponse
    {
        $this->promptService->store(
            $request->validated(),
            $request->file('reference_images'),
        );

        return redirect()
            ->route('admin.vertex.prompts.index')
            ->with('success', 'Vertex şablonu eklendi.');
    }

    public function edit(VertexPrompt $prompt): View
    {
        return view('admin.vertex.prompts.form', [
            'prompt'       => $prompt,
            'mode'         => 'edit',
            'versionCount' => $prompt->versions()->count(),
        ]);
    }

    public function update(VertexPromptRequest $request, VertexPrompt $prompt): RedirectResponse
    {
        $data = $request->validated();
        $removeIndices = $data['reference_images_remove'] ?? [];
        unset($data['reference_images_remove']);

        $this->promptService->updateWithImages(
            $prompt,
            $data,
            $removeIndices,
            $request->user()?->id,
            $request->file('reference_images'),
        );

        return redirect()
            ->route('admin.vertex.prompts.index')
            ->with('success', 'Vertex şablonu güncellendi.');
    }

    public function destroy(VertexPrompt $prompt): RedirectResponse
    {
        $this->promptService->destroy($prompt);

        return redirect()
            ->route('admin.vertex.prompts.index')
            ->with('success', 'Vertex şablonu silindi.');
    }

    public function togglePin(VertexPrompt $prompt): JsonResponse
    {
        $pinned = $this->promptService->togglePin($prompt);

        return response()->json([
            'success'   => true,
            'is_pinned' => $pinned,
            'message'   => $pinned ? 'Şablon sabitlendi.' : 'Sabitleme kaldırıldı.',
        ]);
    }

    public function versions(VertexPrompt $prompt): JsonResponse
    {
        try {
            $versions = $prompt->versions()
                ->with('author:id,first_name,last_name')
                ->get()
                ->map(fn (VertexPromptVersion $v) => [
                    'id'             => $v->id,
                    'version_number' => $v->version_number,
                    'author'         => $v->author?->full_name ?? 'Sistem',
                    'created_at'     => $v->created_at?->format('d.m.Y H:i:s'),
                    'changes'        => $this->summarizeChanges($v->data, $prompt),
                ]);

            return response()->json([
                'prompt_name' => $prompt->name,
                'versions'    => $versions,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'versions' => [],
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function showVersion(VertexPrompt $prompt, VertexPromptVersion $version): JsonResponse
    {
        abort_unless((int) $version->vertex_prompt_id === (int) $prompt->id, 404);

        $version->load('author:id,first_name,last_name');

        return response()->json([
            'id'             => $version->id,
            'version_number' => $version->version_number,
            'author'         => $version->author?->full_name ?? 'Sistem',
            'created_at'     => $version->created_at?->format('d.m.Y H:i:s'),
            'data'           => $version->data,
        ]);
    }

    public function restoreVersion(VertexPrompt $prompt, VertexPromptVersion $version): JsonResponse
    {
        abort_unless((int) $version->vertex_prompt_id === (int) $prompt->id, 404);

        $this->promptService->restoreVersion($prompt, $version, request()->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Versiyon #' . $version->version_number . ' geri yüklendi.',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $versionData
     */
    private function summarizeChanges(?array $versionData, VertexPrompt $prompt): array
    {
        if ($versionData === null) {
            return [];
        }

        $labels = [
            'name'                 => 'Ad',
            'prompt'               => 'Prompt',
            'description'          => 'Açıklama',
            'model'                => 'Model',
            'aspect_ratio'         => 'Oran',
            'image_size'           => 'Çözünürlük',
            'temperature'          => 'Yaratıcılık',
            'ig_caption_template'  => 'Caption',
            'ig_title_template'    => 'Başlık',
            'ig_hashtags_template' => 'Hashtag',
            'reference_images'     => 'Ref. Görseller',
            'random_options'       => 'Seçenek Listeleri',
            'is_active'            => 'Durum',
        ];

        $changes = [];
        foreach ($labels as $key => $label) {
            $old = $versionData[$key] ?? null;
            $current = $prompt->getAttribute($key);

            if ($this->valuesAreDifferent($old, $current)) {
                $changes[] = $label;
            }
        }

        return $changes;
    }

    private function valuesAreDifferent(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) !== json_encode($b);
        }

        return (string) ($a ?? '') !== (string) ($b ?? '');
    }
}
