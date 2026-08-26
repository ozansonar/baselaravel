<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignAttachment>
 */
class CampaignAttachmentFactory extends Factory
{
    protected $model = CampaignAttachment::class;

    public function definition(): array
    {
        return [
            'campaign_id'   => Campaign::factory(),
            'path'          => 'campaigns/' . $this->faker->slug(2) . '.pdf',
            'original_name' => $this->faker->words(2, true) . '.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => $this->faker->numberBetween(1024, 5_242_880),
        ];
    }
}
