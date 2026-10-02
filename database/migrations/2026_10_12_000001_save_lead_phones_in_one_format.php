<?php

use App\Models\Organization;
use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Numbers saved before they had one format ("9876543210",
        // "09876543210") become "+919876543210" like new ones. A number that
        // would then clash with another lead of the workspace is left as is.
        foreach (Organization::query()->cursor() as $organization) {
            $countryCode = $organization->countryCode();
            $taken = DB::table('leads')->where('organization_id', $organization->id)->pluck('phone')->flip();

            DB::table('leads')
                ->where('organization_id', $organization->id)
                ->where('phone', 'not like', '+%')
                ->orderBy('id')
                ->chunkById(500, function ($leads) use ($countryCode, $taken) {
                    foreach ($leads as $lead) {
                        $phone = PhoneNumber::international($lead->phone, $countryCode);

                        if ($phone !== '' && ! $taken->has($phone)) {
                            DB::table('leads')->where('id', $lead->id)->update(['phone' => $phone]);
                            $taken->put($phone, $lead->id);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // The old spellings are not kept, and the new format is valid for both.
    }
};
