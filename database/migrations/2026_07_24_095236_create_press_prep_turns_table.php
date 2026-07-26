<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_prep_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_prep_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('turn_index');
            $table->string('role')->default('interviewer'); // interviewer|communicator|coach
            $table->text('question')->nullable();
            $table->text('user_answer')->nullable();
            $table->text('model_answer')->nullable();
            $table->text('coach_note')->nullable();
            $table->text('follow_up')->nullable();
            $table->boolean('is_follow_up')->default(false);
            $table->json('score_notes')->nullable();
            $table->timestamps();

            $table->index(['press_prep_session_id', 'turn_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_prep_turns');
    }
};
