<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/aktivite-loglari — Audit Trail / aktivite log görüntüleyici.
 */
final class AuditLogController extends Controller
{
    /**
     * Listede gösterilebilecek kayıt sayıları; istekten gelen değer bu kümeyle
     * sınırlı, aksi hâlde tek istekle tüm tablo çekilebilirdi.
     */
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function __construct(
        private readonly AuditLogService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = [
            'event'   => $request->string('event')->value(),
            'user_id' => $request->string('user_id')->value(),
            'model'   => $request->string('model')->value(),
            'ip'      => $request->string('ip')->value(),
            'from'    => $request->string('from')->value(),
            'to'      => $request->string('to')->value(),
            'q'       => $request->string('q')->trim()->value(),
        ];

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 50;

        return view('admin.audit-logs.index', [
            'logs'           => $this->service->paginate($filters, $perPage),
            'stats'          => $this->service->stats(),
            'eventCounts'    => $this->service->eventCounts(),
            'modelOptions'   => $this->service->modelOptions(),
            'ipOptions'      => $this->service->ipOptions(),
            'topActors'      => $this->service->topActors(),
            'users'          => User::query()->select('id', 'first_name', 'last_name', 'email')->orderBy('first_name')->get(),
            'eventTypes'     => AuditEvent::cases(),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'retentionDays'  => AuditLogService::RETENTION_DAYS,
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        $auditLog->load('user');

        return view('admin.audit-logs.show', ['log' => $auditLog]);
    }
}
