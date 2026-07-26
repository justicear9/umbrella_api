<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'comms_level')) {
                $table->string('comms_level', 32)->nullable()->after('occupation');
            }
            if (! Schema::hasColumn('users', 'region_id')) {
                // No DB-level FK: shared MySQL/MariaDB often rejects ALTER on legacy `users`
                // (errno 150). Integrity is enforced in app validation.
                $table->unsignedBigInteger('region_id')->nullable()->after('comms_level');
                $table->index('region_id');
            }
            if (! Schema::hasColumn('users', 'constituency_id')) {
                $table->unsignedBigInteger('constituency_id')->nullable()->after('region_id');
                $table->index('constituency_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'constituency_id')) {
                $table->dropIndex(['constituency_id']);
                $table->dropColumn('constituency_id');
            }
            if (Schema::hasColumn('users', 'region_id')) {
                $table->dropIndex(['region_id']);
                $table->dropColumn('region_id');
            }
            if (Schema::hasColumn('users', 'comms_level')) {
                $table->dropColumn('comms_level');
            }
        });
    }
};
