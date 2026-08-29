<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ayarlar sınıf içinde statik bir dizide tutuluyor ve bu dizi testler
     * arasında yaşıyor.
     *
     * RefreshDatabase satırları geri alıyor ama statik diziye dokunmuyor:
     * reCAPTCHA'yı açan bir test bitince veritabanında ayar kalmıyor, statik
     * dizide "açık" kalıyordu — sonraki testlerin yorum gönderimi robot
     * doğrulaması istediği için 422 alıyordu. Her test kendi ayarlarıyla
     * başlasın.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Setting::clearSettingsCache();
    }

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
        \App\Models\Setting::clearSettingsCache();

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
