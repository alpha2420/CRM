<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the workspace activity log: who did what, when, from where.
 */
final class AuditLogger
{
    private int $muted = 0;

    public function __construct(private readonly TenantContext $tenant) {}

    public function log(string $action, string $description, ?Model $subject = null, ?User $actor = null, ?int $organizationId = null): void
    {
        if ($this->muted > 0) {
            return;
        }

        $actor ??= Auth::user();
        $organizationId ??= $subject?->getAttribute('organization_id') ?? $actor->organization_id ?? $this->tenant->id();

        if ($organizationId === null) {
            return;
        }

        $entry = new AuditLog([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
        $entry->organization_id = $organizationId;
        $entry->save();
    }

    /**
     * Run work without per-item entries (e.g. a CSV import logs one line).
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function quietly(callable $work): mixed
    {
        $this->muted++;

        try {
            return $work();
        } finally {
            $this->muted--;
        }
    }
}
