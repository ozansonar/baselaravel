<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final class AccountService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * Update user profile.
     *
     * @param array{first_name: string, last_name: string, email: string, phone?: string|null, password?: string|null} $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? $user->phone,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $user->update($updateData);

        $user->refresh();

        return $user;
    }

    /**
     * Handle avatar upload: upload new, delete old, update user.
     */
    public function handleAvatarUpload(User $user, UploadedFile $file, string $name): void
    {
        $oldAvatar = $user->avatar;

        $avatarPath = $this->uploadService->uploadImage(
            $file,
            'avatars',
            $name,
            ['thumb', 'sm'],
        );

        if ($oldAvatar) {
            $this->uploadService->deleteImage($oldAvatar);
        }

        $user->update(['avatar' => $avatarPath]);
    }

    /**
     * Remove user avatar.
     */
    public function removeAvatar(User $user): void
    {
        if ($user->avatar) {
            $this->uploadService->deleteImage($user->avatar);
        }

        $user->update(['avatar' => null]);
    }
}
