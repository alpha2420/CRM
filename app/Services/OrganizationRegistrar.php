<?php

namespace App\Services;

use App\Billing\PlanCatalog;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sign-up: one call creates the organization (on a free trial), its
 * default pipeline and sources, and its first admin user, atomically.
 */
final class OrganizationRegistrar
{
    public function register(string $organizationName, string $name, string $email, string $password, ?string $timezone = null): User
    {
        return DB::transaction(function () use ($organizationName, $name, $email, $password, $timezone) {
            $organization = Organization::create(['name' => $organizationName]);
            $organization->forceFill([
                'timezone' => $timezone ?: config('crm.default_timezone'),
                'plan' => PlanCatalog::TRIAL,
                'trial_ends_at' => now()->addDays(config('plans.trial_days')),
            ])->save();

            foreach (config('crm.default_statuses') as $position => $status) {
                $organization->leadStatuses()->create($status + ['sort_order' => $position + 1]);
            }

            foreach (config('crm.default_sources') as $source) {
                $organization->sources()->create(['name' => $source]);
            }

            foreach (config('crm.default_lost_reasons') as $position => $reason) {
                $organization->lostReasons()->create($reason + ['sort_order' => $position + 1]);
            }

            return $organization->users()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => Role::Admin,
            ]);
        });
    }
}
