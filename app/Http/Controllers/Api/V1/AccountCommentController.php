<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccountCommentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\BlogCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Yorumlarım".
 *
 * Yorum gönderilebiliyordu ama kişi kendi yorumlarını göremiyordu: onay
 * bekleyen bir yorumun akıbetini öğrenmenin tek yolu yazıyı tekrar tekrar
 * açmaktı.
 */
final class AccountCommentController extends Controller
{
    public function __construct(
        private readonly BlogCommentService $comments,
    ) {}

    /**
     * GET /api/v1/account/comments
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = min(
            max((int) $request->query('per_page', (string) config('api.pagination.per_page', 15)), 1),
            (int) config('api.pagination.max_per_page', 100),
        );

        return ApiResponse::paginated(
            $this->comments->paginateForUser($user, $perPage),
            AccountCommentResource::class,
        );
    }

    /**
     * DELETE /api/v1/account/comments/{comment}
     */
    public function destroy(Request $request, int $comment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->comments->deleteOwn($user, $comment)) {
            return ApiResponse::error(__('api.common.not_found'), status: 404);
        }

        return ApiResponse::success(null, __('api.comments.deleted'));
    }
}
