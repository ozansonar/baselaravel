<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiImagePromptRequest;
use App\Http\Requests\Admin\GenerateAiImageRequest;
use App\Models\AiGeneratedImage;
use App\Models\AiImagePrompt;
use App\Services\AiImageService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AiImageController extends Controller
{
    public function __construct(
        private readonly AiImageService $aiImageService,
        private readonly UploadService $uploadService,
    ) {}

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [12, 24, 48], true)
            ? (int) $request->query('per_page')
            : 24;

        $prompts = AiImagePrompt::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $images = AiGeneratedImage::query()
            ->with(['promptTemplate', 'author'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total'     => AiGeneratedImage::count(),
            'completed' => AiGeneratedImage::completed()->count(),
            'failed'    => AiGeneratedImage::failed()->count(),
            'prompts'   => AiImagePrompt::active()->count(),
        ];

        return view('admin.ai-images.index', [
            'prompts' => $prompts,
            'images'  => $images,
            'stats'   => $stats,
            'perPage' => $perPage,
        ]);
    }

    public function generate(GenerateAiImageRequest $request): JsonResponse
    {
        $result = $this->aiImageService->generate(
            prompt: (string) $request->input('prompt'),
            promptTemplateId: $request->filled('prompt_template_id') ? (int) $request->input('prompt_template_id') : null,
            userId: $request->user()?->id,
            aspectRatio: (string) $request->input('aspect_ratio', '1:1'),
        );

        $image = $result['image'];

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'image'   => [
                'id'                 => $image->id,
                'status'             => $image->status,
                'image_path'         => $image->image_path,
                'image_url'          => $image->image_path ? upload_url($image->image_path, 'lg') : null,
                'thumb_url'          => $image->image_path ? upload_url($image->image_path, 'thumb') : null,
                'final_prompt'       => $image->final_prompt,
                'model'              => $image->model,
                'aspect_ratio'       => $image->aspect_ratio,
                'error_message'      => $image->error_message,
                'generation_time_ms' => $image->generation_time_ms,
                'created_at'         => $image->created_at?->format('d.m.Y H:i'),
            ],
        ], $result['success'] ? 200 : 422);
    }

    public function storePrompt(AiImagePromptRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] = (int) (AiImagePrompt::max('sort_order') ?? 0) + 10;

        // Referans görsel — BYTE-FOR-BYTE upload (image-to-image flow için AI'a aynen iletilir).
        // uploadFile() $file->move() kullanır → GD re-encode YOK, sıkıştırma YOK,
        // EXIF + metadata + transparency + çözünürlük HEPSİ korunur. Uzantı orijinal kalır.
        if ($request->hasFile('reference_image')) {
            $data['reference_image_path'] = $this->uploadService->uploadFile(
                $request->file('reference_image'),
                'ai-prompts',
                $data['name'],
            );
        }
        // Boş submit + remove flag temizliği zaten store'da gereksiz; sadece update için
        unset($data['reference_image'], $data['reference_image_remove']);

        AiImagePrompt::create($data);

        return redirect()
            ->route('admin.ai-images.index')
            ->with('success', 'Prompt şablonu eklendi.');
    }

    public function updatePrompt(AiImagePromptRequest $request, AiImagePrompt $prompt): RedirectResponse
    {
        $data = $request->validated();
        $removeRequested = (bool) ($data['reference_image_remove'] ?? false);
        unset($data['reference_image'], $data['reference_image_remove']);

        // Referans görsel — yeni dosya yüklendiyse veya kaldırma istendiyse
        // (uploadFile = byte-for-byte, GD yok, sıkıştırma yok)
        if ($request->hasFile('reference_image')) {
            // Eski dosyayı sil + yenisini byte-for-byte yükle
            if ($prompt->reference_image_path) {
                $this->uploadService->deleteFile($prompt->reference_image_path);
            }
            $data['reference_image_path'] = $this->uploadService->uploadFile(
                $request->file('reference_image'),
                'ai-prompts',
                $data['name'] ?? $prompt->name,
            );
        } elseif ($removeRequested && $prompt->reference_image_path) {
            $this->uploadService->deleteFile($prompt->reference_image_path);
            $data['reference_image_path'] = null;
        }

        $prompt->update($data);

        return redirect()
            ->route('admin.ai-images.index')
            ->with('success', 'Prompt şablonu güncellendi.');
    }

    public function destroyPrompt(AiImagePrompt $prompt): RedirectResponse
    {
        $prompt->delete();

        return redirect()
            ->route('admin.ai-images.index')
            ->with('success', 'Prompt şablonu silindi.');
    }

    /**
     * AI Detay modal için JSON — gönderilen prompt, API response, template,
     * referans görsel, üretilen görsel, model/maliyet/süre.
     */
    public function showImage(AiGeneratedImage $image): JsonResponse
    {
        $image->load('promptTemplate');
        $template = $image->promptTemplate;

        return response()->json([
            'id'                 => $image->id,
            'status'             => $image->status,
            'final_prompt'       => $image->final_prompt,
            'request_payload'    => $image->request_payload,
            'response_payload'   => $image->response_payload,
            'provider'           => $image->provider,
            'model'              => $image->model,
            'aspect_ratio'       => $image->aspect_ratio,
            'width'              => $image->width,
            'height'             => $image->height,
            'cost_usd'           => $image->cost_usd,
            'generation_time_ms' => $image->generation_time_ms,
            'attempt_count'      => $image->attempt_count,
            'error_message'      => $image->error_message,
            'created_at'         => $image->created_at?->format('d.m.Y H:i:s'),
            'image_url'          => $image->image_path ? upload_url($image->image_path, 'lg') : null,
            'instagram_post_id'  => $image->instagram_post_id,
            'blog_post_id'       => $image->blog_post_id,
            'template'           => $template ? [
                'id'                   => $template->id,
                'name'                 => $template->name,
                'prompt'               => $template->prompt,
                'description'          => $template->description,
                'reference_image_url'  => $template->reference_image_path
                    ? upload_url($template->reference_image_path)
                    : null,
            ] : null,
        ]);
    }

    public function destroyImage(AiGeneratedImage $image): RedirectResponse
    {
        if ($image->image_path) {
            $this->uploadService->deleteImage($image->image_path);
        }

        $image->delete();

        return redirect()
            ->route('admin.ai-images.index')
            ->with('success', 'Görsel silindi.');
    }
}
