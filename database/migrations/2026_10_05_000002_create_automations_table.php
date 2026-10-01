<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "When <trigger>, if <conditions>, then <actions>" rules.
     */
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('trigger', 20); // lead_created | status_changed
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('runs')->default(0);
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
