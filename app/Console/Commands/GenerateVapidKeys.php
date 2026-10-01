<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('crm:vapid-keys')]
#[Description('Generate the key pair that signs push notifications (run once, put in .env)')]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Add these to your .env, then run php artisan config:clear:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Keep the private key secret, and never change the pair once users have subscribed.');

        return self::SUCCESS;
    }
}
