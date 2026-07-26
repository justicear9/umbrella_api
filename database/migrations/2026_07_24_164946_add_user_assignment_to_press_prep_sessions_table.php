<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_prep_sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            $table->string('assignment_note')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('press_prep_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn(['assigned_at', 'assignment_note']);
        });
    }
};
