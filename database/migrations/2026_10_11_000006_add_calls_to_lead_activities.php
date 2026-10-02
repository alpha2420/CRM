<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            // Set when the entry is a phone call: how it went and roughly how long.
            $table->string('call_outcome', 20)->nullable();
            $table->unsignedInteger('call_seconds')->nullable();
            $table->index(['organization_id', 'user_id', 'call_outcome']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'user_id', 'call_outcome']);
            $table->dropColumn(['call_outcome', 'call_seconds']);
        });
    }
};
