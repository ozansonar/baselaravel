<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UploadController extends Controller
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    public function ckeditor(Request $request): JsonResponse
    {
        $request->validate([
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $this->uploadService->uploadImage(
            $request->file('upload'),
            'content',
            'icerik-gorsel',
            ['sm', 'md', 'lg'],
        );

        return response()->json([
            'url' => upload_url($path),
        ]);
    }
}
