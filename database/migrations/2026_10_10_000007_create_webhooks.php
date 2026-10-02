<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outgoing webhooks: URLs other apps give us, the events they want and
     * how the last delivery went.
     */
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->json('events');
            $table->text('secret');                       // encrypted
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->dateTime('last_delivered_at')->nullable();
            $table->unsignedInteger('failures')->default(0); // in a row
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
