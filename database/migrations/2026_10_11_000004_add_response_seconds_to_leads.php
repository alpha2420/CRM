<?php

use App\Models\Organization;
use App\Support\WorkingHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // How long a lead that arrived on its own waited for its first
            // reply, counting working hours only.
            $table->unsignedInteger('response_seconds')->nullable();
        });

        // Fill it in for leads already answered.
        foreach (Organization::query()->cursor() as $organization) {
            $hours = WorkingHours::for($organization);

            DB::table('leads')
                ->where('organization_id', $organization->id)
                ->whereNull('created_by')
                ->whereNotNull('first_contacted_at')
                ->orderBy('id')
                ->chunkById(500, function ($leads) use ($hours) {
                    foreach ($leads as $lead) {
                        DB::table('leads')->where('id', $lead->id)->update([
                            'response_seconds' => $hours->secondsBetween(Carbon::parse($lead->created_at, 'UTC'), Carbon::parse($lead->first_contacted_at, 'UTC')),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('response_seconds'));
    }
};
