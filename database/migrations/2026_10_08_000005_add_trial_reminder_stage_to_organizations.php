<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which trial email was sent last (0 none, 1 three-day, 2 one-day,
     * 3 ended), so each goes out exactly once.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedTinyInteger('trial_reminder_stage')->default(0)->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('trial_reminder_stage'));
    }
};
