<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignAudience;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\RecipientImportService;
use App\Services\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use RuntimeException;
use Throwable;

final class CampaignController extends Controller
{
    private const PER_PAGE = [15, 25, 50, 100];

    /**
     * Excel önizlemesinde gösterilen satır sayısı. Amaç dosyayı doğrulamak,
     * listeyi ekrana dökmek değil.
     */
    private const PREVIEW_ROWS = 10;

    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignDispatcher $dispatcher,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 15;

        $query = Campaign::query()->withCount('recipients')->latest('id');

        if (($status = $request->string('status')->toString()) !== ''
            && ($case = CampaignStatus::tryFrom($status)) !== null) {
            $query->where('status', $case);
        }

        if (($search = $request->string('search')->toString()) !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return view('admin.campaigns.index', [
            'campaigns'    => $query->paginate($perPage)->withQueryString(),
            'stats'        => $this->stats(),
            'statusCounts' => $this->statusCounts(),
            'perPageList'  => self::PER_PAGE,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('admin.campaigns.create', $this->formData());
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        $campaign = $this->campaigns->create($request->campaignData());

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'Kampanya taslak olarak kaydedildi.');
    }

    /**
     * The review screen: what will go out, to how many people, and when.
     *
     * A campaign is never sent straight from the form — it lands here first and
     * only starts once someone approves it against a real recipient count.
     */
    public function show(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $pending = (int) $campaign->recipients()
            ->where('status', \App\Enums\CampaignRecipientStatus::Pending)
            ->count();

        return view('admin.campaigns.show', [
            'campaign'  => $campaign->load('attachments', 'author'),
            'breakdown' => $this->recipientBreakdown($campaign),
            'preview'   => $this->campaigns->previewAudience($campaign),
            'failures'  => $campaign->recipients()
                ->where('status', \App\Enums\CampaignRecipientStatus::Failed)
                ->limit(20)
                ->get(),
            'pendingCount'  => $pending,
            'hourlyLimit'   => $this->dispatcher->hourlyLimit(),
            'perRunQuota'   => $this->dispatcher->perRunQuota(),
            'sentLastHour'  => $this->dispatcher->sentLastHour(),
            'remaining'     => $this->dispatcher->remainingBudget(),
            'nextRunAt'     => $this->dispatcher->nextRunAt(),
            'nextRunIn'     => $this->dispatcher->secondsUntilNextRun(),
            'estimate'      => $this->estimateFinish($campaign, $pending),
        ]);
    }

    /**
     * Roughly when the queue will be empty, given the hourly limit.
     */
    private function estimateFinish(Campaign $campaign, int $pending): ?\Illuminate\Support\Carbon
    {
        $limit = $this->dispatcher->hourlyLimit();

        if ($pending < 1 || $limit < 1) {
            return null;
        }

        return now()->addMinutes((int) ceil($pending / $limit * 60));
    }

    /**
     * Yüklenen dosyayı kaydetmeden okuyup ne bulduğunu söyler.
     *
     * Sütunlar ada göre eşleşiyor ve başlık yoksa tahmin ediliyor; kullanıcı
     * kampanyayı kaydetmeden önce dosyanın doğru okunduğunu görebilmeli,
     * yoksa hata ancak gönderim anında ortaya çıkıyor.
     */
    public function previewRecipients(Request $request, RecipientImportService $importer): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $request->validate([
            'recipient_file' => ['required', 'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:10240'],
        ], [
            'recipient_file.mimes' => 'Yalnızca Excel (.xlsx, .xls, .ods) veya CSV dosyası yükleyebilirsiniz.',
            'recipient_file.max'   => 'Alıcı dosyası en fazla 10 MB olabilir.',
        ]);

        try {
            $result = $importer->parse($request->file('recipient_file'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'total'   => $result['total'],
            'invalid' => $result['invalid'],
            'sample'  => array_slice($result['rows'], 0, self::PREVIEW_ROWS),
        ]);
    }

    /**
     * The sample file for the Excel import, so nobody has to guess the layout.
     */
    public function template(RecipientImportService $importer): BinaryFileResponse
    {
        $this->authorize('create', Campaign::class);

        $path = tempnam(sys_get_temp_dir(), 'sablon') . '.xlsx';
        $importer->writeTemplate($path);

        return response()
            ->download($path, 'alici-listesi-sablonu.xlsx')
            ->deleteFileAfterSend();
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        abort_unless($campaign->isEditable(), 403, 'Gönderimi başlamış kampanya düzenlenemez.');

        return view('admin.campaigns.edit', $this->formData() + [
            'campaign' => $campaign->load('attachments'),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $this->campaigns->update($campaign, $request->campaignData());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'Kampanya güncellendi.');
    }

    /**
     * Queue the campaign. With no time given it goes out on the next cron run,
     * which is what the "send now" button posts.
     */
    public function send(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        try {
            // Both steps in one transaction: if the audience turns out to be
            // empty, the campaign must stay a draft rather than being left
            // Scheduled, where the cron would retry it forever and the review
            // screen would no longer offer the approve button.
            DB::transaction(function () use ($campaign, $validated): void {
                $this->campaigns->schedule(
                    $campaign,
                    isset($validated['scheduled_at']) ? new \DateTimeImmutable($validated['scheduled_at']) : null,
                );

                // Resolve the audience straight away so an empty list is
                // reported here rather than failing silently inside the cron.
                if (($validated['scheduled_at'] ?? null) === null) {
                    $this->campaigns->start($campaign->refresh());
                }
            });
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $campaign->refresh();

        return back()->with('success', sprintf(
            '%d alıcı sıraya alındı. Saatlik limit %d, her %d dakikada bir %d mail gönderilecek.',
            $campaign->total_recipients,
            $this->dispatcher->hourlyLimit(),
            CampaignDispatcher::RUN_INTERVAL_MINUTES,
            $this->dispatcher->perRunQuota(),
        ));
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        try {
            $this->campaigns->pause($campaign);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Gönderim duraklatıldı.');
    }

    public function resume(Campaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        try {
            $this->campaigns->resume($campaign);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Gönderim sürdürülüyor.');
    }

    public function cancel(Campaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        try {
            $this->campaigns->cancel($campaign);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kampanya iptal edildi, bekleyen alıcılara gönderilmeyecek.');
    }

    public function sendTest(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        try {
            $this->campaigns->sendTest($campaign, $validated['test_email']);
        } catch (Throwable $e) {
            return back()->with('error', 'Test maili gönderilemedi: ' . $e->getMessage());
        }

        return back()->with('success', $validated['test_email'] . ' adresine test maili gönderildi.');
    }

    public function destroyAttachment(Campaign $campaign, int $attachment): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $this->campaigns->deleteAttachment($campaign, $attachment);

        return back()->with('success', 'Ek kaldırıldı.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Kampanya silindi.');
    }

    public function restore(int $campaign): RedirectResponse
    {
        $model = Campaign::withTrashed()->findOrFail($campaign);

        $this->authorize('restore', $model);

        $model->restore();

        return back()->with('success', 'Kampanya geri yüklendi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'audiences' => CampaignAudience::cases(),
            'roles'     => Role::orderBy('name')->get(['id', 'name']),
            'languages' => app(LanguageService::class)->active(),
            'hourlyLimit' => $this->dispatcher->hourlyLimit(),
            'perRunQuota' => $this->dispatcher->perRunQuota(),
            // Seçim kartlarında kaç kişi olduğu yazsın: "site üyeleri" ile
            // "mail listesi" arasında seçim yapan kişi bunu bilmeden karar veremez.
            'audienceCounts' => [
                CampaignAudience::Users->value       => User::query()->where('is_active', true)->count(),
                // Kampanyanın gerçekten kime gideceğiyle aynı ölçüt: durumu
                // "abone" olanlar. Çıkanlar ve dönenler listeye girmiyor.
                CampaignAudience::Subscribers->value => Subscriber::query()->subscribed()->count(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total'   => Campaign::count(),
            'sending' => Campaign::whereIn('status', [CampaignStatus::Sending, CampaignStatus::Scheduled])->count(),
            'sent'    => (int) Campaign::sum('sent_count'),
            'pending' => \App\Models\CampaignRecipient::where('status', \App\Enums\CampaignRecipientStatus::Pending)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = Campaign::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = ['' => Campaign::count()];

        foreach (CampaignStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function recipientBreakdown(Campaign $campaign): array
    {
        return $campaign->recipients()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }
}
