<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function edit(): View
    {
        $user = Auth::user();
        $user->load('roles');

        return view('admin.profile.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->safe()->only(['first_name', 'last_name', 'email', 'phone', 'bio', 'location']);

        // Kaydetmeden önce sorulmalı: sonrasında eski değer elde kalmıyor.
        $emailChanged = ($data['email'] ?? $user->email) !== $user->email;

        $removeAvatar = $request->boolean('remove_avatar');

        $this->userService->update(
            user: $user,
            data: $data,
            avatar: $request->file('avatar'),
            password: $request->validated('password'),
            removeAvatar: $removeAvatar,
        );

        return redirect()
            ->route('admin.profile.edit')
            ->with('success', $emailChanged
                ? 'Profil bilgileriniz güncellendi. E-posta adresiniz değiştiği için yeni adresinize bir doğrulama bağlantısı gönderildi.'
                : 'Profil bilgileriniz başarıyla güncellendi.');
    }
}
