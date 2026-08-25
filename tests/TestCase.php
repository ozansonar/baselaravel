<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Uploads are redirected to a throwaway directory (see phpunit.xml) and
     * wiped between tests, so a test that uploads a file never leaves anything
     * behind in the real public/uploads folder.
     */
    protected function tearDown(): void
    {
        $uploadsPath = config('uploads.path');

        if (is_string($uploadsPath)
            && str_contains($uploadsPath, 'framework/testing')
            && File::isDirectory($uploadsPath)
        ) {
            File::deleteDirectory($uploadsPath);
        }

        parent::tearDown();
    }
}
