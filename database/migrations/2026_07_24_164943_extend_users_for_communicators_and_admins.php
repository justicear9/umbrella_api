<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('communicator')->after('id'); // admin|communicator
            $table->string('party_id')->nullable()->unique()->after('email');
            $table->date('date_of_birth')->nullable()->after('party_id');
            $table->string('constituency')->nullable()->after('date_of_birth');
            $table->string('occupation')->nullable()->after('constituency');
            $table->string('api_token', 80)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'party_id', 'date_of_birth', 'constituency', 'occupation', 'api_token']);
        });
    }
};
