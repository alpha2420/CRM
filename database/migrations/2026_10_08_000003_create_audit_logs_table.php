<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
