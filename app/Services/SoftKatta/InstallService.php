<?php

namespace App\Services\SoftKatta;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use SoftKatta\Licensing\Services\InstallOrchestrator;

/**
 * Study Point install facade — product-specific admin creation + package orchestrator.
 */
class InstallService
{
    public function __construct(
        private InstallOrchestrator $orchestrator,
        private StudyPointAdminCreator $adminCreator,
    ) {}

    public function status(): array
    {
        return $this->orchestrator->status();
    }

    public function requirements(): array
    {
        return $this->orchestrator->requirements();
    }

    public function configureDatabase(array $data): array
    {
        return $this->orchestrator->configureDatabase($data);
    }

    public function configureCompanyApi(array $data): array
    {
        return $this->orchestrator->configureCompanyApi($data);
    }

    public function isCompanyApiConfigured(): bool
    {
        return $this->orchestrator->isCompanyApiConfigured();
    }

    public function createAdmin(array $data): User
    {
        /** @var User $user */
        $user = $this->orchestrator->createAdmin($this->adminCreator, $data);

        return $user;
    }

    public function migrate(): array
    {
        $result = $this->orchestrator->migrate();

        // Roles/permissions only — never seed a super admin user.
        Artisan::call('db:seed', [
            '--class' => PermissionSeeder::class,
            '--force' => true,
        ]);

        $result['permissions_seeded'] = true;
        $result['permissions_output'] = Artisan::output();

        return $result;
    }

    public function downloadConfiguration(): array
    {
        return $this->orchestrator->downloadConfiguration();
    }

    public function complete(): array
    {
        return $this->orchestrator->complete();
    }
}
