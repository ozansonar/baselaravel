<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleReview;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GoogleReviewService
{
    private const FIELD_MASK = 'reviews.name,reviews.relativePublishTimeDescription,reviews.rating,reviews.text.text,reviews.text.languageCode,reviews.originalText.text,reviews.authorAttribution.displayName,reviews.authorAttribution.uri,reviews.authorAttribution.photoUri,reviews.publishTime';

    /**
     * Fetch reviews from Google Places API (New) and sync to database.
     * Makes multiple requests with different language codes to maximize unique reviews.
     *
     * @return int Number of new/updated reviews
     */
    public function fetchAndSync(): int
    {
        $apiKey = Setting::getValue('google_places_api_key');
        $placeId = Setting::getValue('google_places_place_id');

        if (empty($apiKey) || empty($placeId)) {
            throw new \RuntimeException('Google Places API Key ve Place ID ayarlardan yapılandırılmalıdır.');
        }

        try {
            $allReviews = [];

            // Fetch with different language codes to potentially get different review sets
            $languages = [null, 'tr', 'en'];

            foreach ($languages as $lang) {
                $reviews = $this->fetchReviewsFromApi($apiKey, $placeId, $lang);
                foreach ($reviews as $review) {
                    $reviewId = $review['name'] ?? null;
                    if ($reviewId && !isset($allReviews[$reviewId])) {
                        $allReviews[$reviewId] = $review;
                    }
                }
            }

            if (empty($allReviews)) {
                Log::info('No reviews found from Google Places API.');
                return 0;
            }

            $synced = 0;
            foreach ($allReviews as $review) {
                $this->syncReview($review);
                $synced++;
            }

            $this->clearCache();
            Log::info("Google reviews synced: {$synced} unique reviews processed.");

            return $synced;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Google Places API error.', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Google Places API bağlantı hatası: ' . $e->getMessage());
        }
    }

    /**
     * Fetch reviews from API with specific language code.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchReviewsFromApi(string $apiKey, string $placeId, ?string $languageCode = null): array
    {
        $queryParams = [];
        if ($languageCode !== null) {
            $queryParams['languageCode'] = $languageCode;
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->get("https://places.googleapis.com/v1/places/{$placeId}", $queryParams);

        if ($response->failed()) {
            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? $response->body();
            $status = $response->status();

            Log::error('Google Places API request failed.', [
                'status'   => $status,
                'language' => $languageCode,
                'body'     => $response->body(),
            ]);

            throw new \RuntimeException("Google Places API hatası ({$status}): {$errorMessage}");
        }

        $data = $response->json();

        return $data['reviews'] ?? [];
    }

    /**
     * Sync a single review from API response to database.
     *
     * @param array<string, mixed> $review
     */
    private function syncReview(array $review): bool
    {
        $authorAttribution = $review['authorAttribution'] ?? [];
        $textData = $review['originalText'] ?? $review['text'] ?? [];

        $reviewId = $review['name'] ?? null;
        $authorName = $authorAttribution['displayName'] ?? 'Anonim';
        $text = $textData['text'] ?? '';
        $rating = $review['rating'] ?? 5;
        $language = $textData['languageCode'] ?? 'tr';
        $relativeTime = $review['relativePublishTimeDescription'] ?? '';
        $publishTime = isset($review['publishTime']) ? \Carbon\Carbon::parse($review['publishTime']) : null;
        $photoUri = $authorAttribution['photoUri'] ?? null;
        $profileUri = $authorAttribution['uri'] ?? null;

        GoogleReview::updateOrCreate(
            ['google_review_id' => $reviewId],
            [
                'author_name'        => $authorName,
                'author_photo_url'   => $photoUri,
                'author_profile_url' => $profileUri,
                'text'               => $text,
                'rating'             => $rating,
                'language'           => $language,
                'relative_time'      => $relativeTime,
                'review_time'        => $publishTime,
            ],
        );

        return true;
    }

    /**
     * Get visible reviews for frontend display.
     *
     * @return Collection<int, GoogleReview>
     */
    public function getVisibleReviews(int $limit = 5): Collection
    {
        return Cache::remember('google_reviews.visible', 3600, fn () =>
            GoogleReview::visible()
                ->orderBy('sort_order')
                ->orderByDesc('review_time')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * Get average rating from visible reviews.
     *
     * @return array{average: float, count: int}
     */
    public function getStats(): array
    {
        return Cache::remember('google_reviews.stats', 3600, function (): array {
            $stats = GoogleReview::visible()
                ->selectRaw('COUNT(*) as count, COALESCE(AVG(rating), 0) as average')
                ->first();

            return [
                'average' => round((float) $stats->average, 1),
                'count'   => (int) $stats->count,
            ];
        });
    }

    /**
     * Get all reviews for admin panel.
     */
    public function allForAdmin(): Collection
    {
        return GoogleReview::withTrashed()
            ->orderByDesc('review_time')
            ->get();
    }

    /**
     * Toggle review visibility.
     */
    public function toggleVisibility(GoogleReview $review): GoogleReview
    {
        $review->update(['is_visible' => !$review->is_visible]);
        $this->clearCache();

        return $review->refresh();
    }

    private function clearCache(): void
    {
        Cache::forget('google_reviews.visible');
        Cache::forget('google_reviews.stats');
    }
}
