<?php

namespace App\Autopilot;

/**
 * A workspace's Autopilot switches. Stored as JSON on the organization;
 * anything not saved yet falls back to the defaults below, so new and
 * existing workspaces get the safe automations switched on.
 */
final class AutopilotSettings
{
    public const DEFAULTS = [
        // New leads
        'first_follow_up' => true,
        'first_follow_up_minutes' => 15,
        'speed_to_lead' => true,
        'speed_to_lead_minutes' => 30,
        // Follow-ups
        'next_follow_up' => true,
        'next_follow_up_days' => 2,
        'contacted_on_first_message' => true,
        'reopen_returning' => true,
        // Quiet leads
        'reengage' => false,
        'reengage_days' => 14,
        'reengage_template_id' => null,
        'auto_close' => false,
        'auto_close_days' => 60,
        'win_back' => false,
        'win_back_template_id' => null,
        // WhatsApp
        'away_message' => false,
        'away_text' => 'Thanks for your message! We are away right now and will reply as soon as we are back.',
        'work_start' => 10,
        'work_end' => 19,
        'work_sundays' => false,
        'ai_on_reply' => false,
        'meeting_reminders' => true,
        'meeting_template_id' => null,
        // Team
        'share_leads_of_leavers' => true,
        'daily_digest' => true,
        'weekly_report' => true,
    ];

    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param  array<string, mixed>|null  $saved
     */
    public function __construct(?array $saved)
    {
        $this->values = array_merge(self::DEFAULTS, array_intersect_key($saved ?? [], self::DEFAULTS));
    }

    public function on(string $switch): bool
    {
        return (bool) $this->values[$switch];
    }

    public function number(string $key): int
    {
        return (int) $this->values[$key];
    }

    public function get(string $key): mixed
    {
        return $this->values[$key];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
