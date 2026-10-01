<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes a workspace and everything in it (right to erasure).
 * Most rows go by foreign-key cascade; the rest are removed explicitly.
 */
final class WorkspaceEraser
{
    public function erase(Organization $organization): void
    {
        $userIds = $organization->users()->pluck('id');
        $emails = $organization->users()->pluck('email');

        DB::transaction(function () use ($organization, $userIds, $emails) {
            // Leads first: they reference pipeline stages, which cascade with the workspace.
            // Their follow-ups and WhatsApp messages cascade with them.
            Lead::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->delete();

            DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $userIds)->delete();
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();

            $organization->delete();
        });

        // Operator record without personal data.
        Log::info('Workspace erased on request.', ['organization_id' => $organization->id]);
    }
}
