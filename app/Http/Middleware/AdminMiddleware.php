<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Which roles reach the panel is decided by UserRole::hasAdminAccess(),
        // so the list is not repeated here.
        $hasAccess = $user->hasAnyRole(UserRole::adminPanelSlugs());

        if (!$hasAccess) {
            abort(403, 'Bu alana erişim yetkiniz yok.');
        }

        return $next($request);
    }
}
