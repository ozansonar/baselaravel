<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Services\MailLogService;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MailLogController extends Controller
{
    public function __construct(
        private readonly MailLogService $mailLogService,
        private readonly MailService $mailService,
    ) {}

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search', 'date_filter']);

        return view('admin.mail-logs.index', [
            'mailLogs'     => $this->mailLogService->paginate($perPage, $filters),
            'statusCounts' => $this->mailLogService->statusCounts(),
            'stats'        => $this->mailLogService->getAdminStats(),
            'perPage'      => $perPage,
        ]);
    }

    public function show(MailLog $mailLog): View
    {
        $mailLog->load('user');

        return view('admin.mail-logs.show', [
            'mailLog' => $mailLog,
        ]);
    }

    public function body(MailLog $mailLog): JsonResponse
    {
        return response()->json([
            'body' => $mailLog->body ?? '<p>İçerik kaydedilmemiş.</p>',
        ]);
    }

    public function resend(MailLog $mailLog): JsonResponse
    {
        try {
            $success = $this->mailService->resendFromLog($mailLog);

            return response()->json([
                'success' => $success,
                'message' => $success
                    ? 'E-posta başarıyla yeniden gönderildi.'
                    : 'E-posta gönderilemedi.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
