<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        // Super admin is created only via the SoftKatta install wizard — never seeded.
        $this->call(BranchSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(FacilitySeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(TestimonialSeeder::class);
        $this->call(WhatsAppTemplateSeeder::class);
    }
}
