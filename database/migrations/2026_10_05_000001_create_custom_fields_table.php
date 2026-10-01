<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra lead fields each workspace defines for its own business
     * (e.g. "Budget", "Course", "Property type"). Values are stored on the
     * lead as JSON keyed by the field's key.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('key', 60);
            $table->string('type', 10); // text | number | date | select
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->json('custom_values')->nullable()->after('notes');
            // When the lead reached a won or lost status: drives "won in period" reports.
            $table->dateTime('closed_at')->nullable()->after('last_inbound_at');
            $table->index(['organization_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'closed_at']);
            $table->dropColumn(['custom_values', 'closed_at']);
        });
        Schema::dropIfExists('custom_fields');
    }
};
