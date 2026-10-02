<?php

namespace App\Console\Commands;

use App\Sequences\SequenceRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:sequences')]
#[Description('Run the follow-up sequence steps that are due')]
class RunSequences extends Command
{
    public function handle(SequenceRunner $runner): int
    {
        $this->info("Ran {$runner->runDue()} sequence steps.");

        return self::SUCCESS;
    }
}
