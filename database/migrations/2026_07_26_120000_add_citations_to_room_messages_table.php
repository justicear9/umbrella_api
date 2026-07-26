<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_messages', function (Blueprint $table) {
            $table->json('citations')->nullable()->after('body');
            $table->json('footnotes')->nullable()->after('citations');
        });
    }

    public function down(): void
    {
        Schema::table('room_messages', function (Blueprint $table) {
            $table->dropColumn(['citations', 'footnotes']);
        });
    }
};
