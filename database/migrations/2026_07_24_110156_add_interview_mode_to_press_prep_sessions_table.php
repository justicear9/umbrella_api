<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_prep_sessions', function (Blueprint $table) {
            $table->string('interview_mode')->default('text')->after('difficulty'); // text|voice
            $table->string('voice_preset')->nullable()->after('interview_mode'); // ghanaian|ghanaian_lady|...
        });
    }

    public function down(): void
    {
        Schema::table('press_prep_sessions', function (Blueprint $table) {
            $table->dropColumn(['interview_mode', 'voice_preset']);
        });
    }
};
