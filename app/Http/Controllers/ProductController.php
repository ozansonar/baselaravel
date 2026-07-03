<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Services\ProductReviewService;
use App\Services\ProductSeoService;
use App\Services\ProductService;
use App\Services\RelatedContentService;
use Illuminate\View\View;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
        private readonly ProductReviewService $reviewService,
        private readonly ProductSeoService $seoService,
        private readonly RelatedContentService $relatedContentService,
    ) {}

    public function all(): View
    {
        $categories = $this->categoryService->allActive();

        return view('products.all', [
            'categories' => $categories,
            'products' => $this->productService->paginateAll(),
        ]);
    }

    public function index(string $categorySlug): View
    {
        $category = $this->categoryService->findBySlug($categorySlug);

        return view('products.index', [
            'category' => $category,
            'products' => $this->productService->paginateByCategory($categorySlug),
        ]);
    }

    public function show(string $slug): View
    {
        $product = $this->productService->findBySlug($slug);
        $relatedProducts = $this->productService->related($product);
        $reviewStats = $this->reviewService->getProductStats($product->id);
        $productFaqs = \App\Models\Faq::forProduct($product->id)->active()->sorted()->get();

        return view('products.show', [
            'product'          => $product,
            'relatedProducts'  => $relatedProducts,
            'reviewStats'      => $reviewStats,
            'approvedReviews'  => $product->approvedReviews,
            'productFaqs'      => $productFaqs,
            'seoTitle'         => $this->seoService->metaTitle($product),
            'seoDescription'   => $this->seoService->metaDescription($product),
            'relatedBlogPosts' => $this->relatedContentService->getRelatedBlogPostsForProduct($product, 6),
        ]);
    }
}
