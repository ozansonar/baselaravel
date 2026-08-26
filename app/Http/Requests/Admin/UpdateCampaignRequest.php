<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Editing uses the same rules as creating; the only difference is that an
 * import may keep the list it already stored instead of re-uploading, which
 * StoreCampaignRequest already allows for.
 */
class UpdateCampaignRequest extends StoreCampaignRequest
{
}
