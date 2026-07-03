<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\YouTubeVideo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class YouTubeService
{
    private const CACHE_PREFIX = 'youtube_videos';
    private const CACHE_TTL = 3600;

    /**
     * Fetch videos from YouTube Data API v3 and sync to database.
     *
     * @return int Number of new/updated videos
     */
    public function fetchAndSync(): int
    {
        $apiKey = Setting::getValue('youtube_api_key');
        $channelId = Setting::getValue('youtube_channel_id');

        if (empty($apiKey) || empty($channelId)) {
            throw new \RuntimeException('YouTube API Key ve Channel ID ayarlardan yapılandırılmalıdır.');
        }

        try {
            // Step 1: Get uploads playlist ID from channel
            $channelResponse = Http::get('https://www.googleapis.com/youtube/v3/channels', [
                'key'  => $apiKey,
                'id'   => $channelId,
                'part' => 'contentDetails',
            ]);

            if ($channelResponse->failed()) {
                Log::error('YouTube Channels API request failed.', [
                    'status' => $channelResponse->status(),
                    'body'   => $channelResponse->body(),
                ]);
                return 0;
            }

            $channelData = $channelResponse->json();
            $uploadsPlaylistId = $channelData['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;

            if (!$uploadsPlaylistId) {
                Log::error('Could not find uploads playlist for YouTube channel.');
                return 0;
            }

            // Step 2: Get videos from uploads playlist (max 50)
            $playlistResponse = Http::get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'key'        => $apiKey,
                'playlistId' => $uploadsPlaylistId,
                'part'       => 'snippet',
                'maxResults' => 50,
            ]);

            if ($playlistResponse->failed()) {
                Log::error('YouTube PlaylistItems API request failed.', [
                    'status' => $playlistResponse->status(),
                    'body'   => $playlistResponse->body(),
                ]);
                return 0;
            }

            $playlistData = $playlistResponse->json();
            $items = $playlistData['items'] ?? [];

            if (empty($items)) {
                Log::info('No videos found in YouTube channel.');
                return 0;
            }

            // Step 3: Get video details (duration, view count, like count)
            $videoIds = array_map(
                fn (array $item): string => $item['snippet']['resourceId']['videoId'] ?? '',
                $items,
            );
            $videoIds = array_filter($videoIds);

            $detailsResponse = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'key'  => $apiKey,
                'id'   => implode(',', $videoIds),
                'part' => 'contentDetails,statistics',
            ]);

            $detailsMap = [];
            if ($detailsResponse->successful()) {
                foreach ($detailsResponse->json('items') ?? [] as $detail) {
                    $detailsMap[$detail['id']] = [
                        'duration'   => $detail['contentDetails']['duration'] ?? null,
                        'view_count' => (int) ($detail['statistics']['viewCount'] ?? 0),
                        'like_count' => (int) ($detail['statistics']['likeCount'] ?? 0),
                    ];
                }
            }

            // Step 4: Sync to database
            $synced = 0;
            foreach ($items as $item) {
                $snippet = $item['snippet'] ?? [];
                $videoId = $snippet['resourceId']['videoId'] ?? null;

                if (!$videoId) {
                    continue;
                }

                $thumbnails = $snippet['thumbnails'] ?? [];
                $thumbnailUrl = $thumbnails['maxres']['url']
                    ?? $thumbnails['high']['url']
                    ?? $thumbnails['medium']['url']
                    ?? $thumbnails['default']['url']
                    ?? null;

                $details = $detailsMap[$videoId] ?? [];

                YouTubeVideo::updateOrCreate(
                    ['video_id' => $videoId],
                    [
                        'title'         => $snippet['title'] ?? '',
                        'description'   => $snippet['description'] ?? '',
                        'thumbnail_url' => $thumbnailUrl,
                        'published_at'  => isset($snippet['publishedAt'])
                            ? \Carbon\Carbon::parse($snippet['publishedAt'])
                            : null,
                        'duration'      => $details['duration'] ?? null,
                        'view_count'    => $details['view_count'] ?? 0,
                        'like_count'    => $details['like_count'] ?? 0,
                    ],
                );

                $synced++;
            }

            $this->clearCache();
            Log::info("YouTube videos synced: {$synced} videos processed.");

            return $synced;
        } catch (\Throwable $e) {
            Log::error('YouTube API error.', [
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get visible videos for frontend display.
     *
     * @return Collection<int, YouTubeVideo>
     */
    public function getVisibleVideos(int $limit = 6): Collection
    {
        return Cache::remember(self::CACHE_PREFIX . '.visible', self::CACHE_TTL, fn () =>
            YouTubeVideo::visible()
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * Get stats for frontend/admin.
     *
     * @return array{count: int, total_views: int}
     */
    public function getStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX . '.stats', self::CACHE_TTL, function (): array {
            $stats = YouTubeVideo::visible()
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(view_count), 0) as total_views')
                ->first();

            return [
                'count'       => (int) $stats->count,
                'total_views' => (int) $stats->total_views,
            ];
        });
    }

    /**
     * Get all videos for admin panel.
     *
     * @return Collection<int, YouTubeVideo>
     */
    public function allForAdmin(): Collection
    {
        return YouTubeVideo::withTrashed()
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * Toggle video visibility.
     */
    public function toggleVisibility(YouTubeVideo $video): YouTubeVideo
    {
        $video->update(['is_visible' => !$video->is_visible]);
        $this->clearCache();

        return $video->refresh();
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . '.visible');
        Cache::forget(self::CACHE_PREFIX . '.stats');
    }
}
