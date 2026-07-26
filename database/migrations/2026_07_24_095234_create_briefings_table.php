<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category');
            $table->text('summary');
            $table->json('talking_points')->nullable();
            $table->json('key_stats')->nullable();
            $table->json('watch_outs')->nullable();
            $table->json('citations')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefings');
    }
};
