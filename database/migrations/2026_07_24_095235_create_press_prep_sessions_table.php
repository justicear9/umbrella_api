<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_prep_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('outing_type'); // tv|radio|press_conference|town_hall|social_ambush
            $table->string('difficulty'); // soft|standard|hostile
            $table->json('topics');
            $table->text('hot_issues')->nullable();
            $table->unsignedTinyInteger('question_count')->default(5);
            $table->string('status')->default('setup'); // setup|live|completed
            $table->unsignedTinyInteger('current_question')->default(0);
            $table->json('briefing_pack')->nullable();
            $table->json('debrief')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_prep_sessions');
    }
};
