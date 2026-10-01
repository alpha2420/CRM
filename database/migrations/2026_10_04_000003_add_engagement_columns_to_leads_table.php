<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // When the assignee was last reminded about next_follow_up_at.
            $table->dateTime('reminded_at')->nullable()->after('last_activity_at');
            // First follow-up or outbound message: drives "time to first contact".
            $table->dateTime('first_contacted_at')->nullable()->after('reminded_at');
            // Latest WhatsApp message either way: orders the inbox.
            $table->dateTime('last_message_at')->nullable()->after('first_contacted_at');
            // Latest message from the lead: opens WhatsApp's 24-hour reply window.
            $table->dateTime('last_inbound_at')->nullable()->after('last_message_at');

            $table->index(['organization_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'last_message_at']);
            $table->dropColumn(['reminded_at', 'first_contacted_at', 'last_message_at', 'last_inbound_at']);
        });
    }
};
