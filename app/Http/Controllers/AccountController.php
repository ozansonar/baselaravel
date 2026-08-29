<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    /**
     * Account dashboard - profile overview.
     */
    public function dashboard(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.dashboard', compact('user'));
    }

    /**
     * Show profile edit form.
     */
    public function profile(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.profile', compact('user'));
    }

    /**
     * Update profile.
     */
    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $this->accountService->handleAvatarUpload(
                $user,
                $request->file('avatar'),
                $data['first_name'] . '-' . $data['last_name'],
            );
        }

        if ($request->boolean('remove_avatar')) {
            $this->accountService->removeAvatar($user);
        }

        $this->accountService->updateProfile($user, $data);

        return redirect()->route('account.profile')
            ->with('success', __('site.account.profile_updated'));
    }
}
