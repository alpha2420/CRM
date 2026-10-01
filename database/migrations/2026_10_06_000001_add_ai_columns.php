<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('ai_insight')->nullable()->after('custom_values');
            $table->dateTime('ai_insight_at')->nullable()->after('ai_insight');
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Monthly AI quota: "2026-10" and how many analyses were run in it.
            $table->string('ai_usage_month', 7)->nullable()->after('suspended_at');
            $table->unsignedInteger('ai_usage_count')->default(0)->after('ai_usage_month');
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['ai_insight', 'ai_insight_at']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['ai_usage_month', 'ai_usage_count']));
    }
};
