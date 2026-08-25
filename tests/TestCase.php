<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles carry database permissions, so a test that hands a user a role has
     * to seed both or the role grants nothing.
     */
    protected function seedAuthorization(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

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
