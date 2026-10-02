<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lead routing: rules that send matching leads to a group of people,
     * agents' availability, and an optional cap on open leads per agent.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_available')->default(true)->after('is_active');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_open_leads')->nullable()->after('last_assigned_user_id');
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('conditions');
            $table->json('agent_ids');
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_rules');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('max_open_leads'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_available'));
    }
};
