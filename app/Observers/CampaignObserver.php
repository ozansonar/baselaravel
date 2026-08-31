<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Campaign;

/**
 * Cascade lives here rather than in the foreign key, so soft deleting a
 * campaign hides its recipient rows too and restoring brings them back.
 */
final class CampaignObserver
{
    public function deleting(Campaign $campaign): void
    {
        if ($campaign->isForceDeleting()) {
            $campaign->recipients()->withTrashed()->forceDelete();
            $campaign->attachments()->withTrashed()->forceDelete();

            return;
        }

        $campaign->recipients()->delete();
        $campaign->attachments()->delete();
    }

    public function restoring(Campaign $campaign): void
    {
        $campaign->recipients()->onlyTrashed()->restore();
        $campaign->attachments()->onlyTrashed()->restore();
    }
}
