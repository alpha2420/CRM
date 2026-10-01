<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * /up (for uptime monitors and load balancers) fails if the database is down.
 */
class CheckDatabaseOnHealthPing
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::select('select 1');
    }
}
