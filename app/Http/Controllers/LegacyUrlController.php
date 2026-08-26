<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Addresses from before the language prefix (/blog, /hakkimizda).
 *
 * They are moved permanently into the default language instead of 404ing, so
 * the links and the ranking they already earned carry over.
 */
final class LegacyUrlController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $path = trim($request->path(), '/');

        abort_unless($request->isMethodSafe(), 404);

        // A missing file, API call or admin page was never a front page, so it
        // is genuinely gone rather than moved.
        if ($path === ''
            || str_starts_with($path, 'admin')
            || str_starts_with($path, 'api/')
            || str_contains(basename($path), '.')
        ) {
            abort(404);
        }

        $target = url($this->languages->defaultCode() . '/' . $path);
        $query = $request->getQueryString();

        return redirect()->to($query === null ? $target : $target . '?' . $query, 301);
    }
}
