<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FaqService;
use Illuminate\View\View;

final class FaqController extends Controller
{
    public function __construct(
        private readonly FaqService $faqService,
    ) {}

    public function __invoke(): View
    {
        return view('faq', [
            'faqs' => $this->faqService->allActive(),
        ]);
    }
}
