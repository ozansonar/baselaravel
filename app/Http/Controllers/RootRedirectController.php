<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bare root carries no language, so it forwards to the visitor's own.
 *
 * The redirect is temporary on purpose: the address itself is permanent — it is
 * what hreflang advertises as x-default — only its destination depends on who
 * is asking.
 */
final class RootRedirectController extends Controller
{
    public function __construct(
        private readonly LocaleResolver $resolver,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->to(route('home', ['locale' => $this->resolver->fromRequest($request)]), 302);
    }
}
