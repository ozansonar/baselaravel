<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EmailVerificationController extends Controller
{
    /**
     * Landing page users are sent to while their address is unverified.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()?->hasVerifiedEmail()
            ? redirect()->route('account.dashboard')
            : view('auth.verify-email');
    }

    /**
     * Target of the signed link in the verification mail.
     *
     * EmailVerificationRequest checks the signature, the id and the e-mail
     * hash before this method runs.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard')
                ->with('info', __('site.verify.already'));
        }

        $request->fulfill();

        return redirect()->route('account.dashboard')
            ->with('success', __('site.verify.verified'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', __('site.verify.link_sent'));
    }
}
