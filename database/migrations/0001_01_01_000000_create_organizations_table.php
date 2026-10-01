<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An organization is one paying customer (tenant). Every business
     * record in the CRM belongs to exactly one organization.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // SHA-256 of the website lead-capture key; the plain key is shown once.
            $table->string('api_key_hash', 64)->nullable()->unique();
            // Round-robin pointer for automatic lead assignment.
            $table->unsignedBigInteger('last_assigned_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
