<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('comms_level', 32)->nullable()->after('occupation'); // national|constituency
            $table->foreignId('region_id')->nullable()->after('comms_level')->constrained()->nullOnDelete();
            $table->foreignId('constituency_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('constituency_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn('comms_level');
        });
    }
};
