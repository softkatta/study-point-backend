<?php

namespace App\Services\SoftKatta;

use App\Models\User;
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

    public function createAdmin(array $data): User
    {
        /** @var User $user */
        $user = $this->orchestrator->createAdmin($this->adminCreator, $data);

        return $user;
    }

    public function migrate(): array
    {
        return $this->orchestrator->migrate();
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
