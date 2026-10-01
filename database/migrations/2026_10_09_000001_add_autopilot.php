<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autopilot: the workspace's switches, and two markers on a lead so each
     * automatic step happens once (passed on for a slow first response,
     * nudged after going quiet).
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('autopilot')->nullable()->after('require_two_factor');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dateTime('escalated_at')->nullable()->after('first_contacted_at');
            $table->dateTime('reengaged_at')->nullable()->after('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['escalated_at', 'reengaged_at']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('autopilot'));
    }
};
