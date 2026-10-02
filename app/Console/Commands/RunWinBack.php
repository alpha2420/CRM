<?php

namespace App\Console\Commands;

use App\LostReasons\WinBack;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:win-back')]
#[Description('Reopen lost leads whose win-back delay has passed (Settings → Autopilot)')]
class RunWinBack extends Command
{
    public function handle(WinBack $winBack): int
    {
        $this->info("Won back {$winBack->run()} leads.");

        return self::SUCCESS;
    }
}
