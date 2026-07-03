<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InstagramPostStatus;
use App\Http\Controllers\Controller;
use App\Models\InstagramPost;
use App\Models\InstagramPostLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InstagramLogController extends Controller
{
    private const array ACTION_LABELS = [
        'create_container'         => 'Container Oluştur',
        'create_carousel_child'    => 'Carousel Görsel',
        'create_carousel_parent'   => 'Carousel Ana',
        'create_reels_container'   => 'IG Reels Container',
        'create_story_container'   => 'IG Story Container',
        'publish'                  => 'Instagram Yayın',
        'fb_publish_single'        => 'Facebook Yayın (Tekli)',
        'fb_upload_unpublished'    => 'Facebook Görsel Yükleme',
        'fb_publish_multi'         => 'Facebook Yayın (Çoklu)',
        'fb_publish_reels_start'   => 'FB Reels — Başlat',
        'fb_publish_reels_transfer' => 'FB Reels — Transfer',
        'fb_publish_reels_finish'  => 'FB Reels — Bitir/Yayınla',
        'fb_publish_exception'     => 'Facebook Genel Hata (Exception)',
    ];

    public function index(Request $request): View
    {
        $action  = trim((string) $request->query('action', ''));
        $status  = trim((string) $request->query('status', ''));
        $postId  = trim((string) $request->query('post_id', ''));
        $from    = trim((string) $request->query('from', ''));
        $to      = trim((string) $request->query('to', ''));
        $perPage = in_array((int) $request->query('per_page'), [25, 50, 100, 200], true)
            ? (int) $request->query('per_page')
            : 50;

        $query = InstagramPostLog::query()->with('post:id,image_path,caption,status');

        if ($action !== '') {
            // create_carousel_child gibi index'li action'lar prefix ile sorgulanır
            if (in_array($action, ['create_carousel_child', 'fb_upload_unpublished'], true)) {
                $query->where('action', 'like', $action . '%');
            } else {
                $query->where('action', $action);
            }
        }

        if (in_array($status, ['success', 'failed'], true)) {
            $query->where('status', $status);
        }

        if ($postId !== '' && ctype_digit($postId)) {
            $query->where('instagram_post_id', (int) $postId);
        }

        if ($from !== '') {
            try {
                $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
            } catch (\Throwable) {}
        }

        if ($to !== '') {
            try {
                $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
            } catch (\Throwable) {}
        }

        $logs = $query->latest('id')->paginate($perPage)->withQueryString();

        return view('admin.instagram-logs.index', [
            'logs'             => $logs,
            'stats'            => $this->buildStats(),
            'cronHeartbeat'    => $this->buildCronHeartbeat(),
            'actionOptions'    => self::ACTION_LABELS,
            'failedPostsCount' => InstagramPost::where('status', InstagramPostStatus::Failed->value)->count(),
            'totalLogsCount'   => InstagramPostLog::count(),
            'oldLogsCount'     => InstagramPostLog::where('created_at', '<', now()->subDays(30))->count(),
            'filters'          => [
                'action'   => $action,
                'status'   => $status,
                'post_id'  => $postId,
                'from'     => $from,
                'to'       => $to,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(InstagramPostLog $log): JsonResponse
    {
        $responseBody = $log->response_body;
        $prettyResponse = null;

        if (is_string($responseBody) && $responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $prettyResponse = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return response()->json([
            'id'              => $log->id,
            'instagram_post_id' => $log->instagram_post_id,
            'action'          => $log->action,
            'action_label'    => $this->labelForAction($log->action),
            'status'          => $log->status,
            'api_duration_ms' => $log->api_duration_ms,
            'created_at'      => $log->created_at?->format('d.m.Y H:i:s'),
            'error_message'   => $log->error_message,
            'request_payload' => $log->request_payload,
            'response_body'   => $responseBody,
            'response_pretty' => $prettyResponse,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $mode = (string) $request->input('mode', 'old');

        if ($mode === 'all') {
            $count = InstagramPostLog::query()->delete();

            return redirect()->route('admin.instagram-logs.index')
                ->with('success', "Tüm Instagram API logları silindi: {$count} kayıt.");
        }

        $days = (int) $request->input('days', 30);
        if ($days < 1) {
            $days = 30;
        }

        $count = InstagramPostLog::where('created_at', '<', now()->subDays($days))->delete();

        return redirect()->route('admin.instagram-logs.index')
            ->with('success', "{$days} günden eski {$count} log kaydı silindi.");
    }

    public function replay(InstagramPost $instagramPost): RedirectResponse
    {
        if ($instagramPost->status !== InstagramPostStatus::Failed) {
            return redirect()->back()->with('warning', 'Sadece "Başarısız" durumdaki postlar yeniden denemeye sokulabilir.');
        }

        // Manuel replay: kullanıcı problemi gözden geçirdi → fresh retry hakkı
        // notified_failed_at sıfırlanır ki bir sonraki kalıcı fail'de yeni mail tetiklensin
        $instagramPost->update([
            'status'             => InstagramPostStatus::Scheduled,
            'scheduled_at'       => now(),
            'error_message'      => null,
            'retry_count'        => 0,
            'notified_failed_at' => null,
        ]);

        return redirect()->back()->with('success', "#{$instagramPost->id} numaralı post yeniden denemeye alındı. Bir sonraki cron çalışmasında işlenecek.");
    }

    /**
     * @return array{
     *     total: int,
     *     success_24h: int,
     *     failed_24h: int,
     *     success_rate_24h: float|null,
     *     success_7d: int,
     *     failed_7d: int,
     *     success_rate_7d: float|null,
     *     avg_duration_ms: int|null,
     * }
     */
    private function buildStats(): array
    {
        $since24h = now()->subDay();
        $since7d  = now()->subDays(7);

        $row24h = InstagramPostLog::query()
            ->where('created_at', '>=', $since24h)
            ->selectRaw("SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) AS success")
            ->selectRaw("SUM(CASE WHEN status='failed'  THEN 1 ELSE 0 END) AS failed")
            ->first();

        $row7d = InstagramPostLog::query()
            ->where('created_at', '>=', $since7d)
            ->selectRaw("SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) AS success")
            ->selectRaw("SUM(CASE WHEN status='failed'  THEN 1 ELSE 0 END) AS failed")
            ->first();

        $success24h = (int) ($row24h?->success ?? 0);
        $failed24h  = (int) ($row24h?->failed ?? 0);
        $success7d  = (int) ($row7d?->success ?? 0);
        $failed7d   = (int) ($row7d?->failed ?? 0);

        $rate24h = ($success24h + $failed24h) > 0
            ? round($success24h / ($success24h + $failed24h) * 100, 1)
            : null;
        $rate7d = ($success7d + $failed7d) > 0
            ? round($success7d / ($success7d + $failed7d) * 100, 1)
            : null;

        $avg = InstagramPostLog::query()
            ->where('created_at', '>=', $since7d)
            ->whereNotNull('api_duration_ms')
            ->avg('api_duration_ms');

        return [
            'total'            => InstagramPostLog::count(),
            'success_24h'      => $success24h,
            'failed_24h'       => $failed24h,
            'success_rate_24h' => $rate24h,
            'success_7d'       => $success7d,
            'failed_7d'        => $failed7d,
            'success_rate_7d'  => $rate7d,
            'avg_duration_ms'  => $avg !== null ? (int) round((float) $avg) : null,
        ];
    }

    /**
     * Cron günde bir kez (19:00) çalışıyor → son çalışma 26 saatten eski ise sağlıksız.
     *
     * @return array{last_run: ?string, last_run_human: ?string, healthy: bool, threshold_label: string}
     */
    private function buildCronHeartbeat(): array
    {
        $thresholdHours = 26; // 24 saatlik döngü + 2 saat güvenlik payı
        $thresholdLabel = '26 saat';
        $lastRun = Setting::getValue('instagram_cron_last_run');

        if (empty($lastRun)) {
            return [
                'last_run'        => null,
                'last_run_human'  => null,
                'healthy'         => false,
                'threshold_label' => $thresholdLabel,
            ];
        }

        try {
            $parsed = Carbon::parse($lastRun);
        } catch (\Throwable) {
            return [
                'last_run'        => $lastRun,
                'last_run_human'  => null,
                'healthy'         => false,
                'threshold_label' => $thresholdLabel,
            ];
        }

        return [
            'last_run'        => $parsed->format('d.m.Y H:i:s'),
            'last_run_human'  => $parsed->diffForHumans(),
            'healthy'         => $parsed->gt(now()->subHours($thresholdHours)),
            'threshold_label' => $thresholdLabel,
        ];
    }

    private function labelForAction(string $action): string
    {
        foreach (self::ACTION_LABELS as $key => $label) {
            if ($action === $key || str_starts_with($action, $key . '_')) {
                return $label;
            }
        }

        return $action;
    }
}
