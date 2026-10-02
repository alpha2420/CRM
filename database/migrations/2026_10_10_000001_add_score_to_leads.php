<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lead score (0-100) for open leads, kept up to date by app/Scoring.
     * Null for won and lost leads.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('priority');
            $table->index(['organization_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'score']);
            $table->dropColumn('score');
        });
    }
};
