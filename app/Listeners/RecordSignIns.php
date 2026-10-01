<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordSignIns
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.login', 'Signed in', actor: $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.logout', 'Signed out', actor: $event->user);
        }
    }
}
