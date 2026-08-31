<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

final class BulkCustomRouteRequest extends BulkActionRequest
{
    protected function table(): string
    {
        return 'custom_routes';
    }
}
