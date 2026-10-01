<?php

namespace App\Console\Commands;

use App\Ops\SystemHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:health')]
#[Description('Show production readiness: database, scheduler, queue, backups, configuration')]
class HealthCheck extends Command
{
    public function handle(SystemHealth $health): int
    {
        $icons = [SystemHealth::OK => '<fg=green>✓</>', SystemHealth::WARN => '<fg=yellow>!</>', SystemHealth::BAD => '<fg=red>✗</>'];

        foreach ($health->checks() as $check) {
            $this->line(sprintf(' %s %-20s %s', $icons[$check['status']], $check['label'], $check['detail']));
        }

        return $health->healthy() ? self::SUCCESS : self::FAILURE;
    }
}
