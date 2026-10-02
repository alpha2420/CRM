<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // The campaign or ad that first brought the lead in.
            $table->string('campaign', 150)->nullable();
            $table->string('ad_id', 64)->nullable();
            // The ad network's click id (ctwa_clid, gclid, fbclid), for reporting sales back.
            $table->string('click_id')->nullable();
            $table->index(['organization_id', 'campaign']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'campaign']);
            $table->dropColumn(['campaign', 'ad_id', 'click_id']);
        });
    }
};
