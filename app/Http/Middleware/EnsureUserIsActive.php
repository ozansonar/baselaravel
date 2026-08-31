<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account must not keep the session it already has.
 *
 * is_active was read in one place only — AuthService::login() — so it decided
 * who could start a session and nothing more. Someone already signed in stayed
 * signed in after being deactivated, for the rest of the session lifetime and
 * far longer if they had ticked "remember me". The administrator who pressed
 * the button had every reason to believe the account was closed.
 *
 * Permissions never had this problem: AdminMiddleware asks the database on
 * every request, so taking a role away lands immediately. is_active was the
 * one flag nothing rechecked.
 *
 * Runs in the web group, which is what admin routes are built on top of, so
 * the panel and the front-end are covered by the same check.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->is_active) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('site.login.deactivated')], 403);
        }

        // The login screen reads this through @error('email'); a flash message
        // would be dropped by layouts that do not render one.
        return redirect()
            ->route('login')
            ->withErrors(['email' => __('site.login.deactivated')]);
    }
}
