<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RobotsService;
use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    public function __construct(
        private readonly RobotsService $robots,
    ) {}

    public function __invoke(): Response
    {
        return response($this->robots->content())
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
