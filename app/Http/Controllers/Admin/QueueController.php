<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QueueMonitorService;
use App\Services\QueueRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class QueueController extends Controller
{
    /**
     * Listede gösterilebilecek kayıt sayıları; istekten gelen değer bu kümeyle
     * sınırlı, aksi hâlde tek istekle tüm tablo çekilebilirdi.
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function __construct(
        private readonly QueueMonitorService $queue,
        private readonly QueueRunner $runner,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('view-queue');

        $perPage = (int) $request->input('per_page', 25);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;

        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'queue'  => $request->string('queue')->value(),
        ];

        return view('admin.queue.index', [
            'jobs'           => $this->queue->paginate($perPage, $filters),
            'stats'          => $this->queue->stats(),
            'isStuck'        => $this->queue->isStuck(),
            'queueOptions'   => $this->queue->queueOptions(),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Tek işin tam hata metni — ayrıntı penceresi bunu çekiyor.
     */
    public function show(string $uuid): JsonResponse
    {
        $this->authorize('view-queue');

        $job = $this->queue->find($uuid);

        if ($job === null) {
            return response()->json(['success' => false, 'message' => 'Kayıt bulunamadı.'], 404);
        }

        return response()->json([
            'success'   => true,
            'job'       => $job['job'],
            'queue'     => $job['queue'],
            'uuid'      => $job['uuid'],
            'failed_at' => $job['failed_at']->format('d.m.Y H:i:s'),
            'exception' => $job['exception'],
        ]);
    }

    /**
     * Kuyruğu şimdi işle.
     *
     * Cron dakikada bir çalışıyor; beklemek yerine buradan tetiklenebiliyor.
     * Sınırlar dar tutuldu: web isteği bir cron dakikası kadar bekleyemez.
     */
    public function run(): RedirectResponse
    {
        $this->authorize('manage-queue');

        $result = $this->runner->drain(maxJobs: 25, maxSeconds: 15);

        return back()->with('success', sprintf(
            '%d iş işlendi, %d iş başarısız oldu, kuyrukta %d iş kaldı.',
            $result['processed'],
            $result['failed'],
            $result['remaining'],
        ));
    }

    public function retry(string $uuid): RedirectResponse
    {
        $this->authorize('manage-queue');

        return $this->queue->retry($uuid)
            ? back()->with('success', 'İş yeniden kuyruğa alındı.')
            : back()->with('error', 'Kayıt bulunamadı.');
    }

    public function destroy(string $uuid): RedirectResponse
    {
        $this->authorize('manage-queue');

        return $this->queue->forget($uuid)
            ? back()->with('success', 'Kayıt silindi.')
            : back()->with('error', 'Kayıt bulunamadı.');
    }

    public function flush(): RedirectResponse
    {
        $this->authorize('manage-queue');

        $count = $this->queue->flush();

        return back()->with('success', $count === 0
            ? 'Silinecek kayıt yoktu.'
            : "{$count} kayıt silindi.");
    }
}
