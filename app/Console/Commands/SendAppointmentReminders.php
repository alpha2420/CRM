<?php

namespace App\Console\Commands;

use App\Appointments\AppointmentReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:appointment-reminders')]
#[Description('Remind leads (WhatsApp) and owners before booked meetings')]
class SendAppointmentReminders extends Command
{
    public function handle(AppointmentReminders $reminders): int
    {
        $this->info("Sent {$reminders->sendDue()} meeting reminders.");

        return self::SUCCESS;
    }
}
