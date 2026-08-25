<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Services\LanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    /**
     * Remember the language the visitor picked from the switcher.
     *
     * An unsupported code is ignored rather than rejected: the switcher is a
     * convenience, not somewhere to show an error page.
     */
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if ($this->languages->isSupported($code)) {
            $request->session()->put(SetLocale::SESSION_KEY, $code);
        }

        return back();
    }
}
