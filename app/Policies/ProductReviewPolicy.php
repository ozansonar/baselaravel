<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductReview;
use App\Models\User;

final class ProductReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function view(User $user, ProductReview $review): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function approve(User $user, ProductReview $review): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'moderator']);
    }

    public function delete(User $user, ProductReview $review): bool
    {
        return $user->hasRole('admin');
    }
}
