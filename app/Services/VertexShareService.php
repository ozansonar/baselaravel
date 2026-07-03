<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramMediaType;
use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use App\Models\VertexGeneration;
use Carbon\Carbon;

final class VertexShareService
{
    public function __construct(
        private readonly InstagramService $instagramService,
        private readonly FacebookPageService $facebookService,
    ) {}

    /**
     * @param  list<string>           $platforms    ['instagram', 'facebook']
     * @return array{success: bool, message: string, ig_permalink: ?string, fb_permalink: ?string, post_id: int, scheduled: bool}
     */
    public function share(
        VertexGeneration $generation,
        array $platforms,
        ?int $userId,
        InstagramMediaType $mediaType = InstagramMediaType::Image,
        ?Carbon $scheduledAt = null,
    ): array {
        $toInstagram = in_array('instagram', $platforms, true);
        $toFacebook  = in_array('facebook', $platforms, true);

        if ($mediaType === InstagramMediaType::Story && $toFacebook) {
            $toFacebook = false;
        }

        if (! $toInstagram && ! $toFacebook) {
            return [
                'success'      => false,
                'message'      => 'Story sadece Instagram\'da paylaşılabilir.',
                'ig_permalink' => null,
                'fb_permalink' => null,
                'post_id'      => 0,
                'scheduled'    => false,
            ];
        }

        $hashtags = $this->parseHashtags($generation->ig_hashtags);
        $isScheduled = $scheduledAt !== null && $scheduledAt->isFuture();

        $post = InstagramPost::create([
            'image_path'            => $generation->output_image_path,
            'caption'               => trim((string) ($generation->ig_caption ?? '')),
            'hashtags'              => $hashtags !== [] ? $hashtags : null,
            'media_type'            => $mediaType,
            'status'                => InstagramPostStatus::Scheduled,
            'scheduled_at'          => $isScheduled ? $scheduledAt : now(),
            'publish_to_facebook'   => $toFacebook,
            'created_by'            => $userId,
            'vertex_generation_id'  => $generation->id,
        ]);

        if ($isScheduled) {
            return [
                'success'      => true,
                'message'      => $scheduledAt->format('d.m.Y H:i') . ' tarihine planlandı.',
                'ig_permalink' => null,
                'fb_permalink' => null,
                'post_id'      => $post->id,
                'scheduled'    => true,
            ];
        }

        $messages = [];
        $igOk = true;
        $fbOk = true;

        if ($toInstagram) {
            $igResult = $this->instagramService->publish($post);
            $igOk = $igResult['success'];
            $messages[] = 'Instagram: ' . $igResult['message'];
        }

        if ($toFacebook) {
            $post->refresh();
            $fbResult = $this->facebookService->publish($post);
            $fbOk = $fbResult['success'];
            $messages[] = 'Facebook: ' . $fbResult['message'];
        }

        if (! $toInstagram) {
            $post->update([
                'status'       => $fbOk
                    ? InstagramPostStatus::Published
                    : InstagramPostStatus::Failed,
                'scheduled_at' => null,
                'published_at' => $fbOk ? now() : null,
            ]);
        }

        $post->refresh();

        $allOk = ($toInstagram ? $igOk : true) && ($toFacebook ? $fbOk : true);

        return [
            'success'      => $allOk,
            'message'      => implode(' | ', $messages),
            'ig_permalink' => $post->permalink,
            'fb_permalink' => $post->fb_permalink,
            'post_id'      => $post->id,
            'scheduled'    => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function parseHashtags(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        preg_match_all('/#\S+/', $raw, $matches);

        return array_values(array_filter(
            array_map('trim', $matches[0] ?? []),
            static fn (string $t): bool => $t !== '' && $t !== '#',
        ));
    }
}
