<?php

namespace App\Listeners;

use App\Ops\SystemHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use RuntimeException;

/**
 * GET /up (for uptime monitors such as UptimeRobot) answers with an error
 * when the database is down or, in production, when the scheduler or the
 * queue has stopped.
 */
class ReportHealthToMonitors
{
    public function __construct(private readonly SystemHealth $health) {}

    public function handle(DiagnosingHealth $event): void
    {
        $problems = $this->health->problemsForMonitors();

        if ($problems !== []) {
            throw new RuntimeException('Health check failed: '.implode(' | ', $problems));
        }
    }
}
