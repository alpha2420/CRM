<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why leads are lost, per workspace, with an optional win-back delay;
     * and on each lead its reason and when a win-back was tried.
     */
    public function up(): void
    {
        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('win_back_after_days')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('lost_reason_id')->nullable()->after('status_id')->constrained()->nullOnDelete();
            $table->dateTime('win_back_at')->nullable()->after('reengaged_at');
        });

        // Existing workspaces get the default list too.
        $now = now();
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            foreach (config('crm.default_lost_reasons') as $position => $reason) {
                DB::table('lost_reasons')->insert($reason + ['organization_id' => $organizationId, 'sort_order' => $position + 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lost_reason_id');
            $table->dropColumn('win_back_at');
        });
        Schema::dropIfExists('lost_reasons');
    }
};
