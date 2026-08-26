<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Same rules as creating; the unique check already ignores the row being
 * edited via the route binding.
 */
class UpdateLanguageRequest extends StoreLanguageRequest
{
}
