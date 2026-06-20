<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    public function __construct(private SecurityPolicyService $security) {}

    public function log(
        string $action,
        ?User $user = null,
        ?string $resource = null,
        ?string $resourceId = null,
        ?Request $request = null,
        array $meta = [],
    ): void {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    public function purgeExpired(): int
    {
        $days = (int) ($this->security->config()['audit_log_retention_days'] ?? 90);
        $cutoff = now()->subDays(max(7, $days));

        return AuditLog::where('created_at', '<', $cutoff)->delete();
    }

    public function recent(int $limit = 50, ?int $userId = null): array
    {
        $query = AuditLog::with('user:id,name,email')->latest('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->limit($limit)->get()->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'resource' => $log->resource,
            'resource_id' => $log->resource_id,
            'user' => $log->user?->name,
            'email' => $log->user?->email,
            'ip' => $log->ip_address,
            'at' => $log->created_at?->toIso8601String(),
            'meta' => $log->meta,
        ])->all();
    }
}
