<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Which ad network the click id belongs to: ctwa, gclid or fbclid.
            $table->string('click_type', 10)->nullable();
        });

        // Results reported back to Meta for Click-to-WhatsApp ads, once each.
        Schema::create('ad_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->string('status', 10)->default('queued');
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['lead_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_conversions');
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('click_type'));
    }
};
