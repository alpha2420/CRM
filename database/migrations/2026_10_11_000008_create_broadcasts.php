<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One approved WhatsApp template sent to a chosen group of leads.
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('name', 100);
            $table->string('template_name', 100);
            $table->json('audience');
            $table->json('values')->nullable();
            $table->string('status', 12)->default('sending');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('broadcast_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broadcast_id');
        });
        Schema::dropIfExists('broadcasts');
    }
};
