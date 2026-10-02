<?php

namespace App\Console\Commands;

use App\Automations\ScheduledAutomations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:automations')]
#[Description('Run time-based automation rules (quiet leads, overdue follow-ups)')]
class RunScheduledAutomations extends Command
{
    public function handle(ScheduledAutomations $automations): int
    {
        $this->info("Fired {$automations->run()} time-based rules.");

        return self::SUCCESS;
    }
}
