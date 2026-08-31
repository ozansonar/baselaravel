<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Anyone holding at least one permission belongs in the panel; which
        // screens they then see is decided per screen by the policies.
        $hasAccess = $user->roles()
            ->whereHas('permissions')
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Bu alana erişim yetkiniz yok.');
        }

        return $next($request);
    }
}
