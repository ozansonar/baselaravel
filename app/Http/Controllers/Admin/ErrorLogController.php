<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Services\ErrorLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/hata-kayitlari — sunucu hatalarının listesi ve detayı.
 *
 * Ekran, `storage/logs/laravel.log` dosyasının panelden okunabilir hâli değil:
 * dosya her satırı ayrı yazar, bu liste aynı hatayı tek satırda toplayıp kaç
 * kez olduğunu gösterir. Aradaki fark, "yüz bin satırlık log" ile "üç açık
 * hatam var" arasındaki fark.
 */
final class ErrorLogController extends Controller
{
    /**
     * Sayfa başına kayıt. İstekten gelen değer bu kümeyle sınırlı — aksi hâlde
     * tek istekle tüm tablo (yığın izleriyle birlikte) çekilebilirdi.
     */
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function __construct(
        private readonly ErrorLogService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ErrorLog::class);

        $filters = [
            // Varsayılan sekme "açık": ekranı açan kişinin sorusu "şu an neyim
            // bozuk", "geçmişte ne bozulmuştu" değil.
            'status'    => $request->has('status') ? $request->string('status')->value() : 'open',
            'exception' => $request->string('exception')->value(),
            'source'    => $request->string('source')->value(),
            'from'      => $request->string('from')->value(),
            'to'        => $request->string('to')->value(),
            'q'         => $request->string('q')->trim()->value(),
        ];

        $perPage = (int) $request->input('per_page', 25);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;

        return view('admin.error-logs.index', [
            'logs'             => $this->service->paginate($filters, $perPage),
            'stats'            => $this->service->stats(),
            'exceptionOptions' => $this->service->exceptionOptions(),
            'topRepeating'     => $this->service->topRepeating(),
            'filters'          => $filters,
            'perPage'          => $perPage,
            'perPageOptions'   => self::PER_PAGE_OPTIONS,
            'retentionDays'    => ErrorLogService::RETENTION_DAYS,
            'throttleMinutes'  => \App\Services\ExceptionNotifier::THROTTLE_MINUTES,
        ]);
    }

    public function show(ErrorLog $errorLog): View
    {
        $this->authorize('view', $errorLog);

        $errorLog->load(['user', 'resolver']);

        return view('admin.error-logs.show', [
            'log'           => $errorLog,
            'retentionDays' => ErrorLogService::RETENTION_DAYS,
        ]);
    }

    public function resolve(ErrorLog $errorLog): RedirectResponse
    {
        $this->authorize('update', $errorLog);

        $this->service->resolve($errorLog, (int) auth()->id());

        return back()->with(
            'success',
            'Hata çözüldü olarak işaretlendi. Aynı hata yeniden oluşursa işaret kendiliğinden kalkar.',
        );
    }

    public function reopen(ErrorLog $errorLog): RedirectResponse
    {
        $this->authorize('update', $errorLog);

        $this->service->reopen($errorLog);

        return back()->with('success', 'Hata yeniden açıldı.');
    }

    public function destroy(ErrorLog $errorLog): RedirectResponse
    {
        $this->authorize('delete', $errorLog);

        $errorLog->delete();

        return redirect()
            ->route('admin.error-logs.index')
            ->with('success', 'Hata kaydı silindi. Aynı hata yeniden oluşursa listede yeniden görünür.');
    }

    /**
     * Çözülmüş kayıtları topluca siler.
     */
    public function purge(): RedirectResponse
    {
        $this->authorize('deleteAny', ErrorLog::class);

        $count = $this->service->purgeResolved();

        return redirect()
            ->route('admin.error-logs.index')
            ->with('success', $count > 0
                ? "Çözülmüş {$count} kayıt silindi."
                : 'Silinecek çözülmüş kayıt yok.');
    }
}
