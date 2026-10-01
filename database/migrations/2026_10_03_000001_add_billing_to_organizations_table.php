<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('plan', 20)->default('trial')->after('name');
            $table->dateTime('trial_ends_at')->nullable()->after('plan');
            $table->string('subscription_status', 20)->nullable()->after('trial_ends_at');
            $table->string('razorpay_subscription_id', 40)->nullable()->unique()->after('subscription_status');
            $table->dateTime('current_period_end')->nullable()->after('razorpay_subscription_id');
            $table->dateTime('suspended_at')->nullable()->after('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['razorpay_subscription_id']);
            $table->dropColumn(['plan', 'trial_ends_at', 'subscription_status', 'razorpay_subscription_id', 'current_period_end', 'suspended_at']);
        });
    }
};
