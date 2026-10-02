<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // A photo, voice note, video or document sent by the lead.
            $table->string('media_id')->nullable()->after('template_name');
            $table->string('media_mime', 100)->nullable()->after('media_id');
            $table->string('media_name')->nullable()->after('media_mime');
            $table->string('media_path')->nullable()->after('media_name');
            // What was said in a voice note, and a one-line summary.
            $table->text('transcript')->nullable()->after('media_path');
            $table->string('transcript_summary', 300)->nullable()->after('transcript');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['media_id', 'media_mime', 'media_name', 'media_path', 'transcript', 'transcript_summary']);
        });
    }
};
