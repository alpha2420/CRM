<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('language', 15);
            $table->string('category', 20)->nullable();
            $table->string('status', 20);
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('variables')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'language']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 3); // in | out
            $table->string('wa_message_id', 120)->nullable()->unique();
            $table->string('phone', 20);
            $table->string('type', 10)->default('text'); // text | template
            $table->text('body')->nullable();
            $table->string('template_name', 120)->nullable();
            $table->string('status', 12); // queued, sent, delivered, read, failed, received
            $table->string('error')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['organization_id', 'direction', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_templates');
    }
};
