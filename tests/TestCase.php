<?php

namespace Tests;

use App\Services\SoftKatta\StudyPointAdminCreator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedApplication(): void
    {
        $this->seed();
    }

    /**
     * @param  class-string|null  $class
     */
    public function seed($class = null): void
    {
        if ($class === null) {
            parent::seed(DatabaseSeeder::class);
            // Production creates super admin only via install wizard; tests need a known admin.
            app(StudyPointAdminCreator::class)->create([
                'name' => env('SEED_SUPER_ADMIN_NAME', 'Super Admin'),
                'email' => env('SEED_SUPER_ADMIN_EMAIL', 'admin@studypoint.in'),
                'password' => env('SEED_SUPER_ADMIN_PASSWORD', 'demo1234'),
            ]);

            return;
        }

        parent::seed($class);
    }
}
