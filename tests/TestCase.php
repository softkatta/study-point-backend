<?php

namespace Tests;

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

            return;
        }

        parent::seed($class);
    }
}
