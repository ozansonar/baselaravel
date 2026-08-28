<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Yorumlar listesinde seçilen satırlar üzerinde toplu işlem.
 *
 * Kural ve mesajlar {@see BulkActionRequest} içinde; burada yalnız tablo var.
 */
final class BulkBlogCommentRequest extends BulkActionRequest
{
    protected function table(): string
    {
        return 'blog_comments';
    }
}
