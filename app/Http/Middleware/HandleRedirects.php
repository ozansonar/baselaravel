<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CacheKeys;
use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class HandleRedirects
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/' . ltrim($request->path(), '/');

        $redirects = Cache::remember(CacheKeys::REDIRECTS_ACTIVE, 3600, fn (): array =>
            Redirect::where('is_active', true)
                ->get(['old_url', 'new_url', 'status_code'])
                ->keyBy('old_url')
                ->map(fn ($r) => ['new_url' => $r->new_url, 'status_code' => $r->status_code?->value])
                ->toArray()
        );

        if (isset($redirects[$path])) {
            Redirect::where('old_url', $path)->increment('hit_count', 1, ['last_hit_at' => now()]);

            $code = $redirects[$path]['status_code'];

            if ($code === 404) {
                abort(404);
            }

            if ($code === 410) {
                abort(410);
            }

            return redirect($redirects[$path]['new_url'], $code);
        }

        return $next($request);
    }
}
