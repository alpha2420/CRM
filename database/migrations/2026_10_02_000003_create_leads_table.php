<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->constrained('lead_statuses');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('value', 12, 2)->nullable();
            $table->string('priority', 10)->default('medium');
            $table->text('notes')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->timestamps();

            // One lead per phone number inside an organization.
            $table->unique(['organization_id', 'phone']);
            $table->index(['organization_id', 'status_id']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'next_follow_up_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
