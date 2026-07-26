<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_prep_turns', function (Blueprint $table) {
            $table->text('hint_text')->nullable()->after('model_answer');
        });
    }

    public function down(): void
    {
        Schema::table('press_prep_turns', function (Blueprint $table) {
            $table->dropColumn('hint_text');
        });
    }
};
