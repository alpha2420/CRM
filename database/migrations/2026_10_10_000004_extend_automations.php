<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time-based automation triggers ("quiet for 7 days", "follow-up 4
     * hours overdue") and a log of runs, so a rule fires once per occasion.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->unsignedSmallInteger('trigger_after')->nullable()->after('trigger'); // days or hours, per trigger
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['automation_id', 'lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::table('automations', fn (Blueprint $table) => $table->dropColumn('trigger_after'));
    }
};
