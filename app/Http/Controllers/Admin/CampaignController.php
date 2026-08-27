<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignAudience;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\SubscriberListService;
use App\Services\RecipientImportService;
use App\Services\LanguageService;
use App\Support\EmailHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    /**
     * Listede seçilebilecek sıralamalar. İstekten gelen değer bu kümeyle
     * sınırlı; serbest bırakılsaydı sütun adı doğrudan sorguya girerdi.
     *
     * @var array<string, string>
     */
    private const SORT_OPTIONS = [
        'recent'     => 'En yeni',
        'oldest'     => 'En eski',
        'name'       => 'Ada göre (A-Z)',
        'recipients' => 'En çok alıcı',
        'sent'       => 'En çok gönderim',
    ];

    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignDispatcher $dispatcher,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 15;

        $sort = $request->string('sort')->value();
        $sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : '';

        $filters = [
            'search'   => (string) $request->string('search')->trim()->value(),
            'status'   => (string) $request->string('status')->value(),
            'audience' => (string) $request->string('audience')->value(),
            'from'     => (string) $request->string('from')->value(),
            'to'       => (string) $request->string('to')->value(),
            'sort'     => $sort,
        ];

        $query = Campaign::query()->withCount('recipients')->with('author');

        if (($case = CampaignStatus::tryFrom($filters['status'])) !== null) {
            $query->where('status', $case);
        }

        if (($audience = CampaignAudience::tryFrom($filters['audience'])) !== null) {
            $query->where('audience', $audience);
        }

        if ($filters['search'] !== '') {
            // Joker karakterler düz metin sayılıyor: "%" yazan biri tüm listeyi
            // getirmemeli.
            $term = '%' . addcslashes($filters['search'], '%_\\') . '%';

            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('subject', 'like', $term);
            });
        }

        // Tarih aralığı oluşturulma gününe göre; bitiş günü de dâhil olsun diye
        // gün sonuna kadar alınıyor.
        if ($filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if ($filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        match ($filters['sort']) {
            'oldest'     => $query->oldest('id'),
            'name'       => $query->orderBy('name'),
            'recipients' => $query->orderByDesc('total_recipients')->orderByDesc('id'),
            'sent'       => $query->orderByDesc('sent_count')->orderByDesc('id'),
            default      => $query->latest('id'),
        };

        return view('admin.campaigns.index', [
            'campaigns'    => $query->paginate($perPage)->withQueryString(),
            'stats'        => $this->stats(),
            'statusCounts' => $this->statusCounts(),
            'statuses'     => CampaignStatus::cases(),
            'audiences'    => CampaignAudience::cases(),
            'filters'      => $filters,
            'sortOptions'  => self::SORT_OPTIONS,
            'perPage'      => $perPage,
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
    public function show(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        // Liste kayıtla birlikte kuruluyor; kurulamamışsa (kitle o an okunamamış
        // ya da kampanya bu özellik gelmeden önce açılmış) burada bir kez daha
        // deneniyor. Kullanıcının listeyi görmek için düğmeye basması gerekmez.
        if ($campaign->isEditable() && $campaign->recipients()->doesntExist()) {
            $this->campaigns->prepareRecipientsQuietly($campaign);
        }

        $breakdown = $this->recipientBreakdown($campaign);
        $pending = $breakdown[CampaignRecipientStatus::Pending->value] ?? 0;

        return view('admin.campaigns.show', [
            'campaign'  => $campaign->load('attachments', 'author'),
            'breakdown' => $breakdown,
            'preview'   => $this->campaigns->previewAudience($campaign),
            // Önizleme mailin gördüğü gövdeyi göstermeli; ham gövde 600 pikselik
            // sütunu bilmediği için görseller taşıyor ve tasarım bozuk sanılıyordu.
            'bodyPreview' => EmailHtml::previewDocument($campaign->body),
            'failures'  => $campaign->recipients()
                ->where('status', \App\Enums\CampaignRecipientStatus::Failed)
                ->limit(20)
                ->get(),
            // Alıcı listesi ekranda: kime gittiği, kime gitmediği ve nedeni
            // görünmeden kampanyanın yönetilebilir bir tarafı yok.
            'recipients'      => $this->recipientList($campaign, $request),
            // Liste kurulmuş mu: onay kutusu "hazırla" ile "yenile" arasında
            // buna göre karar veriyor.
            'recipientsReady' => $campaign->recipients()->exists(),
            'recipientFilter' => [
                'status' => (string) $request->string('rstatus')->value(),
                'search' => (string) $request->string('rsearch')->trim()->value(),
            ],
            'statuses' => CampaignRecipientStatus::cases(),
            // Sıradaki tur: gönderimle birebir aynı seçim.
            'nextBatch' => $campaign->status === CampaignStatus::Sending
                ? $this->dispatcher->nextBatch($campaign)
                : collect(),
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

        // Zamanlanmış kampanya henüz sıraya girmedi: liste o saatteki cron
        // turunda alınacak. Aynı mesaj kullanılınca ekranda "0 alıcı sıraya
        // alındı" yazıyordu — sayı, gönderim başlamadan doldurulmuyor.
        if ($campaign->status === CampaignStatus::Scheduled) {
            return back()->with('success', sprintf(
                'Kampanya %s için zamanlandı. O saatten sonraki ilk cron turunda %d alıcıya gönderim başlayacak.',
                $campaign->scheduled_at?->format('d.m.Y H:i') ?? '',
                $campaign->pendingCount(),
            ));
        }

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

    /**
     * Tek bir eki, kampanya kaydedilmeden önce kendi isteğiyle alır.
     *
     * On dosya kampanya formuyla birlikte gitseydi gövde post_max_size'ı aşar,
     * PHP her şeyi atar ve CSRF alanı da gittiği için istek 419 dönerdi —
     * kullanıcı yazdığı kampanyayı kaybederdi. Dosya başına tek istek o tavana
     * hiç yaklaşmıyor.
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $limits = $this->campaigns->attachmentLimits();
        $maxKb = (int) floor($limits['per_file'] / 1024);

        $validator = validator($request->all(), [
            'file' => ['required', 'file', 'max:' . $maxKb],
        ], [
            'file.required' => 'Dosya alınamadı.',
            'file.max'      => 'Dosya en fazla ' . $this->humanBytes($limits['per_file']) . ' olabilir.',
            'file.uploaded' => 'Dosya yüklenemedi; sunucu sınırını aşıyor olabilir.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first('file')], 422);
        }

        return response()->json(
            $this->campaigns->storePendingAttachment($request->file('file')),
        );
    }

    /**
     * Kaydetmeden vazgeçilen eki diskten de siler.
     */
    public function destroyPendingAttachment(string $token): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        return response()->json([
            'removed' => $this->campaigns->discardPendingAttachment($token),
        ]);
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1_048_576
            ? round($bytes / 1_048_576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
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
            // Hedef listeler; her birinin yanında mail alabilecek üye sayısı.
            'subscriberLists' => app(SubscriberListService::class)->all(),
            // Ekranda yazan sınır sunucunun gerçek sınırı olmalı: php.ini 2 MB
            // derken arayüzün 10 MB vaat etmesi kullanıcıyı en baştan
            // kaybedeceği bir yüklemeye sokuyordu.
            'attachmentLimits'     => $limits = $this->campaigns->attachmentLimits(),
            'attachmentLimitLabel' => $this->humanBytes($limits['per_file']),
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
    /**
     * Ekranda gösterilecek alıcı sayfası.
     *
     * Durum süzgeci ve arama sunucuda: on binlerce alıcılı bir kampanyada
     * tamamını tarayıcıya yollamak sayfayı kilitler.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\CampaignRecipient>
     */
    private function recipientList(Campaign $campaign, Request $request)
    {
        $status = (string) $request->string('rstatus')->value();
        $search = (string) $request->string('rsearch')->trim()->value();

        return $campaign->recipients()
            ->when(
                CampaignRecipientStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when($search !== '', function ($query) use ($search): void {
                // Joker karakterler düz metin sayılıyor, yoksa "%" tüm listeyi getirir.
                $term = '%' . addcslashes($search, '%_\\') . '%';

                $query->where(function ($inner) use ($term): void {
                    $inner->where('email', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->orderBy('id')
            ->paginate(25, ['*'], 'ralici')
            ->withQueryString();
    }

    /**
     * Alıcı listesini gönderimden önce kurar.
     *
     * Taslakta liste yokken görülebilen tek şey on kişilik bir örnekti; kimin
     * listede olduğunu görüp ayıklamak ancak gönderimi başlattıktan sonra
     * mümkündü. Liste artık onaydan önce dondurulabiliyor, ayıklama gönderime
     * olduğu gibi taşınıyor.
     */
    public function prepareRecipients(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $count = $this->campaigns->prepareRecipients($campaign, $request->boolean('refresh'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            '%d alıcı listeye alındı. Göndermek istemediklerinizi çıkarıp sonra onaylayın.',
            $count,
        ));
    }

    /**
     * Bir alıcıyı gönderim dışında bırakır.
     *
     * Kayıt silinmiyor, "atlandı" işaretleniyor: kimin listede olduğu ve neden
     * gitmediği kampanya sonrası da görülebilmeli. Gönderilmiş bir adres geri
     * alınamaz, çünkü mail çoktan yola çıktı.
     */
    public function excludeRecipient(Campaign $campaign, CampaignRecipient $recipient): RedirectResponse
    {
        $this->authorize('update', $campaign);
        abort_unless($recipient->campaign_id === $campaign->id, 404);

        if ($recipient->status === CampaignRecipientStatus::Sent) {
            return back()->with('error', 'Bu adrese mail gönderilmiş, listeden çıkarılamaz.');
        }

        $recipient->update(['status' => CampaignRecipientStatus::Skipped]);

        return back()->with('success', $recipient->email . ' gönderim dışında bırakıldı.');
    }

    /**
     * Yanlışlıkla çıkarılan alıcıyı sıraya geri koyar.
     */
    public function restoreRecipient(Campaign $campaign, CampaignRecipient $recipient): RedirectResponse
    {
        $this->authorize('update', $campaign);
        abort_unless($recipient->campaign_id === $campaign->id, 404);

        if ($recipient->status !== CampaignRecipientStatus::Skipped) {
            return back()->with('error', 'Yalnızca çıkarılmış bir adres sıraya geri alınabilir.');
        }

        $recipient->update(['status' => CampaignRecipientStatus::Pending]);

        return back()->with('success', $recipient->email . ' yeniden sıraya alındı.');
    }

    /**
     * Seçilen alıcılara toplu işlem.
     *
     * Tek tek çıkarmak on binlerce alıcılı bir listede gerçekçi değil; süzgeçle
     * daraltıp topluca işlemek gerekiyor.
     */
    public function bulkRecipients(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'action'           => ['required', 'string', 'in:exclude,restore,retry'],
            'recipient_ids'    => ['required', 'array', 'min:1'],
            'recipient_ids.*'  => ['integer'],
        ], [
            'recipient_ids.required' => 'Önce en az bir alıcı seçin.',
        ]);

        $ids = $validated['recipient_ids'];

        $count = match ($validated['action']) {
            'exclude' => $this->campaigns->excludeRecipients($campaign, $ids),
            'restore' => $this->campaigns->restoreRecipients($campaign, $ids),
            'retry'   => $this->campaigns->retryFailed($campaign, $ids),
        };

        if ($count === 0) {
            return back()->with('error', 'Seçilen alıcılarda bu işlem uygulanabilir bir satır yok.');
        }

        return back()->with('success', match ($validated['action']) {
            'exclude' => "{$count} alıcı gönderim dışında bırakıldı.",
            'restore' => "{$count} alıcı yeniden sıraya alındı.",
            'retry'   => "{$count} başarısız alıcı yeniden denenecek.",
        });
    }

    /**
     * Kampanyanın tüm başarısızlarını yeniden denemeye alır.
     */
    public function retryFailed(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $count = $this->campaigns->retryFailed($campaign);

        return $count === 0
            ? back()->with('error', 'Yeniden denenecek başarısız alıcı yok.')
            : back()->with('success', "{$count} başarısız alıcı yeniden denenecek.");
    }

    /**
     * Alıcı listesini CSV olarak indirir.
     *
     * Ekrandaki süzgeç aynen uygulanıyor: "başarısızları ver" diyen biri
     * dosyada tüm listeyi bulmamalı. Satırlar akış hâlinde yazılıyor, tamamı
     * belleğe alınmıyor — paylaşımlı hosting'de on binlerce satır belleği
     * doldururdu.
     */
    public function exportRecipients(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('view', $campaign);

        $status = (string) $request->string('rstatus')->value();
        $search = (string) $request->string('rsearch')->trim()->value();

        $query = $campaign->recipients()
            ->when(
                CampaignRecipientStatus::tryFrom($status) !== null,
                fn ($q) => $q->where('status', $status),
            )
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%' . addcslashes($search, '%_\\') . '%';

                $q->where(function ($inner) use ($term): void {
                    $inner->where('email', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->orderBy('id');

        $filename = 'alicilar-' . $campaign->id . '-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            // Excel'in Türkçe karakterleri doğru okuması için BOM; olmadan
            // "Yılmaz" bozuk görünüyor.
            fwrite($handle, "\xEF\xBB\xBF");
            // Türkçe Excel varsayılan ayırıcı olarak noktalı virgül bekliyor.
            fputcsv($handle, ['Sıra', 'E-posta', 'Ad', 'Soyad', 'Durum', 'Deneme', 'Gönderim', 'Hata'], ';');

            $sira = 0;

            $query->chunk(500, function ($rows) use ($handle, &$sira): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        ++$sira,
                        $row->email,
                        $row->first_name,
                        $row->last_name,
                        $row->status->label(),
                        $row->attempts,
                        $row->sent_at?->format('d.m.Y H:i'),
                        $row->error,
                    ], ';');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

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
