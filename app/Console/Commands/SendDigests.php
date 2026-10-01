<?php

namespace App\Console\Commands;

use App\Autopilot\Digests;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:digests')]
#[Description('Send the 9:00 morning summaries and the Monday report, in each workspace\'s time zone')]
class SendDigests extends Command
{
    public function handle(Digests $digests): int
    {
        $sent = $digests->sendDue();
        $this->info("Sent {$sent['daily']} morning summaries and {$sent['weekly']} weekly reports.");

        return self::SUCCESS;
    }
}
