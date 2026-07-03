<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\AddressRequest;
use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Models\Address;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    /**
     * Account dashboard - order summary + profile overview.
     */
    public function dashboard(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $dashboardData = $this->accountService->getDashboardData($user);

        return view('account.dashboard', [
            'user'           => $user,
            'recentOrders'   => $dashboardData['recentOrders'],
            'orderCounts'    => $dashboardData['orderCounts'],
            'defaultAddress' => $dashboardData['defaultAddress'],
        ]);
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
            ->with('success', 'Profiliniz başarıyla güncellendi.');
    }

    /**
     * List user orders.
     */
    public function orders(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $orders = $this->accountService->getOrders($user);

        return view('account.orders.index', compact('orders'));
    }

    /**
     * Show order detail.
     */
    public function orderShow(int $id): View
    {
        /** @var User $user */
        $user = auth()->user();

        $order = $this->accountService->getOrder($user, $id);

        if (!$order) {
            abort(404);
        }

        return view('account.orders.show', compact('order'));
    }

    /**
     * List user addresses.
     */
    public function addresses(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $addresses = $this->accountService->getAddresses($user);

        return view('account.addresses.index', compact('addresses'));
    }

    /**
     * Show address create form.
     */
    public function addressCreate(): View
    {
        return view('account.addresses.create');
    }

    /**
     * Store new address.
     */
    public function addressStore(AddressRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->accountService->createAddress($user, $request->validated());

        return redirect()->route('account.addresses')
            ->with('success', 'Adres başarıyla eklendi.');
    }

    /**
     * Show address edit form.
     */
    public function addressEdit(Address $address): View
    {
        /** @var User $user */
        $user = auth()->user();

        if ($address->user_id !== $user->id) {
            abort(403);
        }

        return view('account.addresses.edit', compact('address'));
    }

    /**
     * Update address.
     */
    public function addressUpdate(AddressRequest $request, Address $address): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $this->accountService->updateAddress($user, $address, $request->validated());

        return redirect()->route('account.addresses')
            ->with('success', 'Adres başarıyla güncellendi.');
    }

    /**
     * Delete address.
     */
    public function addressDestroy(Address $address): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $this->accountService->deleteAddress($user, $address);

        return redirect()->route('account.addresses')
            ->with('success', 'Adres başarıyla silindi.');
    }

    /**
     * Set address as default (AJAX).
     */
    public function addressSetDefault(Request $request, Address $address): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $this->accountService->setDefaultAddress($user, $address);

        return redirect()->route('account.addresses')
            ->with('success', 'Varsayılan adres güncellendi.');
    }
}
