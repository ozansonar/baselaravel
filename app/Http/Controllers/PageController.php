<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PageService;
use Illuminate\View\View;

final class PageController extends Controller
{
    public function __construct(
        private readonly PageService $pageService,
    ) {}

    public function show(string $slug): View
    {
        $page = $this->pageService->findBySlug($slug);

        $viewName = view()->exists('pages.' . str_replace('-', '_', $slug))
            ? 'pages.' . str_replace('-', '_', $slug)
            : 'pages.show';

        return view($viewName, [
            'page' => $page,
        ]);
    }
}
