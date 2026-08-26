<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Services\LanguageService;
use App\Services\LocalizedUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
        private readonly LocalizedUrlService $localizedUrls,
    ) {}

    /**
     * Switch language and land on the same page in the new one.
     *
     * The switcher in the layout links straight to the translated URL; this
     * route is what older links and the fallback path go through, so it has to
     * do the same mapping itself.
     *
     * An unsupported code is ignored rather than rejected: the switcher is a
     * convenience, not somewhere to show an error page.
     */
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if (! $this->languages->isSupported($code)) {
            return back();
        }

        $request->session()->put(SetLocale::SESSION_KEY, $code);

        $target = $this->localizedUrls->fromUrl($request->headers->get('referer'), $code);

        return redirect()->to($target ?? route('home', ['locale' => $code]));
    }
}
