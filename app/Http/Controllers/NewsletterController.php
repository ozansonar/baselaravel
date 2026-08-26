<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NewsletterController extends Controller
{
    public function __construct(
        private readonly SubscriberService $subscribers,
    ) {}

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:191'],
            'name'  => ['nullable', 'string', 'max:191'],
        ]);

        $this->subscribers->subscribe(
            $validated['email'],
            $validated['name'] ?? null,
            app()->getLocale(),
            'form',
        );

        return response()->json([
            'success' => true,
            'message' => __('site.newsletter.subscribed'),
        ]);
    }

    /**
     * Reached from the footer link in every campaign mail. It is deliberately a
     * plain GET with no login: someone who wants out must not have to remember
     * a password to get out.
     */
    public function unsubscribe(string $token): View
    {
        $subscriber = $this->subscribers->unsubscribeByToken($token);

        return view('newsletter.unsubscribe', [
            'success' => $subscriber !== null,
            'email'   => $subscriber?->email ?? '',
        ]);
    }
}
