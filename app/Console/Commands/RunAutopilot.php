<?php

namespace App\Console\Commands;

use App\Autopilot\AutopilotSweep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:autopilot')]
#[Description('Pass on unanswered leads, nudge quiet ones and close dead ones (per Settings → Autopilot)')]
class RunAutopilot extends Command
{
    public function handle(AutopilotSweep $sweep): int
    {
        $done = $sweep->run();
        $this->info("Passed on {$done['passed_on']}, re-engaged {$done['reengaged']}, closed {$done['closed']}.");

        return self::SUCCESS;
    }
}
