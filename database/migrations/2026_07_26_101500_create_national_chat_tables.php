<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('room_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16)->default('user'); // user|ai
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['chat_room_id', 'id']);
        });

        Schema::create('room_message_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_message_id')->constrained('room_messages')->cascadeOnDelete();
            $table->string('mention_type', 32); // comrade|constituency
            $table->unsignedBigInteger('constituency_id')->nullable();
            $table->timestamps();

            $table->index(['mention_type', 'constituency_id']);
        });

        DB::table('chat_rooms')->insert([
            'slug' => 'national',
            'name' => 'National Chat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_message_mentions');
        Schema::dropIfExists('room_messages');
        Schema::dropIfExists('chat_rooms');
    }
};
