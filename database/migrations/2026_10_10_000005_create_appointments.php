<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meetings, site visits, demos and calls booked with a lead, with
     * markers so each reminder goes out once.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who meets the lead
            $table->string('type', 20);
            $table->dateTime('starts_at');
            $table->string('location', 150)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->dateTime('lead_reminded_day_at')->nullable();
            $table->dateTime('lead_reminded_hour_at')->nullable();
            $table->dateTime('owner_reminded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['lead_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
