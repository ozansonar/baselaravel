<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    /**
     * Show search results page.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = $request->string('q')->trim()->value();
        $products = null;
        $blogPosts = null;

        if ($query !== '') {
            $products = $this->searchService->search($query);
            $blogPosts = $this->searchService->searchBlog($query);
        }

        return view('search.index', compact('query', 'products', 'blogPosts'));
    }

    /**
     * Autocomplete suggestions (AJAX).
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $query = $request->string('q')->trim()->value();
        $suggestions = $this->searchService->suggest($query);

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }
}
