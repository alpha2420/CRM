<?php

namespace App\Ai;

use App\Models\Organization;

/**
 * Each workspace may use the AI a set number of times a month
 * (CRM_AI_MONTHLY_LIMIT): lead analyses and voice-note transcripts alike.
 * The count starts again on the 1st.
 */
final class AiUsage
{
    public function remaining(Organization $organization): int
    {
        $used = $organization->ai_usage_month === now()->format('Y-m') ? $organization->ai_usage_count : 0;

        return max(0, (int) config('crm.ai_monthly_limit') - $used);
    }

    public function record(Organization $organization): void
    {
        $month = now()->format('Y-m');

        $organization->forceFill([
            'ai_usage_month' => $month,
            'ai_usage_count' => $organization->ai_usage_month === $month ? $organization->ai_usage_count + 1 : 1,
        ])->save();
    }
}
