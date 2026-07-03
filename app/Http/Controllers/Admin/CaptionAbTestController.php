<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CaptionAbTestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/caption-ab-test — Caption özelliklerine göre engagement karşılaştırması.
 */
final class CaptionAbTestController extends Controller
{
    public function __construct(
        private readonly CaptionAbTestService $service,
    ) {}

    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 90);
        $days = max(14, min(365, $days));

        return view('admin.caption-ab-test.index', [
            'days'   => $days,
            'report' => $this->service->analyze($days),
        ]);
    }
}
