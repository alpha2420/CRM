<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per connected channel (WhatsApp, Facebook, Google, website
     * form). Credentials live encrypted in "settings". "webhook_key" is the
     * unguessable part of the channel's public webhook / form URL.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('webhook_key', 40)->unique();
            $table->text('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
