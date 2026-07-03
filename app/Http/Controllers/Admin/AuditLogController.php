<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/aktivite-loglari — Audit Trail / aktivite log görüntüleyici.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user:id,first_name,last_name,email');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('model')) {
            // Kullanıcı input'undaki LIKE wildcard'ları (%, _) escape et — yanlış eşleşmeyi önle
            $modelName = addcslashes($request->string('model')->toString(), '%_\\');
            $query->where('auditable_type', 'like', '%\\' . $modelName);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('admin.audit-logs.index', [
            'logs'       => $logs,
            'users'      => User::query()->select('id', 'first_name', 'last_name', 'email')->orderBy('id')->get(),
            'eventTypes' => [
                AuditLog::EVENT_CREATED => 'Oluşturuldu',
                AuditLog::EVENT_UPDATED => 'Güncellendi',
                AuditLog::EVENT_DELETED => 'Silindi',
                AuditLog::EVENT_CUSTOM  => 'Özel',
            ],
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user');
        return view('admin.audit-logs.show', ['log' => $auditLog]);
    }
}
