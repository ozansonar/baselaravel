<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PushAudience;
use App\Enums\PushNotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePushNotificationRequest;
use App\Models\PushNotification;
use App\Models\Role;
use App\Models\User;
use App\Services\PushBroadcastService;
use App\Services\PushNotificationDispatcher;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

final class PushNotificationController extends Controller
{
    private const PER_PAGE = [15, 25, 50, 100];

    /**
     * Listede seçilebilecek sıralamalar. İstekten gelen değer bu kümeyle
     * sınırlı; serbest bırakılsaydı sütun adı doğrudan sorguya girerdi.
     *
     * @var array<string, string>
     */
    private const SORT_OPTIONS = [
        'recent'  => 'En yeni',
        'oldest'  => 'En eski',
        'title'   => 'Başlığa göre (A-Z)',
        'devices' => 'En çok cihaz',
        'sent'    => 'En çok ulaşan',
    ];

    public function __construct(
        private readonly PushBroadcastService $broadcasts,
        private readonly PushNotificationDispatcher $dispatcher,
        private readonly PushNotificationService $push,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PushNotification::class);

        $filters = [
            'search'   => trim((string) $request->query('search', '')),
            'status'   => (string) $request->query('status', ''),
            'audience' => (string) $request->query('audience', ''),
            'from'     => (string) $request->query('from', ''),
            'to'       => (string) $request->query('to', ''),
            'sort'     => (string) $request->query('sort', ''),
        ];

        $perPage = (int) $request->query('per_page', (string) self::PER_PAGE[0]);

        if (! in_array($perPage, self::PER_PAGE, true)) {
            $perPage = self::PER_PAGE[0];
        }

        $notifications = $this->broadcasts->query($filters)
            ->paginate($perPage)
            ->withQueryString();

        // Durum sekmelerinin sayıları, durum süzgeci dışındaki süzgeçlere göre
        // hesaplanıyor: sekme değiştirmek diğer süzgeçleri silmemeli.
        $base = array_merge($filters, ['status' => '']);

        $statusCounts = ['' => $this->broadcasts->query($base)->count()];

        foreach (PushNotificationStatus::cases() as $case) {
            $statusCounts[$case->value] = $this->broadcasts
                ->query(array_merge($base, ['status' => $case->value]))
                ->count();
        }

        return view('admin.push-notifications.index', [
            'notifications' => $notifications,
            'filters'       => $filters,
            'stats'         => $this->broadcasts->stats(),
            'statusCounts'  => $statusCounts,
            'audiences'     => PushAudience::cases(),
            'sortOptions'   => self::SORT_OPTIONS,
            'perPage'       => $perPage,
            'perPageList'   => self::PER_PAGE,
            'devices'       => $this->broadcasts->registeredDevices(),
            'configured'    => $this->push->isConfigured(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PushNotification::class);

        return view('admin.push-notifications.create', [
            'audiences'  => PushAudience::cases(),
            'roles'      => Role::orderBy('name')->get(['id', 'name']),
            'configured' => $this->push->isConfigured(),
            'devices'    => $this->broadcasts->registeredDevices(),
            'reach'      => $this->dispatcher->audienceSize(PushAudience::All),
            'interval'   => PushNotificationDispatcher::RUN_INTERVAL_MINUTES,
            // Doğrulama hatasından sonra form geri geldiğinde seçilmiş
            // kullanıcının adı da geri gelmeli: yalnız kimliği taşımak,
            // kullanıcıya "kimi seçmiştim" sorusunu sordurur.
            'selectedUser' => $this->rememberedUser(),
        ]);
    }

    /**
     * Formdan geri dönen seçili kullanıcı — yalnız "tek kullanıcı" hedefinde.
     */
    private function rememberedUser(): ?User
    {
        if ((string) old('audience') !== PushAudience::User->value) {
            return null;
        }

        $id = old('audience_id');

        if ($id === null || $id === '') {
            return null;
        }

        return User::find((int) $id);
    }

    public function store(StorePushNotificationRequest $request): RedirectResponse
    {
        $this->authorize('create', PushNotification::class);

        $notification = $this->broadcasts->create($request->notificationData());

        return redirect()
            ->route('admin.push-notifications.show', $notification)
            ->with('success', sprintf(
                'Duyuru sıraya alındı, en geç %d dakika içinde gönderilmeye başlanacak.',
                PushNotificationDispatcher::RUN_INTERVAL_MINUTES,
            ));
    }

    public function show(PushNotification $pushNotification): View
    {
        $this->authorize('view', $pushNotification);

        return view('admin.push-notifications.show', [
            'notification' => $pushNotification->load('sender'),
            'interval'     => PushNotificationDispatcher::RUN_INTERVAL_MINUTES,
            'configured'   => $this->push->isConfigured(),
        ]);
    }

    /**
     * Formdaki hedef seçimi değiştiğinde "kaç cihaza gidecek" sorusunun cevabı.
     *
     * Sayı sunucudan alınıyor: hedefin cihaz sayısı, kullanıcının bildirim
     * tercihine ve hesabının açık olmasına bağlı ve bunlar ekranda yok.
     */
    public function audienceSize(Request $request): JsonResponse
    {
        $this->authorize('create', PushNotification::class);

        $audience = PushAudience::tryFrom((string) $request->input('audience'));

        if ($audience === null) {
            return response()->json(['message' => 'Geçersiz hedef kitle.'], 422);
        }

        $id = $request->input('audience_id');
        $id = ($id === null || $id === '') ? null : (int) $id;

        if ($audience->needsTarget() && $id === null) {
            return response()->json(['count' => 0, 'pending' => true]);
        }

        return response()->json([
            'count'   => $this->dispatcher->audienceSize($audience, $id),
            'pending' => false,
        ]);
    }

    /**
     * Kullanıcı arama — "tek kullanıcı" hedefi seçildiğinde.
     *
     * Tam liste basılmıyor: binlerce kullanıcılı bir kurulumda seçim kutusu
     * sayfanın kendisinden büyük olurdu.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $this->authorize('create', PushNotification::class);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = \App\Support\LikeSearch::term($term);

        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($like): void {
                $query->whereRaw(\App\Support\LikeSearch::clause('first_name'), [$like])
                    ->orWhereRaw(\App\Support\LikeSearch::clause('last_name'), [$like])
                    ->orWhereRaw(\App\Support\LikeSearch::clause('email'), [$like]);
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json([
            'results' => $users->map(static fn (User $user): array => [
                'id'    => $user->getKey(),
                'name'  => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
            ])->all(),
        ]);
    }

    public function cancel(PushNotification $pushNotification): RedirectResponse
    {
        $this->authorize('cancel', $pushNotification);

        try {
            $this->broadcasts->cancel($pushNotification);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Duyuru iptal edildi, cihazlara gönderilmeyecek.');
    }

    public function destroy(PushNotification $pushNotification): RedirectResponse
    {
        $this->authorize('delete', $pushNotification);

        $pushNotification->delete();

        return redirect()
            ->route('admin.push-notifications.index')
            ->with('success', 'Duyuru kaydı silindi. Gönderilmiş bildirimler cihazlarda kalır.');
    }

    public function restore(int $pushNotification): RedirectResponse
    {
        $model = PushNotification::withTrashed()->findOrFail($pushNotification);

        $this->authorize('restore', $model);

        $model->restore();

        return back()->with('success', 'Duyuru kaydı geri yüklendi.');
    }
}
