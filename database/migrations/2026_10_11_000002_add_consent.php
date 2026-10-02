<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // The lead asked not to get messages (e.g. replied STOP).
            $table->timestamp('opted_out_at')->nullable();
            // Personal data removed on request or by the retention rule.
            $table->timestamp('erased_at')->nullable();
        });

        // When and how a lead agreed to be contacted, or withdrew it (DPDP).
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('how', 200);
            $table->timestamp('created_at')->nullable();
            $table->index(['lead_id', 'id']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Erase personal data of leads closed this many months ago (null = keep).
            $table->unsignedSmallInteger('retention_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['opted_out_at', 'erased_at']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('retention_months'));
    }
};
