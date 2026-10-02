<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Follow-up sequences: a named list of timed steps, and each lead's
     * progress through one (an enrollment).
     */
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_on_reply')->default(true);
            $table->timestamps();
        });

        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day');          // days after the lead was enrolled
            $table->string('action', 30);
            $table->foreignId('whatsapp_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->index(['sequence_id', 'day']);
        });

        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('next_step')->default(0);  // index into the sequence's steps
            $table->dateTime('next_run_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('stop_reason', 100)->nullable();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_enrollments');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('sequences');
    }
};
