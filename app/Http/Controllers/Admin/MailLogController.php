<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Enums\MailLogStatus;
use App\Services\MailLogService;
use App\Services\MailService;
use App\Services\QueueRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MailLogController extends Controller
{
    public function __construct(
        private readonly MailLogService $mailLogService,
        private readonly MailService $mailService,
        private readonly QueueRunner $queueRunner,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MailLog::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search', 'date_filter']);

        return view('admin.mail-logs.index', [
            'mailLogs'     => $this->mailLogService->paginate($perPage, $filters),
            'statusCounts' => $this->mailLogService->statusCounts(),
            'stats'        => $this->mailLogService->getAdminStats(),
            'perPage'      => $perPage,
            // Beklemedeki mailler kuyruktaki işlerle gider; kaç iş beklediği
            // "neden hâlâ gönderilmedi" sorusunun cevabı.
            'queuedJobs'   => $this->queueRunner->pendingJobs(),
        ]);
    }

    public function show(MailLog $mailLog): View
    {
        $this->authorize('view', $mailLog);

        $mailLog->load('user');

        return view('admin.mail-logs.show', [
            'mailLog' => $mailLog,
        ]);
    }

    public function body(MailLog $mailLog): JsonResponse
    {
        $this->authorize('view', $mailLog);

        return response()->json([
            'body' => $mailLog->body ?? '<p>İçerik kaydedilmemiş.</p>',
        ]);
    }

    /**
     * Kuyrukta bekleyen maili şimdi gönder.
     *
     * Doğru yol kuyruğu çalıştırmak: mail kendi işiyle gider, durumu dinleyici
     * günceller ve çift gönderim olmaz. Kuyruk boşaldığı hâlde kayıt hâlâ
     * beklemedeyse iş kaybolmuş demektir; o zaman gövde doğrudan gönderilir.
     */
    public function sendNow(MailLog $mailLog): JsonResponse
    {
        $this->authorize('resend', $mailLog);

        if ($mailLog->status !== MailLogStatus::Pending) {
            return response()->json([
                'success' => false,
                'message' => 'Bu mail zaten işlenmiş, beklemede değil.',
            ], 422);
        }

        // Web isteği cron dakikası kadar bekleyemez; sınırlar daha dar.
        $result = $this->queueRunner->drain(maxJobs: 25, maxSeconds: 15);

        $mailLog->refresh();

        if ($mailLog->status === MailLogStatus::Sent) {
            return response()->json([
                'success' => true,
                'message' => 'Mail gönderildi.',
            ]);
        }

        if ($mailLog->status === MailLogStatus::Failed) {
            return response()->json([
                'success' => false,
                'message' => 'Mail gönderilemedi: ' . ($mailLog->error_message ?: 'bilinmeyen hata'),
            ], 422);
        }

        if ($result['remaining'] > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kuyrukta {$result['remaining']} iş kaldı, bu mailin sırası gelmedi. Biraz sonra tekrar deneyin.",
            ], 422);
        }

        try {
            $this->mailService->sendPendingNow($mailLog);

            return response()->json([
                'success' => true,
                'message' => 'Kuyrukta işi kalmamıştı, mail doğrudan gönderildi.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function resend(MailLog $mailLog): JsonResponse
    {
        $this->authorize('resend', $mailLog);

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
