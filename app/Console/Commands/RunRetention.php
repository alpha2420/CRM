<?php

namespace App\Console\Commands;

use App\Consent\RetentionSweep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:retention')]
#[Description('Erase the personal data of leads closed longer ago than each workspace keeps them (Settings → Privacy)')]
class RunRetention extends Command
{
    public function handle(RetentionSweep $sweep): int
    {
        $this->info("Erased {$sweep->run()} leads.");

        return self::SUCCESS;
    }
}
